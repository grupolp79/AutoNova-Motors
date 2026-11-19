<?php

namespace Tests\Feature;

use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\ArmaDatos;
use Tests\TestCase;

/**
 * RF-08: comparar entre 2 y 3 vehículos del catálogo lado a lado.
 * Lo usan todos los perfiles (también el visitante). La selección vive en la sesión (clave 'comparar').
 */
class ComparadorTest extends TestCase
{
    use RefreshDatabase, ArmaDatos;

    /** Pedido como el perfil indicado ('visitante' no inicia sesión). */
    private function como(string $rol)
    {
        return $rol === 'visitante' ? $this : $this->actingAs($this->{$rol}());
    }

    public static function perfiles(): array
    {
        return ['visitante' => ['visitante'], 'cliente' => ['cliente'], 'empleado' => ['empleado'], 'admin' => ['admin']];
    }

    // ----------------------------------------------------------------- Acceso

    #[Group('RF-08')]
    #[DataProvider('perfiles')]
    public function test_los_cuatro_perfiles_pueden_usar_el_comparador(string $rol): void
    {
        $a = $this->vehiculo(['modelo' => 'Corolla']);
        $b = $this->vehiculo(['modelo' => 'Yaris']);
        $actor = $this->como($rol);

        $actor->post("/comparar/{$a->id}")->assertRedirect();
        $actor->post("/comparar/{$b->id}")->assertRedirect();
        $this->assertSame([$a->id, $b->id], session('comparar'));

        $actor->get('/comparar')->assertOk()->assertSee('Toyota Corolla')->assertSee('Toyota Yaris');

        $actor->post("/comparar/{$a->id}/quitar")->assertRedirect();
        $this->assertSame([$b->id], session('comparar'));

        $actor->post('/comparar/vaciar')->assertRedirect(route('vehiculos.index'));
        $this->assertNull(session('comparar'));
    }

    #[Group('RF-08')]
    public function test_las_rutas_no_piden_sesion_iniciada_ni_rol(): void
    {
        $rutas = collect(app('router')->getRoutes()->getRoutes())->keyBy(fn ($r) => $r->getName());

        foreach (['comparador.index', 'comparador.agregar', 'comparador.quitar', 'comparador.vaciar'] as $nombre) {
            $middleware = $rutas[$nombre]->gatherMiddleware();
            $this->assertContains('web', $middleware, $nombre); // sesión y protección CSRF
            $this->assertNotContains('auth', $middleware, $nombre);
            $this->assertEmpty(array_filter($middleware, fn ($m) => str_starts_with($m, 'rol:')), $nombre);
        }
    }

    // ------------------------------------------------------- Agregar y quitar

    #[Group('RF-08')]
    public function test_agregar_guarda_el_vehiculo_en_la_sesion_y_vuelve_a_la_pagina(): void
    {
        $v = $this->vehiculo(['marca' => 'Ford', 'modelo' => 'Fiesta']);

        $this->from('/vehiculos')->post("/comparar/{$v->id}")
            ->assertRedirect('/vehiculos')
            ->assertSessionHas('success', 'Agregaste Ford Fiesta a la comparación.')
            ->assertSessionHas('comparar', [$v->id]);
    }

    #[Group('RF-08')]
    public function test_no_se_repite_un_vehiculo(): void
    {
        $v = $this->vehiculo(['marca' => 'Ford', 'modelo' => 'Fiesta']);

        $this->post("/comparar/{$v->id}");
        $this->post("/comparar/{$v->id}")
            ->assertSessionHas('success', 'Ford Fiesta ya estaba en la comparación.');

        $this->assertSame([$v->id], session('comparar'));
    }

    #[Group('RF-08')]
    public function test_el_tope_es_de_3_y_el_cuarto_da_un_mensaje_claro(): void
    {
        $ids = [];
        foreach (range(1, 3) as $i) {
            $ids[] = $this->vehiculo()->id;
            $this->post('/comparar/' . end($ids))->assertSessionHas('success');
        }
        $cuarto = $this->vehiculo();

        $this->post("/comparar/{$cuarto->id}")
            ->assertSessionHas('error', 'Ya elegiste 3 vehículos para comparar, que es el máximo. Quitá uno antes de agregar otro.');

        $this->assertSame($ids, session('comparar'));
    }

