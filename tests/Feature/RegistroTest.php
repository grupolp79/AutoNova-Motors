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
 * RF-05: un visitante puede crear una cuenta de cliente. La cuenta es siempre de rol cliente.
 */
class RegistroTest extends TestCase
{
    use RefreshDatabase, ArmaDatos;

    private function datos(array $cambios = []): array
    {
        return $cambios + [
            'name' => 'Ana Pérez',
            'email' => 'ana@correo.test',
            'password' => 'clave-segura-1',
            'password_confirmation' => 'clave-segura-1',
        ];
    }

    #[Group('RF-05')]
    public function test_el_formulario_de_registro_se_muestra_a_un_visitante(): void
    {
        $this->get('/registro')
            ->assertOk()
            ->assertSee('Crear cuenta')
            ->assertSee(route('registro.store'), false);
    }

    #[Group('RF-05')]
    public function test_un_usuario_con_sesion_no_ve_el_registro_ni_puede_enviarlo(): void
    {
        $this->actingAs($this->cliente())->get('/registro')->assertRedirect();
        $this->actingAs($this->cliente())->post('/registro', $this->datos())->assertRedirect();

        $this->assertNull(User::where('email', 'ana@correo.test')->first());
    }

    #[Group('RF-05')]
    public function test_el_registro_crea_un_cliente_y_deja_la_sesion_iniciada(): void
    {
        $this->post('/registro', $this->datos())
            ->assertRedirect(route('vehiculos.index'))
            ->assertSessionHas('success', 'Cuenta creada. ¡Bienvenido, Ana Pérez!');

        $usuario = User::where('email', 'ana@correo.test')->sole();
        $this->assertSame('cliente', $usuario->rol);
        $this->assertSame('Ana Pérez', $usuario->name);
        $this->assertAuthenticatedAs($usuario);
    }

    #[Group('RF-05')]
    public function test_la_contrasena_se_guarda_con_hash(): void
    {
        $this->post('/registro', $this->datos());

        $usuario = User::where('email', 'ana@correo.test')->sole();
        $this->assertNotSame('clave-segura-1', $usuario->password);
        $this->assertTrue(Hash::check('clave-segura-1', $usuario->password));
    }

    #[Group('RF-05')]
    #[DataProvider('rolesPedidos')]
    public function test_el_rol_no_se_puede_elegir_desde_el_formulario(string $rolPedido): void
    {
        $this->post('/registro', $this->datos(['rol' => $rolPedido]));

        $this->assertSame('cliente', User::where('email', 'ana@correo.test')->sole()->rol);
    }

    public static function rolesPedidos(): array
    {
        return ['admin' => ['admin'], 'empleado' => ['empleado']];
    }

    #[Group('RF-05')]
    public function test_el_correo_se_guarda_en_minusculas_y_sin_espacios(): void
    {
        $this->post('/registro', $this->datos(['email' => '  Ana@Correo.TEST ']))->assertSessionHasNoErrors();

        $this->assertNotNull(User::where('email', 'ana@correo.test')->first());
    }

    #[Group('RF-05')]
    public function test_un_correo_ya_registrado_se_rechaza_aunque_cambien_las_mayusculas(): void
    {
        User::factory()->cliente()->create(['email' => 'ana@correo.test']);

        $this->post('/registro', $this->datos(['email' => 'ANA@correo.test']))
            ->assertSessionHasErrors(['email' => 'Ya existe una cuenta con ese correo electrónico.']);

        $this->assertSame(1, User::where('email', 'ana@correo.test')->count());
    }

    #[Group('RF-05')]
    #[Group('Validaciones')]
    #[DataProvider('datosInvalidos')]
    public function test_rechaza_datos_invalidos(array $cambios, string $campo): void
    {
        $antes = User::count();

        $this->post('/registro', $this->datos($cambios))->assertSessionHasErrors($campo);

        $this->assertSame($antes, User::count());
        $this->assertGuest();
    }

    public static function datosInvalidos(): array
    {
        return [
            'sin nombre' => [['name' => ''], 'name'],
            'nombre demasiado largo' => [['name' => str_repeat('a', 101)], 'name'],
            'sin correo' => [['email' => ''], 'email'],
            'correo mal escrito' => [['email' => 'ana@'], 'email'],
            'sin contraseña' => [['password' => '', 'password_confirmation' => ''], 'password'],
            'contraseña de menos de 8 caracteres' => [['password' => 'corta12', 'password_confirmation' => 'corta12'], 'password'],
            'contraseña de más de 72 caracteres' => [['password' => str_repeat('a', 73), 'password_confirmation' => str_repeat('a', 73)], 'password'],
            'la confirmación no coincide' => [['password_confirmation' => 'otra-clave-2'], 'password'],
            'sin confirmación' => [['password_confirmation' => ''], 'password'],
        ];
    }

