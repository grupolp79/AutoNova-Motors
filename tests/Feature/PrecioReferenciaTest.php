<?php

namespace Tests\Feature;

use App\Models\PrecioReferencia;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\ArmaDatos;
use Tests\TestCase;

/**
 * RF-04: tabla de precios de referencia para que el Empleado fundamente una cotización.
 * Pantalla de solo lectura para Empleado y Admin, y comparación en el detalle de cada vehículo.
 */
class PrecioReferenciaTest extends TestCase
{
    use RefreshDatabase, ArmaDatos;

    private function referencia(string $marca, string $modelo, int $desde, int $hasta, float $precio): PrecioReferencia
    {
        return PrecioReferencia::create([
            'marca' => $marca, 'modelo' => $modelo,
            'anio_desde' => $desde, 'anio_hasta' => $hasta, 'precio_referencia' => $precio,
        ]);
    }

    // ----------------------------------------------------------------- Acceso

    public static function roles(): array
    {
        return [
            'visitante' => ['visitante', 302],
            'cliente' => ['cliente', 403],
            'empleado' => ['empleado', 200],
            'admin' => ['admin', 200],
        ];
    }

    #[Group('RF-04')]
    #[DataProvider('roles')]
    public function test_matriz_de_permisos_de_la_tabla(string $rol, int $esperado): void
    {
        $cliente = $rol === 'visitante' ? $this : $this->actingAs($this->{$rol}());

        $respuesta = $cliente->get('/precios-referencia');

        $respuesta->assertStatus($esperado);
        if ($rol === 'visitante') {
            $respuesta->assertRedirect(route('login'));
        }
    }

    #[Group('RF-04')]
    public function test_es_solo_lectura_no_hay_rutas_para_modificar(): void
    {
        $this->referencia('Toyota', 'Corolla', 2018, 2026, 20000);
        $admin = $this->actingAs($this->admin());

        $admin->post('/precios-referencia', ['marca' => 'Ford'])->assertStatus(405);
        $admin->put('/precios-referencia/1', ['precio_referencia' => 1])->assertStatus(404);
        $admin->delete('/precios-referencia/1')->assertStatus(404);

        $this->assertSame(1, PrecioReferencia::count());
        $this->assertEquals(20000, PrecioReferencia::first()->precio_referencia);
    }

    #[Group('RF-04')]
    public function test_la_ruta_esta_declarada_con_su_middleware(): void
    {
        $ruta = collect(app('router')->getRoutes()->getRoutes())->first(fn ($r) => $r->getName() === 'precios.index');

        $this->assertContains('rol:empleado,admin', $ruta->gatherMiddleware());
    }

    // ----------------------------------------------------------------- Listado

    #[Group('RF-04')]
    public function test_lista_ordenada_por_marca_modelo_y_anios_con_el_precio_formateado(): void
    {
        $this->referencia('Toyota', 'Yaris', 2012, 2017, 9000);
        $this->referencia('Ford', 'Fiesta', 2018, 2026, 14500);
        $this->referencia('Toyota', 'Corolla', 2018, 2026, 21000);
        $this->referencia('Toyota', 'Corolla', 2012, 2017, 12000);

        $this->actingAs($this->empleado())->get('/precios-referencia')
            ->assertOk()
            ->assertSeeInOrder([
                'Ford', 'Fiesta', '2018 a 2026', 'U$S 14.500',
                'Toyota', 'Corolla', '2012 a 2017', 'U$S 12.000',
                'Toyota', 'Corolla', '2018 a 2026', 'U$S 21.000',
                'Toyota', 'Yaris',
            ]);
    }

    #[Group('RF-04')]
    public function test_filtra_por_marca_modelo_o_varias_palabras(): void
    {
        $this->referencia('Toyota', 'Corolla', 2018, 2026, 21000);
        $this->referencia('Toyota', 'Yaris', 2018, 2026, 15000);
        $this->referencia('Ford', 'Fiesta', 2018, 2026, 14500);
        $empleado = $this->actingAs($this->empleado());

        $empleado->get('/precios-referencia?q=toyota')
            ->assertSee('Corolla')->assertSee('Yaris')->assertDontSee('Fiesta');

        $empleado->get('/precios-referencia?q=fiesta')
            ->assertSee('Fiesta')->assertDontSee('Corolla');

        $empleado->get('/precios-referencia?q=' . urlencode('Toyota Corolla'))
            ->assertSee('Corolla')->assertDontSee('Yaris')->assertDontSee('Fiesta');
    }

