<?php

namespace Tests\Feature;

use App\Models\Interaccion;
use App\Models\Lead;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\ArmaDatos;
use Tests\TestCase;

/**
 * RF-03, RF-06 y RF-09: el equipo maneja las consultas, las avanza por el ciclo
 * (Nuevo → Contactado → Negociando → Cerrado), el vehículo cambia de estado y queda el historial de contactos.
 */
class ConsultaGestionTest extends TestCase
{
    use RefreshDatabase, ArmaDatos;

    // ===== Bandeja =====

    #[Group('RF-03')]
    #[DataProvider('equipo')]
    public function test_la_bandeja_lista_las_consultas_con_cliente_y_vehiculo(string $rol): void
    {
        $cliente = $this->cliente();
        $this->lead($this->vehiculo(['marca' => 'Fiat', 'modelo' => 'Cronos']), 'nuevo', null, $cliente);

        $this->actingAs($this->{$rol}())->get('/consultas')
            ->assertOk()
            ->assertSee($cliente->name)
            ->assertSee($cliente->email)
            ->assertSee('Cronos');
    }

    public static function equipo(): array
    {
        return ['empleado' => ['empleado'], 'admin' => ['admin']];
    }

    #[Group('RF-03')]
    public function test_la_bandeja_filtra_por_estado(): void
    {
        $this->lead($this->vehiculo(['modelo' => 'ModeloNuevo']), 'nuevo');
        $this->lead($this->vehiculo(['modelo' => 'ModeloCerrado']), 'cerrado');

        $this->actingAs($this->empleado())->get('/consultas?estado=cerrado')
            ->assertOk()
            ->assertSee('ModeloCerrado')
            ->assertDontSee('ModeloNuevo');
    }

    #[Group('RF-03')]
    public function test_un_estado_inventado_en_el_filtro_se_ignora(): void
    {
        $this->lead($this->vehiculo(['modelo' => 'ModeloUno']));

        $this->actingAs($this->empleado())->get('/consultas?estado=hackeado')
            ->assertOk()
            ->assertSee('ModeloUno');
    }

    #[Group('RF-03')]
    public function test_la_bandeja_filtra_por_vehiculo_y_un_vehiculo_inexistente_se_ignora(): void
    {
        $a = $this->vehiculo(['modelo' => 'AutoA']);
        $b = $this->vehiculo(['modelo' => 'AutoB']);
        $this->lead($a);
        $this->lead($b);
        $equipo = $this->empleado();

        $this->actingAs($equipo)->get("/consultas?vehiculo={$a->id}")
            ->assertSee('AutoA')->assertDontSee('AutoB');

        $this->actingAs($equipo)->get('/consultas?vehiculo=99999')
            ->assertOk()->assertSee('AutoA')->assertSee('AutoB');
    }

    #[Group('RF-03')]
    public function test_los_totales_por_estado_no_cambian_con_el_filtro(): void
    {
        $this->lead($this->vehiculo(), 'nuevo');
        $this->lead($this->vehiculo(), 'nuevo');
        $this->lead($this->vehiculo(), 'cerrado');

        $r = $this->actingAs($this->empleado())->get('/consultas?estado=cerrado')->assertOk();

        $totales = $r->viewData('totales')->map(fn ($n) => (int) $n)->all();
        $this->assertSame(2, $totales['nuevo']);
        $this->assertSame(1, $totales['cerrado']);
    }

    #[Group('RF-03')]
    public function test_la_bandeja_muestra_primero_las_que_esperan_respuesta(): void
    {
        $this->lead($this->vehiculo(), 'cerrado');
        $this->lead($this->vehiculo(), 'negociando');
        $this->lead($this->vehiculo(), 'nuevo');

        $r = $this->actingAs($this->empleado())->get('/consultas')->assertOk();

        $this->assertSame(['nuevo', 'negociando', 'cerrado'], $r->viewData('consultas')->pluck('estado')->all());
    }

