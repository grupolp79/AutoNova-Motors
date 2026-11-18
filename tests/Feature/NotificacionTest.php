<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\User;
use App\Notifications\ConsultaActualizada;
use App\Notifications\NuevaConsulta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\ArmaDatos;
use Tests\TestCase;

/**
 * Campanita de avisos: quién recibe cada aviso, quién NO, qué dice, cómo se marca como leído
 * y que nadie pueda ver ni tocar los avisos de otro usuario.
 *   - El cliente envía una consulta → avisa a todo el equipo (Empleado y Admin).
 *   - El equipo avanza la consulta o registra un contacto → avisa al cliente dueño de esa consulta.
 */
#[Group('Notificaciones')]
class NotificacionTest extends TestCase
{
    use RefreshDatabase, ArmaDatos;

    /** Un aviso de prueba ya guardado para $usuario (por defecto, sin leer). */
    private function aviso(User $usuario, Lead $lead, bool $leido = false): DatabaseNotification
    {
        $usuario->notify($usuario->puedeGestionar() ? new NuevaConsulta($lead) : ConsultaActualizada::porEstado($lead));
        $aviso = $usuario->notifications()->latest()->first();
        if ($leido) {
            $aviso->markAsRead();
        }

        return $aviso;
    }

    // ===== Tabla =====

    public function test_la_tabla_de_avisos_tiene_las_columnas_esperadas(): void
    {
        $this->assertTrue(Schema::hasColumns('notifications', [
            'id', 'type', 'notifiable_type', 'notifiable_id', 'data', 'read_at', 'created_at', 'updated_at',
        ]));
    }