    #[Group('RF-04')]
    public function test_filtra_por_anio_con_los_extremos_incluidos(): void
    {
        $this->referencia('Toyota', 'Corolla', 2012, 2017, 12000);
        $this->referencia('Toyota', 'Corolla', 2018, 2026, 21000);
        $empleado = $this->actingAs($this->empleado());

        $empleado->get('/precios-referencia?anio=2017')->assertSee('U$S 12.000')->assertDontSee('U$S 21.000');
        $empleado->get('/precios-referencia?anio=2018')->assertSee('U$S 21.000')->assertDontSee('U$S 12.000');
        $empleado->get('/precios-referencia?anio=2012')->assertSee('U$S 12.000')->assertDontSee('U$S 21.000');
        $empleado->get('/precios-referencia?anio=2026')->assertSee('U$S 21.000')->assertDontSee('U$S 12.000');
        $empleado->get('/precios-referencia?anio=2011')->assertDontSee('U$S 12.000')->assertDontSee('U$S 21.000')
            ->assertSee('No hay precios de referencia para esa búsqueda.');
    }

    #[Group('RF-04')]
    public function test_se_pueden_combinar_texto_y_anio(): void
    {
        $this->referencia('Toyota', 'Corolla', 2012, 2017, 12000);
        $this->referencia('Toyota', 'Corolla', 2018, 2026, 21000);
        $this->referencia('Ford', 'Fiesta', 2018, 2026, 14500);

        $this->actingAs($this->empleado())->get('/precios-referencia?q=toyota&anio=2020')
            ->assertSee('U$S 21.000')->assertDontSee('U$S 12.000')->assertDontSee('Fiesta');
    }

    public static function filtrosInvalidos(): array
    {
        return [
            'año con letras' => ['anio=abc'],
            'año menor a 1900' => ['anio=1800'],
            'año mayor a 2100' => ['anio=3000'],
            'texto demasiado largo' => ['q=' . 'a'],  // se reemplaza en el test por 101 caracteres
        ];
    }

    #[Group('RF-04')]
    #[DataProvider('filtrosInvalidos')]
    public function test_un_filtro_invalido_se_ignora_en_lugar_de_dar_error(string $consulta): void
    {
        $this->referencia('Toyota', 'Corolla', 2018, 2026, 21000);
        $this->referencia('Ford', 'Fiesta', 2018, 2026, 14500);

        if ($consulta === 'q=a') {
            $consulta = 'q=' . str_repeat('a', 101);
        }

        $this->actingAs($this->empleado())->get('/precios-referencia?' . $consulta)
            ->assertOk()->assertSee('Corolla')->assertSee('Fiesta');
    }

    #[Group('RF-04')]
    public function test_el_porcentaje_de_la_busqueda_se_trata_como_texto_y_no_como_comodin(): void
    {
        $this->referencia('Toyota', 'Corolla', 2018, 2026, 21000);

        $this->actingAs($this->empleado())->get('/precios-referencia?q=' . urlencode('%'))
            ->assertOk()->assertDontSee('Corolla')->assertSee('No hay precios de referencia para esa búsqueda.');
    }

    #[Group('RF-04')]
    public function test_pagina_de_a_20_y_conserva_el_filtro_en_los_enlaces(): void
    {
        foreach (range(1, 25) as $i) {
            $this->referencia('Toyota', 'Modelo ' . str_pad((string) $i, 2, '0', STR_PAD_LEFT), 2018, 2026, 10000 + $i);
        }
        $this->referencia('Ford', 'Fiesta', 2018, 2026, 14500);

        $respuesta = $this->actingAs($this->empleado())->get('/precios-referencia?q=toyota');

        $respuesta->assertViewHas('precios', fn ($p) => $p->count() === 20 && $p->total() === 25);
        $respuesta->assertSee('q=toyota&amp;page=2', false);

        $this->actingAs($this->empleado())->get('/precios-referencia?q=toyota&page=2')
            ->assertViewHas('precios', fn ($p) => $p->count() === 5);
    }

