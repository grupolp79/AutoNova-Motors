<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\ArmaDatos;
use Tests\TestCase;

/**
 * Panel "Descripción" de la ficha del vehículo y su edición rápida (PATCH vehiculos/{vehiculo}/descripcion).
 * Mismo permiso que "Editar" (empleado y admin) y la misma regla de largo que el formulario completo.
 * Cada cambio queda en la auditoría, sin el texto.
 */
#[Group('RF-01')]
#[Group('Auditoria')]
class DescripcionFichaTest extends TestCase
{
    use RefreshDatabase, ArmaDatos;

    private function editar(Vehiculo $v, ?string $texto)
    {
        return $this->from("/vehiculos/{$v->id}")->patch("/vehiculos/{$v->id}/descripcion", ['descripcion' => $texto]);
    }

    // ----------------------------------------------------------------- Permisos

    #[DataProvider('perfiles')]
    public function test_permisos_de_la_edicion_rapida(string $rol, int $esperado, bool $cambia): void
    {
        $v = $this->vehiculo(['descripcion' => 'Texto original.']);
        $actor = $rol === 'visitante' ? $this : $this->actingAs($this->{$rol}());

        $respuesta = $actor->patch("/vehiculos/{$v->id}/descripcion", ['descripcion' => 'Texto nuevo.'])->assertStatus($esperado);

        if ($rol === 'visitante') {
            $respuesta->assertRedirect(route('login'));
        }
        $this->assertSame($cambia ? 'Texto nuevo.' : 'Texto original.', $v->fresh()->descripcion);
        $this->assertSame($cambia ? 1 : 0, Auditoria::count());
    }

    public static function perfiles(): array
    {
        return [
            'visitante' => ['visitante', 302, false],
            'cliente' => ['cliente', 403, false],
            'empleado' => ['empleado', 302, true],
            'admin' => ['admin', 302, true],
        ];
    }

    public function test_la_ruta_tiene_el_mismo_filtro_de_rol_que_editar(): void
    {
        $rutas = collect(app('router')->getRoutes()->getRoutes())->keyBy(fn ($r) => $r->getName());

        $this->assertSame(['PATCH'], $rutas['vehiculos.descripcion']->methods());
        $this->assertSame('vehiculos/{vehiculo}/descripcion', $rutas['vehiculos.descripcion']->uri());
        $this->assertContains('rol:empleado,admin', $rutas['vehiculos.descripcion']->gatherMiddleware());
        $this->assertContains('rol:empleado,admin', $rutas['vehiculos.edit']->gatherMiddleware());
    }

    // ------------------------------------------------------------ Guardar

    #[DataProvider('equipo')]
    public function test_empleado_y_admin_guardan_y_vuelven_a_la_ficha_con_el_mensaje(string $rol): void
    {
        $v = $this->vehiculo(['descripcion' => null]);

        $this->actingAs($this->{$rol}())->editar($v, "  Único dueño.\nService al día.  ")
            ->assertRedirect(route('vehiculos.show', $v))
            ->assertSessionHas('success', 'Descripción actualizada')
            ->assertSessionHasNoErrors();

        $this->assertSame("Único dueño.\nService al día.", $v->fresh()->descripcion);
    }

    public static function equipo(): array
    {
        return ['empleado' => ['empleado'], 'admin' => ['admin']];
    }

    #[DataProvider('vacios')]
    public function test_enviarla_vacia_borra_la_descripcion(?string $texto): void
    {
        $v = $this->vehiculo(['descripcion' => 'Algo para borrar.']);

        $this->actingAs($this->empleado())->editar($v, $texto)->assertSessionHas('success', 'Descripción actualizada');

        $this->assertNull($v->fresh()->descripcion);
    }

    public static function vacios(): array
    {
        return ['vacía' => [''], 'solo espacios' => ['     '], 'nula' => [null]];
    }

