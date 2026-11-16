<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\ArmaDatos;
use Tests\TestCase;

/**
 * RF-02 (catálogo público) y RF-07 (búsqueda y filtros).
 * Usa la base de pruebas SQLite en memoria definida en phpunit.xml: nunca toca la base de desarrollo.
 */
class CatalogoPublicoTest extends TestCase
{
    use RefreshDatabase, ArmaDatos;

    /** Cuatro vehículos con valores conocidos para probar los filtros. */
    private function cuatroVehiculos(): array
    {
        return [
            'corolla' => $this->vehiculo(['marca' => 'Toyota', 'modelo' => 'Corolla', 'anio' => 2020, 'precio' => 15000, 'kilometraje' => 50000, 'transmision' => 'manual', 'combustible' => 'nafta', 'estado' => 'disponible']),
            'yaris' => $this->vehiculo(['marca' => 'Toyota', 'modelo' => 'Yaris', 'anio' => 2024, 'precio' => 22000, 'kilometraje' => 0, 'transmision' => 'automatica', 'combustible' => 'hibrido', 'estado' => 'disponible']),
            'ranger' => $this->vehiculo(['marca' => 'Ford', 'modelo' => 'Ranger', 'anio' => 2018, 'precio' => 18000, 'kilometraje' => 120000, 'transmision' => 'manual', 'combustible' => 'diesel', 'estado' => 'vendido']),
            'argo' => $this->vehiculo(['marca' => 'Fiat', 'modelo' => 'Argo', 'anio' => 2022, 'precio' => 12000, 'kilometraje' => 30000, 'transmision' => 'manual', 'combustible' => 'nafta', 'estado' => 'reservado', 'destacado' => true]),
        ];
    }

    /** Ids que devolvió el catálogo con esos filtros, en orden ascendente para comparar fácil. */
    private function idsCon(string $query): array
    {
        $respuesta = $this->get('/vehiculos?' . $query)->assertOk();

        return $respuesta->viewData('vehiculos')->pluck('id')->sort()->values()->all();
    }

    #[Group('RF-02')]
    public function test_un_visitante_ve_el_catalogo_sin_iniciar_sesion(): void
    {
        $this->vehiculo(['marca' => 'Renault', 'modelo' => 'Duster']);

        $this->get('/vehiculos')
            ->assertOk()
            ->assertSee('Renault')
            ->assertSee('Duster');
    }

    #[Group('RF-02')]
    public function test_el_catalogo_pagina_de_a_12_vehiculos(): void
    {
        for ($i = 0; $i < 15; $i++) {
            $this->vehiculo();
        }

        $this->get('/vehiculos')
            ->assertOk()
            ->assertViewHas('vehiculos', fn ($pagina) => $pagina->count() === 12 && $pagina->total() === 15);

        $this->get('/vehiculos?page=2')
            ->assertViewHas('vehiculos', fn ($pagina) => $pagina->count() === 3);
    }

    #[Group('RF-02')]
    public function test_el_detalle_muestra_los_datos_del_vehiculo(): void
    {
        $v = $this->vehiculo(['marca' => 'Peugeot', 'modelo' => '208', 'anio' => 2021, 'precio' => 17500]);
        $v->fotos()->create(['ruta' => 'https://ejemplo.com/foto.jpg', 'orden' => 0]);

        $this->get("/vehiculos/{$v->id}")
            ->assertOk()
            ->assertSee('Peugeot')
            ->assertSee('208')
            ->assertSee('17.500')
            ->assertSee('https://ejemplo.com/foto.jpg', false);
    }

    #[Group('RF-02')]
    public function test_un_vehiculo_que_no_existe_da_404(): void
    {
        $this->get('/vehiculos/9999')->assertNotFound();
    }

    #[Group('RF-07')]
    public function test_filtra_por_marca(): void
    {
        $v = $this->cuatroVehiculos();

        $this->assertSame([$v['corolla']->id, $v['yaris']->id], $this->idsCon('marca[]=Toyota'));
        $this->assertSame([$v['corolla']->id, $v['yaris']->id, $v['argo']->id], $this->idsCon('marca[]=Toyota&marca[]=Fiat'));
    }

    #[Group('RF-07')]
    public function test_filtra_por_rango_de_precio(): void
    {
        $v = $this->cuatroVehiculos();

        $this->assertSame([$v['yaris']->id, $v['ranger']->id], $this->idsCon('precio_min=16000'));
        $this->assertSame([$v['corolla']->id, $v['argo']->id], $this->idsCon('precio_max=15000'));
        $this->assertSame([$v['corolla']->id, $v['ranger']->id], $this->idsCon('precio_min=15000&precio_max=18000'));
    }

