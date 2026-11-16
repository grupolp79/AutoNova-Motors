<?php

namespace Tests\Feature;

use App\Models\Interaccion;
use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\ArmaDatos;
use Tests\TestCase;

/**
 * RF-03: el cliente consulta por un vehículo (se crea un lead) y sigue sus consultas.
 * Los permisos por ruta están en ConsultaAccesoTest.
 */
class ConsultaClienteTest extends TestCase
{
    use RefreshDatabase, ArmaDatos;

    #[Group('RF-03')]
    public function test_el_cliente_envia_una_consulta_y_se_crea_un_lead_nuevo(): void
    {
        $cliente = $this->cliente();
        $v = $this->vehiculo();

        $this->actingAs($cliente)
            ->post("/vehiculos/{$v->id}/consultar", ['mensaje' => '  ¿Se puede ver el sábado?  '])
            ->assertRedirect(route('consultas.mias'))
            ->assertSessionHas('success');

        $lead = Lead::sole();
        $this->assertSame($cliente->id, $lead->usuario_id);
        $this->assertSame($v->id, $lead->vehiculo_id);
        $this->assertSame('nuevo', $lead->estado);
        $this->assertSame('¿Se puede ver el sábado?', $lead->mensaje);
    }

    #[Group('RF-03')]
    public function test_el_mensaje_es_opcional(): void
    {
        $v = $this->vehiculo();

        $this->actingAs($this->cliente())->post("/vehiculos/{$v->id}/consultar")->assertSessionHasNoErrors();

        $this->assertNull(Lead::sole()->mensaje);
    }

    #[Group('RF-03')]
    public function test_un_mensaje_de_mas_de_500_caracteres_se_rechaza(): void
    {
        $v = $this->vehiculo();

        $this->actingAs($this->cliente())
            ->post("/vehiculos/{$v->id}/consultar", ['mensaje' => str_repeat('a', 501)])
            ->assertSessionHasErrors(['mensaje' => 'El campo mensaje no puede tener más de 500 caracteres.']);

        $this->assertSame(0, Lead::count());
    }

    #[Group('RF-03')]
    public function test_no_se_puede_consultar_por_un_vehiculo_vendido(): void
    {
        $v = $this->vehiculo(['estado' => 'vendido']);

        $this->actingAs($this->cliente())
            ->post("/vehiculos/{$v->id}/consultar")
            ->assertRedirect(route('vehiculos.show', $v))
            ->assertSessionHas('error', 'Este vehículo ya fue vendido, no se pueden enviar consultas.');

        $this->assertSame(0, Lead::count());
    }

    #[Group('RF-03')]
    public function test_un_vehiculo_reservado_si_admite_consultas(): void
    {
        $v = $this->vehiculo(['estado' => 'reservado']);

        $this->actingAs($this->cliente())->post("/vehiculos/{$v->id}/consultar")->assertSessionHas('success');

        $this->assertSame(1, Lead::count());
    }

    #[Group('RF-03')]
    public function test_no_se_duplica_una_consulta_abierta(): void
    {
        $cliente = $this->cliente();
        $v = $this->vehiculo();

        $this->actingAs($cliente)->post("/vehiculos/{$v->id}/consultar");
        $this->actingAs($cliente)->post("/vehiculos/{$v->id}/consultar")
            ->assertRedirect(route('consultas.mias'))
            ->assertSessionHas('error', 'Ya tenés una consulta abierta por este vehículo. Podés seguirla acá.');

        $this->assertSame(1, Lead::count());
    }

    #[Group('RF-03')]
    public function test_otro_cliente_si_puede_consultar_por_el_mismo_vehiculo(): void
    {
        $v = $this->vehiculo();

        $this->actingAs($this->cliente())->post("/vehiculos/{$v->id}/consultar");
        $this->actingAs($this->cliente())->post("/vehiculos/{$v->id}/consultar");

        $this->assertSame(2, Lead::count());
    }

    #[Group('RF-03')]
    public function test_puede_volver_a_consultar_si_la_anterior_esta_cerrada(): void
    {
        $cliente = $this->cliente();
        $v = $this->vehiculo();
        $this->lead($v, 'cerrado', null, $cliente);

        $this->actingAs($cliente)->post("/vehiculos/{$v->id}/consultar")->assertSessionHas('success');

        $this->assertSame(2, Lead::count());
    }

