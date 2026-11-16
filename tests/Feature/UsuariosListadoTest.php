<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\ArmaDatos;
use Tests\TestCase;

/**
 * RF-10: el Admin puede ver qué usuarios existen y con qué rol (solo lectura).
 * Los permisos por ruta (visitante / cliente / empleado / admin) están en RolesAccesoTest.
 */
class UsuariosListadoTest extends TestCase
{
    use RefreshDatabase, ArmaDatos;

    #[Group('RF-10')]
    public function test_el_admin_ve_a_todos_los_usuarios_con_su_rol(): void
    {
        $admin = $this->admin();
        User::factory()->empleado()->create(['name' => 'Lucia Empleada', 'email' => 'lucia@autonova.test']);
        User::factory()->cliente()->create(['name' => 'Sofia Cliente', 'email' => 'sofia@correo.test']);

        $this->actingAs($admin)->get('/usuarios')
            ->assertOk()
            ->assertSee('Lucia Empleada')
            ->assertSee('lucia@autonova.test')
            ->assertSee('Sofia Cliente')
            ->assertSee($admin->email)
            ->assertSee('Empleado')
            ->assertSee('Cliente');
    }

    #[Group('RF-10')]
    public function test_muestra_la_cantidad_de_usuarios_por_rol(): void
    {
        $this->admin();
        User::factory()->empleado()->count(2)->create();
        User::factory()->cliente()->count(5)->create();

        $respuesta = $this->actingAs(User::where('rol', 'admin')->first())->get('/usuarios')->assertOk();

        $totales = $respuesta->viewData('totales')->map(fn ($n) => (int) $n)->all();
        ksort($totales);

        $this->assertSame(['admin' => 1, 'cliente' => 5, 'empleado' => 2], $totales);
    }

    #[Group('RF-10')]
    public function test_ordena_admin_primero_despues_empleados_y_clientes(): void
    {
        User::factory()->cliente()->create(['name' => 'Ana Cliente']);
        User::factory()->empleado()->create(['name' => 'Beto Empleado']);
        $admin = $this->admin();

        $nombres = $this->actingAs($admin)->get('/usuarios')->viewData('usuarios')->pluck('rol')->all();

        $this->assertSame(['admin', 'empleado', 'cliente'], $nombres);
    }

    #[Group('RF-10')]
    public function test_se_puede_filtrar_por_rol(): void
    {
        $admin = $this->admin();
        User::factory()->empleado()->create(['name' => 'Persona Empleada']);
        User::factory()->cliente()->create(['name' => 'Persona Cliente']);

        $this->actingAs($admin)->get('/usuarios?rol=empleado')
            ->assertOk()
            ->assertSee('Persona Empleada')
            ->assertDontSee('Persona Cliente');

        // Las tarjetas de cantidad siguen mostrando el total aunque haya filtro
        $this->actingAs($admin)->get('/usuarios?rol=empleado')
            ->assertViewHas('totales', fn ($t) => $t['cliente'] === 1);
    }

    #[Group('RF-10')]
    public function test_un_rol_inventado_en_el_filtro_se_ignora(): void
    {
        $admin = $this->admin();
        User::factory()->cliente()->create(['name' => 'Persona Cliente']);

        $this->actingAs($admin)->get('/usuarios?rol=superadmin')
            ->assertOk()
            ->assertSee('Persona Cliente');
    }

    #[Group('RF-10')]
    public function test_marca_al_usuario_que_esta_mirando_la_lista(): void
    {
        $admin = $this->admin();
        User::factory()->cliente()->create();

        $this->actingAs($admin)->get('/usuarios')->assertSee('(vos)', false);
    }

    #[Group('RF-10')]
    public function test_nunca_muestra_contrasenas(): void
    {
        $admin = $this->admin();
        $cliente = $this->cliente();

        $html = $this->actingAs($admin)->get('/usuarios')->assertOk()->getContent();

        $this->assertStringNotContainsString($cliente->password, $html);
        $this->assertStringNotContainsString($admin->password, $html);
        $this->assertStringNotContainsStringIgnoringCase('contraseña', $html);
        $this->assertStringNotContainsString('remember_token', $html);
    }

    #[Group('RF-10')]
    public function test_es_solo_lectura_no_hay_rutas_para_modificar_usuarios(): void
    {
        $admin = $this->admin();
        $cliente = $this->cliente();

        // 405 = la ruta existe pero no admite ese método; 404 = la ruta no existe. Ninguna modifica datos.
        $this->actingAs($admin)->post('/usuarios', ['rol' => 'admin'])->assertStatus(405);
        $this->actingAs($admin)->put("/usuarios/{$cliente->id}", ['rol' => 'admin'])->assertNotFound();
        $this->actingAs($admin)->delete("/usuarios/{$cliente->id}")->assertNotFound();

        $this->assertSame('cliente', $cliente->fresh()->rol);
    }

    #[Group('RF-10')]
    public function test_el_enlace_del_menu_lo_ve_solo_el_admin(): void
    {
        $enlace = route('usuarios.index');

        $this->get('/vehiculos')->assertDontSee($enlace, false);
        $this->actingAs($this->cliente())->get('/vehiculos')->assertDontSee($enlace, false);
        $this->actingAs($this->empleado())->get('/vehiculos')->assertDontSee($enlace, false);
        $this->actingAs($this->admin())->get('/vehiculos')->assertSee($enlace, false);
    }

    #[Group('RF-10')]
    public function test_pagina_de_a_25_usuarios(): void
    {
        $admin = $this->admin();
        User::factory()->cliente()->count(29)->create();

        $respuesta = $this->actingAs($admin)->get('/usuarios')->assertOk();

        $this->assertCount(25, $respuesta->viewData('usuarios'));
        $this->assertSame(30, $respuesta->viewData('usuarios')->total());
    }
}
