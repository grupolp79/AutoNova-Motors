<?php

namespace Tests\Feature;

use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\ArmaDatos;
use Tests\TestCase;

/**
 * El catálogo y el detalle cambian según el rol.
 * Visitante y Cliente ven la vidriera; Empleado y Admin ven además las herramientas de gestión.
 * Las herramientas no tienen que estar ni siquiera en el HTML de quien no las puede usar.
 */
class VistaGestionTest extends TestCase
{
    use RefreshDatabase, ArmaDatos;

    // Marca que solo aparece en el formulario de eliminar (los formularios simulan DELETE con este campo)
    private const FORM_ELIMINAR = 'name="_method" value="DELETE"';

    #[Group('RF-02')]
    public function test_visitante_y_cliente_no_ven_la_barra_ni_los_totales_ni_los_botones(): void
    {
        $v = $this->vehiculo();
        $editar = route('vehiculos.edit', $v);

        foreach ([null, $this->cliente()] as $usuario) {
            $pedido = $usuario ? $this->actingAs($usuario) : $this;

            $pedido->get('/vehiculos')->assertOk()
                ->assertSee('Encontrá tu próximo auto')
                ->assertDontSee('Vista de gestión')
                ->assertDontSee('totalesGestion', false)
                ->assertDontSee('data-total=', false)
                ->assertDontSee($editar, false)
                ->assertDontSee(self::FORM_ELIMINAR, false);

            $pedido->get("/vehiculos/{$v->id}")->assertOk()
                ->assertDontSee('Gestión del vehículo')
                ->assertDontSee('Última modificación')
                ->assertDontSee($editar, false)
                ->assertDontSee(self::FORM_ELIMINAR, false);
        }
    }

    #[Group('RF-01')]
    public function test_el_empleado_ve_editar_pero_no_eliminar(): void
    {
        $v = $this->vehiculo();
        $empleado = $this->empleado();

        $this->actingAs($empleado)->get('/vehiculos')->assertOk()
            ->assertSee('Vista de gestión · Empleado')
            ->assertSee(route('vehiculos.edit', $v), false)
            ->assertSee('+ Nuevo vehículo')
            ->assertDontSee(self::FORM_ELIMINAR, false)
            ->assertDontSee('Eliminar');

        $this->actingAs($empleado)->get("/vehiculos/{$v->id}")->assertOk()
            ->assertSee('Gestión del vehículo')
            ->assertSee('Última modificación')
            ->assertSee(route('vehiculos.edit', $v), false)
            ->assertDontSee(self::FORM_ELIMINAR, false)
            ->assertDontSee('Eliminar');
    }

    #[Group('RF-10')]
    public function test_el_admin_ve_editar_eliminar_y_los_atajos(): void
    {
        $v = $this->vehiculo();
        $admin = $this->admin();

        $this->actingAs($admin)->get('/vehiculos')->assertOk()
            ->assertSee('Vista de gestión · Admin')
            ->assertSee(route('vehiculos.edit', $v), false)
            ->assertSee(self::FORM_ELIMINAR, false)
            ->assertSee('form="eliminar-' . $v->id . '"', false)
            ->assertSee('Eliminar')
            ->assertSee(route('usuarios.index'), false)
            ->assertSee(route('reportes.ventas'), false);

        $this->actingAs($admin)->get("/vehiculos/{$v->id}")->assertOk()
            ->assertSee('Gestión del vehículo')
            ->assertSee(route('vehiculos.edit', $v), false)
            ->assertSee(self::FORM_ELIMINAR, false);
    }

    #[Group('RF-10')]
    public function test_el_boton_eliminar_del_catalogo_borra_el_vehiculo(): void
    {
        // El formulario del catálogo apunta a la misma ruta destroy de siempre
        $v = $this->vehiculo();

        $this->actingAs($this->admin())
            ->delete(route('vehiculos.destroy', $v))
            ->assertRedirect(route('vehiculos.index'));

        $this->assertModelMissing($v);
    }

    #[Group('RF-02')]
    public function test_los_totales_son_de_toda_la_tabla_y_no_cambian_con_los_filtros(): void
    {
        foreach (['disponible', 'disponible', 'disponible', 'reservado', 'reservado', 'vendido'] as $estado) {
            $this->vehiculo(['estado' => $estado]);
        }

        $this->assertSame(
            ['total' => 6, 'disponible' => 3, 'reservado' => 2, 'vendido' => 1],
            Vehiculo::totalesPorEstado()
        );

        $empleado = $this->empleado();

        // Con un filtro que deja un solo resultado, los totales siguen siendo los de toda la tabla
        foreach (['/vehiculos', '/vehiculos?estado[]=vendido', '/vehiculos?q=inexistente'] as $url) {
            $this->actingAs($empleado)->get($url)->assertOk()
                ->assertSee('data-total="total">6<', false)
                ->assertSee('data-total="disponible">3<', false)
                ->assertSee('data-total="reservado">2<', false)
                ->assertSee('data-total="vendido">1<', false);
        }

        // El listado sí respeta el filtro
        $this->actingAs($empleado)->get('/vehiculos?estado[]=vendido')->assertSee('1 vehículo encontrado');
    }

    #[Group('RF-02')]
    public function test_totales_en_cero_con_la_tabla_vacia(): void
    {
        $this->assertSame(['total' => 0, 'disponible' => 0, 'reservado' => 0, 'vendido' => 0], Vehiculo::totalesPorEstado());

        $this->actingAs($this->empleado())->get('/vehiculos')->assertOk()
            ->assertSee('data-total="total">0<', false)
            ->assertSee('No encontramos vehículos con esos filtros.');
    }

    #[Group('RF-01')]
    public function test_ocultar_botones_no_es_la_proteccion_el_servidor_sigue_rechazando(): void
    {
        $v = $this->vehiculo();

        // Aunque alguien arme el pedido a mano, el empleado no puede eliminar y el cliente no puede editar
        $this->actingAs($this->empleado())->delete(route('vehiculos.destroy', $v))->assertForbidden();
        $this->actingAs($this->cliente())->get(route('vehiculos.edit', $v))->assertForbidden();
        $this->assertModelExists($v);
    }
}
