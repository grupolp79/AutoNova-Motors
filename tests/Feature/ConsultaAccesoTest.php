<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\ArmaDatos;
use Tests\TestCase;

/**
 * RF-10: quién puede hacer qué con las consultas.
 * 302 a /login = visitante sin sesión; 403 = tiene sesión pero su rol no alcanza.
 * Los pedidos que pasan el permiso pueden terminar en 302 (redirección) o 200 según la ruta.
 */
class ConsultaAccesoTest extends TestCase
{
    use RefreshDatabase, ArmaDatos;

    #[Group('RF-10')]
    #[DataProvider('permisos')]
    public function test_matriz_de_permisos_de_consultas(string $rol, string $metodo, string $ruta, int $esperado): void
    {
        $v = $this->vehiculo();
        $lead = $this->lead($v);
        $url = str_replace(['{vehiculo}', '{consulta}'], [(string) $v->id, (string) $lead->id], $ruta);

        $cliente = $rol === 'visitante' ? $this : $this->actingAs($this->{$rol}());
        $datos = str_contains($ruta, 'interacciones') ? ['tipo' => 'llamada'] : [];

        $respuesta = $cliente->call($metodo, $url, $datos);

        $respuesta->assertStatus($esperado);
        if ($rol === 'visitante') {
            $respuesta->assertRedirect(route('login'));
        }
    }

    public static function permisos(): array
    {
        $filas = [];
        // [método, ruta, visitante, cliente, empleado, admin]
        $tabla = [
            ['POST', '/vehiculos/{vehiculo}/consultar', 302, 302, 403, 403],
            ['GET', '/mis-consultas', 302, 200, 403, 403],
            ['GET', '/consultas', 302, 403, 200, 200],
            ['GET', '/consultas/{consulta}', 302, 403, 200, 200],
            ['PATCH', '/consultas/{consulta}/estado', 302, 403, 302, 302],
            ['POST', '/consultas/{consulta}/interacciones', 302, 403, 302, 302],
        ];

        foreach ($tabla as [$metodo, $ruta, $visitante, $cliente, $empleado, $admin]) {
            foreach (compact('visitante', 'cliente', 'empleado', 'admin') as $rol => $codigo) {
                $filas["$rol $metodo $ruta"] = [$rol, $metodo, $ruta, $codigo];
            }
        }

        return $filas;
    }

    #[Group('RF-10')]
    public function test_un_pedido_rechazado_por_rol_no_modifica_datos(): void
    {
        $lead = $this->lead($this->vehiculo());

        $this->actingAs($this->cliente())->patch("/consultas/{$lead->id}/estado");
        $this->actingAs($this->cliente())->post("/consultas/{$lead->id}/interacciones", ['tipo' => 'llamada']);
        $this->actingAs($this->empleado())->post("/vehiculos/{$lead->vehiculo_id}/consultar");

        $this->assertSame('nuevo', $lead->fresh()->estado);
        $this->assertSame(0, $lead->interacciones()->count());
        $this->assertSame(1, \App\Models\Lead::count());
    }

    #[Group('RF-10')]
    public function test_el_menu_cambia_segun_el_rol(): void
    {
        $this->lead($this->vehiculo());
        $this->lead($this->vehiculo());
        $mias = route('consultas.mias');
        $bandeja = route('consultas.index');

        $this->get('/vehiculos')->assertDontSee($mias, false)->assertDontSee($bandeja, false);

        $this->actingAs($this->cliente())->get('/vehiculos')
            ->assertSee($mias, false)->assertSee('Mis consultas')->assertDontSee($bandeja, false);

        foreach (['empleado', 'admin'] as $rol) {
            $this->actingAs($this->{$rol}())->get('/vehiculos')
                ->assertSee($bandeja, false)->assertDontSee($mias, false);
        }
    }

    #[Group('RF-10')]
    public function test_el_menu_del_equipo_avisa_cuantas_consultas_nuevas_hay(): void
    {
        $this->lead($this->vehiculo(), 'nuevo');
        $this->lead($this->vehiculo(), 'nuevo');
        $this->lead($this->vehiculo(), 'cerrado');

        $this->actingAs($this->empleado())->get('/vehiculos')
            ->assertSee('text-bg-danger">2<', false);
    }

    #[Group('RF-10')]
    public function test_las_rutas_de_consultas_estan_declaradas_con_su_middleware(): void
    {
        $rutas = collect(app('router')->getRoutes()->getRoutes())->keyBy(fn ($r) => $r->getName());

        foreach (['consultas.store', 'consultas.mias'] as $nombre) {
            $this->assertContains('rol:cliente', $rutas[$nombre]->gatherMiddleware(), $nombre);
        }
        foreach (['consultas.index', 'consultas.show', 'consultas.avanzar', 'consultas.interacciones.store'] as $nombre) {
            $this->assertContains('rol:empleado,admin', $rutas[$nombre]->gatherMiddleware(), $nombre);
        }
    }
}