    #[Group('RF-07')]
    public function test_filtra_por_kilometraje_y_condicion(): void
    {
        $v = $this->cuatroVehiculos();

        $this->assertSame([$v['yaris']->id], $this->idsCon('condicion=0km'));
        $this->assertSame([$v['corolla']->id, $v['ranger']->id, $v['argo']->id], $this->idsCon('condicion=usado'));
        $this->assertSame([$v['corolla']->id, $v['yaris']->id, $v['argo']->id], $this->idsCon('km_max=50000'));
        // km_max=0 es un valor válido y no debe ignorarse
        $this->assertSame([$v['yaris']->id], $this->idsCon('km_max=0'));
    }

    #[Group('RF-07')]
    public function test_filtra_por_anio(): void
    {
        $v = $this->cuatroVehiculos();

        $this->assertSame([$v['yaris']->id, $v['argo']->id], $this->idsCon('anio_desde=2022'));
        $this->assertSame([$v['corolla']->id, $v['ranger']->id], $this->idsCon('anio_hasta=2020'));
    }

    #[Group('RF-07')]
    public function test_filtra_por_estado_transmision_y_combustible(): void
    {
        $v = $this->cuatroVehiculos();

        $this->assertSame([$v['ranger']->id], $this->idsCon('estado[]=vendido'));
        $this->assertSame([$v['yaris']->id], $this->idsCon('transmision[]=automatica'));
        $this->assertSame([$v['ranger']->id], $this->idsCon('combustible[]=diesel'));
        $this->assertSame([$v['corolla']->id, $v['yaris']->id], $this->idsCon('estado[]=disponible'));
    }

    #[Group('RF-07')]
    public function test_filtra_solo_destacados(): void
    {
        $v = $this->cuatroVehiculos();

        $this->assertSame([$v['argo']->id], $this->idsCon('destacados=1'));
    }

    #[Group('RF-07')]
    public function test_la_busqueda_por_texto_encuentra_marca_modelo_o_anio(): void
    {
        $v = $this->cuatroVehiculos();

        $this->assertSame([$v['ranger']->id], $this->idsCon('q=ranger'));
        $this->assertSame([$v['ranger']->id], $this->idsCon('q=RANGER'), 'no distingue mayúsculas');
        $this->assertSame([$v['corolla']->id, $v['yaris']->id], $this->idsCon('q=toyota'));
        $this->assertSame([$v['yaris']->id], $this->idsCon('q=toyota+2024'), 'todas las palabras deben coincidir');
        $this->assertSame([$v['argo']->id], $this->idsCon('q=2022'));
    }

    #[Group('RF-07')]
    public function test_el_porcentaje_de_la_busqueda_se_trata_como_texto_y_no_como_comodin(): void
    {
        $this->cuatroVehiculos();

        $this->assertSame([], $this->idsCon('q=' . urlencode('%')));
    }

    #[Group('RF-07')]
    public function test_se_pueden_combinar_filtros(): void
    {
        $v = $this->cuatroVehiculos();

        $this->assertSame([$v['corolla']->id], $this->idsCon('marca[]=Toyota&precio_max=15000&condicion=usado'));
    }

    #[Group('RF-07')]
    public function test_un_filtro_invalido_se_ignora_en_lugar_de_dar_error(): void
    {
        $this->cuatroVehiculos();

        $this->assertCount(4, $this->idsCon('precio_min=abc&estado[]=inventado&condicion=roto'));
    }

    #[Group('RF-07')]
    public function test_el_orden_por_precio_funciona(): void
    {
        $v = $this->cuatroVehiculos();

        $respuesta = $this->get('/vehiculos?orden=precio_asc')->assertOk();
        $this->assertSame(
            [$v['argo']->id, $v['corolla']->id, $v['ranger']->id, $v['yaris']->id],
            $respuesta->viewData('vehiculos')->pluck('id')->all()
        );
    }

    #[Group('RF-07')]
    public function test_por_defecto_los_destacados_salen_primero(): void
    {
        $v = $this->cuatroVehiculos();

        $primero = $this->get('/vehiculos')->viewData('vehiculos')->first();
        $this->assertSame($v['argo']->id, $primero->id);
    }
}