    #[Group('RF-03')]
    public function test_la_bandeja_marca_las_consultas_abiertas_de_un_vehiculo_ya_vendido(): void
    {
        $v = $this->vehiculo(['estado' => 'vendido']);
        $this->lead($v, 'contactado');

        $this->actingAs($this->empleado())->get('/consultas')->assertOk()->assertSee('Vehículo vendido');
    }

    // ===== Detalle e historial =====

    #[Group('RF-09')]
    public function test_el_detalle_muestra_cliente_mensaje_e_historial_en_orden_cronologico(): void
    {
        $lead = $this->lead($this->vehiculo());
        $lead->update(['mensaje' => 'Me interesa la financiación']);
        Interaccion::create(['lead_id' => $lead->id, 'tipo' => 'visita', 'fecha' => Carbon::parse('2026-11-10 10:00'), 'comentario' => 'SEGUNDO']);
        Interaccion::create(['lead_id' => $lead->id, 'tipo' => 'llamada', 'fecha' => Carbon::parse('2026-11-05 09:00'), 'comentario' => 'PRIMERO']);

        $this->actingAs($this->empleado())->get("/consultas/{$lead->id}")
            ->assertOk()
            ->assertSee($lead->cliente->email)
            ->assertSee('Me interesa la financiación')
            ->assertSeeInOrder(['PRIMERO', 'SEGUNDO']);
    }

    #[Group('RF-09')]
    public function test_el_detalle_sin_mensaje_ni_contactos_lo_aclara(): void
    {
        $lead = $this->lead($this->vehiculo());

        $this->actingAs($this->empleado())->get("/consultas/{$lead->id}")
            ->assertOk()
            ->assertSee('El cliente no dejó mensaje.')
            ->assertSee('Todavía no hay contactos registrados.');
    }

    #[Group('RF-09')]
    public function test_una_consulta_inexistente_da_404(): void
    {
        $this->actingAs($this->empleado())->get('/consultas/99999')->assertNotFound();
    }

    // ===== Avance de estado y estado del vehículo =====

    #[Group('RF-03')]
    public function test_la_consulta_avanza_un_paso_por_vez_hasta_cerrarse(): void
    {
        $lead = $this->lead($this->vehiculo());
        $equipo = $this->empleado();

        foreach (['contactado', 'negociando', 'cerrado'] as $esperado) {
            $this->actingAs($equipo)->patch("/consultas/{$lead->id}/estado")
                ->assertRedirect(route('consultas.show', $lead))
                ->assertSessionHas('success');

            $this->assertSame($esperado, $lead->fresh()->estado);
        }
    }

    #[Group('RF-03')]
    public function test_no_se_puede_saltear_pasos_mandando_el_estado_en_el_pedido(): void
    {
        $lead = $this->lead($this->vehiculo(), 'nuevo');

        $this->actingAs($this->empleado())->patch("/consultas/{$lead->id}/estado", ['estado' => 'cerrado']);

        $this->assertSame('contactado', $lead->fresh()->estado);
        $this->assertSame('disponible', $lead->vehiculo->fresh()->estado);
    }

    #[Group('RF-03')]
    public function test_una_consulta_cerrada_no_avanza_mas(): void
    {
        $lead = $this->lead($this->vehiculo(['estado' => 'vendido']), 'cerrado');

        $this->actingAs($this->empleado())->patch("/consultas/{$lead->id}/estado")
            ->assertSessionHas('error', 'La consulta ya está cerrada.');

        $this->assertSame('cerrado', $lead->fresh()->estado);
    }

    #[Group('RF-06')]
    public function test_pasar_a_contactado_no_cambia_el_estado_del_vehiculo(): void
    {
        $lead = $this->lead($this->vehiculo(['estado' => 'disponible']));

        $this->actingAs($this->empleado())->patch("/consultas/{$lead->id}/estado");

        $this->assertSame('disponible', $lead->vehiculo->fresh()->estado);
    }

