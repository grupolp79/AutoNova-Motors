<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\ArmaDatos;
use Tests\TestCase;

/**
 * Alta de cuentas de empleado y de admin por el Admin (extra B, RF-10).
 * El rol se valida contra la lista permitida; la contraseña provisoria se guarda con hash.
 */
#[Group('RF-10')]
#[Group('Usuarios')]
class UsuarioAltaTest extends TestCase
{
    use RefreshDatabase, ArmaDatos;

    private const CLAVE = 'provisoria-123';

    private function datos(array $cambios = []): array
    {
        return $cambios + [
            'name' => 'Laura Gómez',
            'email' => 'laura@autonova.test',
            'rol' => 'empleado',
            'password' => self::CLAVE,
            'password_confirmation' => self::CLAVE,
        ];
    }

    // ----------------------------------------------------------------- Acceso

    #[DataProvider('permisos')]
    public function test_matriz_de_permisos(string $rol, string $metodo, string $ruta, int $esperado): void
    {
        $actor = $rol === 'visitante' ? $this : $this->actingAs($this->{$rol}());

        $respuesta = $actor->call($metodo, $ruta, $this->datos());

        $respuesta->assertStatus($esperado);
        if ($rol === 'visitante') {
            $respuesta->assertRedirect(route('login'));
        }
    }

    public static function permisos(): array
    {
        $filas = [];
        // [método, ruta, visitante, cliente, empleado, admin]
        foreach ([['GET', '/usuarios/crear', 302, 403, 403, 200], ['POST', '/usuarios', 302, 403, 403, 302]] as [$metodo, $ruta, $visitante, $cliente, $empleado, $admin]) {
            foreach (compact('visitante', 'cliente', 'empleado', 'admin') as $rol => $codigo) {
                $filas["$rol $metodo $ruta"] = [$rol, $metodo, $ruta, $codigo];
            }
        }

        return $filas;
    }

    public function test_un_pedido_rechazado_por_rol_no_crea_la_cuenta(): void
    {
        foreach (['cliente', 'empleado'] as $rol) {
            $this->actingAs($this->{$rol}())->post('/usuarios', $this->datos(['rol' => 'admin']))->assertForbidden();
        }
        auth()->logout();
        $this->post('/usuarios', $this->datos(['rol' => 'admin']))->assertRedirect(route('login'));

        $this->assertDatabaseMissing('users', ['email' => 'laura@autonova.test']);
    }

    public function test_las_rutas_estan_declaradas_con_rol_admin(): void
    {
        $rutas = collect(app('router')->getRoutes()->getRoutes())->keyBy(fn ($r) => $r->getName());

        foreach (['usuarios.index', 'usuarios.create', 'usuarios.store'] as $nombre) {
            $this->assertContains('rol:admin', $rutas[$nombre]->gatherMiddleware(), $nombre);
        }
    }

    // ------------------------------------------------------------ Pantallas

    public function test_el_listado_tiene_el_boton_crear_usuario(): void
    {
        $this->actingAs($this->admin())->get('/usuarios')->assertOk()
            ->assertSee('Crear usuario')
            ->assertSee(route('usuarios.create'), false);
    }

    public function test_el_formulario_ofrece_solo_empleado_y_admin(): void
    {
        $this->actingAs($this->admin())->get('/usuarios/crear')->assertOk()
            ->assertSee('name="name"', false)
            ->assertSee('name="email"', false)
            ->assertSee('name="password_confirmation"', false)
            ->assertSee('value="empleado"', false)
            ->assertSee('value="admin"', false)
            ->assertDontSee('value="cliente"', false)
            ->assertSee('La persona puede cambiar la contraseña con «¿Olvidaste tu contraseña?».');
    }

    // ------------------------------------------------------------- Alta

    #[DataProvider('rolesCreables')]
    public function test_el_admin_crea_la_cuenta_y_esa_persona_puede_iniciar_sesion(string $rol, string $texto): void
    {
        $this->actingAs($this->admin())->post('/usuarios', $this->datos(['rol' => $rol]))
            ->assertRedirect(route('usuarios.index'))
            ->assertSessionHas('success', "Creaste la cuenta de Laura Gómez ({$texto}). La persona puede cambiar la contraseña con «¿Olvidaste tu contraseña?».");

        $nueva = User::where('email', 'laura@autonova.test')->sole();
        $this->assertSame('Laura Gómez', $nueva->name);
        $this->assertSame($rol, $nueva->rol);

        // La cuenta nueva entra con la contraseña provisoria
        auth()->logout();
        $this->post('/login', ['email' => 'laura@autonova.test', 'password' => self::CLAVE])->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($nueva);
    }

    public static function rolesCreables(): array
    {
        return ['empleado' => ['empleado', 'Empleado'], 'admin' => ['admin', 'Administrador']];
    }

    public function test_el_empleado_creado_tiene_los_permisos_de_empleado_y_no_los_de_admin(): void
    {
        $this->actingAs($this->admin())->post('/usuarios', $this->datos(['rol' => 'empleado']));
        $nuevo = User::where('email', 'laura@autonova.test')->sole();

        $this->actingAs($nuevo)->get('/consultas')->assertOk();
        $this->actingAs($nuevo)->get('/usuarios')->assertForbidden();
    }

