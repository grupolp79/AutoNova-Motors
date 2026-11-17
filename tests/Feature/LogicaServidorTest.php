<?php

namespace Tests\Feature;

use App\Models\Lead;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\ArmaDatos;
use Tests\TestCase;

/**
 * Hito 3 de Base de Datos: índices de rendimiento y lógica en el servidor (vista y trigger).
 * Corre sobre SQLite en memoria; el procedimiento almacenado es solo de MariaDB y se prueba a mano
 * con database/sql/verificar_servidor.sql.
 */
class LogicaServidorTest extends TestCase
{
    use RefreshDatabase, ArmaDatos;

    private function nombresDeIndices(string $tabla): array
    {
        return array_column(Schema::getIndexes($tabla), 'name');
    }

    // ----------------------------------------------------------------- Índices

    public static function indicesEsperados(): array
    {
        return [
            'marca + modelo' => ['vehiculos', 'idx_vehiculos_marca_modelo', ['marca', 'modelo']],
            'estado + precio' => ['vehiculos', 'idx_vehiculos_estado_precio', ['estado', 'precio']],
            'año' => ['vehiculos', 'idx_vehiculos_anio', ['anio']],
            'kilometraje' => ['vehiculos', 'idx_vehiculos_kilometraje', ['kilometraje']],
            'fotos por vehículo y orden' => ['vehiculo_fotos', 'idx_fotos_vehiculo_orden', ['vehiculo_id', 'orden']],
            'leads por estado y fecha' => ['leads', 'idx_leads_estado_actualizado', ['estado', 'updated_at']],
            'leads por cliente, vehículo y estado' => ['leads', 'idx_leads_cliente_vehiculo_estado', ['usuario_id', 'vehiculo_id', 'estado']],
            'historial de un lead' => ['interacciones', 'idx_interacciones_lead_fecha', ['lead_id', 'fecha']],
            'agenda de turnos' => ['turnos', 'idx_turnos_estado_fecha', ['estado', 'fecha_hora']],
            'reseñas de un vehículo' => ['resenas', 'idx_resenas_vehiculo_moderacion', ['vehiculo_id', 'estado_moderacion']],
        ];
    }

    #[Group('BD-Indices')]
    #[DataProvider('indicesEsperados')]
    public function test_existen_los_indices_con_las_columnas_en_el_orden_previsto(string $tabla, string $nombre, array $columnas): void
    {
        $indice = collect(Schema::getIndexes($tabla))->firstWhere('name', $nombre);

        $this->assertNotNull($indice, "Falta el índice {$nombre} en {$tabla}");
        $this->assertSame($columnas, $indice['columns']);
        $this->assertFalse($indice['unique'], "{$nombre} no debería ser único");
    }

    #[Group('BD-Indices')]
    public function test_no_se_indexan_columnas_de_poca_variedad_por_si_solas(): void
    {
        // "destacado" solo tiene dos valores: un índice propio no ayudaría y haría más lentas las escrituras
        $columnasIndexadas = collect(Schema::getIndexes('vehiculos'))->flatMap(fn ($i) => $i['columns']);

        $this->assertFalse(
            collect(Schema::getIndexes('vehiculos'))->contains(fn ($i) => $i['columns'] === ['destacado']),
            'No debería haber un índice solo sobre destacado'
        );
        $this->assertTrue($columnasIndexadas->contains('estado'));
    }

    #[Group('BD-Indices')]
    public function test_los_indices_no_cambian_el_resultado_de_las_consultas(): void
    {
        $this->vehiculo(['marca' => 'Toyota', 'modelo' => 'Corolla', 'precio' => 20000]);
        $this->vehiculo(['marca' => 'Toyota', 'modelo' => 'Yaris', 'precio' => 12000]);
        $this->vehiculo(['marca' => 'Ford', 'modelo' => 'Fiesta', 'precio' => 9000]);

        $this->assertSame(2, DB::table('vehiculos')->where('marca', 'Toyota')->count());
        $this->assertSame(
            ['Fiesta', 'Yaris'],
            DB::table('vehiculos')->where('precio', '<=', 12000)->orderBy('precio')->pluck('modelo')->all()
        );
    }

    // -------------------------------------------------------------------- Vista

    #[Group('BD-Servidor')]
    public function test_la_vista_lista_solo_las_consultas_cerradas_con_cliente_vehiculo_y_monto(): void
    {
        $cliente = $this->cliente();
        $vendido = $this->vehiculo(['marca' => 'Honda', 'modelo' => 'Civic', 'anio' => 2020, 'precio' => 18500, 'estado' => 'vendido']);
        $cerrado = $this->lead($vendido, 'cerrado', Carbon::parse('2026-11-10 12:00:00'), $cliente);
        $this->lead($this->vehiculo(), 'nuevo');
        $this->lead($this->vehiculo(), 'contactado');
        $this->lead($this->vehiculo(), 'negociando');

        $filas = DB::table('vista_ventas_cerradas')->get();

        $this->assertCount(1, $filas);
        $fila = $filas->first();
        $this->assertSame($cerrado->id, (int) $fila->lead_id);
        $this->assertSame($cliente->name, $fila->cliente);
        $this->assertSame('Honda', $fila->marca);
        $this->assertSame('Civic', $fila->modelo);
        $this->assertSame(2020, (int) $fila->anio);
        $this->assertEqualsWithDelta(18500.0, (float) $fila->monto, 0.001);
        $this->assertSame('2026-11-10 12:00:00', $fila->fecha_cierre);
    }