    #[Group('RF-08')]
    public function test_quitar_saca_solo_ese_vehiculo(): void
    {
        $a = $this->vehiculo();
        $b = $this->vehiculo();
        $c = $this->vehiculo();

        $this->withSession(['comparar' => [$a->id, $b->id, $c->id]])
            ->post("/comparar/{$b->id}/quitar")
            ->assertRedirect(route('comparador.index'))
            ->assertSessionHas('success', 'Quitaste el vehículo de la comparación.');

        $this->assertSame([$a->id, $c->id], session('comparar'));
    }

    #[Group('RF-08')]
    public function test_vaciar_borra_toda_la_seleccion(): void
    {
        $this->withSession(['comparar' => [$this->vehiculo()->id, $this->vehiculo()->id]])
            ->post('/comparar/vaciar')
            ->assertRedirect(route('vehiculos.index'))
            ->assertSessionHas('success', 'Vaciaste la comparación.')
            ->assertSessionMissing('comparar');
    }

    #[Group('RF-08')]
    public function test_agregar_un_vehiculo_que_no_existe_da_404(): void
    {
        $this->post('/comparar/9999')->assertNotFound();
        $this->assertNull(session('comparar'));
    }

    #[Group('RF-08')]
    public function test_los_formularios_del_comparador_llevan_el_token_csrf(): void
    {
        // Laravel no controla el token en las pruebas; se comprueba que los formularios lo envíen
        $v = $this->vehiculo();
        $token = '<input type="hidden" name="_token"';

        $detalle = $this->get("/vehiculos/{$v->id}")->getContent();
        $this->assertMatchesRegularExpression(
            '#action="' . preg_quote(route('comparador.agregar', $v), '#') . '"[^>]*>\s*' . preg_quote($token, '#') . '#',
            $detalle
        );

        $catalogo = $this->get('/vehiculos')->getContent();
        $this->assertMatchesRegularExpression('#id="comparar-' . $v->id . '"[^>]*>\s*' . preg_quote($token, '#') . '#s', $catalogo);
    }

    // ------------------------------------------------- Pantalla de comparación

    #[Group('RF-08')]
    public function test_con_menos_de_2_explica_que_hay_que_elegir_al_menos_2(): void
    {
        // Sin ninguno
        $this->get('/comparar')->assertOk()
            ->assertSee('Elegí al menos 2 vehículos para comparar')
            ->assertSee(route('vehiculos.index'), false)
            ->assertDontSee('tabla-comparar', false);

        // Con uno solo
        $v = $this->vehiculo(['marca' => 'Ford', 'modelo' => 'Fiesta']);
        $this->withSession(['comparar' => [$v->id]])->get('/comparar')->assertOk()
            ->assertSee('Elegí al menos 2 vehículos para comparar')
            ->assertSee('Por ahora elegiste solo el Ford Fiesta.')
            ->assertDontSee('tabla-comparar', false);
    }

    #[Group('RF-08')]
    public function test_la_tabla_muestra_todos_los_datos_lado_a_lado(): void
    {
        $a = $this->vehiculo([
            'marca' => 'Ford', 'modelo' => 'Ranger', 'anio' => 2021, 'precio' => 32000, 'kilometraje' => 40000,
            'transmision' => 'manual', 'combustible' => 'diesel', 'estado' => 'reservado',
        ]);
        $b = $this->vehiculo([
            'marca' => 'Toyota', 'modelo' => 'Yaris', 'anio' => 2026, 'precio' => 21000, 'kilometraje' => 0,
            'transmision' => 'automatica', 'combustible' => 'hibrido', 'estado' => 'disponible',
        ]);
        $a->fotos()->create(['ruta' => 'https://ejemplo.com/ranger.jpg', 'orden' => 1]);

        $this->withSession(['comparar' => [$a->id, $b->id]])->get('/comparar')->assertOk()
            ->assertSee('tabla-comparar', false)
            ->assertSee('https://ejemplo.com/ranger.jpg', false)
            ->assertSee('Sin foto')
            // Columnas en el orden en que se agregaron
            ->assertSeeInOrder(['Ford Ranger', 'Toyota Yaris'])
            ->assertSeeInOrder(['U$S 32.000', 'U$S 21.000'])
            ->assertSeeInOrder(['2021', '2026'])
            ->assertSeeInOrder(['40.000 km', '0 km'])
            ->assertSeeInOrder(['Manual', 'Automática'])
            ->assertSeeInOrder(['Diésel', 'Híbrido'])
            ->assertSeeInOrder(['Condición', 'Usado', '0 km'])
            ->assertSeeInOrder(['Reservado', 'Disponible'])
            // Cada columna enlaza al detalle y se puede quitar
            ->assertSee(route('vehiculos.show', $a), false)
            ->assertSee(route('vehiculos.show', $b), false)
            ->assertSee(route('comparador.quitar', $a->id), false)
            ->assertSee(route('comparador.quitar', $b->id), false)
            ->assertSee('Vaciar comparación');
    }