    #[Group('RF-03')]
    public function test_mis_consultas_muestra_solo_las_propias(): void
    {
        $yo = $this->cliente();
        $mio = $this->vehiculo(['marca' => 'Renault', 'modelo' => 'Sandero']);
        $ajeno = $this->vehiculo(['marca' => 'Peugeot', 'modelo' => '208']);
        $this->lead($mio, 'contactado', null, $yo)->update(['mensaje' => 'Quiero verlo']);
        $this->lead($ajeno);

        $this->actingAs($yo)->get('/mis-consultas')
            ->assertOk()
            ->assertSee('Sandero')
            ->assertSee('Contactado')
            ->assertSee('Quiero verlo')
            ->assertDontSee('208');
    }

    #[Group('RF-03')]
    public function test_mis_consultas_no_muestra_las_notas_internas_pero_si_la_cantidad_de_contactos(): void
    {
        $yo = $this->cliente();
        $lead = $this->lead($this->vehiculo(), 'contactado', null, $yo);
        Interaccion::create(['lead_id' => $lead->id, 'tipo' => 'llamada', 'fecha' => now(), 'comentario' => 'NOTA INTERNA SECRETA']);
        Interaccion::create(['lead_id' => $lead->id, 'tipo' => 'correo', 'fecha' => now(), 'comentario' => 'otra nota']);

        $this->actingAs($yo)->get('/mis-consultas')
            ->assertOk()
            ->assertSee('se comunicó con vos 2 veces')
            ->assertDontSee('NOTA INTERNA SECRETA')
            ->assertDontSee('otra nota');
    }

    #[Group('RF-03')]
    public function test_mis_consultas_vacio_invita_a_ver_el_catalogo(): void
    {
        $this->actingAs($this->cliente())->get('/mis-consultas')
            ->assertOk()
            ->assertSee('Todavía no consultaste por ningún vehículo');
    }

    #[Group('RF-03')]
    public function test_mis_consultas_pagina_de_a_10(): void
    {
        $yo = $this->cliente();
        foreach (range(1, 12) as $i) {
            $this->lead($this->vehiculo(), 'nuevo', null, $yo);
        }

        $r = $this->actingAs($yo)->get('/mis-consultas')->assertOk();

        $this->assertCount(10, $r->viewData('consultas'));
        $this->assertSame(12, $r->viewData('consultas')->total());
    }

    #[Group('RF-03')]
    public function test_el_detalle_del_vehiculo_invita_al_visitante_a_iniciar_sesion(): void
    {
        $v = $this->vehiculo();

        $this->get("/vehiculos/{$v->id}")
            ->assertOk()
            ->assertSee('Iniciar sesión para consultar')
            ->assertDontSee('Enviar consulta');
    }

    #[Group('RF-03')]
    public function test_el_detalle_del_vehiculo_ofrece_el_formulario_al_cliente(): void
    {
        $v = $this->vehiculo();

        $this->actingAs($this->cliente())->get("/vehiculos/{$v->id}")
            ->assertOk()
            ->assertSee('Enviar consulta')
            ->assertSee(route('consultas.store', $v), false);
    }

    #[Group('RF-03')]
    public function test_el_detalle_avisa_al_cliente_que_ya_consulto(): void
    {
        $cliente = $this->cliente();
        $v = $this->vehiculo();
        $this->lead($v, 'negociando', null, $cliente);

        $this->actingAs($cliente)->get("/vehiculos/{$v->id}")
            ->assertOk()
            ->assertSee('Ya consultaste por este vehículo')
            ->assertSee('Negociando')
            ->assertDontSee('Enviar consulta');
    }

    #[Group('RF-03')]
    public function test_el_detalle_de_un_vehiculo_vendido_no_ofrece_consultar(): void
    {
        $v = $this->vehiculo(['estado' => 'vendido']);

        $this->actingAs($this->cliente())->get("/vehiculos/{$v->id}")
            ->assertOk()
            ->assertSee('Vehículo vendido')
            ->assertDontSee('Enviar consulta');

        $this->get("/vehiculos/{$v->id}")->assertDontSee('Iniciar sesión para consultar');
    }

    #[Group('RF-03')]
    public function test_el_detalle_le_muestra_al_equipo_la_cantidad_de_consultas_y_no_el_formulario(): void
    {
        $v = $this->vehiculo();
        $this->lead($v);
        $this->lead($v);

        $this->actingAs($this->empleado())->get("/vehiculos/{$v->id}")
            ->assertOk()
            ->assertSee('Recibió 2 consultas.')
            ->assertSee(route('consultas.index', ['vehiculo' => $v->id]), false)
            ->assertDontSee('Enviar consulta');
    }
}
