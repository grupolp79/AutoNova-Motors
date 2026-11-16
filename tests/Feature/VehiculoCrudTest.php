<?php

namespace Tests\Feature;

use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\ArmaDatos;
use Tests\TestCase;

/**
 * RF-01 (alta y modificación de vehículos), RF-12 (destacados) y validaciones del formulario.
 * Los tests usan una base SQLite en memoria (phpunit.xml): la base de desarrollo no se toca.
 */
class VehiculoCrudTest extends TestCase
{
    use RefreshDatabase, ArmaDatos;

    // ---------------------------------------------------------------- Alta

    #[Group('RF-01')]
    public function test_un_empleado_da_de_alta_un_vehiculo_con_fotos(): void
    {
        $respuesta = $this->actingAs($this->empleado())
            ->post('/vehiculos', $this->datosFormulario());

        $vehiculo = Vehiculo::firstOrFail();
        $respuesta->assertRedirect(route('vehiculos.show', $vehiculo))
            ->assertSessionHas('success', 'Vehículo creado correctamente.');

        $this->assertDatabaseHas('vehiculos', [
            'marca' => 'Ford', 'modelo' => 'Fiesta', 'anio' => 2019,
            'kilometraje' => 60000, 'transmision' => 'manual', 'combustible' => 'nafta',
            'estado' => 'disponible',
        ]);
        $this->assertSame(
            ['https://ejemplo.com/a.jpg', 'https://ejemplo.com/b.jpg'],
            $vehiculo->fotos()->orderBy('orden')->pluck('ruta')->all()
        );
        $this->assertSame([0, 1], $vehiculo->fotos()->orderBy('orden')->pluck('orden')->all());
    }

    #[Group('RF-01')]
    public function test_un_admin_tambien_puede_dar_de_alta(): void
    {
        $this->actingAs($this->admin())
            ->post('/vehiculos', $this->datosFormulario(['fotos' => '']))
            ->assertRedirect();

        $this->assertDatabaseCount('vehiculos', 1);
        $this->assertDatabaseCount('vehiculo_fotos', 0);
    }

    #[Group('RF-01')]
    public function test_el_formulario_de_alta_se_muestra_a_un_empleado(): void
    {
        $this->actingAs($this->empleado())
            ->get('/vehiculos/create')
            ->assertOk()
            ->assertSee('Marca');
    }

    // ---------------------------------------------------------- Modificación

    #[Group('RF-01')]
    public function test_un_empleado_modifica_un_vehiculo_y_reemplaza_las_fotos(): void
    {
        $v = $this->vehiculo(['precio' => 15000]);
        $v->fotos()->create(['ruta' => 'https://ejemplo.com/vieja.jpg', 'orden' => 0]);

        $this->actingAs($this->empleado())
            ->put("/vehiculos/{$v->id}", $this->datosFormulario([
                'marca' => 'Toyota', 'modelo' => 'Corolla', 'precio' => 14000,
                'fotos' => 'https://ejemplo.com/nueva.jpg',
            ]))
            ->assertRedirect(route('vehiculos.show', $v))
            ->assertSessionHas('success', 'Vehículo actualizado correctamente.');

        $this->assertEquals(14000, $v->fresh()->precio);
        $this->assertSame(['https://ejemplo.com/nueva.jpg'], $v->fotos()->pluck('ruta')->all());
    }

    #[Group('RF-01')]
    public function test_modificar_un_vehiculo_inexistente_da_404(): void
    {
        $this->actingAs($this->empleado())
            ->put('/vehiculos/9999', $this->datosFormulario())
            ->assertNotFound();
    }

    // --------------------------------------------------------- Validaciones

    #[Group('Validaciones')]
    public function test_el_formulario_vacio_muestra_mensajes_en_espanol(): void
    {
        $this->actingAs($this->empleado())
            ->from('/vehiculos/create')
            ->post('/vehiculos', [])
            ->assertRedirect('/vehiculos/create')
            ->assertSessionHasErrors([
                'marca' => 'El campo marca es obligatorio.',
                'modelo' => 'El campo modelo es obligatorio.',
                'anio' => 'El campo año es obligatorio.',
                'precio' => 'El campo precio es obligatorio.',
                'kilometraje' => 'El campo kilometraje es obligatorio.',
                'transmision' => 'El campo transmisión es obligatorio.',
                'combustible' => 'El campo combustible es obligatorio.',
                'estado' => 'El campo estado es obligatorio.',
            ]);

        $this->assertDatabaseCount('vehiculos', 0);
    }

    #[Group('Validaciones')]
    public function test_los_errores_se_ven_en_la_pantalla_del_formulario(): void
    {
        $this->actingAs($this->empleado())
            ->from('/vehiculos/create')
            ->post('/vehiculos', []);

        $this->actingAs($this->empleado())
            ->withSession(['errors' => session('errors')])
            ->get('/vehiculos/create')
            ->assertOk()
            ->assertSee('El campo marca es obligatorio.');
    }

    #[Group('Validaciones')]
    #[\PHPUnit\Framework\Attributes\DataProvider('datosInvalidos')]
    public function test_rechaza_datos_invalidos(array $cambios, string $campo): void
    {
        $this->actingAs($this->empleado())
            ->post('/vehiculos', $this->datosFormulario($cambios))
            ->assertSessionHasErrors($campo);

        $this->assertDatabaseCount('vehiculos', 0);
    }

