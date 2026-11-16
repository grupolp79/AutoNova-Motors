<?php

namespace Tests\Feature;

use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\ArmaDatos;
use Tests\TestCase;

/**
 * RF-05 y RF-10: rutas protegidas y diferencia de permisos entre Cliente, Empleado y Admin.
 * Cada fila de la tabla es: quién entra, a qué ruta, y qué respuesta se espera.
 * 302 a /login = visitante sin sesión; 403 = tiene sesión pero su rol no alcanza.
 */
class RolesAccesoTest extends TestCase
{
    use RefreshDatabase, ArmaDatos;

    /** Hace la petición como el rol indicado ('visitante' = sin sesión). */
    private function pedir(string $rol, string $metodo, string $url, array $datos = [])
    {
        $cliente = $rol === 'visitante' ? $this : $this->actingAs(match ($rol) {
            'cliente' => $this->cliente(),
            'empleado' => $this->empleado(),
            'admin' => $this->admin(),
        });

        return $cliente->call($metodo, $url, $datos);
    }

    #[Group('RF-10')]
    #[DataProvider('permisos')]
    public function test_matriz_de_permisos(string $rol, string $metodo, string $ruta, int $esperado): void
    {
        $v = $this->vehiculo();
        $url = str_replace('{id}', (string) $v->id, $ruta);
        $datos = in_array($metodo, ['POST', 'PUT'], true) ? $this->datosFormulario() : [];

        $respuesta = $this->pedir($rol, $metodo, $url, $datos);

        $respuesta->assertStatus($esperado);
        if ($esperado === 302 && $rol === 'visitante') {
            $respuesta->assertRedirect(route('login'));
        }
    }

    public static function permisos(): array
    {
        $filas = [];
        // [método, ruta, visitante, cliente, empleado, admin]
        $tabla = [
            ['GET', '/vehiculos', 200, 200, 200, 200],
            ['GET', '/vehiculos/{id}', 200, 200, 200, 200],
            ['GET', '/vehiculos/create', 302, 403, 200, 200],
            ['POST', '/vehiculos', 302, 403, 302, 302],
            ['GET', '/vehiculos/{id}/edit', 302, 403, 200, 200],
            ['PUT', '/vehiculos/{id}', 302, 403, 302, 302],
            ['DELETE', '/vehiculos/{id}', 302, 403, 403, 302],
            ['GET', '/reportes/ventas', 302, 403, 403, 200],
        ];

        foreach ($tabla as [$metodo, $ruta, $visitante, $cliente, $empleado, $admin]) {
            foreach (compact('visitante', 'cliente', 'empleado', 'admin') as $rol => $codigo) {
                $filas["$rol $metodo $ruta"] = [$rol, $metodo, $ruta, $codigo];
            }
        }

        return $filas;
    }

    #[Group('RF-05')]
    public function test_la_pagina_de_acceso_denegado_explica_el_motivo(): void
    {
        $this->actingAs($this->cliente())
            ->get('/vehiculos/create')
            ->assertForbidden()
            ->assertSee('Acceso denegado')
            ->assertSee('Tu rol no tiene permiso para realizar esta acción.')
            ->assertSee('cliente');
    }

    #[Group('RF-05')]
    public function test_un_visitante_ve_el_aviso_de_que_tiene_que_iniciar_sesion(): void
    {
        $this->get('/vehiculos/create')
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Iniciá sesión para continuar.');
    }

    #[Group('RF-10')]
    public function test_el_boton_editar_lo_ven_empleado_y_admin_pero_no_el_cliente_ni_el_visitante(): void
    {
        $v = $this->vehiculo();
        $editar = "/vehiculos/{$v->id}/edit";

        $this->get("/vehiculos/{$v->id}")->assertDontSee($editar, false);
        $this->actingAs($this->cliente())->get("/vehiculos/{$v->id}")->assertDontSee($editar, false);
        $this->actingAs($this->empleado())->get("/vehiculos/{$v->id}")->assertSee($editar, false);
        $this->actingAs($this->admin())->get("/vehiculos/{$v->id}")->assertSee($editar, false);
    }

    #[Group('RF-10')]
    public function test_el_boton_eliminar_lo_ve_solo_el_admin(): void
    {
        $v = $this->vehiculo();

        $this->get("/vehiculos/{$v->id}")->assertDontSee('Eliminar');
        $this->actingAs($this->cliente())->get("/vehiculos/{$v->id}")->assertDontSee('Eliminar');
        $this->actingAs($this->empleado())->get("/vehiculos/{$v->id}")->assertDontSee('Eliminar');
        $this->actingAs($this->admin())->get("/vehiculos/{$v->id}")->assertSee('Eliminar');
    }

    #[Group('RF-05')]
    public function test_un_pedido_rechazado_por_rol_no_modifica_datos(): void
    {
        $v = $this->vehiculo(['marca' => 'Toyota']);

        $this->actingAs($this->cliente())->post('/vehiculos', $this->datosFormulario());
        $this->actingAs($this->cliente())->put("/vehiculos/{$v->id}", $this->datosFormulario(['marca' => 'Hackeado']));

        $this->assertSame(1, Vehiculo::count());
        $this->assertSame('Toyota', $v->fresh()->marca);
    }

    #[Group('RF-02')]
    public function test_el_catalogo_sigue_siendo_publico_aunque_haya_roles(): void
    {
        $this->vehiculo(['marca' => 'Renault']);

        $this->get('/vehiculos')->assertOk()->assertSee('Renault');
    }

    #[Group('RF-05')]
    public function test_las_rutas_protegidas_estan_declaradas_con_su_middleware(): void
    {
        $rutas = collect(app('router')->getRoutes()->getRoutes())->keyBy(fn ($r) => $r->getName());

        $this->assertContains('rol:empleado,admin', $rutas['vehiculos.create']->gatherMiddleware());
        $this->assertContains('rol:empleado,admin', $rutas['vehiculos.store']->gatherMiddleware());
        $this->assertContains('rol:empleado,admin', $rutas['vehiculos.edit']->gatherMiddleware());
        $this->assertContains('rol:empleado,admin', $rutas['vehiculos.update']->gatherMiddleware());
        $this->assertContains('rol:admin', $rutas['vehiculos.destroy']->gatherMiddleware());
        $this->assertContains('rol:admin', $rutas['reportes.ventas']->gatherMiddleware());
        $this->assertNotContains('rol:admin', $rutas['vehiculos.index']->gatherMiddleware());
    }
}
