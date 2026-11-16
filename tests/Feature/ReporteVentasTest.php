<?php

namespace Tests\Feature;

use App\Models\Lead;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\ArmaDatos;
use Tests\TestCase;

/**
 * Reporte gerencial mínimo: ventas por mes contra el período anterior.
 * Criterio (supuesto documentado): una venta es un lead en estado "cerrado", su mes es el de
 * updated_at y su monto es el precio publicado del vehículo.
 *
 * Fecha fija de los tests: 16/11/2026. Datos armados:
 *   Setiembre: 0 ventas
 *   Octubre:   2 ventas (10.000 + 15.000 = 25.000)
 *   Noviembre: 3 ventas (10.000 + 12.000 + 8.000 = 30.000)  → +1 venta (+50 %), +5.000 (+20 %)
 */
class ReporteVentasTest extends TestCase
{
    use RefreshDatabase, ArmaDatos;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-16 12:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function venta(int $precio, string $fecha, string $estado = 'cerrado'): Lead
    {
        return $this->lead($this->vehiculo(['precio' => $precio]), $estado, Carbon::parse($fecha));
    }

    private function datosDeEjemplo(): void
    {
        $this->venta(10000, '2026-10-05 10:00:00');
        $this->venta(15000, '2026-10-20 10:00:00');
        $this->venta(10000, '2026-11-02 10:00:00');
        $this->venta(12000, '2026-11-10 10:00:00');
        $this->venta(8000, '2026-11-15 10:00:00');
    }

    private function fila($filas, string $mes): array
    {
        return $filas->first(fn ($f) => $f['mes']->format('Y-m') === $mes);
    }

    // ------------------------------------------------------------- Cálculo

    #[Group('Reporte')]
    public function test_cuenta_ventas_y_suma_montos_por_mes(): void
    {
        $this->datosDeEjemplo();

        $filas = Lead::ventasPorMes(6);

        $this->assertSame(2, $this->fila($filas, '2026-10')['ventas']);
        $this->assertEquals(25000, $this->fila($filas, '2026-10')['monto']);
        $this->assertSame(3, $this->fila($filas, '2026-11')['ventas']);
        $this->assertEquals(30000, $this->fila($filas, '2026-11')['monto']);
        $this->assertSame(0, $this->fila($filas, '2026-09')['ventas']);
    }

    #[Group('Reporte')]
    public function test_compara_cada_mes_con_el_anterior(): void
    {
        $this->datosDeEjemplo();

        $nov = $this->fila(Lead::ventasPorMes(6), '2026-11');

        $this->assertSame(1, $nov['variacion_ventas']);
        $this->assertSame(50.0, $nov['variacion_ventas_pct']);
        $this->assertEquals(5000, $nov['variacion_monto']);
        $this->assertSame(20.0, $nov['variacion_monto_pct']);
    }

    #[Group('Reporte')]
    public function test_una_baja_da_variacion_negativa(): void
    {
        $this->venta(10000, '2026-10-05');
        $this->venta(10000, '2026-10-06');
        $this->venta(10000, '2026-11-02');

        $nov = $this->fila(Lead::ventasPorMes(6), '2026-11');

        $this->assertSame(-1, $nov['variacion_ventas']);
        $this->assertSame(-50.0, $nov['variacion_ventas_pct']);
    }

    #[Group('Reporte')]
    public function test_si_el_mes_anterior_no_tuvo_ventas_el_porcentaje_es_nulo(): void
    {
        $this->datosDeEjemplo();

        $oct = $this->fila(Lead::ventasPorMes(6), '2026-10');

        $this->assertSame(2, $oct['variacion_ventas']);
        $this->assertNull($oct['variacion_ventas_pct']);
    }

    #[Group('Reporte')]
    public function test_solo_cuentan_los_leads_cerrados(): void
    {
        $this->venta(10000, '2026-11-02', 'cerrado');
        $this->venta(10000, '2026-11-03', 'nuevo');
        $this->venta(10000, '2026-11-04', 'contactado');
        $this->venta(10000, '2026-11-05', 'negociando');

        $this->assertSame(1, $this->fila(Lead::ventasPorMes(6), '2026-11')['ventas']);
    }

    #[Group('Reporte')]
    public function test_los_limites_de_mes_se_respetan(): void
    {
        $this->venta(10000, '2026-10-31 23:59:59');
        $this->venta(10000, '2026-11-01 00:00:00');
        $this->venta(10000, '2026-11-30 23:59:59');
        $this->venta(10000, '2026-12-01 00:00:00'); // fuera del período: es del mes siguiente

        $filas = Lead::ventasPorMes(6);

        $this->assertSame(1, $this->fila($filas, '2026-10')['ventas']);
        $this->assertSame(2, $this->fila($filas, '2026-11')['ventas']);
    }