    #[Group('RF-06')]
    public function test_pasar_a_negociando_deja_el_vehiculo_reservado(): void
    {
        $lead = $this->lead($this->vehiculo(['estado' => 'disponible']), 'contactado');

        $this->actingAs($this->empleado())->patch("/consultas/{$lead->id}/estado")
            ->assertSessionHas('success', 'Consulta en negociación. El vehículo quedó Reservado.');

        $this->assertSame('reservado', $lead->vehiculo->fresh()->estado);
    }

    #[Group('RF-06')]
    public function test_cerrar_la_consulta_deja_el_vehiculo_vendido(): void
    {
        $lead = $this->lead($this->vehiculo(['estado' => 'reservado']), 'negociando');

        $this->actingAs($this->empleado())->patch("/consultas/{$lead->id}/estado")
            ->assertSessionHas('success', 'Consulta cerrada. El vehículo pasó a Vendido.');

        $this->assertSame('cerrado', $lead->fresh()->estado);
        $this->assertSame('vendido', $lead->vehiculo->fresh()->estado);
    }

    #[Group('RF-06')]
    public function test_no_se_puede_cerrar_una_consulta_si_el_vehiculo_ya_se_vendio(): void
    {
        $v = $this->vehiculo(['estado' => 'vendido']);
        $lead = $this->lead($v, 'negociando');

        $this->actingAs($this->empleado())->patch("/consultas/{$lead->id}/estado")
            ->assertSessionHas('error', 'El vehículo ya figura como vendido, así que esta consulta no se puede avanzar.');

        $this->assertSame('negociando', $lead->fresh()->estado);
    }

    #[Group('RF-06')]
    public function test_dos_consultas_del_mismo_vehiculo_no_pueden_cerrarse_las_dos(): void
    {
        $v = $this->vehiculo(['estado' => 'reservado']);
        $a = $this->lead($v, 'negociando');
        $b = $this->lead($v, 'negociando');
        $equipo = $this->empleado();

        $this->actingAs($equipo)->patch("/consultas/{$a->id}/estado");
        $this->actingAs($equipo)->patch("/consultas/{$b->id}/estado")->assertSessionHas('error');

        $this->assertSame('cerrado', $a->fresh()->estado);
        $this->assertSame('negociando', $b->fresh()->estado);
    }

    #[Group('RF-06')]
    public function test_el_detalle_avisa_cuando_el_vehiculo_ya_se_vendio_y_no_ofrece_avanzar(): void
    {
        $lead = $this->lead($this->vehiculo(['estado' => 'vendido']), 'contactado');

        $this->actingAs($this->empleado())->get("/consultas/{$lead->id}")
            ->assertOk()
            ->assertSee('la consulta no se puede avanzar')
            ->assertDontSee('Pasar a Negociando');
    }

    #[Group('RF-06')]
    public function test_el_detalle_ofrece_el_siguiente_paso_y_avisa_lo_que_pasa_con_el_vehiculo(): void
    {
        $contactado = $this->lead($this->vehiculo(), 'contactado');
        $negociando = $this->lead($this->vehiculo(), 'negociando');
        $equipo = $this->empleado();

        $this->actingAs($equipo)->get("/consultas/{$contactado->id}")
            ->assertSee('Pasar a Negociando')
            ->assertSee('el vehículo queda Reservado');
        $this->actingAs($equipo)->get("/consultas/{$negociando->id}")
            ->assertSee('Pasar a Cerrado')
            ->assertSee('el vehículo pasa a Vendido');
    }

    #[Group('RF-06')]
    public function test_cerrar_una_consulta_alimenta_el_reporte_de_ventas(): void
    {
        $v = $this->vehiculo(['precio' => 18000, 'estado' => 'reservado']);
        $lead = $this->lead($v, 'negociando');

        $this->actingAs($this->empleado())->patch("/consultas/{$lead->id}/estado");

        $actual = Lead::ventasPorMes(1)->last();
        $this->assertSame(1, $actual['ventas']);
        $this->assertEquals(18000, $actual['monto']);
    }

    // ===== Historial de contactos =====