    #[Group('RF-08')]
    public function test_columnas_de_igual_ancho_y_cada_foto_dentro_de_su_marco(): void
    {
        // Una foto subida (chica) y una externa: las dos van en el mismo marco, sin importar su tamaño
        $a = $this->vehiculo(['modelo' => 'Corolla']);
        $b = $this->vehiculo(['modelo' => 'Yaris']);
        $c = $this->vehiculo(['modelo' => 'Hilux']);
        $a->fotos()->create(['ruta' => '/uploads/vehiculos/' . $a->id . '/chica.png', 'orden' => 0]);
        $b->fotos()->create(['ruta' => 'https://ejemplo.com/panoramica.jpg', 'orden' => 0]);

        $html = $this->withSession(['comparar' => [$a->id, $b->id, $c->id]])->get('/comparar')->assertOk()
            ->assertSee('style="--columnas: 3"', false)
            ->getContent();

        // Una <col> para las etiquetas y una por vehículo: los anchos no dependen de las fotos
        preg_match('#<colgroup>(.*?)</colgroup>#s', $html, $colgroup);
        $this->assertSame(1, substr_count($colgroup[1], 'class="col-etiqueta"'));
        $this->assertSame(3, substr_count($colgroup[1], 'class="col-vehiculo"'));

        // Cada foto (y el "Sin foto") está dentro del marco .comparar-foto
        $this->assertMatchesRegularExpression('#class="comparar-foto d-block">\s*<img src="' . preg_quote(asset('uploads/vehiculos/' . $a->id . '/chica.png'), '#') . '"#', $html);
        $this->assertMatchesRegularExpression('#class="comparar-foto d-block">\s*<img src="https://ejemplo\.com/panoramica\.jpg"#', $html);
        $this->assertMatchesRegularExpression('#class="comparar-foto d-block">\s*<span class="sin-foto">#', $html);
        $this->assertSame(3, substr_count($html, 'class="comparar-foto d-block"'));
        // La imagen no trae ancho ni alto propios que agranden o achiquen la columna
        $this->assertDoesNotMatchRegularExpression('#<img[^>]+(width|height)=#', substr($html, strpos($html, '<table')));
    }

    #[Group('RF-08')]
    public function test_marca_el_menor_precio_el_menor_km_y_el_anio_mas_nuevo(): void
    {
        $caro = $this->vehiculo(['anio' => 2024, 'precio' => 30000, 'kilometraje' => 10000]);
        $barato = $this->vehiculo(['anio' => 2018, 'precio' => 12000, 'kilometraje' => 90000]);
        $medio = $this->vehiculo(['anio' => 2020, 'precio' => 18000, 'kilometraje' => 5000]);

        $respuesta = $this->withSession(['comparar' => [$caro->id, $barato->id, $medio->id]])->get('/comparar');

        $this->assertSame(
            ['precio' => 12000.0, 'kilometraje' => 5000.0, 'anio' => 2024.0],
            $respuesta->viewData('mejores')
        );
        // Una marca por cada dato (más la de la explicación de arriba de la tabla)
        $this->assertSame(4, substr_count($respuesta->getContent(), 'class="mejor-valor"'));
    }