    #[Group('RF-04')]
    public function test_sin_datos_se_explica_que_no_hay_precios_cargados(): void
    {
        $this->actingAs($this->empleado())->get('/precios-referencia')
            ->assertOk()->assertSee('Todavía no hay precios de referencia cargados.');
    }

    // ------------------------------------------------- Coincidencia con un vehículo

    #[Group('RF-04')]
    public function test_encuentra_la_referencia_por_marca_modelo_y_rango_de_anios(): void
    {
        $vieja = $this->referencia('Toyota', 'Corolla', 2012, 2017, 12000);
        $nueva = $this->referencia('Toyota', 'Corolla', 2018, 2026, 21000);
        $this->referencia('Toyota', 'Yaris', 2018, 2026, 15000);
        $this->referencia('Ford', 'Corolla', 2018, 2026, 99999);

        $this->assertTrue($this->vehiculo(['anio' => 2015])->precioReferencia()->is($vieja));
        $this->assertTrue($this->vehiculo(['anio' => 2022])->precioReferencia()->is($nueva));
    }

    public static function extremosDelRango(): array
    {
        return [
            'primer año del rango' => [2018, true],
            'último año del rango' => [2026, true],
            'un año antes' => [2017, false],
            'un año después' => [2027, false],
        ];
    }

    #[Group('RF-04')]
    #[DataProvider('extremosDelRango')]
    public function test_los_extremos_del_rango_estan_incluidos(int $anio, bool $hayReferencia): void
    {
        $this->referencia('Toyota', 'Corolla', 2018, 2026, 21000);

        $this->assertSame($hayReferencia, $this->vehiculo(['anio' => $anio])->precioReferencia() !== null);
    }

    #[Group('RF-04')]
    public function test_si_los_rangos_se_superponen_gana_el_que_empieza_mas_tarde(): void
    {
        $this->referencia('Toyota', 'Corolla', 2012, 2026, 15000);
        $especifica = $this->referencia('Toyota', 'Corolla', 2020, 2026, 25000);

        $this->assertTrue($this->vehiculo(['anio' => 2022])->precioReferencia()->is($especifica));
    }

    #[Group('RF-04')]
    public function test_un_vehiculo_sin_referencia_da_null(): void
    {
        $this->referencia('Toyota', 'Yaris', 2018, 2026, 15000);
        $vehiculo = $this->vehiculo();

        $this->assertNull($vehiculo->precioReferencia());
        $this->assertNull($vehiculo->comparacionPrecio());
    }

    public static function comparaciones(): array
    {
        // precio publicado, diferencia %, veredicto. La referencia es siempre 10.000
        return [
            'mucho más barato' => [9000, -10.0, 'por_debajo'],
            'un poco más barato, todavía en línea' => [9500, -5.0, 'en_linea'],
            'igual a la referencia' => [10000, 0.0, 'en_linea'],
            'un poco más caro, todavía en línea' => [10500, 5.0, 'en_linea'],
            'más caro' => [10600, 6.0, 'por_encima'],
            'mucho más caro' => [13000, 30.0, 'por_encima'],
        ];
    }

    #[Group('RF-04')]
    #[DataProvider('comparaciones')]
    public function test_compara_el_precio_publicado_con_la_referencia(int $precio, float $porcentaje, string $veredicto): void
    {
        $this->referencia('Toyota', 'Corolla', 2018, 2026, 10000);

        $c = $this->vehiculo(['anio' => 2020, 'precio' => $precio])->comparacionPrecio();

        $this->assertSame(10000.0, $c['referencia']);
        $this->assertSame((float) ($precio - 10000), $c['diferencia']);
        $this->assertSame($porcentaje, $c['porcentaje']);
        $this->assertSame($veredicto, $c['veredicto']);
    }