    #[Group('RF-09')]
    public function test_se_registra_un_contacto_en_el_historial(): void
    {
        $lead = $this->lead($this->vehiculo(), 'contactado');

        $this->actingAs($this->empleado())->post("/consultas/{$lead->id}/interacciones", [
            'tipo' => 'llamada',
            'fecha' => '2026-11-10T14:30',
            'comentario' => '  Atendió, quedó en venir el jueves  ',
        ])->assertRedirect(route('consultas.show', $lead))->assertSessionHas('success');

        $i = $lead->interacciones()->sole();
        $this->assertSame('llamada', $i->tipo);
        $this->assertSame('2026-11-10 14:30', $i->fecha->format('Y-m-d H:i'));
        $this->assertSame('Atendió, quedó en venir el jueves', $i->comentario);
    }

    #[Group('RF-09')]
    public function test_sin_fecha_se_usa_el_momento_actual_y_el_comentario_es_opcional(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-16 15:00:00'));
        $lead = $this->lead($this->vehiculo());

        $this->actingAs($this->empleado())->post("/consultas/{$lead->id}/interacciones", ['tipo' => 'correo'])
            ->assertSessionHasNoErrors();

        $i = $lead->interacciones()->sole();
        $this->assertSame('2026-11-16 15:00', $i->fecha->format('Y-m-d H:i'));
        $this->assertNull($i->comentario);
        Carbon::setTestNow();
    }

    #[Group('RF-09')]
    #[DataProvider('contactosInvalidos')]
    public function test_rechaza_contactos_invalidos(array $datos, string $campo): void
    {
        $lead = $this->lead($this->vehiculo());

        $this->actingAs($this->empleado())->post("/consultas/{$lead->id}/interacciones", $datos)
            ->assertSessionHasErrors($campo);

        $this->assertSame(0, Interaccion::count());
    }

    public static function contactosInvalidos(): array
    {
        return [
            'sin tipo' => [['comentario' => 'hola'], 'tipo'],
            'tipo inventado' => [['tipo' => 'telepatia'], 'tipo'],
            'fecha que no es fecha' => [['tipo' => 'llamada', 'fecha' => 'mañana'], 'fecha'],
            'fecha futura' => [['tipo' => 'llamada', 'fecha' => now()->addDays(2)->format('Y-m-d\TH:i')], 'fecha'],
            'comentario muy largo' => [['tipo' => 'visita', 'comentario' => str_repeat('x', 501)], 'comentario'],
        ];
    }

    #[Group('RF-09')]
    public function test_a_una_consulta_cerrada_no_se_le_agregan_contactos(): void
    {
        $lead = $this->lead($this->vehiculo(['estado' => 'vendido']), 'cerrado');

        $this->actingAs($this->empleado())->post("/consultas/{$lead->id}/interacciones", ['tipo' => 'llamada'])
            ->assertSessionHas('error', 'La consulta está cerrada, no se pueden agregar contactos.');

        $this->assertSame(0, Interaccion::count());
    }

    #[Group('RF-09')]
    public function test_agregar_un_contacto_no_cambia_el_estado_ni_la_fecha_de_la_consulta(): void
    {
        $fecha = Carbon::parse('2026-10-01 10:00:00');
        $lead = $this->lead($this->vehiculo(), 'contactado', $fecha);

        $this->actingAs($this->empleado())->post("/consultas/{$lead->id}/interacciones", ['tipo' => 'visita']);

        $this->assertSame('contactado', $lead->fresh()->estado);
        $this->assertTrue($lead->fresh()->updated_at->equalTo($fecha));
    }

    #[Group('RF-09')]
    public function test_el_detalle_de_una_consulta_cerrada_no_muestra_el_formulario_de_contactos(): void
    {
        $lead = $this->lead($this->vehiculo(['estado' => 'vendido']), 'cerrado');

        $this->actingAs($this->empleado())->get("/consultas/{$lead->id}")
            ->assertOk()
            ->assertSee('no se agregan más contactos')
            ->assertDontSee('Registrar un contacto');
    }
}
