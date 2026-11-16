<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Los seeders son la "carga de datos de prueba" del proyecto. Algunas tablas tienen cantidad fija
 * y otras varían en cada corrida (usan azar): por eso solo se afirma lo fijo y, de lo variable,
 * que haya datos y que no queden registros huérfanos.
 */
class SeedersTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_carga_de_datos_deja_las_cantidades_esperadas_y_coherentes(): void
    {
        $this->seed();

        // Cantidades fijas
        $this->assertSame(40, DB::table('vehiculos')->count());
        $this->assertSame(15, DB::table('users')->count());
        $this->assertSame(20, DB::table('turnos')->count());
        $this->assertSame(64, DB::table('precios_referencia')->count());
        $this->assertEquals(
            ['admin' => 1, 'cliente' => 12, 'empleado' => 2],
            DB::table('users')->select('rol', DB::raw('count(*) as c'))->groupBy('rol')->orderBy('rol')->pluck('c', 'rol')->all()
        );

        // Cantidades que varían: al menos hay datos
        foreach (['vehiculo_fotos', 'leads', 'interacciones', 'resenas', 'favoritos'] as $tabla) {
            $this->assertGreaterThan(0, DB::table($tabla)->count(), "$tabla quedó vacía");
        }
    }

    public function test_la_carga_no_deja_registros_huerfanos(): void
    {
        $this->seed();

        $this->assertSame(0, DB::table('leads')->whereNotIn('vehiculo_id', DB::table('vehiculos')->select('id'))->count());
        $this->assertSame(0, DB::table('leads')->whereNotIn('usuario_id', DB::table('users')->select('id'))->count());
        $this->assertSame(0, DB::table('interacciones')->whereNotIn('lead_id', DB::table('leads')->select('id'))->count());
        $this->assertSame(0, DB::table('vehiculo_fotos')->whereNotIn('vehiculo_id', DB::table('vehiculos')->select('id'))->count());
        $this->assertSame(0, DB::table('favoritos')->whereNotIn('vehiculo_id', DB::table('vehiculos')->select('id'))->count());
    }

    public function test_las_resenas_son_de_ventas_cerradas(): void
    {
        $this->seed();

        // RF-14: una reseña solo existe si el cliente cerró una compra de ese vehículo
        $sinVenta = DB::table('resenas')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))->from('leads')
                    ->whereColumn('leads.usuario_id', 'resenas.usuario_id')
                    ->whereColumn('leads.vehiculo_id', 'resenas.vehiculo_id')
                    ->where('leads.estado', 'cerrado');
            })->count();

        $this->assertSame(0, $sinVenta);
    }
}
