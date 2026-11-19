<?php

namespace Tests\Feature;

use App\Http\Controllers\RecuperacionController;
use App\Models\User;
use App\Notifications\RestablecerContrasena;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\ArmaDatos;
use Tests\TestCase;

/**
 * "¿Olvidaste tu contraseña?" (RF-05): pedir el enlace por correo y elegir una contraseña nueva.
 * Usa el Password broker de Laravel y la tabla password_reset_tokens.
 */
#[Group('RF-05')]
#[Group('Recuperacion')]
class RecuperacionTest extends TestCase
{
    use RefreshDatabase, ArmaDatos;

    private const CLAVE_VIEJA = 'clave-vieja-123';
    private const CLAVE_NUEVA = 'clave-nueva-456';

    private function usuario(string $rol = 'cliente', string $email = 'ana@correo.test'): User
    {
        return User::factory()->{$rol}()->create(['email' => $email, 'password' => self::CLAVE_VIEJA]);
    }

    /** Pide el enlace y devuelve el código que llegó en el correo (la base solo guarda su hash). */
    private function pedirCodigo(User $usuario): string
    {
        Notification::fake();
        $this->post('/olvide-contrasena', ['email' => $usuario->email]);

        $codigo = null;
        Notification::assertSentTo($usuario, RestablecerContrasena::class, function ($aviso) use (&$codigo, $usuario) {
            // El enlace del correo tiene el formato /restablecer-contrasena/{codigo}?email=...
            preg_match('#/restablecer-contrasena/([^?]+)#', $aviso->toMail($usuario)->viewData['enlace'], $partes);
            $codigo = $partes[1] ?? null;

            return $codigo !== null;
        });

        return $codigo;
    }

    private function cambiar(string $email, string $codigo, string $clave = self::CLAVE_NUEVA, ?string $confirmacion = null)
    {
        return $this->post('/restablecer-contrasena', [
            'token' => $codigo, 'email' => $email,
            'password' => $clave, 'password_confirmation' => $confirmacion ?? $clave,
        ]);
    }

    // ------------------------------------------------------------------- Tabla