    public function test_acepta_el_largo_maximo_y_rechaza_uno_mas_con_el_mismo_mensaje_del_formulario(): void
    {
        $v = $this->vehiculo(['descripcion' => 'Original.']);
        $empleado = $this->empleado();
        $mensaje = 'La descripción puede tener hasta 500 caracteres. Acortala un poco y volvé a guardar.';

        $this->actingAs($empleado)->editar($v, str_repeat('a', Vehiculo::DESCRIPCION_MAX))->assertSessionHasNoErrors();
        $this->assertSame(500, mb_strlen($v->fresh()->descripcion));

        $this->actingAs($empleado)->editar($v, str_repeat('b', Vehiculo::DESCRIPCION_MAX + 1))
            ->assertRedirect("/vehiculos/{$v->id}")
            ->assertSessionHasErrorsIn('descripcion', ['descripcion' => $mensaje]);
        $this->assertSame(str_repeat('a', 500), $v->fresh()->descripcion);

        // El formulario completo usa la misma regla y el mismo mensaje
        $this->actingAs($empleado)->post('/vehiculos', $this->datosFormulario(['descripcion' => str_repeat('c', 501)]))
            ->assertSessionHasErrors(['descripcion' => $mensaje]);
    }

    public function test_los_errores_se_muestran_dentro_del_panel_con_el_formulario_abierto(): void
    {
        $v = $this->vehiculo(['descripcion' => 'Original.']);
        $empleado = $this->empleado();

        $this->actingAs($empleado)->editar($v, str_repeat('b', 501));

        $html = $this->actingAs($empleado)->get("/vehiculos/{$v->id}")->assertOk()
            ->assertSee('La descripción puede tener hasta 500 caracteres.')
            ->assertSee('class="collapse show" id="editarDescripcion"', false)
            ->getContent();

        // El error está dentro del panel (después del título "Descripción")
        $panel = substr($html, strpos($html, 'id="tituloDescripcion"'));
        $this->assertStringContainsString('La descripción puede tener hasta 500 caracteres.', $panel);
        // Y el textarea conserva lo que escribió
        $this->assertStringContainsString(str_repeat('b', 501), $panel);
    }

    // ------------------------------------------------------------ Panel

    public function test_con_descripcion_todos_ven_el_panel_pero_solo_el_equipo_puede_editar(): void
    {
        $v = $this->vehiculo(['descripcion' => 'Muy cuidado, siempre en garage.']);
        $accion = route('vehiculos.descripcion', $v);

        foreach ([null, 'cliente'] as $rol) {
            $pedido = $rol ? $this->actingAs($this->{$rol}()) : $this;
            $pedido->get("/vehiculos/{$v->id}")->assertOk()
                ->assertSee('id="tituloDescripcion"', false)
                ->assertSee('Muy cuidado, siempre en garage.')
                ->assertDontSee('Editar descripción')
                ->assertDontSee($accion, false);
        }

        foreach (['empleado', 'admin'] as $rol) {
            $this->actingAs($this->{$rol}())->get("/vehiculos/{$v->id}")->assertOk()
                ->assertSee('Muy cuidado, siempre en garage.')
                ->assertSee('Editar descripción')
                ->assertSee($accion, false)
                ->assertSee('id="contadorRapida"', false)
                ->assertSee('Guardar')
                ->assertSee('Cancelar');
        }
    }

    public function test_sin_descripcion_el_panel_no_aparece_para_visitante_y_cliente_y_si_para_el_equipo(): void
    {
        $v = $this->vehiculo(['descripcion' => null]);

        foreach ([null, 'cliente'] as $rol) {
            $pedido = $rol ? $this->actingAs($this->{$rol}()) : $this;
            $pedido->get("/vehiculos/{$v->id}")->assertOk()
                ->assertDontSee('id="tituloDescripcion"', false)
                ->assertDontSee('Todavía no tiene descripción');
        }

        foreach (['empleado', 'admin'] as $rol) {
            $this->actingAs($this->{$rol}())->get("/vehiculos/{$v->id}")->assertOk()
                ->assertSee('id="tituloDescripcion"', false)
                ->assertSee('Todavía no tiene descripción')
                ->assertSee('Editar descripción');
        }
    }