    #[Group('RF-08')]
    public function test_si_todos_tienen_el_mismo_valor_no_se_marca_ninguno(): void
    {
        $a = $this->vehiculo(); // los dos con los mismos datos
        $b = $this->vehiculo();

        $respuesta = $this->withSession(['comparar' => [$a->id, $b->id]])->get('/comparar');

        $this->assertSame(['precio' => null, 'kilometraje' => null, 'anio' => null], $respuesta->viewData('mejores'));
        $this->assertSame(1, substr_count($respuesta->getContent(), 'class="mejor-valor"'));
    }

    #[Group('RF-08')]
    public function test_un_vehiculo_eliminado_despues_se_ignora_sin_error(): void
    {
        $a = $this->vehiculo(['modelo' => 'Corolla']);
        $b = $this->vehiculo(['modelo' => 'Yaris']);
        $c = $this->vehiculo(['modelo' => 'Hilux']);
        $b->delete();

        $this->withSession(['comparar' => [$a->id, $b->id, $c->id]])->get('/comparar')
            ->assertOk()
            ->assertSee('Toyota Corolla')->assertSee('Toyota Hilux')->assertDontSee('Toyota Yaris');

        // La sesión queda limpia y el contador del menú vuelve a ser correcto
        $this->assertSame([$a->id, $c->id], session('comparar'));
        $this->get('/vehiculos')->assertSee('Comparar (2)');
    }

    #[Group('RF-08')]
    public function test_si_se_eliminaron_y_queda_uno_solo_explica_que_faltan(): void
    {
        $a = $this->vehiculo();
        $b = $this->vehiculo();
        $b->delete();

        $this->withSession(['comparar' => [$a->id, $b->id]])->get('/comparar')
            ->assertOk()->assertSee('Elegí al menos 2 vehículos para comparar');

        // También se puede quitar el id del vehículo eliminado sin error
        $this->withSession(['comparar' => [$a->id, $b->id]])->post("/comparar/{$b->id}/quitar")->assertRedirect();
        $this->assertSame([$a->id], session('comparar'));
    }

    // ------------------------------------------------ Botones y contador

    #[Group('RF-08')]
    #[DataProvider('perfiles')]
    public function test_el_boton_comparar_esta_en_el_catalogo_y_en_el_detalle(string $rol): void
    {
        $v = $this->vehiculo();
        $agregar = route('comparador.agregar', $v);
        $actor = $this->como($rol);

        $actor->get('/vehiculos')->assertOk()
            ->assertSee('form="comparar-' . $v->id . '"', false)
            ->assertSee($agregar, false)
            ->assertSee('Comparar');

        $actor->get("/vehiculos/{$v->id}")->assertOk()
            ->assertSee($agregar, false)
            ->assertSee('Comparar');
    }

    #[Group('RF-08')]
    public function test_si_ya_esta_elegido_el_boton_lo_quita(): void
    {
        $v = $this->vehiculo();

        $this->withSession(['comparar' => [$v->id]]);

        $this->get('/vehiculos')
            ->assertSee(route('comparador.quitar', $v->id), false)
            ->assertDontSee('action="' . route('comparador.agregar', $v) . '"', false)
            ->assertSee('Quitar de comparar');

        $this->get("/vehiculos/{$v->id}")
            ->assertSee(route('comparador.quitar', $v->id), false)
            ->assertSee('Quitar de comparar');
    }

    #[Group('RF-08')]
    #[DataProvider('perfiles')]
    public function test_el_contador_del_menu_aparece_cuando_hay_al_menos_uno(string $rol): void
    {
        $actor = $this->como($rol);

        $actor->get('/vehiculos')->assertDontSee(route('comparador.index'), false);

        $actor->post('/comparar/' . $this->vehiculo()->id);
        $actor->get('/vehiculos')->assertSee(route('comparador.index'), false)->assertSee('Comparar (1)');

        $actor->post('/comparar/' . $this->vehiculo()->id);
        $actor->get('/vehiculos')->assertSee('Comparar (2)');
    }

    #[Group('RF-08')]
    public function test_la_seleccion_de_un_visitante_no_se_mezcla_con_la_de_otro(): void
    {
        $v = $this->vehiculo();

        $this->post("/comparar/{$v->id}");
        $this->assertSame([$v->id], session('comparar'));

        // Una sesión nueva (otro navegador) empieza vacía
        $this->flushSession();
        $this->get('/comparar')->assertOk()->assertSee('Elegí al menos 2 vehículos para comparar');
    }
}