    public function test_los_avisos_se_guardan_solo_en_la_base_de_datos_sin_cola_ni_correo(): void
    {
        $lead = $this->lead($this->vehiculo());

        $this->assertSame(['database'], (new NuevaConsulta($lead))->via($lead->cliente));
        $this->assertSame(['database'], ConsultaActualizada::porEstado($lead)->via($lead->cliente));
        // No implementan ShouldQueue: se guardan al instante, sin necesitar un proceso de colas
        $this->assertNotInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, new NuevaConsulta($lead));
        $this->assertNotInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, ConsultaActualizada::porEstado($lead));
    }

    // ===== Consulta nueva → avisa al equipo =====

    public function test_una_consulta_nueva_avisa_a_todos_los_empleados_y_admins(): void
    {
        $empleado1 = $this->empleado();
        $empleado2 = $this->empleado();
        $admin = $this->admin();
        $v = $this->vehiculo();

        $this->actingAs($this->cliente())->post("/vehiculos/{$v->id}/consultar")->assertSessionHas('success');

        foreach ([$empleado1, $empleado2, $admin] as $miembro) {
            $this->assertCount(1, $miembro->notifications, 'Cada miembro del equipo recibe un aviso.');
            $this->assertSame(NuevaConsulta::class, $miembro->notifications->first()->type);
            $this->assertNull($miembro->notifications->first()->read_at);
        }
    }

    public function test_el_aviso_de_consulta_nueva_dice_quien_consulto_y_por_que_vehiculo(): void
    {
        $empleado = $this->empleado();
        $cliente = User::factory()->cliente()->create(['name' => 'Marta Pérez']);
        $v = $this->vehiculo(['marca' => 'Fiat', 'modelo' => 'Cronos', 'anio' => 2021]);

        $this->actingAs($cliente)->post("/vehiculos/{$v->id}/consultar", ['mensaje' => 'Texto privado del cliente']);

        $datos = $empleado->notifications->first()->data;
        $this->assertSame('nueva_consulta', $datos['tipo']);
        $this->assertSame(Lead::sole()->id, $datos['lead_id']);
        $this->assertSame('Nueva consulta', $datos['titulo']);
        $this->assertSame('Marta Pérez consultó por Fiat Cronos 2021.', $datos['texto']);
        // El mensaje que escribió el cliente no se copia al aviso
        $this->assertStringNotContainsString('Texto privado', json_encode($datos));
    }

    public function test_el_cliente_que_consulta_no_recibe_aviso_propio_ni_los_otros_clientes(): void
    {
        $this->empleado();
        $cliente = $this->cliente();
        $otroCliente = $this->cliente();
        $v = $this->vehiculo();

        $this->actingAs($cliente)->post("/vehiculos/{$v->id}/consultar");

        $this->assertCount(0, $cliente->fresh()->notifications);
        $this->assertCount(0, $otroCliente->fresh()->notifications);
    }

    public function test_una_consulta_duplicada_no_genera_un_segundo_aviso(): void
    {
        $empleado = $this->empleado();
        $cliente = $this->cliente();
        $v = $this->vehiculo();

        $this->actingAs($cliente)->post("/vehiculos/{$v->id}/consultar");
        $this->actingAs($cliente)->post("/vehiculos/{$v->id}/consultar")->assertSessionHas('error');

        $this->assertCount(1, $empleado->notifications);
    }

    public function test_consultar_un_vehiculo_vendido_no_genera_aviso(): void
    {
        $empleado = $this->empleado();
        $v = $this->vehiculo(['estado' => 'vendido']);

        $this->actingAs($this->cliente())->post("/vehiculos/{$v->id}/consultar")->assertSessionHas('error');

        $this->assertCount(0, $empleado->notifications);
    }

    public function test_una_consulta_con_datos_invalidos_no_genera_aviso(): void
    {
        $empleado = $this->empleado();
        $v = $this->vehiculo();

        $this->actingAs($this->cliente())
            ->post("/vehiculos/{$v->id}/consultar", ['mensaje' => str_repeat('a', 501)])
            ->assertSessionHasErrors('mensaje');

        $this->assertCount(0, $empleado->notifications);
        $this->assertSame(0, Lead::count());
    }

    // ===== El equipo avanza la consulta → avisa al cliente =====

    public function test_al_avanzar_la_consulta_el_cliente_recibe_un_aviso_con_el_nuevo_estado(): void
    {
        $cliente = User::factory()->cliente()->create();
        $lead = $this->lead($this->vehiculo(['marca' => 'Fiat', 'modelo' => 'Cronos']), 'nuevo', null, $cliente);

        $this->actingAs($this->empleado())->patch("/consultas/{$lead->id}/estado")->assertSessionHas('success');

        $aviso = $cliente->notifications()->sole();
        $this->assertSame(ConsultaActualizada::class, $aviso->type);
        $this->assertSame('estado_consulta', $aviso->data['tipo']);
        $this->assertSame($lead->id, $aviso->data['lead_id']);
        $this->assertSame('contactado', $aviso->data['estado']);
        $this->assertSame('Tu consulta pasó a Contactado', $aviso->data['titulo']);
        $this->assertSame('Tu consulta por Fiat Cronos ahora está en estado Contactado.', $aviso->data['texto']);
    }

    public function test_cada_paso_del_ciclo_genera_su_propio_aviso(): void
    {
        $cliente = $this->cliente();
        $lead = $this->lead($this->vehiculo(), 'nuevo', null, $cliente);
        $empleado = $this->empleado();

        foreach (range(1, 3) as $paso) {
            $this->actingAs($empleado)->patch("/consultas/{$lead->id}/estado");
        }

        $estados = $cliente->notifications->pluck('data.estado')->sort()->values()->all();
        $this->assertSame(['cerrado', 'contactado', 'negociando'], $estados);
    }

    public function test_el_aviso_de_avance_le_llega_solo_al_dueno_de_la_consulta(): void
    {
        $duenio = $this->cliente();
        $otroCliente = $this->cliente();
        $lead = $this->lead($this->vehiculo(), 'nuevo', null, $duenio);

        $this->actingAs($this->empleado())->patch("/consultas/{$lead->id}/estado");

        $this->assertCount(1, $duenio->notifications);
        $this->assertCount(0, $otroCliente->notifications);
    }

    public function test_el_equipo_no_recibe_avisos_por_los_avances(): void
    {
        $lead = $this->lead($this->vehiculo());
        $empleado = $this->empleado();
        $admin = $this->admin();

        $this->actingAs($empleado)->patch("/consultas/{$lead->id}/estado");

        $this->assertCount(0, $empleado->notifications);
        $this->assertCount(0, $admin->notifications);
    }

    public function test_un_avance_rechazado_no_genera_aviso(): void
    {
        $cliente = $this->cliente();
        // El vehículo ya figura como vendido: avanzar la consulta se rechaza
        $lead = $this->lead($this->vehiculo(['estado' => 'vendido']), 'contactado', null, $cliente);

        $this->actingAs($this->empleado())->patch("/consultas/{$lead->id}/estado")->assertSessionHas('error');

        $this->assertCount(0, $cliente->notifications);
        $this->assertSame('contactado', $lead->fresh()->estado);
    }

    public function test_avanzar_una_consulta_ya_cerrada_no_genera_aviso(): void
    {
        $cliente = $this->cliente();
        $lead = $this->lead($this->vehiculo(), 'cerrado', null, $cliente);

        $this->actingAs($this->empleado())->patch("/consultas/{$lead->id}/estado")->assertSessionHas('error');

        $this->assertCount(0, $cliente->notifications);
    }

    public function test_un_cliente_no_puede_avanzar_una_consulta_ni_genera_avisos(): void
    {
        $cliente = $this->cliente();
        $lead = $this->lead($this->vehiculo(), 'nuevo', null, $cliente);

        $this->actingAs($cliente)->patch("/consultas/{$lead->id}/estado")->assertForbidden();

        $this->assertCount(0, $cliente->notifications);
    }

    // ===== El equipo registra un contacto → avisa al cliente =====

    #[DataProvider('contactos')]
    public function test_al_registrar_un_contacto_el_cliente_recibe_un_aviso(string $tipo, string $frase): void
    {
        $cliente = $this->cliente();
        $lead = $this->lead($this->vehiculo(['marca' => 'Fiat', 'modelo' => 'Cronos']), 'nuevo', null, $cliente);

        $this->actingAs($this->empleado())
            ->post("/consultas/{$lead->id}/interacciones", ['tipo' => $tipo])
            ->assertSessionHas('success');

        $aviso = $cliente->notifications()->sole();
        $this->assertSame('contacto_consulta', $aviso->data['tipo']);
        $this->assertSame($lead->id, $aviso->data['lead_id']);
        $this->assertSame($tipo, $aviso->data['contacto']);
        $this->assertSame('El equipo se comunicó con vos', $aviso->data['titulo']);
        $this->assertSame("Registramos {$frase} por tu consulta sobre Fiat Cronos.", $aviso->data['texto']);
    }

    public static function contactos(): array
    {
        return [
            'llamada' => ['llamada', 'una llamada'],
            'correo' => ['correo', 'un correo'],
            'visita' => ['visita', 'una visita'],
        ];
    }

    public function test_el_comentario_interno_del_contacto_no_aparece_en_el_aviso(): void
    {
        $cliente = $this->cliente();
        $lead = $this->lead($this->vehiculo(), 'nuevo', null, $cliente);

        $this->actingAs($this->empleado())->post("/consultas/{$lead->id}/interacciones", [
            'tipo' => 'llamada',
            'comentario' => 'NOTA INTERNA: el cliente pide descuento',
        ]);

        $aviso = $cliente->notifications()->sole();
        $this->assertStringNotContainsString('NOTA INTERNA', json_encode($aviso->data));

        // Tampoco se ve en las pantallas del cliente
        $this->actingAs($cliente)->get('/notificaciones')->assertOk()->assertDontSee('NOTA INTERNA');
    }

    public function test_un_contacto_invalido_no_genera_aviso(): void
    {
        $cliente = $this->cliente();
        $lead = $this->lead($this->vehiculo(), 'nuevo', null, $cliente);

        $this->actingAs($this->empleado())
            ->post("/consultas/{$lead->id}/interacciones", ['tipo' => 'telepatia'])
            ->assertSessionHasErrors('tipo');

        $this->assertCount(0, $cliente->notifications);
    }

    public function test_registrar_un_contacto_en_una_consulta_cerrada_no_genera_aviso(): void
    {
        $cliente = $this->cliente();
        $lead = $this->lead($this->vehiculo(), 'cerrado', null, $cliente);

        $this->actingAs($this->empleado())
            ->post("/consultas/{$lead->id}/interacciones", ['tipo' => 'llamada'])
            ->assertSessionHas('error');

        $this->assertCount(0, $cliente->notifications);
    }

    // ===== Campanita en la barra =====

    public function test_el_visitante_no_ve_la_campanita(): void
    {
        $this->get('/vehiculos')->assertOk()->assertDontSee('campanita-boton', false);
    }

    #[DataProvider('todosLosRoles')]
    public function test_los_tres_roles_ven_la_campanita(string $rol): void
    {
        $this->actingAs($this->{$rol}())->get('/vehiculos')
            ->assertOk()
            ->assertSee('campanita-boton', false)
            ->assertSee('No tenés avisos todavía');
    }

    public static function todosLosRoles(): array
    {
        return ['cliente' => ['cliente'], 'empleado' => ['empleado'], 'admin' => ['admin']];
    }

    public function test_sin_avisos_sin_leer_la_campanita_no_muestra_numero(): void
    {
        $this->actingAs($this->empleado())->get('/vehiculos')
            ->assertOk()
            ->assertDontSee('campanita-contador', false)
            ->assertSee('ninguno sin leer');
    }

    public function test_la_campanita_muestra_la_cantidad_de_avisos_sin_leer(): void
    {
        $empleado = $this->empleado();
        $lead = $this->lead($this->vehiculo());
        $this->aviso($empleado, $lead);
        $this->aviso($empleado, $lead);
        $this->aviso($empleado, $lead, leido: true); // un aviso leído no cuenta

        $this->actingAs($empleado)->get('/vehiculos')
            ->assertOk()
            ->assertSee('campanita-contador', false)
            ->assertSee('2 sin leer');
    }

    public function test_con_mas_de_nueve_avisos_la_campanita_muestra_9_mas(): void
    {
        $empleado = $this->empleado();
        $lead = $this->lead($this->vehiculo());
        foreach (range(1, 12) as $i) {
            $this->aviso($empleado, $lead);
        }

        $this->actingAs($empleado)->get('/vehiculos')
            ->assertOk()
            ->assertSee('9+')
            ->assertSee('12 sin leer');
    }

    public function test_un_aviso_de_menos_de_un_minuto_dice_recien_y_uno_viejo_dice_hace_cuanto(): void
    {
        $empleado = $this->empleado();
        $lead = $this->lead($this->vehiculo());
        $viejo = $this->aviso($empleado, $lead);
        $viejo->forceFill(['created_at' => now()->subHours(3)])->save();

        $this->actingAs($empleado)->get('/notificaciones')
            ->assertOk()
            ->assertSee('hace 3 horas')
            ->assertDontSee('Recién');

        $this->aviso($empleado, $lead);

        $this->actingAs($empleado)->get('/notificaciones')->assertOk()->assertSee('Recién');
    }

    public function test_el_desplegable_muestra_solo_los_cinco_avisos_mas_nuevos(): void
    {
        $empleado = $this->empleado();
        // Seis avisos, cada uno con el nombre de un cliente distinto numerado del 1 al 6
        foreach (range(1, 6) as $i) {
            $cliente = User::factory()->cliente()->create(['name' => "Cliente{$i}"]);
            $lead = $this->lead($this->vehiculo(), 'nuevo', null, $cliente);
            $empleado->notify(new NuevaConsulta($lead));
        }
        // Se fijan horas distintas (el 1 el más viejo): si no, avisos creados en el mismo segundo no tienen orden definido
        foreach ($empleado->notifications()->get() as $aviso) {
            $numero = (int) substr($aviso->data['texto'], strlen('Cliente'), 1);
            $aviso->forceFill(['created_at' => now()->subHour()->addMinutes($numero)])->save();
        }

        $this->actingAs($empleado)->get('/vehiculos')
            ->assertOk()
            ->assertSee('Cliente6 consultó')
            ->assertSee('Cliente2 consultó')
            ->assertDontSee('Cliente1 consultó');
    }

    // ===== Página de avisos =====

    public function test_el_visitante_es_redirigido_al_login_en_todas_las_rutas_de_avisos(): void
    {
        $this->get('/notificaciones')->assertRedirect(route('login'));
        $this->post('/notificaciones/leer-todas')->assertRedirect(route('login'));
        $this->post('/notificaciones/' . fake()->uuid() . '/leer')->assertRedirect(route('login'));
    }

    public function test_la_pagina_de_avisos_lista_los_propios_y_no_los_de_otros(): void
    {
        $empleado = $this->empleado();
        $otro = $this->empleado();
        $cliente = User::factory()->cliente()->create(['name' => 'Clienta Visible']);
        $lead = $this->lead($this->vehiculo(), 'nuevo', null, $cliente);
        $this->aviso($empleado, $lead);

        $otroCliente = User::factory()->cliente()->create(['name' => 'Clienta Ajena']);
        $this->aviso($otro, $this->lead($this->vehiculo(), 'nuevo', null, $otroCliente));

        $this->actingAs($empleado)->get('/notificaciones')
            ->assertOk()
            ->assertSee('Clienta Visible')
            ->assertDontSee('Clienta Ajena');
    }

    public function test_la_pagina_de_avisos_sin_avisos_explica_que_va_a_aparecer(): void
    {
        $this->actingAs($this->empleado())->get('/notificaciones')
            ->assertOk()->assertSee('entre una consulta nueva');
        $this->actingAs($this->cliente())->get('/notificaciones')
            ->assertOk()->assertSee('avance o se comunique');
    }

    public function test_la_pagina_de_avisos_pagina_de_a_quince(): void
    {
        $empleado = $this->empleado();
        $lead = $this->lead($this->vehiculo());
        foreach (range(1, 17) as $i) {
            $this->aviso($empleado, $lead);
        }

        $this->actingAs($empleado)->get('/notificaciones')->assertOk()
            ->assertViewHas('notificaciones', fn ($p) => $p->count() === 15 && $p->total() === 17);
    }

    // ===== Marcar como leído =====

    public function test_tocar_un_aviso_lo_marca_como_leido_y_lleva_al_detalle_si_es_del_equipo(): void
    {
        $empleado = $this->empleado();
        $lead = $this->lead($this->vehiculo());
        $aviso = $this->aviso($empleado, $lead);

        $this->actingAs($empleado)->post("/notificaciones/{$aviso->id}/leer")
            ->assertRedirect(route('consultas.show', $lead));

        $this->assertNotNull($aviso->fresh()->read_at);
    }

    public function test_tocar_un_aviso_lo_marca_como_leido_y_lleva_a_mis_consultas_si_es_cliente(): void
    {
        $cliente = $this->cliente();
        $lead = $this->lead($this->vehiculo(), 'nuevo', null, $cliente);
        $aviso = $this->aviso($cliente, $lead);

        $this->actingAs($cliente)->post("/notificaciones/{$aviso->id}/leer")
            ->assertRedirect(route('consultas.mias'));

        $this->assertNotNull($aviso->fresh()->read_at);
    }

    public function test_el_admin_tambien_va_al_detalle_de_la_consulta(): void
    {
        $admin = $this->admin();
        $lead = $this->lead($this->vehiculo());
        $aviso = $this->aviso($admin, $lead);

        $this->actingAs($admin)->post("/notificaciones/{$aviso->id}/leer")
            ->assertRedirect(route('consultas.show', $lead));
    }

    public function test_marcar_un_aviso_como_leido_baja_el_contador(): void
    {
        $empleado = $this->empleado();
        $lead = $this->lead($this->vehiculo());
        $aviso = $this->aviso($empleado, $lead);
        $this->aviso($empleado, $lead);

        $this->actingAs($empleado)->post("/notificaciones/{$aviso->id}/leer");

        $this->assertSame(1, $empleado->unreadNotifications()->count());
    }

    public function test_no_se_puede_marcar_como_leido_el_aviso_de_otro_usuario(): void
    {
        $empleado = $this->empleado();
        $otro = $this->empleado();
        $aviso = $this->aviso($otro, $this->lead($this->vehiculo()));

        $this->actingAs($empleado)->post("/notificaciones/{$aviso->id}/leer")->assertNotFound();

        $this->assertNull($aviso->fresh()->read_at);
    }

    public function test_un_aviso_inexistente_da_404(): void
    {
        $this->actingAs($this->empleado())->post('/notificaciones/' . fake()->uuid() . '/leer')->assertNotFound();
    }

    public function test_si_la_consulta_del_aviso_ya_no_existe_se_marca_leido_y_vuelve_a_la_lista(): void
    {
        $empleado = $this->empleado();
        $lead = $this->lead($this->vehiculo());
        $aviso = $this->aviso($empleado, $lead);
        $lead->delete();

        $this->actingAs($empleado)->post("/notificaciones/{$aviso->id}/leer")
            ->assertRedirect(route('notificaciones.index'));

        $this->assertNotNull($aviso->fresh()->read_at);
    }

    public function test_marcar_todos_como_leidos_solo_toca_los_avisos_propios(): void
    {
        $empleado = $this->empleado();
        $otro = $this->empleado();
        $lead = $this->lead($this->vehiculo());
        $this->aviso($empleado, $lead);
        $this->aviso($empleado, $lead);
        $this->aviso($otro, $lead);

        $this->actingAs($empleado)->post('/notificaciones/leer-todas')
            ->assertRedirect(route('notificaciones.index'))
            ->assertSessionHas('success');

        $this->assertSame(0, $empleado->unreadNotifications()->count());
        $this->assertSame(1, $otro->unreadNotifications()->count());
    }

    public function test_un_aviso_ya_leido_conserva_su_fecha_de_lectura_al_marcar_todos(): void
    {
        $empleado = $this->empleado();
        $lead = $this->lead($this->vehiculo());
        $viejo = $this->aviso($empleado, $lead, leido: true);
        $viejo->forceFill(['read_at' => now()->subDays(3)])->save();
        $this->aviso($empleado, $lead);

        $this->actingAs($empleado)->post('/notificaciones/leer-todas');

        $this->assertTrue($viejo->fresh()->read_at->isSameDay(now()->subDays(3)));
    }

    // ===== Flujo completo =====

    public function test_flujo_completo_cliente_consulta_equipo_responde_y_cada_uno_ve_su_aviso(): void
    {
        $empleado = $this->empleado();
        $cliente = User::factory()->cliente()->create(['name' => 'Ana Gómez']);
        $v = $this->vehiculo(['marca' => 'Ford', 'modelo' => 'Fiesta']);

        // 1. La clienta consulta y al empleado le aparece el aviso con el número en la campanita
        $this->actingAs($cliente)->post("/vehiculos/{$v->id}/consultar");
        $this->actingAs($empleado)->get('/vehiculos')->assertSee('1 sin leer');

        // 2. El empleado toca el aviso y va al detalle de la consulta
        $aviso = $empleado->notifications()->sole();
        $lead = Lead::sole();
        $this->actingAs($empleado)->post("/notificaciones/{$aviso->id}/leer")
            ->assertRedirect(route('consultas.show', $lead));
        $this->actingAs($empleado)->get('/vehiculos')->assertSee('ninguno sin leer');

        // 3. El empleado llama a la clienta y avanza la consulta: a ella le llegan dos avisos
        $this->actingAs($empleado)->post("/consultas/{$lead->id}/interacciones", ['tipo' => 'llamada']);
        $this->actingAs($empleado)->patch("/consultas/{$lead->id}/estado");
        $this->actingAs($cliente)->get('/vehiculos')->assertSee('2 sin leer');
        $this->actingAs($cliente)->get('/notificaciones')
            ->assertOk()
            ->assertSee('Registramos una llamada por tu consulta sobre Ford Fiesta.')
            ->assertSee('Tu consulta pasó a Contactado');

        // 4. Ella marca todo como leído
        $this->actingAs($cliente)->post('/notificaciones/leer-todas');
        $this->actingAs($cliente)->get('/vehiculos')->assertSee('ninguno sin leer');
    }
}