    #[Group('RF-05')]
    public function test_los_mensajes_de_error_estan_en_espanol_y_no_se_devuelve_la_contrasena(): void
    {
        $this->from('/registro')
            ->post('/registro', $this->datos(['password' => 'clave-secreta-9', 'password_confirmation' => 'distinta-clave-8']))
            ->assertRedirect('/registro')
            ->assertSessionHasErrors(['password' => 'La confirmación de contraseña no coincide.']);

        $this->get('/registro')
            ->assertSee('Ana Pérez')
            ->assertDontSee('clave-secreta-9')
            ->assertDontSee('distinta-clave-8');
    }

    #[Group('RF-05')]
    public function test_despues_de_registrarse_vuelve_a_la_pagina_que_queria_ver(): void
    {
        $v = $this->vehiculo();

        // Un visitante que intenta algo que pide sesión queda con esa URL guardada
        $this->post("/vehiculos/{$v->id}/consultar")->assertRedirect(route('login'));
        $this->get('/mis-consultas')->assertRedirect(route('login'));

        $this->post('/registro', $this->datos())->assertRedirect('/mis-consultas');
    }

    #[Group('RF-05')]
    public function test_el_registro_tiene_limite_de_intentos(): void
    {
        foreach (range(1, 6) as $i) {
            $this->post('/registro', $this->datos(['email' => 'mal']))->assertSessionHasErrors('email');
        }

        $this->post('/registro', $this->datos())->assertStatus(429);
        $this->assertNull(User::where('email', 'ana@correo.test')->first());
    }

    #[Group('RF-03')]
    public function test_un_cliente_recien_registrado_puede_consultar_por_un_vehiculo(): void
    {
        $v = $this->vehiculo();

        $this->post('/registro', $this->datos());
        $this->post("/vehiculos/{$v->id}/consultar", ['mensaje' => 'Hola'])->assertSessionHas('success');

        $this->get('/mis-consultas')->assertOk()->assertSee('Corolla');
    }

    #[Group('RF-10')]
    public function test_un_cliente_registrado_no_tiene_acceso_a_las_pantallas_del_equipo(): void
    {
        $this->post('/registro', $this->datos());

        foreach (['/vehiculos/create', '/consultas', '/reportes/ventas', '/usuarios'] as $ruta) {
            $this->get($ruta)->assertForbidden();
        }
    }

    #[Group('RF-10')]
    public function test_el_admin_ve_al_nuevo_cliente_en_la_lista_de_usuarios(): void
    {
        $this->post('/registro', $this->datos());
        auth()->logout();

        $this->actingAs($this->admin())->get('/usuarios?rol=cliente')
            ->assertOk()
            ->assertSee('Ana Pérez')
            ->assertSee('ana@correo.test');
    }

    #[Group('RF-05')]
    public function test_el_menu_de_un_visitante_ofrece_registrarse(): void
    {
        $this->get('/vehiculos')->assertSee(route('registro'), false)->assertSee('Registrarse');
        $this->actingAs($this->cliente())->get('/vehiculos')->assertDontSee(route('registro'), false);
    }

    #[Group('RF-05')]
    public function test_el_login_enlaza_al_registro_y_el_detalle_invita_a_crear_cuenta(): void
    {
        $v = $this->vehiculo();

        $this->get('/login')->assertSee(route('registro'), false);
        $this->get("/vehiculos/{$v->id}")->assertSee(route('registro'), false)->assertSee('Crear cuenta');
    }

    #[Group('RF-05')]
    public function test_las_rutas_de_registro_estan_declaradas_con_guest(): void
    {
        $rutas = collect(app('router')->getRoutes()->getRoutes())->keyBy(fn ($r) => $r->getName());

        $this->assertContains('guest', $rutas['registro']->gatherMiddleware());
        $this->assertContains('guest', $rutas['registro.store']->gatherMiddleware());
        $this->assertContains('throttle:6,1', $rutas['registro.store']->gatherMiddleware());
    }
}
