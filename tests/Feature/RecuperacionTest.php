<?php

namespace Tests\Feature;

use App\Http\Controllers\RecuperacionController;
use App\Models\User;
use App\Notifications\RestablecerContrasena;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Tests\TestCase;

/**
 * "¿Olvidaste tu contraseña?" (RF-05) con un código de 6 dígitos que llega por correo.
 * El código se guarda con hash en password_reset_tokens; los intentos fallidos se cuentan con RateLimiter.
 *
 * Las rutas POST tienen throttle:5,1 y Laravel cuenta esos pedidos por IP. Las pruebas que hacen
 * muchos pedidos seguidos sacan ese middleware (sinLimiteDeRuta) para probar las reglas propias del código;
 * el throttle de las rutas tiene sus propias pruebas al final.
 */
#[Group('RF-05')]
#[Group('Recuperacion')]
class RecuperacionTest extends TestCase
{
    use RefreshDatabase;

    private const CLAVE_VIEJA = 'clave-vieja-123';
    private const CLAVE_NUEVA = 'clave-nueva-456';

    private function usuario(string $rol = 'cliente', string $email = 'ana@correo.test'): User
    {
        return User::factory()->{$rol}()->create(['email' => $email, 'password' => self::CLAVE_VIEJA]);
    }

    private function sinLimiteDeRuta(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    /** Pide el código y devuelve el que llegó en el correo (la base solo guarda su hash). */
    private function pedirCodigo(User $usuario): string
    {
        Notification::fake();
        $this->post('/olvide-contrasena', ['email' => $usuario->email]);

        $codigo = null;
        Notification::assertSentTo($usuario, RestablecerContrasena::class, function ($aviso) use (&$codigo, $usuario) {
            $codigo = $aviso->toMail($usuario)->viewData['codigo'];

            return true;
        });

        return $codigo;
    }

    private function cambiar(string $email, string $codigo, string $clave = self::CLAVE_NUEVA, ?string $confirmacion = null)
    {
        return $this->from('/restablecer-contrasena')->post('/restablecer-contrasena', [
            'email' => $email, 'codigo' => $codigo,
            'password' => $clave, 'password_confirmation' => $confirmacion ?? $clave,
        ]);
    }

    private function filas(): int
    {
        return DB::table('password_reset_tokens')->count();
    }

    /** Un código de 6 dígitos que seguro no es el correcto. */
    private function otroCodigo(string $codigo): string
    {
        return RecuperacionController::formatearCodigo(((int) $codigo + 1) % 1000000);
    }

    // ------------------------------------------------------------------ Tabla y reglas

    public function test_usa_la_tabla_que_ya_existe_y_las_reglas_del_sistema(): void
    {
        $this->assertTrue(Schema::hasColumns('password_reset_tokens', ['email', 'token', 'created_at']));
        $this->assertSame(15, RecuperacionController::MINUTOS_VALIDEZ);
        $this->assertSame(60, RecuperacionController::SEGUNDOS_ENTRE_PEDIDOS);
        $this->assertSame(5, RecuperacionController::MAX_INTENTOS);
        $this->assertSame(['required', 'string', 'min:8', 'max:72', 'confirmed'], User::REGLAS_CONTRASENA);
    }

    public function test_el_codigo_tiene_6_digitos_con_ceros_a_la_izquierda(): void
    {
        $this->assertSame('004217', RecuperacionController::formatearCodigo(4217));
        $this->assertSame('000000', RecuperacionController::formatearCodigo(0));
        $this->assertSame('999999', RecuperacionController::formatearCodigo(999999));

        // Los códigos que se mandan de verdad son siempre 6 números
        $this->sinLimiteDeRuta();
        for ($i = 0; $i < 5; $i++) {
            $this->assertMatchesRegularExpression('/^[0-9]{6}$/', $this->pedirCodigo($this->usuario('cliente', "persona{$i}@correo.test")));
        }
    }

    // ------------------------------------------------------------------ Pantallas

    public function test_el_login_tiene_el_enlace_y_las_pantallas_se_ven_en_espanol(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('¿Olvidaste tu contraseña?')
            ->assertSee(route('password.request'), false);

        $this->get('/olvide-contrasena')->assertOk()
            ->assertSee('Escribí el correo con el que te registraste')
            ->assertSee('código de 6 dígitos')
            ->assertSee('name="email"', false)
            ->assertSee('Enviar código')
            ->assertSee('name="_token"', false);

        $this->get('/restablecer-contrasena')->assertOk()
            ->assertSee('Crear contraseña nueva')
            ->assertSee('name="email"', false)
            ->assertSee('name="codigo"', false)
            ->assertSee('inputmode="numeric"', false)
            ->assertSee('maxlength="6"', false)
            ->assertSee('autocomplete="one-time-code"', false)
            ->assertSee('name="password"', false)
            ->assertSee('name="password_confirmation"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('Pedir un código nuevo')
            ->assertSee(route('password.request'), false);
    }

    public function test_la_ruta_vieja_con_token_ya_no_existe(): void
    {
        $this->get('/restablecer-contrasena/un-token?email=ana@correo.test')->assertNotFound();
    }

    // ------------------------------------------------------------------ Pedir el código

    public function test_con_un_correo_existente_o_inexistente_la_respuesta_es_la_misma_y_va_a_la_pantalla_2(): void
    {
        Notification::fake();
        $ana = $this->usuario();

        // Las dos usan la misma sesión de prueba: cada una se revisa apenas responde
        $this->from('/olvide-contrasena')->post('/olvide-contrasena', ['email' => 'ana@correo.test'])
            ->assertRedirect(route('password.reset'))
            ->assertSessionHas('success', RecuperacionController::MENSAJE_ENVIADO)
            ->assertSessionHas('email', 'ana@correo.test')
            ->assertSessionHasNoErrors();
        $this->from('/olvide-contrasena')->post('/olvide-contrasena', ['email' => 'nadie@correo.test'])
            ->assertRedirect(route('password.reset'))
            ->assertSessionHas('success', RecuperacionController::MENSAJE_ENVIADO)
            ->assertSessionHas('email', 'nadie@correo.test')
            ->assertSessionHasNoErrors();

        // Solo al correo existente se le manda el correo, y una sola vez
        Notification::assertSentToTimes($ana, RestablecerContrasena::class, 1);
        Notification::assertCount(1);
        $this->assertSame(1, $this->filas());
    }

    public function test_el_mensaje_dice_6_digitos_y_15_minutos(): void
    {
        $this->assertSame(
            'Si el correo está registrado, te enviamos un código de 6 dígitos. Vence en 15 minutos.',
            RecuperacionController::MENSAJE_ENVIADO,
        );
    }

    public function test_la_pantalla_2_trae_el_correo_ya_escrito(): void
    {
        Notification::fake();
        $this->usuario();

        $this->followingRedirects()->post('/olvide-contrasena', ['email' => 'ana@correo.test'])
            ->assertOk()
            ->assertSee(RecuperacionController::MENSAJE_ENVIADO)
            ->assertSee('value="ana@correo.test"', false);
    }

    public function test_el_correo_se_busca_sin_importar_mayusculas_ni_espacios(): void
    {
        Notification::fake();
        $ana = $this->usuario();

        $this->post('/olvide-contrasena', ['email' => '  ANA@Correo.TEST ']);

        Notification::assertSentTo($ana, RestablecerContrasena::class);
    }

    #[DataProvider('correosInvalidos')]
    public function test_un_correo_mal_escrito_da_error_en_espanol(string $email, string $mensaje): void
    {
        Notification::fake();

        $this->from('/olvide-contrasena')->post('/olvide-contrasena', ['email' => $email])
            ->assertRedirect('/olvide-contrasena')
            ->assertSessionHasErrors(['email' => $mensaje]);

        Notification::assertNothingSent();
    }

    public static function correosInvalidos(): array
    {
        return [
            'vacío' => ['', 'Escribí el correo con el que te registraste.'],
            'sin arroba' => ['ana.correo.test', 'Ese correo no tiene un formato válido. Revisalo.'],
        ];
    }

    public function test_la_base_guarda_el_hash_y_no_el_codigo(): void
    {
        $ana = $this->usuario();
        $codigo = $this->pedirCodigo($ana);

        $guardado = DB::table('password_reset_tokens')->where('email', $ana->email)->value('token');
        $this->assertNotSame($codigo, $guardado);
        $this->assertStringNotContainsString($codigo, $guardado);
        $this->assertTrue(Hash::check($codigo, $guardado));
    }

    public function test_un_pedido_nuevo_reemplaza_al_anterior_y_el_codigo_viejo_ya_no_sirve(): void
    {
        $this->sinLimiteDeRuta();
        $ana = $this->usuario();

        $viejo = $this->pedirCodigo($ana);
        $this->travel(61)->seconds();
        $nuevo = $this->pedirCodigo($ana);

        // Una sola fila por correo
        $this->assertSame(1, $this->filas());

        if ($viejo !== $nuevo) { // por azar podrían coincidir (1 en un millón)
            $this->cambiar($ana->email, $viejo)->assertSessionHasErrors(['codigo' => RecuperacionController::MENSAJE_CODIGO_INVALIDO]);
        }
        $this->cambiar($ana->email, $nuevo)->assertRedirect(route('login'));
    }

    public function test_no_se_manda_otro_codigo_antes_de_60_segundos(): void
    {
        $this->sinLimiteDeRuta();
        Notification::fake();
        $ana = $this->usuario();

        $this->post('/olvide-contrasena', ['email' => $ana->email]);
        $hash = DB::table('password_reset_tokens')->value('token');

        // A los 30 segundos: la respuesta es la normal, pero no sale otro correo ni cambia el código
        $this->travel(30)->seconds();
        $this->post('/olvide-contrasena', ['email' => $ana->email])
            ->assertRedirect(route('password.reset'))
            ->assertSessionHas('success', RecuperacionController::MENSAJE_ENVIADO);
        Notification::assertSentToTimes($ana, RestablecerContrasena::class, 1);
        $this->assertSame($hash, DB::table('password_reset_tokens')->value('token'));

        // Pasado el minuto, sí
        $this->travel(31)->seconds();
        $this->post('/olvide-contrasena', ['email' => $ana->email]);
        Notification::assertSentToTimes($ana, RestablecerContrasena::class, 2);
    }

    public function test_el_pedido_tiene_limite_de_5_intentos_por_minuto(): void
    {
        Notification::fake();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/olvide-contrasena', ['email' => "persona{$i}@correo.test"])->assertRedirect();
        }

        $this->post('/olvide-contrasena', ['email' => 'otra@correo.test'])->assertStatus(429);
    }

    public function test_si_falla_el_envio_del_correo_la_respuesta_es_la_misma_y_no_hay_error_500(): void
    {
        Exceptions::fake();

        // Un "servidor de correo" que siempre falla, como un SMTP que no responde
        Mail::extend('falla', fn () => new class extends AbstractTransport {
            protected function doSend(SentMessage $message): void
            {
                throw new TransportException('El servidor SMTP no responde.');
            }

            public function __toString(): string
            {
                return 'falla';
            }
        });
        config(['mail.mailers.falla' => ['transport' => 'falla'], 'mail.default' => 'falla']);
        Mail::forgetMailers();

        $this->usuario();

        $this->post('/olvide-contrasena', ['email' => 'ana@correo.test'])
            ->assertRedirect(route('password.reset'))
            ->assertSessionHas('success', RecuperacionController::MENSAJE_ENVIADO)
            ->assertSessionHasNoErrors();

        // El error quedó registrado (report) y el código que nunca llegó se borró
        Exceptions::assertReported(TransportException::class);
        $this->assertSame(0, $this->filas());
    }

    // ------------------------------------------------------------------ El correo

    public function test_el_correo_esta_en_espanol_y_trae_el_codigo(): void
    {
        $ana = $this->usuario();
        $mail = (new RestablecerContrasena('004217'))->toMail($ana);

        $this->assertSame('Tu código para crear una contraseña nueva - AutoNova Motors', $mail->subject);
        $this->assertSame('004217', $mail->viewData['codigo']);
        $this->assertSame(15, $mail->viewData['minutos']);
        $this->assertSame(['mail'], (new RestablecerContrasena('x'))->via($ana));
    }

    public function test_el_correo_es_solo_texto_y_el_codigo_va_solo_en_su_linea(): void
    {
        // Se manda de verdad (en las pruebas el correo queda en memoria: MAIL_MAILER=array en phpunit.xml)
        $ana = User::factory()->cliente()->create(['name' => "Ana O'Neill", 'email' => 'ana@correo.test']);
        $ana->notify(new RestablecerContrasena('004217'));

        $correo = app('mailer')->getSymfonyTransport()->messages()->sole()->getOriginalMessage();
        $texto = $correo->getTextBody();

        // Sin parte HTML y sin etiquetas en el texto
        $this->assertNull($correo->getHtmlBody());
        $this->assertSame($texto, strip_tags($texto));

        // El código ocupa una línea entera, sin nada antes ni después
        $lineas = array_map('trim', preg_split('/\R/', $texto));
        $this->assertContains('004217', $lineas);

        // En español, con el nombre tal cual (sin &#039;)
        $this->assertStringContainsString("Hola, Ana O'Neill:", $texto);
        $this->assertStringContainsString('Vence en 15 minutos y se puede usar una sola vez.', $texto);
        $this->assertStringContainsString('Si no lo pediste, no tenés que hacer nada: tu contraseña sigue siendo la misma.', $texto);
        $this->assertStringContainsString('No compartas este código con nadie.', $texto);
        $this->assertStringNotContainsString('http', $texto); // ya no hay enlace
        $this->assertSame('Tu código para crear una contraseña nueva - AutoNova Motors', $correo->getSubject());
    }

    // ------------------------------------------------------------------ Cambiar la contraseña

    #[DataProvider('roles')]
    public function test_cambia_la_contrasena_y_la_vieja_deja_de_funcionar_para_cada_rol(string $rol): void
    {
        $usuario = $this->usuario($rol, "{$rol}@correo.test");
        $codigo = $this->pedirCodigo($usuario);

        $this->cambiar($usuario->email, $codigo)
            ->assertRedirect(route('login'))
            ->assertSessionHas('success', 'Listo, cambiaste tu contraseña. Ya podés iniciar sesión con la nueva.');

        // No inicia sesión sola
        $this->assertGuest();

        // Con hash, nunca en texto plano
        $guardada = $usuario->fresh()->password;
        $this->assertNotSame(self::CLAVE_NUEVA, $guardada);
        $this->assertTrue(Hash::check(self::CLAVE_NUEVA, $guardada));

        // La vieja ya no entra; la nueva sí (pasa el minuto del límite de la ruta, que comparte con el login)
        $this->travel(61)->seconds();
        $this->post('/login', ['email' => $usuario->email, 'password' => self::CLAVE_VIEJA])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/login', ['email' => $usuario->email, 'password' => self::CLAVE_NUEVA])->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($usuario->fresh());
    }

    public static function roles(): array
    {
        return ['cliente' => ['cliente'], 'empleado' => ['empleado'], 'admin' => ['admin']];
    }

    public function test_el_codigo_se_acepta_con_espacios(): void
    {
        $ana = $this->usuario();
        $codigo = $this->pedirCodigo($ana);

        $this->cambiar($ana->email, ' ' . substr($codigo, 0, 3) . ' ' . substr($codigo, 3) . ' ')
            ->assertRedirect(route('login'));
    }

    public function test_despues_del_cambio_se_invalida_el_recordarme(): void
    {
        $ana = $this->usuario();
        $ana->forceFill(['remember_token' => 'token-viejo-de-recordarme'])->save();
        $codigo = $this->pedirCodigo($ana);

        $this->cambiar($ana->email, $codigo);

        $this->assertNotSame('token-viejo-de-recordarme', $ana->fresh()->remember_token);
        $this->assertNotEmpty($ana->fresh()->remember_token);
    }

    public function test_un_codigo_incorrecto_no_cambia_nada(): void
    {
        $ana = $this->usuario();
        $codigo = $this->pedirCodigo($ana);

        $this->cambiar($ana->email, $this->otroCodigo($codigo))
            ->assertRedirect('/restablecer-contrasena')
            ->assertSessionHasErrors(['codigo' => RecuperacionController::MENSAJE_CODIGO_INVALIDO])
            ->assertSessionHasInput('email', 'ana@correo.test');

        $this->assertTrue(Hash::check(self::CLAVE_VIEJA, $ana->fresh()->password));
        $this->assertSame(1, $this->filas());
    }

    public function test_un_correo_que_no_existe_da_el_mismo_mensaje(): void
    {
        $this->cambiar('nadie@correo.test', '123456')
            ->assertSessionHasErrors(['codigo' => RecuperacionController::MENSAJE_CODIGO_INVALIDO]);
    }

    public function test_el_codigo_de_una_persona_no_sirve_para_otra(): void
    {
        $this->sinLimiteDeRuta();
        $ana = $this->usuario();
        $beto = $this->usuario('cliente', 'beto@correo.test');
        $codigoDeAna = $this->pedirCodigo($ana);

        // Beto sin código pedido, y Beto con su propio código pedido
        $this->cambiar($beto->email, $codigoDeAna)->assertSessionHasErrors(['codigo' => RecuperacionController::MENSAJE_CODIGO_INVALIDO]);
        $codigoDeBeto = $this->pedirCodigo($beto);
        if ($codigoDeBeto !== $codigoDeAna) {
            $this->cambiar($beto->email, $codigoDeAna)->assertSessionHasErrors(['codigo' => RecuperacionController::MENSAJE_CODIGO_INVALIDO]);
        }

        $this->assertTrue(Hash::check(self::CLAVE_VIEJA, $beto->fresh()->password));
        $this->assertTrue(Hash::check(self::CLAVE_VIEJA, $ana->fresh()->password));
    }

    public function test_a_los_14_minutos_el_codigo_sirve(): void
    {
        $ana = $this->usuario();
        $codigo = $this->pedirCodigo($ana);

        $this->travel(14)->minutes();

        $this->cambiar($ana->email, $codigo)->assertRedirect(route('login'));
    }

    public function test_a_los_16_minutos_el_codigo_ya_vencio(): void
    {
        $ana = $this->usuario();
        $codigo = $this->pedirCodigo($ana);

        $this->travel(16)->minutes();

        $this->cambiar($ana->email, $codigo)->assertSessionHasErrors(['codigo' => RecuperacionController::MENSAJE_CODIGO_INVALIDO]);
        $this->assertTrue(Hash::check(self::CLAVE_VIEJA, $ana->fresh()->password));
    }

    public function test_el_codigo_sirve_una_sola_vez(): void
    {
        $ana = $this->usuario();
        $codigo = $this->pedirCodigo($ana);

        $this->cambiar($ana->email, $codigo)->assertRedirect(route('login'));
        $this->assertSame(0, $this->filas());

        $this->cambiar($ana->email, $codigo, 'otra-clave-789')
            ->assertSessionHasErrors(['codigo' => RecuperacionController::MENSAJE_CODIGO_INVALIDO]);
        $this->assertTrue(Hash::check(self::CLAVE_NUEVA, $ana->fresh()->password));
    }

    public function test_al_quinto_intento_fallido_se_bloquea_hasta_pedir_un_codigo_nuevo(): void
    {
        $this->sinLimiteDeRuta();
        $ana = $this->usuario();
        $codigo = $this->pedirCodigo($ana);
        $incorrecto = $this->otroCodigo($codigo);

        // 4 fallos: el código todavía existe
        for ($i = 0; $i < 4; $i++) {
            $this->cambiar($ana->email, $incorrecto)->assertSessionHasErrors(['codigo' => RecuperacionController::MENSAJE_CODIGO_INVALIDO]);
        }
        $this->assertSame(1, $this->filas());

        // 5.º fallo: el código se borra
        $this->cambiar($ana->email, $incorrecto)->assertSessionHasErrors('codigo');
        $this->assertSame(0, $this->filas());

        // Ni el código correcto sirve
        $this->cambiar($ana->email, $codigo)->assertSessionHasErrors(['codigo' => RecuperacionController::MENSAJE_CODIGO_INVALIDO]);
        $this->assertTrue(Hash::check(self::CLAVE_VIEJA, $ana->fresh()->password));

        // Un código nuevo reinicia el contador y funciona
        $this->travel(61)->seconds();
        $nuevo = $this->pedirCodigo($ana);
        $this->cambiar($ana->email, $nuevo)->assertRedirect(route('login'));
    }

    public function test_un_pedido_nuevo_reinicia_el_contador_de_intentos(): void
    {
        $this->sinLimiteDeRuta();
        $ana = $this->usuario();
        $codigo = $this->pedirCodigo($ana);

        for ($i = 0; $i < 4; $i++) {
            $this->cambiar($ana->email, $this->otroCodigo($codigo));
        }

        // Con 4 fallos acumulados se pide otro código: vuelve a tener 5 intentos
        $this->travel(61)->seconds();
        $nuevo = $this->pedirCodigo($ana);
        for ($i = 0; $i < 4; $i++) {
            $this->cambiar($ana->email, $this->otroCodigo($nuevo));
        }
        $this->assertSame(1, $this->filas());
        $this->cambiar($ana->email, $nuevo)->assertRedirect(route('login'));
    }

    #[DataProvider('clavesInvalidas')]
    public function test_una_contrasena_que_no_cumple_las_reglas_no_gasta_el_codigo_ni_el_intento(string $clave, string $confirmacion, string $mensaje): void
    {
        $this->sinLimiteDeRuta();
        $ana = $this->usuario();
        $codigo = $this->pedirCodigo($ana);

        // Más veces que el límite de intentos: ninguna cuenta, porque el formulario se valida antes
        for ($i = 0; $i < RecuperacionController::MAX_INTENTOS + 1; $i++) {
            $this->cambiar($ana->email, $codigo, $clave, $confirmacion)->assertSessionHasErrors(['password' => $mensaje]);
        }

        $this->assertTrue(Hash::check(self::CLAVE_VIEJA, $ana->fresh()->password));
        $this->assertSame(1, $this->filas());

        // Corrige la contraseña y el mismo código sirve
        $this->cambiar($ana->email, $codigo)->assertRedirect(route('login'));
    }

    public static function clavesInvalidas(): array
    {
        return [
            'vacía' => ['', '', 'El campo contraseña es obligatorio.'],
            'corta' => ['corta1', 'corta1', 'El campo contraseña debe tener al menos 8 caracteres.'],
            'sin confirmación' => ['clave-nueva-456', '', 'La confirmación de contraseña no coincide.'],
            'confirmación distinta' => ['clave-nueva-456', 'clave-nueva-999', 'La confirmación de contraseña no coincide.'],
            'más de 72' => [str_repeat('a', 73), str_repeat('a', 73), 'El campo contraseña no puede tener más de 72 caracteres.'],
        ];
    }

    #[DataProvider('codigosMalEscritos')]
    public function test_un_codigo_mal_escrito_da_error_de_formulario_y_no_cuenta_el_intento(string $codigo, string $mensaje): void
    {
        $this->sinLimiteDeRuta();
        $ana = $this->usuario();
        $bueno = $this->pedirCodigo($ana);

        for ($i = 0; $i < RecuperacionController::MAX_INTENTOS + 1; $i++) {
            $this->cambiar($ana->email, $codigo)->assertSessionHasErrors(['codigo' => $mensaje]);
        }

        $this->cambiar($ana->email, $bueno)->assertRedirect(route('login'));
    }

    public static function codigosMalEscritos(): array
    {
        return [
            'vacío' => ['', 'Escribí el código de 6 dígitos que te llegó por correo.'],
            'corto' => ['12345', 'El código tiene 6 números. Revisalo en el correo.'],
            'largo' => ['1234567', 'El código tiene 6 números. Revisalo en el correo.'],
            'con letras' => ['12a456', 'El código tiene 6 números. Revisalo en el correo.'],
        ];
    }

    public function test_el_cambio_tiene_limite_de_5_intentos_por_minuto(): void
    {
        $ana = $this->usuario();

        for ($i = 0; $i < 5; $i++) {
            $this->cambiar($ana->email, '123456')->assertSessionHasErrors('codigo');
        }

        $this->cambiar($ana->email, '123456')->assertStatus(429);
    }

    // ------------------------------------------------------------------ Solo sin sesión

    #[DataProvider('roles')]
    public function test_las_rutas_no_sirven_para_quien_ya_inicio_sesion(string $rol): void
    {
        Notification::fake();
        $usuario = $this->usuario($rol, "{$rol}@correo.test");
        $this->actingAs($usuario);

        $this->get('/olvide-contrasena')->assertRedirect();
        $this->post('/olvide-contrasena', ['email' => $usuario->email])->assertRedirect()->assertSessionMissing('success');
        $this->get('/restablecer-contrasena')->assertRedirect();
        $this->post('/restablecer-contrasena', [
            'email' => $usuario->email, 'codigo' => '123456', 'password' => self::CLAVE_NUEVA, 'password_confirmation' => self::CLAVE_NUEVA,
        ])->assertRedirect();

        Notification::assertNothingSent();
        $this->assertSame(0, $this->filas());
        $this->assertTrue(Hash::check(self::CLAVE_VIEJA, $usuario->fresh()->password));
    }

    public function test_las_rutas_estan_declaradas_como_guest(): void
    {
        $rutas = collect(app('router')->getRoutes()->getRoutes())->keyBy(fn ($r) => $r->getName());

        foreach (['password.request', 'password.email', 'password.reset', 'password.update'] as $nombre) {
            $this->assertContains('guest', $rutas[$nombre]->gatherMiddleware(), $nombre);
        }
        $this->assertContains('throttle:5,1', $rutas['password.email']->gatherMiddleware());
        $this->assertContains('throttle:5,1', $rutas['password.update']->gatherMiddleware());
        $this->assertSame('restablecer-contrasena', $rutas['password.reset']->uri());
    }
}