    public static function datosInvalidos(): array
    {
        return [
            'precio negativo' => [['precio' => -1], 'precio'],
            'precio que no es número' => [['precio' => 'caro'], 'precio'],
            'precio que no entra en la columna' => [['precio' => 100000000], 'precio'],
            'kilometraje negativo' => [['kilometraje' => -5], 'kilometraje'],
            'kilometraje decimal' => [['kilometraje' => 10.5], 'kilometraje'],
            'año anterior a 1900' => [['anio' => 1899], 'anio'],
            'año demasiado futuro' => [['anio' => (int) date('Y') + 2], 'anio'],
            'transmisión inventada' => [['transmision' => 'cvt'], 'transmision'],
            'combustible inventado' => [['combustible' => 'gas'], 'combustible'],
            'estado inventado' => [['estado' => 'regalado'], 'estado'],
            'marca demasiado larga' => [['marca' => str_repeat('a', 61)], 'marca'],
            'foto que no es URL' => [['fotos' => 'esto no es una url'], 'lista_fotos.0'],
            'foto con protocolo no permitido' => [['fotos' => 'ftp://ejemplo.com/a.jpg'], 'lista_fotos.0'],
            'foto de más de 255 caracteres' => [['fotos' => 'https://ejemplo.com/' . str_repeat('a', 250)], 'lista_fotos.0'],
        ];
    }

    #[Group('Validaciones')]
    public function test_el_mensaje_de_foto_invalida_indica_la_linea(): void
    {
        $this->actingAs($this->empleado())
            ->post('/vehiculos', $this->datosFormulario(['fotos' => "https://ejemplo.com/a.jpg\nno-es-url"]))
            ->assertSessionHasErrors(['lista_fotos.1' => 'La línea 2 de fotos no es una URL válida.']);
    }

    #[Group('Validaciones')]
    public function test_las_lineas_vacias_y_los_espacios_del_textarea_se_ignoran(): void
    {
        $this->actingAs($this->empleado())
            ->post('/vehiculos', $this->datosFormulario([
                'fotos' => "  https://ejemplo.com/a.jpg  \r\n\r\n\r\n  https://ejemplo.com/b.jpg\r\n",
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(
            ['https://ejemplo.com/a.jpg', 'https://ejemplo.com/b.jpg'],
            Vehiculo::firstOrFail()->fotos()->orderBy('orden')->pluck('ruta')->all()
        );
    }

    #[Group('Validaciones')]
    public function test_un_dato_invalido_en_la_edicion_no_modifica_el_vehiculo(): void
    {
        $v = $this->vehiculo(['marca' => 'Toyota', 'precio' => 15000]);

        $this->actingAs($this->empleado())
            ->put("/vehiculos/{$v->id}", $this->datosFormulario(['precio' => -10]))
            ->assertSessionHasErrors('precio');

        $this->assertSame('Toyota', $v->fresh()->marca);
        $this->assertEquals(15000, $v->fresh()->precio);
    }

    #[Group('Validaciones')]
    public function test_el_limite_de_la_columna_precio_se_acepta(): void
    {
        $this->actingAs($this->empleado())
            ->post('/vehiculos', $this->datosFormulario(['precio' => '99999999.99']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('vehiculos', 1);
    }

    // ------------------------------------------------ Destacados (RF-12)

    #[Group('RF-12')]
    public function test_el_admin_puede_marcar_un_vehiculo_como_destacado(): void
    {
        $this->actingAs($this->admin())
            ->post('/vehiculos', $this->datosFormulario(['destacado' => '1']));

        $this->assertTrue(Vehiculo::firstOrFail()->destacado);
    }

    #[Group('RF-12')]
    public function test_el_admin_puede_quitar_el_destacado_al_editar(): void
    {
        $v = $this->vehiculo(['destacado' => true]);

        // El checkbox desmarcado no se envía en el formulario
        $this->actingAs($this->admin())
            ->put("/vehiculos/{$v->id}", $this->datosFormulario());

        $this->assertFalse($v->fresh()->destacado);
    }

    #[Group('RF-12')]
    public function test_un_empleado_no_puede_marcar_destacados_aunque_manipule_el_formulario(): void
    {
        $this->actingAs($this->empleado())
            ->post('/vehiculos', $this->datosFormulario(['destacado' => '1']));

        $this->assertFalse(Vehiculo::firstOrFail()->destacado);
    }

    #[Group('RF-12')]
    public function test_editar_como_empleado_conserva_el_destacado_que_puso_el_admin(): void
    {
        $v = $this->vehiculo(['destacado' => true]);

        $this->actingAs($this->empleado())
            ->put("/vehiculos/{$v->id}", $this->datosFormulario(['destacado' => '0']));

        $this->assertTrue($v->fresh()->destacado);
    }

    #[Group('RF-12')]
    public function test_la_casilla_de_destacado_solo_se_muestra_al_admin(): void
    {
        $v = $this->vehiculo();

        $this->actingAs($this->empleado())->get("/vehiculos/{$v->id}/edit")
            ->assertOk()->assertDontSee('name="destacado"', false);

        $this->actingAs($this->admin())->get("/vehiculos/{$v->id}/edit")
            ->assertOk()->assertSee('name="destacado"', false);
    }
}