    #[Group('RF-04')]
    public function test_una_referencia_en_cero_no_se_usa_para_no_dividir_por_cero(): void
    {
        $this->referencia('Toyota', 'Corolla', 2018, 2026, 0);

        $this->assertNull($this->vehiculo(['anio' => 2020])->comparacionPrecio());
    }

    #[Group('RF-04')]
    public function test_los_datos_de_ejemplo_dejan_referencia_para_todos_los_vehiculos(): void
    {
        $this->seed();

        $sinReferencia = Vehiculo::all()->filter(fn ($v) => $v->precioReferencia() === null);

        $this->assertSame(0, $sinReferencia->count());
    }

    // ------------------------------------------------------- Detalle del vehículo

    #[Group('RF-04')]
    #[DataProvider('equipo')]
    public function test_el_detalle_muestra_la_comparacion_al_equipo(string $rol): void
    {
        $this->referencia('Toyota', 'Corolla', 2018, 2026, 10000);
        $barato = $this->vehiculo(['anio' => 2020, 'precio' => 9000]);
        $caro = $this->vehiculo(['anio' => 2020, 'precio' => 10600]);
        $justo = $this->vehiculo(['anio' => 2020, 'precio' => 10000]);
        $usuario = $this->actingAs($this->{$rol}());

        $usuario->get("/vehiculos/{$barato->id}")
            ->assertSee('Precio de referencia')
            ->assertSee('U$S 10.000')
            ->assertSee('10,0 % por debajo de la referencia')
            ->assertSee('−1.000');

        $usuario->get("/vehiculos/{$caro->id}")
            ->assertSee('6,0 % por encima de la referencia')
            ->assertSee('+600');

        $usuario->get("/vehiculos/{$justo->id}")
            ->assertSee('En línea con la referencia');
    }

    public static function equipo(): array
    {
        return ['empleado' => ['empleado'], 'admin' => ['admin']];
    }

    #[Group('RF-04')]
    #[DataProvider('equipo')]
    public function test_el_detalle_enlaza_a_la_tabla_filtrada_por_ese_vehiculo(string $rol): void
    {
        $this->referencia('Toyota', 'Corolla', 2018, 2026, 10000);
        $v = $this->vehiculo(['anio' => 2020]);

        $this->actingAs($this->{$rol}())->get("/vehiculos/{$v->id}")
            ->assertSee(e(route('precios.index', ['q' => 'Toyota Corolla', 'anio' => 2020])), false);
    }

    #[Group('RF-04')]
    #[DataProvider('equipo')]
    public function test_el_detalle_avisa_cuando_no_hay_referencia(string $rol): void
    {
        $v = $this->vehiculo(['anio' => 2020]);

        $this->actingAs($this->{$rol}())->get("/vehiculos/{$v->id}")
            ->assertOk()
            ->assertSee('No hay un precio de referencia cargado para Toyota Corolla 2020.');
    }

    #[Group('RF-04')]
    public function test_el_visitante_y_el_cliente_no_ven_la_referencia(): void
    {
        $this->referencia('Toyota', 'Corolla', 2018, 2026, 10000);
        $v = $this->vehiculo(['anio' => 2020, 'precio' => 9000]);

        $this->get("/vehiculos/{$v->id}")
            ->assertOk()->assertDontSee('Precio de referencia')->assertDontSee('por debajo de la referencia');

        $this->actingAs($this->cliente())->get("/vehiculos/{$v->id}")
            ->assertOk()->assertDontSee('Precio de referencia')->assertDontSee('por debajo de la referencia');
    }

    #[Group('RF-04')]
    public function test_el_enlace_del_menu_lo_ve_solo_el_equipo(): void
    {
        $enlace = route('precios.index');

        $this->get('/vehiculos')->assertDontSee($enlace, false);
        $this->actingAs($this->cliente())->get('/vehiculos')->assertDontSee($enlace, false);
        foreach (['empleado', 'admin'] as $rol) {
            $this->actingAs($this->{$rol}())->get('/vehiculos')
                ->assertSee($enlace, false)->assertSee('Precios de referencia');
        }
    }
}