    #[Group('BD-Servidor')]
    public function test_la_vista_coincide_con_el_reporte_de_la_aplicacion(): void
    {
        Carbon::setTestNow('2026-11-16 15:00:00');
        $this->lead($this->vehiculo(['precio' => 10000]), 'cerrado', Carbon::parse('2026-11-02 10:00:00'));
        $this->lead($this->vehiculo(['precio' => 12000]), 'cerrado', Carbon::parse('2026-11-10 10:00:00'));
        $this->lead($this->vehiculo(['precio' => 9000]), 'cerrado', Carbon::parse('2026-10-20 10:00:00'));

        $deLaApp = Lead::ventasPorMes(3)->first(fn ($f) => $f['mes']->format('Y-m') === '2026-11');
        $deLaVista = DB::table('vista_ventas_cerradas')->where('fecha_cierre', '>=', '2026-11-01')->get();

        $this->assertSame($deLaApp['ventas'], $deLaVista->count());
        $this->assertEqualsWithDelta($deLaApp['monto'], (float) $deLaVista->sum('monto'), 0.001);

        Carbon::setTestNow();
    }

    #[Group('BD-Servidor')]
    public function test_la_vista_se_actualiza_sola_cuando_cambia_el_estado(): void
    {
        $lead = $this->lead($this->vehiculo(), 'negociando');
        $this->assertSame(0, DB::table('vista_ventas_cerradas')->count());

        $lead->update(['estado' => 'cerrado']);

        $this->assertSame(1, DB::table('vista_ventas_cerradas')->count());
    }

    // ------------------------------------------------------------------ Trigger

    public static function retrocesosProhibidos(): array
    {
        return [
            'contactado -> nuevo' => ['contactado', 'nuevo'],
            'negociando -> contactado' => ['negociando', 'contactado'],
            'negociando -> nuevo' => ['negociando', 'nuevo'],
            'cerrado -> negociando' => ['cerrado', 'negociando'],
            'cerrado -> contactado' => ['cerrado', 'contactado'],
            'cerrado -> nuevo' => ['cerrado', 'nuevo'],
        ];
    }

    #[Group('BD-Servidor')]
    #[DataProvider('retrocesosProhibidos')]
    public function test_la_base_rechaza_que_una_consulta_retroceda_de_estado(string $desde, string $hasta): void
    {
        $lead = $this->lead($this->vehiculo(), $desde);

        try {
            DB::table('leads')->where('id', $lead->id)->update(['estado' => $hasta]);
            $this->fail('La base debería haber rechazado el retroceso');
        } catch (QueryException $e) {
            $this->assertStringContainsString('no puede volver a un estado anterior', $e->getMessage());
        }

        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'estado' => $desde]);
    }

    public static function avancesPermitidos(): array
    {
        return [
            'nuevo -> contactado' => ['nuevo', 'contactado'],
            'contactado -> negociando' => ['contactado', 'negociando'],
            'negociando -> cerrado' => ['negociando', 'cerrado'],
            'se mantiene igual' => ['negociando', 'negociando'],
            'cerrado se mantiene cerrado' => ['cerrado', 'cerrado'],
        ];
    }

    #[Group('BD-Servidor')]
    #[DataProvider('avancesPermitidos')]
    public function test_la_base_permite_avanzar_o_mantener_el_estado(string $desde, string $hasta): void
    {
        $lead = $this->lead($this->vehiculo(), $desde);

        DB::table('leads')->where('id', $lead->id)->update(['estado' => $hasta]);

        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'estado' => $hasta]);
    }

    #[Group('BD-Servidor')]
    public function test_cambiar_otras_columnas_de_una_consulta_cerrada_no_dispara_el_trigger(): void
    {
        $lead = $this->lead($this->vehiculo(), 'cerrado');

        DB::table('leads')->where('id', $lead->id)->update(['mensaje' => 'Nota agregada después']);

        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'estado' => 'cerrado', 'mensaje' => 'Nota agregada después']);
    }

    #[Group('BD-Servidor')]
    public function test_el_flujo_normal_de_la_aplicacion_no_choca_con_el_trigger(): void
    {
        $vehiculo = $this->vehiculo();
        $lead = $this->lead($vehiculo, 'nuevo');

        foreach (['contactado', 'negociando', 'cerrado'] as $esperado) {
            $this->assertNull($lead->fresh()->avanzar());
            $this->assertSame($esperado, $lead->fresh()->estado);
        }

        $this->assertSame('vendido', $vehiculo->fresh()->estado);
    }
}
