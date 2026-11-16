<?php

namespace Tests\Feature;

use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\ArmaDatos;
use Tests\TestCase;

/**
 * Eliminación de vehículos (solo Admin, RF-10) y mensajes que explican el motivo real
 * cuando hay registros relacionados (leads, turnos, reseñas con restrictOnDelete).
 */
class EliminacionVehiculoTest extends TestCase
{
    use RefreshDatabase, ArmaDatos;

    #[Group('RF-10')]
    public function test_el_admin_elimina_un_vehiculo_sin_historial(): void
    {
        $v = $this->vehiculo();
        $v->fotos()->create(['ruta' => 'https://ejemplo.com/a.jpg', 'orden' => 0]);

        $this->actingAs($this->admin())
            ->delete("/vehiculos/{$v->id}")
            ->assertRedirect(route('vehiculos.index'))
            ->assertSessionHas('success', 'Vehículo eliminado correctamente.');

        $this->assertDatabaseMissing('vehiculos', ['id' => $v->id]);
        // Las fotos se borran solas por el cascadeOnDelete
        $this->assertDatabaseCount('vehiculo_fotos', 0);
    }

    public function test_eliminar_borra_tambien_los_favoritos_del_vehiculo(): void
    {
        $v = $this->vehiculo();
        $cliente = $this->cliente();
        $cliente->favoritos()->attach($v->id);
        $this->assertDatabaseCount('favoritos', 1);

        $this->actingAs($this->admin())->delete("/vehiculos/{$v->id}");

        $this->assertDatabaseCount('favoritos', 0);
        $this->assertDatabaseHas('users', ['id' => $cliente->id]);
    }

    #[Group('RF-10')]
    public function test_un_empleado_no_puede_eliminar(): void
    {
        $v = $this->vehiculo();

        $this->actingAs($this->empleado())->delete("/vehiculos/{$v->id}")->assertForbidden();

        $this->assertDatabaseHas('vehiculos', ['id' => $v->id]);
    }

    #[Group('RF-10')]
    public function test_un_cliente_no_puede_eliminar(): void
    {
        $v = $this->vehiculo();

        $this->actingAs($this->cliente())->delete("/vehiculos/{$v->id}")->assertForbidden();

        $this->assertDatabaseHas('vehiculos', ['id' => $v->id]);
    }

    #[Group('RF-10')]
    public function test_un_visitante_que_intenta_eliminar_va_al_login(): void
    {
        $v = $this->vehiculo();

        $this->delete("/vehiculos/{$v->id}")->assertRedirect(route('login'));

        $this->assertDatabaseHas('vehiculos', ['id' => $v->id]);
    }

    public function test_eliminar_un_vehiculo_inexistente_da_404(): void
    {
        $this->actingAs($this->admin())->delete('/vehiculos/9999')->assertNotFound();
    }

    // ---------------------------------------------- Mensajes con historial

    public function test_vendido_con_un_lead_explica_que_queda_como_registro_de_la_venta(): void
    {
        $v = $this->vehiculo(['marca' => 'Ford', 'modelo' => 'Ranger', 'estado' => 'vendido']);
        $this->lead($v, 'cerrado');

        $this->actingAs($this->admin())
            ->delete("/vehiculos/{$v->id}")
            ->assertRedirect(route('vehiculos.show', $v))
            ->assertSessionHas('error', 'No se puede eliminar Ford Ranger porque tiene registros asociados: 1 consulta (lead). '
                . 'Se conservan como historial de la automotora. '
                . 'Como ya figura como "Vendido", queda en el sistema como registro de la venta.');

        $this->assertDatabaseHas('vehiculos', ['id' => $v->id]);
    }

    public function test_disponible_con_historial_sugiere_pasarlo_a_vendido(): void
    {
        $v = $this->vehiculo(['estado' => 'disponible']);
        $this->lead($v, 'nuevo');

        $this->actingAs($this->admin())
            ->delete("/vehiculos/{$v->id}")
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'cambiá su estado a "Vendido" desde Editar.')
                && ! str_contains($m, 'queda en el sistema como registro de la venta'));
    }

    public function test_el_mensaje_cuenta_cada_tipo_de_registro_relacionado(): void
    {
        $v = $this->vehiculo(['marca' => 'Fiat', 'modelo' => 'Argo']);
        $this->lead($v);
        $this->lead($v);
        $this->turno($v);
        $this->resena($v);

        $this->actingAs($this->admin())
            ->delete("/vehiculos/{$v->id}")
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'registros asociados: 2 consultas (leads), 1 turno y 1 reseña.'));

        $this->assertDatabaseHas('vehiculos', ['id' => $v->id]);
    }

    public function test_un_vehiculo_solo_con_turnos_tampoco_se_puede_eliminar(): void
    {
        $v = $this->vehiculo();
        $this->turno($v);

        $this->actingAs($this->admin())
            ->delete("/vehiculos/{$v->id}")
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'registros asociados: 1 turno.'));

        $this->assertDatabaseHas('vehiculos', ['id' => $v->id]);
    }

    public function test_el_mensaje_de_error_se_muestra_en_la_pantalla_del_vehiculo(): void
    {
        $v = $this->vehiculo(['estado' => 'vendido']);
        $this->lead($v, 'cerrado');

        $this->actingAs($this->admin())
            ->followingRedirects()
            ->delete("/vehiculos/{$v->id}")
            ->assertOk()
            ->assertSee('No se puede eliminar')
            ->assertSee('registro de la venta');
    }

    public function test_no_se_conservan_registros_huerfanos_si_no_se_pudo_eliminar(): void
    {
        $v = $this->vehiculo();
        $this->lead($v);
        $v->fotos()->create(['ruta' => 'https://ejemplo.com/a.jpg', 'orden' => 0]);

        $this->actingAs($this->admin())->delete("/vehiculos/{$v->id}");

        // Como el borrado se rechazó, la foto sigue ligada a su vehículo
        $this->assertDatabaseCount('vehiculo_fotos', 1);
        $this->assertSame(1, Vehiculo::count());
    }
}