    public function test_la_tabla_de_codigos_existe_con_sus_columnas(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumns('password_reset_tokens', ['email', 'token', 'created_at']));
        $this->assertSame(60, config('auth.passwords.users.expire'));
    }

    // ------------------------------------------------------------ Pantallas

    public function test_el_login_tiene_el_enlace_y_las_pantallas_se_ven_en_espanol(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('¿Olvidaste tu contraseña?')
            ->assertSee(route('password.request'), false);

        $this->get('/olvide-contrasena')->assertOk()
            ->assertSee('Escribí el correo con el que te registraste')
            ->assertSee('name="email"', false)
            ->assertSee('Enviar enlace');

        $this->get('/restablecer-contrasena/un-codigo?email=ana@correo.test')->assertOk()
            ->assertSee('Crear contraseña nueva')
            ->assertSee('name="token" value="un-codigo"', false)
            ->assertSee('ana@correo.test')
            ->assertSee('name="password_confirmation"', false);
    }

    // ------------------------------------------------------- Pedir el enlace

    public function test_con_un_correo_existente_o_inexistente_la_respuesta_es_la_misma(): void
    {
        Notification::fake();
        $ana = $this->usuario();

        $existente = $this->from('/olvide-contrasena')->post('/olvide-contrasena', ['email' => 'ana@correo.test']);
        $inexistente = $this->from('/olvide-contrasena')->post('/olvide-contrasena', ['email' => 'nadie@correo.test']);

        foreach ([$existente, $inexistente] as $respuesta) {
            $respuesta->assertRedirect('/olvide-contrasena')
                ->assertSessionHas('success', RecuperacionController::MENSAJE_ENVIADO)
                ->assertSessionHasNoErrors();
        }

        // Solo al correo existente se le manda el correo, y una sola vez
        Notification::assertSentToTimes($ana, RestablecerContrasena::class, 1);
        Notification::assertCount(1);
        $this->assertSame(1, DB::table('password_reset_tokens')->count());
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

    public function test_el_pedido_tiene_limite_de_5_intentos_por_minuto(): void
    {
        Notification::fake();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/olvide-contrasena', ['email' => "persona{$i}@correo.test"])->assertRedirect();
        }

        $this->post('/olvide-contrasena', ['email' => 'otra@correo.test'])->assertStatus(429);
    }

    public function test_el_correo_esta_en_espanol_y_trae_el_enlace(): void
    {
        $ana = $this->usuario();
        $mail = (new RestablecerContrasena('codigo-de-prueba'))->toMail($ana);

        $this->assertSame('Crear una contraseña nueva - AutoNova Motors', $mail->subject);
        $this->assertSame(route('password.reset', ['token' => 'codigo-de-prueba', 'email' => 'ana@correo.test']), $mail->viewData['enlace']);
        $this->assertSame(60, $mail->viewData['minutos']);
        $this->assertSame(['mail'], (new RestablecerContrasena('x'))->via($ana));
    }

    public function test_el_correo_es_solo_texto_sin_html_y_el_enlace_va_solo_en_su_linea(): void
    {
        // Se manda de verdad (en las pruebas el correo queda en memoria: MAIL_MAILER=array en phpunit.xml)
        $ana = User::factory()->cliente()->create(['name' => "Ana O'Neill", 'email' => 'ana@correo.test']);
        $ana->notify(new RestablecerContrasena('codigo-de-prueba'));

        $correo = app('mailer')->getSymfonyTransport()->messages()->sole()->getOriginalMessage();
        $enlace = route('password.reset', ['token' => 'codigo-de-prueba', 'email' => 'ana@correo.test']);
        $texto = $correo->getTextBody();

        // Sin parte HTML y sin etiquetas en el texto
        $this->assertNull($correo->getHtmlBody());
        $this->assertSame($texto, strip_tags($texto));
        $this->assertDoesNotMatchRegularExpression('#<[a-z/!][^>]*>#i', $texto);

        // El enlace ocupa una línea entera, sin nada antes ni después
        $lineas = array_map('trim', preg_split('/\R/', $texto));
        $this->assertContains($enlace, $lineas);

        // En español, con el nombre tal cual (sin &#039;)
        $this->assertStringContainsString("Hola, Ana O'Neill:", $texto);
        $this->assertStringContainsString('El enlace vence en 60 minutos y se puede usar una sola vez.', $texto);
        $this->assertSame('Crear una contraseña nueva - AutoNova Motors', $correo->getSubject());
    }

    public function test_la_base_guarda_el_codigo_con_hash_y_no_el_codigo(): void
    {
        $ana = $this->usuario();
        $codigo = $this->pedirCodigo($ana);

        $guardado = DB::table('password_reset_tokens')->where('email', $ana->email)->value('token');
        $this->assertNotSame($codigo, $guardado);
        $this->assertTrue(Hash::check($codigo, $guardado));
    }

    // ---------------------------------------------------- Cambiar la contraseña

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

        // La vieja ya no entra; la nueva sí
        $this->post('/login', ['email' => $usuario->email, 'password' => self::CLAVE_VIEJA])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/login', ['email' => $usuario->email, 'password' => self::CLAVE_NUEVA])->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($usuario->fresh());
    }

    public static function roles(): array
    {
        return ['cliente' => ['cliente'], 'empleado' => ['empleado'], 'admin' => ['admin']];
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

    public function test_un_codigo_invalido_no_cambia_nada(): void
    {
        $ana = $this->usuario();
        $this->pedirCodigo($ana);

        $this->from('/restablecer-contrasena/inventado')->cambiar($ana->email, 'codigo-inventado')
            ->assertRedirect('/restablecer-contrasena/inventado')
            ->assertSessionHasErrors(['email' => RecuperacionController::MENSAJE_ENLACE_INVALIDO]);

        $this->assertTrue(Hash::check(self::CLAVE_VIEJA, $ana->fresh()->password));
    }

    public function test_el_codigo_de_una_persona_no_sirve_para_otra(): void
    {
        $ana = $this->usuario();
        $beto = $this->usuario('cliente', 'beto@correo.test');
        $codigoDeAna = $this->pedirCodigo($ana);

        $this->cambiar($beto->email, $codigoDeAna)->assertSessionHasErrors(['email' => RecuperacionController::MENSAJE_ENLACE_INVALIDO]);

        $this->assertTrue(Hash::check(self::CLAVE_VIEJA, $beto->fresh()->password));
    }

    public function test_un_enlace_vencido_no_sirve(): void
    {
        $ana = $this->usuario();
        $codigo = $this->pedirCodigo($ana);

        // 61 minutos después: ya venció (dura 60)
        $this->travel(61)->minutes();

        $this->cambiar($ana->email, $codigo)->assertSessionHasErrors(['email' => RecuperacionController::MENSAJE_ENLACE_INVALIDO]);
        $this->assertTrue(Hash::check(self::CLAVE_VIEJA, $ana->fresh()->password));
    }

    public function test_a_los_59_minutos_todavia_sirve(): void
    {
        $ana = $this->usuario();
        $codigo = $this->pedirCodigo($ana);

        $this->travel(59)->minutes();

        $this->cambiar($ana->email, $codigo)->assertRedirect(route('login'));
    }

    public function test_el_enlace_sirve_una_sola_vez(): void
    {
        $ana = $this->usuario();
        $codigo = $this->pedirCodigo($ana);

        $this->cambiar($ana->email, $codigo)->assertRedirect(route('login'));
        $this->assertSame(0, DB::table('password_reset_tokens')->count());

        $this->cambiar($ana->email, $codigo, 'otra-clave-789')
            ->assertSessionHasErrors(['email' => RecuperacionController::MENSAJE_ENLACE_INVALIDO]);
        $this->assertTrue(Hash::check(self::CLAVE_NUEVA, $ana->fresh()->password));
    }

    #[DataProvider('clavesInvalidas')]
    public function test_la_contrasena_nueva_cumple_las_reglas_del_registro(string $clave, string $confirmacion, string $mensaje): void
    {
        $ana = $this->usuario();
        $codigo = $this->pedirCodigo($ana);

        $this->cambiar($ana->email, $codigo, $clave, $confirmacion)->assertSessionHasErrors(['password' => $mensaje]);

        // No se cambió y el código sigue sirviendo para intentar de nuevo
        $this->assertTrue(Hash::check(self::CLAVE_VIEJA, $ana->fresh()->password));
        $this->assertSame(1, DB::table('password_reset_tokens')->count());
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

    public function test_usa_las_mismas_reglas_de_contrasena_que_el_registro(): void
    {
        $this->assertSame(['required', 'string', 'min:8', 'max:72', 'confirmed'], User::REGLAS_CONTRASENA);
    }

    public function test_el_cambio_tiene_limite_de_intentos(): void
    {
        $ana = $this->usuario();

        for ($i = 0; $i < 5; $i++) {
            $this->cambiar($ana->email, "codigo-inventado-{$i}")->assertSessionHasErrors('email');
        }

        $this->cambiar($ana->email, 'codigo-inventado-6')->assertStatus(429);
    }

    // --------------------------------------------------- Solo sin sesión

    #[DataProvider('roles')]
    public function test_las_rutas_no_sirven_para_quien_ya_inicio_sesion(string $rol): void
    {
        Notification::fake();
        $usuario = $this->usuario($rol, "{$rol}@correo.test");
        $this->actingAs($usuario);

        $this->get('/olvide-contrasena')->assertRedirect();
        $this->post('/olvide-contrasena', ['email' => $usuario->email])->assertRedirect()->assertSessionMissing('success');
        $this->get('/restablecer-contrasena/un-codigo')->assertRedirect();
        $this->post('/restablecer-contrasena', [
            'token' => 'x', 'email' => $usuario->email, 'password' => self::CLAVE_NUEVA, 'password_confirmation' => self::CLAVE_NUEVA,
        ])->assertRedirect();

        Notification::assertNothingSent();
        $this->assertTrue(Hash::check(self::CLAVE_VIEJA, $usuario->fresh()->password));
    }

    public function test_las_rutas_estan_declaradas_como_guest(): void
    {
        $rutas = collect(app('router')->getRoutes()->getRoutes())->keyBy(fn ($r) => $r->getName());

        foreach (['password.request', 'password.email', 'password.reset', 'password.update'] as $nombre) {
            $this->assertContains('guest', $rutas[$nombre]->gatherMiddleware(), $nombre);
        }
        $this->assertContains('throttle:5,1', $rutas['password.email']->gatherMiddleware());
    }
}