    public function test_la_cuenta_nueva_aparece_en_el_listado(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/usuarios', $this->datos());

        $this->actingAs($admin)->get('/usuarios')->assertSee('Laura Gómez')->assertSee('laura@autonova.test');
    }

    public function test_la_contrasena_queda_con_hash_y_no_se_muestra(): void
    {
        $admin = $this->admin();
        $respuesta = $this->actingAs($admin)->post('/usuarios', $this->datos());

        $guardada = User::where('email', 'laura@autonova.test')->value('password');
        $this->assertNotSame(self::CLAVE, $guardada);
        $this->assertTrue(Hash::check(self::CLAVE, $guardada));

        // Ni en el mensaje ni en el listado
        $this->assertStringNotContainsString(self::CLAVE, (string) session('success'));
        $this->assertStringNotContainsString(self::CLAVE, $this->actingAs($admin)->get('/usuarios')->getContent());
    }

    public function test_el_correo_se_guarda_en_minusculas_y_sin_espacios(): void
    {
        $this->actingAs($this->admin())->post('/usuarios', $this->datos(['email' => '  Laura@AutoNova.TEST ']));

        $this->assertDatabaseHas('users', ['email' => 'laura@autonova.test']);
    }

    // -------------------------------------------------------- Validaciones

    public function test_el_correo_repetido_se_rechaza_aunque_cambien_las_mayusculas(): void
    {
        User::factory()->cliente()->create(['email' => 'laura@autonova.test']);

        $this->actingAs($this->admin())->post('/usuarios', $this->datos(['email' => 'LAURA@autonova.test']))
            ->assertSessionHasErrors(['email' => 'Ya existe una cuenta con ese correo electrónico.']);

        $this->assertSame(1, User::where('email', 'laura@autonova.test')->count());
    }

    #[DataProvider('datosInvalidos')]
    public function test_valida_los_datos_con_mensajes_en_espanol(array $cambios, string $campo, string $mensaje): void
    {
        $this->actingAs($this->admin())->from('/usuarios/crear')->post('/usuarios', $this->datos($cambios))
            ->assertRedirect('/usuarios/crear')
            ->assertSessionHasErrors([$campo => $mensaje]);

        $this->assertDatabaseMissing('users', ['name' => 'Laura Gómez']);
    }

    public static function datosInvalidos(): array
    {
        $rolInvalido = 'El rol tiene que ser empleado o administrador. Los clientes crean su cuenta desde «Registrarse».';

        return [
            'sin nombre' => [['name' => ''], 'name', 'El campo nombre es obligatorio.'],
            'nombre largo' => [['name' => str_repeat('a', 101)], 'name', 'El campo nombre no puede tener más de 100 caracteres.'],
            'sin correo' => [['email' => ''], 'email', 'El campo correo electrónico es obligatorio.'],
            'correo inválido' => [['email' => 'laura.autonova'], 'email', 'El campo correo electrónico debe ser un correo electrónico válido.'],
            'contraseña corta' => [['password' => 'corta1', 'password_confirmation' => 'corta1'], 'password', 'El campo contraseña debe tener al menos 8 caracteres.'],
            'sin confirmación' => [['password_confirmation' => ''], 'password', 'La confirmación de contraseña no coincide.'],
            'sin rol' => [['rol' => ''], 'rol', 'Elegí el rol de la cuenta: empleado o administrador.'],
            // Pedidos armados a mano: el formulario no ofrece estos valores
            'rol cliente forzado' => [['rol' => 'cliente'], 'rol', $rolInvalido],
            'rol inventado' => [['rol' => 'superadmin'], 'rol', $rolInvalido],
            'rol con mayúsculas' => [['rol' => 'Admin'], 'rol', $rolInvalido],
            'rol como lista' => [['rol' => ['admin']], 'rol', $rolInvalido],
        ];
    }

    public function test_usa_las_mismas_reglas_de_contrasena_que_el_registro(): void
    {
        $this->actingAs($this->admin())
            ->post('/usuarios', $this->datos(['password' => str_repeat('a', 73), 'password_confirmation' => str_repeat('a', 73)]))
            ->assertSessionHasErrors(['password' => 'El campo contraseña no puede tener más de 72 caracteres.']);
    }

    public function test_si_vuelve_con_error_conserva_los_datos_menos_la_contrasena(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->from('/usuarios/crear')->post('/usuarios', $this->datos(['email' => 'mal']));

        $this->actingAs($admin)->get('/usuarios/crear')
            ->assertSee('value="Laura Gómez"', false)
            ->assertSee('value="empleado" selected', false)
            ->assertDontSee(self::CLAVE);
    }

    public function test_los_datos_se_muestran_escapados(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/usuarios', $this->datos(['name' => '<script>alert(1)</script>']));

        $this->actingAs($admin)->get('/usuarios')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }
}