    #[Group('Reporte')]
    public function test_devuelve_tantas_filas_como_meses_pedidos_del_mas_viejo_al_mas_nuevo(): void
    {
        foreach ([3, 6, 12] as $meses) {
            $filas = Lead::ventasPorMes($meses);

            $this->assertCount($meses, $filas);
            $this->assertSame('2026-11', $filas->last()['mes']->format('Y-m'));
            $this->assertSame(
                Carbon::parse('2026-11-01')->subMonths($meses - 1)->format('Y-m'),
                $filas->first()['mes']->format('Y-m')
            );
        }
    }

    #[Group('Reporte')]
    public function test_el_primer_mes_se_compara_con_uno_anterior_que_no_se_muestra(): void
    {
        // Con 3 meses se muestran Setiembre, Octubre y Noviembre; Agosto es solo la base de comparación
        $this->venta(10000, '2026-08-15');
        $this->venta(10000, '2026-09-15');
        $this->venta(10000, '2026-09-16');

        $filas = Lead::ventasPorMes(3);

        $this->assertCount(3, $filas);
        $this->assertSame('2026-09', $filas->first()['mes']->format('Y-m'));
        $this->assertSame(1, $filas->first()['variacion_ventas']);
        $this->assertSame(100.0, $filas->first()['variacion_ventas_pct']);
    }

    #[Group('Reporte')]
    public function test_una_venta_vieja_fuera_del_periodo_no_aparece(): void
    {
        $this->venta(10000, '2026-01-10');

        $this->assertSame(0, Lead::ventasPorMes(6)->sum('ventas'));
    }

    #[Group('Reporte')]
    public function test_sin_datos_devuelve_ceros_sin_dividir_por_cero(): void
    {
        $filas = Lead::ventasPorMes(6);

        $this->assertCount(6, $filas);
        $this->assertSame(0, $filas->sum('ventas'));
        $this->assertNull($filas->last()['variacion_ventas_pct']);
    }

    // ------------------------------------------------------- Pantalla

    #[Group('Reporte')]
    public function test_el_admin_ve_el_reporte_con_los_numeros(): void
    {
        $this->datosDeEjemplo();

        $this->actingAs($this->admin())
            ->get('/reportes/ventas')
            ->assertOk()
            ->assertSee('Reporte de ventas por mes')
            ->assertSee('Noviembre 2026')
            ->assertSee('Octubre 2026')
            ->assertSee('U$S 30.000')
            ->assertSee('U$S 25.000')
            ->assertSee('+50,0 %')
            ->assertSee('+20,0 %');
    }

    #[Group('Reporte')]
    public function test_el_reporte_aclara_el_criterio_usado(): void
    {
        $this->actingAs($this->admin())
            ->get('/reportes/ventas')
            ->assertOk()
            ->assertSee('Una venta es un lead en estado «Cerrado»')
            ->assertSee('el sistema no guarda un precio de venta aparte');
    }

    #[Group('Reporte')]
    public function test_el_selector_de_periodo_cambia_la_cantidad_de_filas(): void
    {
        $admin = $this->admin();

        foreach ([3, 6, 12] as $meses) {
            $this->actingAs($admin)->get("/reportes/ventas?meses=$meses")
                ->assertOk()
                ->assertViewHas('filas', fn ($f) => $f->count() === $meses)
                ->assertViewHas('meses', $meses);
        }
    }

    #[Group('Reporte')]
    public function test_un_periodo_invalido_vuelve_a_6_meses(): void
    {
        $this->actingAs($this->admin())->get('/reportes/ventas?meses=999')
            ->assertOk()
            ->assertViewHas('meses', 6);

        $this->actingAs($this->admin())->get('/reportes/ventas?meses=abc')
            ->assertOk()
            ->assertViewHas('meses', 6);
    }

    #[Group('Reporte')]
    public function test_el_reporte_vacio_se_muestra_sin_errores(): void
    {
        $this->actingAs($this->admin())->get('/reportes/ventas')
            ->assertOk()
            ->assertSee('U$S 0');
    }

    #[Group('Reporte')]
    public function test_el_reporte_no_es_accesible_sin_ser_admin(): void
    {
        $this->get('/reportes/ventas')->assertRedirect(route('login'));
        $this->actingAs($this->cliente())->get('/reportes/ventas')->assertForbidden();
        $this->actingAs($this->empleado())->get('/reportes/ventas')->assertForbidden();
    }
}