    public function test_el_texto_se_escapa_y_respeta_los_saltos_de_linea(): void
    {
        $v = $this->vehiculo(['descripcion' => "<script>alert(1)</script>\nSegunda línea."]);

        foreach ([null, 'empleado'] as $rol) {
            $pedido = $rol ? $this->actingAs($this->{$rol}()) : $this;
            $pedido->get("/vehiculos/{$v->id}")
                ->assertDontSee('<script>alert(1)</script>', false)
                ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
                ->assertSee('class="texto-descripcion', false)
                ->assertSee("&lt;/script&gt;\nSegunda línea.", false);
        }
    }

    public function test_el_panel_va_despues_de_los_datos_en_el_html(): void
    {
        // En el celular la grilla es una sola columna en el orden del HTML: fotos, datos y después la descripción
        $v = $this->vehiculo(['descripcion' => 'Texto.']);

        $this->get("/vehiculos/{$v->id}")
            ->assertSeeInOrder(['class="ficha-fotos"', 'class="ficha-info"', 'class="precio-detalle"', 'ficha-descripcion card panel-descripcion'], false);
    }

    public function test_el_formulario_completo_sigue_teniendo_el_campo_descripcion(): void
    {
        $v = $this->vehiculo(['descripcion' => 'Desde el formulario.']);
        $empleado = $this->empleado();

        $this->actingAs($empleado)->get('/vehiculos/create')->assertOk()->assertSee('name="descripcion"', false);
        $this->actingAs($empleado)->get("/vehiculos/{$v->id}/edit")->assertOk()
            ->assertSee('name="descripcion"', false)->assertSee('Desde el formulario.');
    }

    // ------------------------------------------------------------- Auditoría

    public function test_el_cambio_queda_en_la_auditoria_sin_el_texto(): void
    {
        $empleado = $this->empleado();
        $v = $this->vehiculo(['descripcion' => 'Texto VIEJO secreto.']);

        $this->actingAs($empleado)->editar($v, 'Texto NUEVO secreto.');

        $r = Auditoria::sole();
        $this->assertSame('vehiculo.descripcion', $r->accion);
        $this->assertSame($empleado->id, $r->usuario_id);
        $this->assertSame('vehiculo', $r->referencia_tipo);
        $this->assertSame($v->id, $r->referencia_id);
        $this->assertSame('Toyota Corolla 2020: descripción actualizada.', $r->detalle);

        $fila = json_encode($r->getAttributes());
        $this->assertStringNotContainsString('VIEJO', $fila);
        $this->assertStringNotContainsString('NUEVO', $fila);
    }

    public function test_borrarla_tambien_queda_en_la_auditoria(): void
    {
        $v = $this->vehiculo(['descripcion' => 'Algo.']);

        $this->actingAs($this->admin())->editar($v, '');

        $this->assertSame('Toyota Corolla 2020: descripción borrada.', Auditoria::sole()->detalle);
    }

    public function test_si_la_validacion_falla_no_queda_registro_ni_cambio(): void
    {
        $v = $this->vehiculo(['descripcion' => 'Original.']);

        $this->actingAs($this->empleado())->editar($v, str_repeat('x', 501));

        $this->assertSame(0, Auditoria::count());
        $this->assertSame('Original.', $v->fresh()->descripcion);
    }

    public function test_la_accion_aparece_en_el_filtro_de_la_pantalla_de_auditoria(): void
    {
        $this->assertArrayHasKey('vehiculo.descripcion', Auditoria::ACCIONES);

        $admin = $this->admin();
        $v = $this->vehiculo();
        $this->actingAs($admin)->editar($v, 'Texto.');

        $this->actingAs($admin)->get('/auditoria?accion=vehiculo.descripcion')->assertOk()
            ->assertSee('Editó la descripción de un vehículo')
            ->assertSee('Toyota Corolla 2020: descripción actualizada.');
    }

    // ---------------------------------------------------------------- Menú

    public function test_los_items_del_menu_no_se_parten_en_varias_lineas(): void
    {
        $css = file_get_contents(public_path('css/autonova.css'));

        $this->assertMatchesRegularExpression('#\.navbar-autonova \.nav-link \{[^}]*white-space: nowrap;#', $css);
        // Los nombres de los ítems no cambiaron
        $this->actingAs($this->admin())->get('/vehiculos')
            ->assertSee('Precios de referencia')->assertSee('Reporte de ventas');
    }
}
