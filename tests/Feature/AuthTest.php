<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\ArmaDatos;
use Tests\TestCase;

/**
 * RF-05: autenticación de usuarios (el cliente, el empleado y el admin entran con correo y contraseña).
 * Las contraseñas de estos tests son las del factory de pruebas, no las de los usuarios reales.
 */
class AuthTest extends TestCase
{
    use RefreshDatabase, ArmaDatos;

    private function credenciales(User $usuario, array $cambios = []): array
    {
        return $cambios + ['email' => $usuario->email, 'password' => 'password'];
    }

    #[Group('RF-05')]
    public function test_el_login_se_muestra_a_un_visitante(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Iniciar sesión')
            ->assertSee('Correo electrónico')
            ->assertSee('Contraseña');
    }

    #[Group('RF-05')]
    public function test_un_usuario_ya_logueado_no_ve_el_login(): void
    {
        $this->actingAs($this->cliente())->get('/login')->assertRedirect();
    }

    #[Group('RF-05')]
    public function test_login_correcto_inicia_sesion_y_lleva_al_catalogo(): void
    {
        $empleado = $this->empleado();

        $this->post('/login', $this->credenciales($empleado))
            ->assertRedirect(route('vehiculos.index'))
            ->assertSessionHas('success');

        $this->assertAuthenticatedAs($empleado);
    }

    #[Group('RF-05')]
    public function test_login_correcto_para_cada_uno_de_los_tres_roles(): void
    {
        foreach (['cliente', 'empleado', 'admin'] as $rol) {
            $usuario = User::factory()->create(['rol' => $rol]);

            $this->post('/login', $this->credenciales($usuario));
            $this->assertAuthenticatedAs($usuario);
            $this->assertSame($rol, auth()->user()->rol);

            $this->post('/logout');
            $this->assertGuest();
        }
    }

    #[Group('RF-05')]
    public function test_contrasena_incorrecta_no_inicia_sesion(): void
    {
        $usuario = $this->cliente();

        $this->from('/login')
            ->post('/login', $this->credenciales($usuario, ['password' => 'incorrecta']))
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => 'El correo o la contraseña no son correctos.']);

        $this->assertGuest();
    }

    #[Group('RF-05')]
    public function test_correo_inexistente_da_el_mismo_mensaje_que_contrasena_incorrecta(): void
    {
        // Mismo mensaje en ambos casos: no se revela si el correo está registrado
        $this->post('/login', ['email' => 'nadie@ejemplo.com', 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'El correo o la contraseña no son correctos.']);

        $this->assertGuest();
    }

    #[Group('RF-05')]
    public function test_el_login_vacio_muestra_mensajes_de_validacion(): void
    {
        $this->post('/login', [])
            ->assertSessionHasErrors([
                'email' => 'El campo correo electrónico es obligatorio.',
                'password' => 'El campo contraseña es obligatorio.',
            ]);
    }

    #[Group('RF-05')]
    public function test_un_correo_mal_escrito_se_rechaza_con_mensaje_en_espanol(): void
    {
        $this->post('/login', ['email' => 'no-es-un-correo', 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'El campo correo electrónico debe ser un correo electrónico válido.']);
    }

    #[Group('RF-05')]
    public function test_despues_del_login_vuelve_a_la_pagina_que_se_intentaba_abrir(): void
    {
        $admin = $this->admin();

        // Un visitante intenta abrir el alta: se lo manda al login y se recuerda el destino
        $this->get('/vehiculos/create')->assertRedirect(route('login'));

        $this->post('/login', $this->credenciales($admin))
            ->assertRedirect(url('/vehiculos/create'));
    }

    #[Group('RF-05')]
    public function test_las_contrasenas_se_guardan_con_hash(): void
    {
        $usuario = $this->cliente();

        $this->assertNotSame('password', $usuario->password);
        $this->assertTrue(Hash::check('password', $usuario->password));
    }

    #[Group('RF-05')]
    public function test_cerrar_sesion_termina_la_sesion(): void
    {
        $this->actingAs($this->cliente())
            ->post('/logout')
            ->assertRedirect(route('vehiculos.index'))
            ->assertSessionHas('success', 'Sesión cerrada.');

        $this->assertGuest();
    }

    #[Group('RF-05')]
    public function test_cerrar_sesion_solo_se_puede_por_post(): void
    {
        $this->actingAs($this->cliente())->get('/logout')->assertStatus(405);

        $this->assertAuthenticated();
    }

    #[Group('RF-05')]
    public function test_un_visitante_que_cierra_sesion_va_al_login(): void
    {
        $this->post('/logout')->assertRedirect(route('login'));
    }

    #[Group('RF-05')]
    public function test_despues_de_5_intentos_fallidos_se_bloquea_el_login(): void
    {
        $usuario = $this->cliente();
        $malas = $this->credenciales($usuario, ['password' => 'incorrecta']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', $malas)->assertStatus(302);
        }

        // El 6.º intento dentro del minuto se rechaza, incluso con la contraseña correcta
        $this->post('/login', $malas)->assertStatus(429);
        $this->post('/login', $this->credenciales($usuario))->assertStatus(429);
        $this->assertGuest();
    }

    // ------------------------------------------- Lo que ve cada rol en el menú

    #[Group('RF-05')]
    public function test_el_menu_de_un_visitante_ofrece_iniciar_sesion(): void
    {
        $this->get('/vehiculos')
            ->assertSee('Iniciar sesión')
            ->assertDontSee('Cerrar sesión')
            ->assertDontSee('+ Nuevo vehículo')
            ->assertDontSee('Reporte de ventas');
    }

    #[Group('RF-05')]
    public function test_el_menu_de_un_cliente_no_ofrece_gestion(): void
    {
        $cliente = User::factory()->cliente()->create(['name' => 'Ana Cliente']);

        $this->actingAs($cliente)->get('/vehiculos')
            ->assertSee('Ana Cliente')
            ->assertSee('Cliente')
            ->assertSee('Cerrar sesión')
            ->assertDontSee('+ Nuevo vehículo')
            ->assertDontSee('Reporte de ventas');
    }

    #[Group('RF-10')]
    public function test_el_menu_de_un_empleado_ofrece_alta_pero_no_el_reporte(): void
    {
        $this->actingAs($this->empleado())->get('/vehiculos')
            ->assertSee('+ Nuevo vehículo')
            ->assertDontSee('Reporte de ventas');
    }

    #[Group('RF-10')]
    public function test_el_menu_de_un_admin_ofrece_alta_y_reporte(): void
    {
        $this->actingAs($this->admin())->get('/vehiculos')
            ->assertSee('+ Nuevo vehículo')
            ->assertSee('Reporte de ventas');
    }
}
