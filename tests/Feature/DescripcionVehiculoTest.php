<?php

namespace Tests\Feature;

use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\ArmaDatos;
use Tests\TestCase;

/**
 * Mini descripción del vehículo (columna vehiculos.descripcion, opcional, hasta 500 caracteres):
 * se guarda desde el formulario, se valida el largo, se ve en el catálogo y en el detalle,
 * y si está vacía se muestra un texto armado con los datos (Vehiculo::resumen).
 */
class DescripcionVehiculoTest extends TestCase
{
    use RefreshDatabase, ArmaDatos;

    #[Group('RF-01')]
    public function test_la_columna_existe_y_es_opcional(): void
    {
        $this->assertTrue(Schema::hasColumn('vehiculos', 'descripcion'));

        // Se puede crear un vehículo sin descripción
        $v = $this->vehiculo(['descripcion' => null]);
        $this->assertNull($v->fresh()->descripcion);
    }

    #[Group('RF-01')]
    public function test_el_alta_guarda_la_descripcion(): void
    {
        $this->actingAs($this->empleado())
            ->post('/vehiculos', $this->datosFormulario(['descripcion' => 'Muy cuidado, con el service al día.']))
            ->assertRedirect();

        $this->assertDatabaseHas('vehiculos', ['marca' => 'Ford', 'descripcion' => 'Muy cuidado, con el service al día.']);
    }

    #[Group('RF-01')]
    public function test_el_alta_sin_descripcion_la_deja_vacia(): void
    {
        $this->actingAs($this->empleado())
            ->post('/vehiculos', $this->datosFormulario(['descripcion' => '']))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertNull(Vehiculo::firstOrFail()->descripcion);
    }

    #[Group('RF-01')]
    public function test_la_edicion_cambia_la_descripcion(): void
    {
        $v = $this->vehiculo(['descripcion' => 'Texto viejo.']);

        $this->actingAs($this->empleado())
            ->put("/vehiculos/{$v->id}", $this->datosFormulario(['descripcion' => 'Texto nuevo.']))
            ->assertRedirect(route('vehiculos.show', $v));

        $this->assertSame('Texto nuevo.', $v->fresh()->descripcion);
    }

    #[Group('RF-01')]
    public function test_acepta_500_caracteres_y_rechaza_501(): void
    {
        $empleado = $this->empleado();

        $this->actingAs($empleado)
            ->post('/vehiculos', $this->datosFormulario(['descripcion' => str_repeat('a', 500)]))
            ->assertSessionHasNoErrors();

        $this->actingAs($empleado)
            ->post('/vehiculos', $this->datosFormulario(['descripcion' => str_repeat('a', 501)]))
            ->assertSessionHasErrors(['descripcion' => 'La descripción puede tener hasta 500 caracteres. Acortala un poco y volvé a guardar.']);

        $this->assertDatabaseCount('vehiculos', 1);
    }

    #[Group('RF-01')]
    public function test_el_formulario_muestra_el_campo_con_la_descripcion_actual(): void
    {
        $v = $this->vehiculo(['descripcion' => 'Ideal para la ciudad.']);

        $this->actingAs($this->empleado())
            ->get("/vehiculos/{$v->id}/edit")
            ->assertOk()
            ->assertSee('name="descripcion"', false)
            ->assertSee('Ideal para la ciudad.');
    }

    #[Group('RF-02')]
    public function test_la_descripcion_se_ve_en_el_catalogo_y_en_el_detalle(): void
    {
        $v = $this->vehiculo(['descripcion' => 'SUV cómoda y alta, con lugar para toda la familia.']);

        $this->get('/vehiculos')->assertOk()->assertSee('SUV cómoda y alta, con lugar para toda la familia.');
        $this->get("/vehiculos/{$v->id}")->assertOk()->assertSee('SUV cómoda y alta, con lugar para toda la familia.');
    }

    #[Group('RF-02')]
    public function test_una_descripcion_larga_se_acorta_en_el_catalogo_y_se_ve_completa_en_el_detalle(): void
    {
        $larga = str_repeat('Muy buen estado general. ', 10) . 'FINAL-DEL-TEXTO';
        $v = $this->vehiculo(['descripcion' => $larga]);

        $this->get('/vehiculos')->assertOk()->assertDontSee('FINAL-DEL-TEXTO');
        $this->get("/vehiculos/{$v->id}")->assertOk()->assertSee('FINAL-DEL-TEXTO');
    }

    #[Group('RF-02')]
    public function test_sin_descripcion_se_muestra_un_texto_armado_con_los_datos(): void
    {
        $usado = $this->vehiculo(['descripcion' => null]); // 2020, 50.000 km, manual, nafta
        $cerokm = $this->vehiculo([
            'descripcion' => null, 'modelo' => 'Yaris', 'anio' => 2026, 'kilometraje' => 0,
            'transmision' => 'automatica', 'combustible' => 'hibrido',
        ]);

        $this->assertSame('Año 2020, 50.000 km. Caja manual y motor a nafta.', $usado->resumen);
        $this->assertSame('0 km, modelo 2026. Caja automática y motor híbrido.', $cerokm->resumen);

        $this->get('/vehiculos')->assertSee('Año 2020, 50.000 km. Caja manual y motor a nafta.');
        $this->get("/vehiculos/{$cerokm->id}")->assertSee('0 km, modelo 2026. Caja automática y motor híbrido.');
    }

    #[Group('RF-02')]
    public function test_el_factory_genera_descripciones_en_castellano_que_entran_en_la_columna(): void
    {
        $vehiculos = Vehiculo::factory(20)->create();

        foreach ($vehiculos as $v) {
            $this->assertNotEmpty($v->descripcion);
            $this->assertLessThanOrEqual(Vehiculo::DESCRIPCION_MAX, mb_strlen($v->descripcion));
            $this->assertStringNotContainsStringIgnoringCase('lorem', $v->descripcion);
        }

        // Un 0 km nunca dice que es usado
        $cerokm = Vehiculo::factory()->create(['kilometraje' => 0]);
        $this->assertStringContainsString('0 km', $cerokm->descripcion);
    }
}
