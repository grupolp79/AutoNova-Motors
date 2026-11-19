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

    public function test_los_turnos_de_ejemplo_respetan_las_reglas_de_turnos(): void
    {
        $this->seed();

        $turnos = \App\Models\Turno::with('vehiculo')->get();

        // Al menos uno de cada estado
        foreach (array_keys(\App\Models\Turno::ESTADOS) as $estado) {
            $this->assertTrue($turnos->contains('estado', $estado), "No hay turnos en estado $estado");
        }

        foreach ($turnos as $t) {
            // Lunes a sábado, de 09:00 a 18:00, cada 30 minutos
            $this->assertNull(\App\Models\Turno::problemaDeHorario($t->fecha_hora), "Turno {$t->id} fuera de horario");

            if ($t->estaActivo()) {
                $this->assertTrue($t->fecha_hora->isFuture(), "Turno activo {$t->id} en el pasado");
                $this->assertSame('disponible', $t->vehiculo->estado, "Turno activo {$t->id} de un vehículo no disponible");
            }
        }

        $activos = $turnos->filter->estaActivo();
        $this->assertSame($activos->count(), $activos->unique(fn ($t) => $t->usuario_id . '-' . $t->vehiculo_id)->count(), 'Cliente con dos turnos activos del mismo vehículo');
        $this->assertSame($activos->count(), $activos->unique(fn ($t) => $t->vehiculo_id . '-' . $t->fecha_hora)->count(), 'Vehículo con dos turnos activos en el mismo horario');
    }

    public function test_el_seeder_de_turnos_se_puede_correr_solo_y_reemplaza_los_turnos(): void
    {
        $this->seed();
        $antes = \App\Models\Turno::pluck('id')->all();

        $this->seed(\Database\Seeders\TurnoSeeder::class);

        // Siguen siendo 20, todos nuevos, y el resto de los datos no cambió
        $this->assertSame(20, DB::table('turnos')->count());
        $this->assertEmpty(array_intersect($antes, \App\Models\Turno::pluck('id')->all()));
        $this->assertSame(40, DB::table('vehiculos')->count());
        $this->assertSame(15, DB::table('users')->count());
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
