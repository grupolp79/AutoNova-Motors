<?php

namespace Tests\Feature;

use App\Models\Turno;
use App\Models\User;
use App\Models\Vehiculo;
use App\Notifications\NuevoTurno;
use App\Notifications\TurnoActualizado;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\ArmaDatos;
use Tests\TestCase;

/**
 * RF-13: el Cliente agenda un turno para conocer o probar un vehículo.
 * Empleado y Admin lo confirman, lo cancelan o lo marcan como realizado desde la agenda.
 * El "hoy" de estas pruebas es fijo: miércoles 18/11/2026 a las 10:00.
 */
class TurnoTest extends TestCase
{
    use RefreshDatabase, ArmaDatos;

    // Viernes 20/11/2026 a las 10:30: un horario válido
    private const FECHA_VALIDA = '2026-11-20T10:30';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-18 10:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Turno guardado directamente, con dueño, estado y fecha conocidos. */
    private function turnoDe(User $cliente, Vehiculo $vehiculo, string $estado = 'pendiente', string $fecha = '2026-11-20 10:30'): Turno
    {
        return Turno::create([
            'usuario_id' => $cliente->id, 'vehiculo_id' => $vehiculo->id,
            'fecha_hora' => Carbon::parse($fecha), 'estado' => $estado,
        ]);
    }

    /** Pide un turno como lo manda el formulario: dos listas, dia (2026-11-20) y hora (10:30). */
    private function pedir(User $cliente, Vehiculo $vehiculo, string $fecha = self::FECHA_VALIDA)
    {
        [$dia, $hora] = explode('T', $fecha) + ['', ''];

        return $this->actingAs($cliente)->from("/vehiculos/{$vehiculo->id}")
            ->post("/vehiculos/{$vehiculo->id}/turno", ['dia' => $dia, 'hora' => $hora]);
    }

    // ----------------------------------------------------------------- Acceso

    #[Group('RF-13')]
    #[DataProvider('permisos')]
    public function test_matriz_de_permisos_de_turnos(string $rol, string $metodo, string $ruta, int $esperado): void
    {
        $v = $this->vehiculo();
        $t = $this->turnoDe($this->cliente(), $v);
        $url = str_replace(['{vehiculo}', '{turno}'], [(string) $v->id, (string) $t->id], $ruta);
        $actor = $rol === 'visitante' ? $this : $this->actingAs($this->{$rol}());

        $respuesta = $actor->call($metodo, $url, ['dia' => '2026-11-21', 'hora' => '11:00', 'estado' => 'confirmado']);

        $respuesta->assertStatus($esperado);
        if ($rol === 'visitante') {
            $respuesta->assertRedirect(route('login'));
        }
    }

    public static function permisos(): array
    {
        $filas = [];
        // [método, ruta, visitante, cliente, empleado, admin]
        $tabla = [
            ['POST', '/vehiculos/{vehiculo}/turno', 302, 302, 403, 403],
            ['GET', '/mis-turnos', 302, 200, 403, 403],
            ['PATCH', '/mis-turnos/{turno}/cancelar', 302, 403, 403, 403], // el cliente de la prueba no es el dueño
            ['GET', '/turnos', 302, 403, 200, 200],
            ['PATCH', '/turnos/{turno}/estado', 302, 403, 302, 302],
        ];

        foreach ($tabla as [$metodo, $ruta, $visitante, $cliente, $empleado, $admin]) {
            foreach (compact('visitante', 'cliente', 'empleado', 'admin') as $rol => $codigo) {
                $filas["$rol $metodo $ruta"] = [$rol, $metodo, $ruta, $codigo];
            }
        }

        return $filas;
    }

    #[Group('RF-13')]
    public function test_las_rutas_estan_declaradas_con_su_middleware(): void
    {
        $rutas = collect(app('router')->getRoutes()->getRoutes())->keyBy(fn ($r) => $r->getName());

        foreach (['turnos.store', 'turnos.mios', 'turnos.cancelar'] as $nombre) {
            $this->assertContains('rol:cliente', $rutas[$nombre]->gatherMiddleware(), $nombre);
        }
        foreach (['turnos.index', 'turnos.estado'] as $nombre) {
            $this->assertContains('rol:empleado,admin', $rutas[$nombre]->gatherMiddleware(), $nombre);
        }
    }

    #[Group('RF-13')]
    public function test_un_pedido_rechazado_por_rol_no_crea_ni_cambia_turnos(): void
    {
        $v = $this->vehiculo();
        $t = $this->turnoDe($this->cliente(), $v);

        foreach (['empleado', 'admin'] as $rol) {
            $this->actingAs($this->{$rol}())->post("/vehiculos/{$v->id}/turno", ['dia' => '2026-11-21', 'hora' => '11:00'])->assertForbidden();
        }
        $this->actingAs($this->cliente())->patch("/turnos/{$t->id}/estado", ['estado' => 'confirmado'])->assertForbidden();
        auth()->logout();
        $this->patch("/turnos/{$t->id}/estado", ['estado' => 'cancelado'])->assertRedirect(route('login'));

        $this->assertSame(1, Turno::count());
        $this->assertSame('pendiente', $t->fresh()->estado);
    }

    // ------------------------------------------------------------ Pedir turno

    #[Group('RF-13')]
    public function test_el_cliente_pide_un_turno_y_queda_pendiente(): void
    {
        $cliente = $this->cliente();
        $v = $this->vehiculo();

        $this->pedir($cliente, $v)
            ->assertRedirect(route('turnos.mios'))
            ->assertSessionHas('success', 'Pediste el turno. Te avisamos cuando el equipo lo confirme.');

        $turno = Turno::sole();
        $this->assertSame($cliente->id, $turno->usuario_id);
        $this->assertSame($v->id, $turno->vehiculo_id);
        $this->assertSame('2026-11-20 10:30:00', $turno->fecha_hora->format('Y-m-d H:i:s'));
        $this->assertSame('pendiente', $turno->estado);
    }

    #[Group('RF-13')]
    public function test_acepta_el_sabado_y_los_extremos_del_horario(): void
    {
        $cliente = $this->cliente();

        foreach (['2026-11-21T10:00', '2026-11-23T09:00', '2026-11-24T18:00'] as $fecha) {
            $this->pedir($cliente, $this->vehiculo(), $fecha)->assertSessionHasNoErrors()->assertRedirect(route('turnos.mios'));
        }

        $this->assertSame(3, Turno::count());
    }

    #[Group('RF-13')]
    #[DataProvider('fechasInvalidas')]
    public function test_rechaza_fechas_invalidas_con_mensaje_en_espanol(string $dia, string $hora, string $campo, string $mensaje): void
    {
        // Las listas del formulario no ofrecen estos valores, pero el pedido se puede armar a mano
        $v = $this->vehiculo();

        $this->actingAs($this->cliente())->from("/vehiculos/{$v->id}")
            ->post("/vehiculos/{$v->id}/turno", ['dia' => $dia, 'hora' => $hora])
            ->assertRedirect("/vehiculos/{$v->id}")
            ->assertSessionHasErrors([$campo => $mensaje]);

        $this->assertSame(0, Turno::count());
    }

    public static function fechasInvalidas(): array
    {
        // [dia, hora, campo con el error, mensaje]
        return [
            'sin día' => ['', '10:30', 'dia', 'Elegí el día del turno.'],
            'sin hora' => ['2026-11-20', '', 'hora', 'Elegí la hora del turno.'],
            'día con formato inválido' => ['mañana', '10:30', 'dia', 'El día elegido no es válido. Elegilo de nuevo en la lista.'],
            'hora con formato inválido' => ['2026-11-20', 'a la tarde', 'hora', 'La hora elegida no es válida. Elegila de nuevo en la lista.'],
            'fecha pasada' => ['2026-11-17', '10:00', 'fecha_hora', 'La fecha del turno tiene que ser futura.'],
            'hoy más temprano' => ['2026-11-18', '09:30', 'fecha_hora', 'La fecha del turno tiene que ser futura.'],
            'domingo' => ['2026-11-22', '10:00', 'fecha_hora', 'Los turnos son de lunes a sábado. Elegí otro día.'],
            'antes de abrir' => ['2026-11-20', '08:30', 'fecha_hora', 'Los turnos son entre las 09:00 y las 18:00. Elegí otro horario.'],
            'después de cerrar' => ['2026-11-20', '18:30', 'fecha_hora', 'Los turnos son entre las 09:00 y las 18:00. Elegí otro horario.'],
            'fuera del intervalo de 30 minutos' => ['2026-11-20', '10:15', 'fecha_hora', 'Los turnos se dan cada 30 minutos (por ejemplo 10:00 o 10:30).'],
        ];
    }

    // ------------------------------------------------ Listas desplegables del formulario

    #[Group('RF-13')]
    public function test_la_lista_de_dias_ofrece_solo_dias_de_atencion_sin_domingos(): void
    {
        $dias = array_map(fn ($d) => $d->format('Y-m-d'), Turno::diasParaElegir());

        // Hoy (miércoles 18, 10:00) todavía tiene horarios, así que entra; el domingo 22 y el 29 no
        $this->assertCount(Turno::DIAS_A_OFRECER, $dias);
        $this->assertSame(['2026-11-18', '2026-11-19', '2026-11-20', '2026-11-21', '2026-11-23'], array_slice($dias, 0, 5));
        $this->assertNotContains('2026-11-22', $dias);
        $this->assertNotContains('2026-11-29', $dias);

        // Después del último horario (18:00) hoy ya no se ofrece
        Carbon::setTestNow(Carbon::parse('2026-11-18 18:10'));
        $this->assertSame('2026-11-19', Turno::diasParaElegir()[0]->format('Y-m-d'));
    }

    #[Group('RF-13')]
    public function test_la_lista_de_horas_va_de_9_a_18_cada_30_minutos(): void
    {
        $horarios = Turno::horarios();

        $this->assertCount(19, $horarios);
        $this->assertSame('09:00', $horarios[0]);
        $this->assertSame('09:30', $horarios[1]);
        $this->assertSame('18:00', end($horarios));
    }

    #[Group('RF-13')]
    public function test_el_formulario_tiene_las_dos_listas_con_dias_y_horarios_de_atencion(): void
    {
        $v = $this->vehiculo();

        $html = $this->actingAs($this->cliente())->get("/vehiculos/{$v->id}")->assertOk()
            ->assertSee('<select id="turnoDia" name="dia"', false)
            ->assertSee('<select id="turnoHora" name="hora"', false)
            ->assertSee('value="2026-11-20"', false)
            ->assertSee('Viernes 20/11')
            ->assertSee('Sábado 21/11')
            ->assertDontSee('value="2026-11-22"', false) // domingo
            ->assertSee('value="09:00"', false)
            ->assertSee('value="18:00"', false)
            ->assertDontSee('value="08:30"', false)
            ->assertDontSee('value="18:30"', false)
            ->getContent();

        // Ya no se usa el calendario libre
        $this->assertStringNotContainsString('type="datetime-local"', $html);
    }

    #[Group('RF-13')]
    public function test_los_horarios_ocupados_y_los_que_ya_pasaron_salen_deshabilitados(): void
    {
        $v = $this->vehiculo();
        $otro = $this->vehiculo();
        // Hoy (el primer día de la lista) a las 11:00 hay un turno confirmado de este vehículo;
        // a las 12:00 hay uno cancelado (no ocupa) y a las 13:00 uno de otro vehículo (tampoco)
        $this->turnoDe($this->cliente(), $v, 'confirmado', '2026-11-18 11:00');
        $this->turnoDe($this->cliente(), $v, 'cancelado', '2026-11-18 12:00');
        $this->turnoDe($this->cliente(), $otro, 'pendiente', '2026-11-18 13:00');
        // Otro día ocupado: viernes 20 a las 15:30
        $this->turnoDe($this->cliente(), $v, 'pendiente', '2026-11-20 15:30');

        $html = $this->actingAs($this->cliente())->get("/vehiculos/{$v->id}")->assertOk()->getContent();

        // En el día elegido (hoy): 09:00, 09:30 y 10:00 ya pasaron y 11:00 está ocupado
        foreach (['09:00', '09:30', '10:00', '11:00'] as $hora) {
            $this->assertMatchesRegularExpression('#<option value="' . $hora . '"\s+disabled#', $html, "$hora tendría que estar deshabilitado");
        }
        foreach (['10:30', '12:00', '13:00', '18:00'] as $hora) {
            $this->assertMatchesRegularExpression('#<option value="' . $hora . '"\s*>#', $html, "$hora tendría que estar habilitado");
        }

        // Los de los demás días van en el script que actualiza la lista al cambiar de día
        $this->assertEquals(
            ['2026-11-18' => ['09:00', '09:30', '10:00', '11:00'], '2026-11-20' => ['15:30']],
            Turno::horariosNoDisponibles($v, Turno::diasParaElegir())
        );
        $this->assertStringContainsString('"2026-11-20":["15:30"]', $html);
    }

    #[Group('RF-13')]
    public function test_si_vuelve_con_error_quedan_elegidos_el_dia_y_la_hora_que_mando(): void
    {
        $v = $this->vehiculo();
        $this->turnoDe($this->cliente(), $v, 'confirmado', '2026-11-20 10:30');

        $this->pedir($this->cliente(), $v)->assertSessionHasErrors('fecha_hora'); // horario ocupado

        $html = $this->get("/vehiculos/{$v->id}")->getContent();
        $this->assertMatchesRegularExpression('#<option value="2026-11-20"\s+selected#', $html);
        $this->assertStringContainsString('Ese horario ya está tomado para este vehículo. Elegí otro.', $html);
    }

    #[Group('RF-13')]
    public function test_el_horario_de_atencion_esta_en_constantes_del_modelo(): void
    {
        $this->assertSame([1, 2, 3, 4, 5, 6], Turno::DIAS_ATENCION);
        $this->assertSame('09:00', Turno::HORA_APERTURA);
        $this->assertSame('18:00', Turno::HORA_CIERRE);
        $this->assertNull(Turno::problemaDeHorario(Carbon::parse('2026-11-20 10:30')));
        $this->assertNotNull(Turno::problemaDeHorario(Carbon::parse('2026-11-22 10:30')));
    }

    #[Group('RF-13')]
    #[DataProvider('estadosNoDisponibles')]
    public function test_no_se_pide_turno_de_un_vehiculo_que_no_esta_disponible(string $estado): void
    {
        $v = $this->vehiculo(['estado' => $estado]);

        $this->pedir($this->cliente(), $v)
            ->assertRedirect(route('vehiculos.show', $v))
            ->assertSessionHas('error', 'Solo se pueden pedir turnos de vehículos disponibles.');

        $this->assertSame(0, Turno::count());
    }

    public static function estadosNoDisponibles(): array
    {
        return ['reservado' => ['reservado'], 'vendido' => ['vendido']];
    }

    #[Group('RF-13')]
    public function test_un_cliente_no_puede_tener_dos_turnos_activos_para_el_mismo_vehiculo(): void
    {
        $cliente = $this->cliente();
        $v = $this->vehiculo();

        foreach (['pendiente', 'confirmado'] as $estado) {
            Turno::query()->delete();
            $this->turnoDe($cliente, $v, $estado);

            $this->pedir($cliente, $v, '2026-11-23T11:00')
                ->assertRedirect(route('turnos.mios'))
                ->assertSessionHas('error', fn ($m) => str_contains($m, 'Ya tenés un turno activo para este vehículo.'));

            $this->assertSame(1, Turno::count());
        }
    }

    #[Group('RF-13')]
    public function test_con_un_turno_cancelado_o_realizado_puede_pedir_otro(): void
    {
        $cliente = $this->cliente();
        $v = $this->vehiculo();
        $this->turnoDe($cliente, $v, 'cancelado');
        $this->turnoDe($cliente, $v, 'realizado', '2026-11-10 10:00');

        $this->pedir($cliente, $v)->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertSame(1, Turno::activos()->count());
    }

    #[Group('RF-13')]
    public function test_dos_turnos_activos_del_mismo_vehiculo_no_pueden_ser_a_la_misma_hora(): void
    {
        $v = $this->vehiculo();
        $this->turnoDe($this->cliente(), $v, 'confirmado', '2026-11-20 10:30');

        $this->pedir($this->cliente(), $v)
            ->assertRedirect("/vehiculos/{$v->id}")
            ->assertSessionHasErrors(['fecha_hora' => 'Ese horario ya está tomado para este vehículo. Elegí otro.']);

        $this->assertSame(1, Turno::count());
    }

    #[Group('RF-13')]
    public function test_el_mismo_horario_si_se_puede_en_otro_vehiculo_o_si_el_otro_turno_se_cancelo(): void
    {
        $v = $this->vehiculo();
        $otro = $this->vehiculo();
        $this->turnoDe($this->cliente(), $v, 'cancelado', '2026-11-20 10:30');
        $this->turnoDe($this->cliente(), $otro, 'pendiente', '2026-11-20 10:30');

        $this->pedir($this->cliente(), $v)->assertSessionHasNoErrors()->assertSessionHas('success');
    }

    #[Group('RF-13')]
    public function test_un_vehiculo_que_no_existe_da_404(): void
    {
        $this->actingAs($this->cliente())->post('/vehiculos/9999/turno', ['dia' => '2026-11-20', 'hora' => '10:30'])->assertNotFound();
    }

    // ----------------------------------------------------- Mis turnos y cancelar

    #[Group('RF-13')]
    public function test_mis_turnos_muestra_solo_los_propios_con_su_estado(): void
    {
        $ana = $this->cliente();
        $this->turnoDe($ana, $this->vehiculo(['modelo' => 'Yaris']), 'confirmado', '2026-11-20 10:30');
        $this->turnoDe($ana, $this->vehiculo(['modelo' => 'Hilux']), 'realizado', '2026-11-10 11:00');
        $this->turnoDe($this->cliente(), $this->vehiculo(['modelo' => 'Kicks']));

        $this->actingAs($ana)->get('/mis-turnos')->assertOk()
            ->assertSeeInOrder(['Yaris', '20/11/2026', '10:30', 'Confirmado', 'Hilux', '10/11/2026', 'Realizado'])
            ->assertDontSee('Kicks');
    }

    #[Group('RF-13')]
    public function test_mis_turnos_vacio_invita_a_ver_el_catalogo(): void
    {
        $this->actingAs($this->cliente())->get('/mis-turnos')->assertOk()
            ->assertSee('Todavía no pediste ningún turno')
            ->assertSee(route('vehiculos.index'), false);
    }

    #[Group('RF-13')]
    public function test_el_cliente_cancela_su_turno_pendiente_o_confirmado(): void
    {
        $cliente = $this->cliente();

        foreach (['pendiente', 'confirmado'] as $estado) {
            $t = $this->turnoDe($cliente, $this->vehiculo(), $estado);

            $this->actingAs($cliente)->patch("/mis-turnos/{$t->id}/cancelar")
                ->assertRedirect(route('turnos.mios'))
                ->assertSessionHas('success', 'Cancelaste el turno.');

            $this->assertSame('cancelado', $t->fresh()->estado);
        }
    }

    #[Group('RF-13')]
    public function test_el_boton_cancelar_solo_aparece_en_los_turnos_activos(): void
    {
        $cliente = $this->cliente();
        $activo = $this->turnoDe($cliente, $this->vehiculo(), 'pendiente');
        $final = $this->turnoDe($cliente, $this->vehiculo(), 'realizado', '2026-11-10 10:00');

        $this->actingAs($cliente)->get('/mis-turnos')
            ->assertSee(route('turnos.cancelar', $activo), false)
            ->assertDontSee(route('turnos.cancelar', $final), false);
    }

    #[Group('RF-13')]
    public function test_no_se_puede_cancelar_un_turno_ya_cancelado_o_realizado(): void
    {
        $cliente = $this->cliente();

        foreach (['cancelado', 'realizado'] as $estado) {
            $t = $this->turnoDe($cliente, $this->vehiculo(), $estado);

            $this->actingAs($cliente)->patch("/mis-turnos/{$t->id}/cancelar")
                ->assertRedirect(route('turnos.mios'))
                ->assertSessionHas('error', fn ($m) => str_contains($m, 'no se puede cancelar'));

            $this->assertSame($estado, $t->fresh()->estado);
        }
    }

    #[Group('RF-13')]
    public function test_un_cliente_no_puede_cancelar_el_turno_de_otro(): void
    {
        $t = $this->turnoDe($this->cliente(), $this->vehiculo());

        $this->actingAs($this->cliente())->patch("/mis-turnos/{$t->id}/cancelar")->assertForbidden();

        $this->assertSame('pendiente', $t->fresh()->estado);
    }

    // ----------------------------------------------------------- Agenda del equipo

    #[Group('RF-13')]
    public function test_la_agenda_muestra_todos_los_turnos_ordenados_por_fecha(): void
    {
        $this->turnoDe($this->cliente(), $this->vehiculo(['modelo' => 'Tercero']), 'pendiente', '2026-11-25 09:00');
        $this->turnoDe($this->cliente(), $this->vehiculo(['modelo' => 'Primero']), 'cancelado', '2026-11-19 15:00');
        $this->turnoDe($this->cliente(), $this->vehiculo(['modelo' => 'Segundo']), 'confirmado', '2026-11-20 10:30');

        foreach (['empleado', 'admin'] as $rol) {
            $this->actingAs($this->{$rol}())->get('/turnos')->assertOk()
                ->assertSee('Agenda de turnos')
                ->assertSeeInOrder(['Primero', 'Segundo', 'Tercero']);
        }
    }

    #[Group('RF-13')]
    public function test_la_agenda_filtra_por_estado_y_los_totales_no_cambian(): void
    {
        $this->turnoDe($this->cliente(), $this->vehiculo(['modelo' => 'Pendiente1']), 'pendiente');
        $this->turnoDe($this->cliente(), $this->vehiculo(['modelo' => 'Confirmado1']), 'confirmado');

        $respuesta = $this->actingAs($this->empleado())->get('/turnos?estado=confirmado')->assertOk()
            ->assertSee('Confirmado1')->assertDontSee('Pendiente1');
        $this->assertSame(1, $respuesta->viewData('totales')['pendiente']);
        $this->assertSame(1, $respuesta->viewData('totales')['confirmado']);

        // Un estado inventado no filtra
        $this->actingAs($this->empleado())->get('/turnos?estado=cualquiera')->assertOk()
            ->assertSee('Confirmado1')->assertSee('Pendiente1');
    }

    #[Group('RF-13')]
    public function test_la_agenda_ofrece_solo_los_botones_de_las_transiciones_validas(): void
    {
        $empleado = $this->empleado();

        $this->turnoDe($this->cliente(), $this->vehiculo(), 'pendiente');
        $this->actingAs($empleado)->get('/turnos')
            ->assertSee('Confirmar')->assertSee('Cancelar')->assertDontSee('Marcar realizado');

        Turno::query()->update(['estado' => 'confirmado']);
        $this->actingAs($empleado)->get('/turnos')
            ->assertSee('Marcar realizado')->assertSee('Cancelar')->assertDontSee('>Confirmar<', false);

        Turno::query()->update(['estado' => 'realizado']);
        $this->actingAs($empleado)->get('/turnos')->assertSee('Sin acciones')->assertDontSee('name="estado"', false);
    }

    #[Group('RF-13')]
    #[DataProvider('transicionesValidas')]
    public function test_transiciones_validas(string $desde, string $hacia, string $mensaje): void
    {
        $t = $this->turnoDe($this->cliente(), $this->vehiculo(), $desde);

        $this->actingAs($this->empleado())->from('/turnos')->patch("/turnos/{$t->id}/estado", ['estado' => $hacia])
            ->assertRedirect('/turnos')
            ->assertSessionHas('success', $mensaje);

        $this->assertSame($hacia, $t->fresh()->estado);
    }

    public static function transicionesValidas(): array
    {
        return [
            'pendiente a confirmado' => ['pendiente', 'confirmado', 'El turno quedó confirmado.'],
            'pendiente a cancelado' => ['pendiente', 'cancelado', 'El turno quedó cancelado.'],
            'confirmado a realizado' => ['confirmado', 'realizado', 'El turno quedó realizado.'],
            'confirmado a cancelado' => ['confirmado', 'cancelado', 'El turno quedó cancelado.'],
        ];
    }

    #[Group('RF-13')]
    #[DataProvider('transicionesInvalidas')]
    public function test_transiciones_invalidas_se_rechazan(string $desde, string $hacia): void
    {
        $cliente = $this->cliente();
        $t = $this->turnoDe($cliente, $this->vehiculo(), $desde);

        $this->actingAs($this->admin())->from('/turnos')->patch("/turnos/{$t->id}/estado", ['estado' => $hacia])
            ->assertRedirect('/turnos')
            ->assertSessionHas('error');

        $this->assertSame($desde, $t->fresh()->estado);
        $this->assertCount(0, $cliente->notifications, 'Un cambio rechazado no avisa al cliente.');
    }

    public static function transicionesInvalidas(): array
    {
        return [
            'pendiente a realizado' => ['pendiente', 'realizado'],
            'pendiente a pendiente' => ['pendiente', 'pendiente'],
            'confirmado a pendiente' => ['confirmado', 'pendiente'],
            'cancelado a confirmado' => ['cancelado', 'confirmado'],
            'cancelado a pendiente' => ['cancelado', 'pendiente'],
            'realizado a cancelado' => ['realizado', 'cancelado'],
            'realizado a confirmado' => ['realizado', 'confirmado'],
        ];
    }

    #[Group('RF-13')]
    public function test_el_mensaje_de_transicion_invalida_explica_el_motivo(): void
    {
        $t = $this->turnoDe($this->cliente(), $this->vehiculo(), 'cancelado');

        $this->actingAs($this->empleado())->patch("/turnos/{$t->id}/estado", ['estado' => 'confirmado'])
            ->assertSessionHas('error', 'Un turno cancelado no puede pasar a confirmado.');
    }

    #[Group('RF-13')]
    public function test_un_estado_inventado_o_vacio_da_error_de_validacion(): void
    {
        $t = $this->turnoDe($this->cliente(), $this->vehiculo());
        $empleado = $this->actingAs($this->empleado());

        $empleado->patch("/turnos/{$t->id}/estado", ['estado' => 'borrado'])
            ->assertSessionHasErrors(['estado' => 'El estado elegido no es válido.']);
        $empleado->patch("/turnos/{$t->id}/estado", [])
            ->assertSessionHasErrors(['estado' => 'Falta el estado nuevo del turno.']);

        $this->assertSame('pendiente', $t->fresh()->estado);
    }

    // ------------------------------------------------------------------ Avisos

    #[Group('RF-13')]
    public function test_al_pedir_un_turno_se_avisa_a_empleados_y_admins(): void
    {
        $empleado = $this->empleado();
        $admin = $this->admin();
        $cliente = User::factory()->cliente()->create(['name' => 'Marta Pérez']);
        $otroCliente = $this->cliente();

        $this->pedir($cliente, $this->vehiculo(['marca' => 'Fiat', 'modelo' => 'Cronos']));

        foreach ([$empleado, $admin] as $miembro) {
            $this->assertCount(1, $miembro->notifications);
            $aviso = $miembro->notifications->first();
            $this->assertSame(NuevoTurno::class, $aviso->type);
            $this->assertSame('nuevo_turno', $aviso->data['tipo']);
            $this->assertSame(Turno::sole()->id, $aviso->data['turno_id']);
            $this->assertSame('Marta Pérez pidió un turno para Fiat Cronos el 20/11/2026 a las 10:30.', $aviso->data['texto']);
        }
        $this->assertCount(0, $cliente->notifications);
        $this->assertCount(0, $otroCliente->notifications);
    }

    #[Group('RF-13')]
    public function test_un_pedido_rechazado_no_genera_aviso(): void
    {
        $empleado = $this->empleado();

        $this->pedir($this->cliente(), $this->vehiculo(), '2026-11-22T10:00'); // domingo
        $this->pedir($this->cliente(), $this->vehiculo(['estado' => 'vendido']));

        $this->assertCount(0, $empleado->notifications);
    }

    #[Group('RF-13')]
    public function test_al_cambiar_el_estado_se_avisa_solo_al_cliente_dueno(): void
    {
        $dueno = $this->cliente();
        $otro = $this->cliente();
        $empleado = $this->empleado();
        $t = $this->turnoDe($dueno, $this->vehiculo(['marca' => 'Fiat', 'modelo' => 'Cronos']));

        $this->actingAs($empleado)->patch("/turnos/{$t->id}/estado", ['estado' => 'confirmado']);

        $this->assertCount(1, $dueno->notifications);
        $aviso = $dueno->notifications->first();
        $this->assertSame(TurnoActualizado::class, $aviso->type);
        $this->assertSame('estado_turno', $aviso->data['tipo']);
        $this->assertSame('confirmado', $aviso->data['estado']);
        $this->assertSame('Tu turno está confirmado', $aviso->data['titulo']);
        $this->assertSame('Tu turno para Fiat Cronos del 20/11/2026 a las 10:30 ahora está confirmado.', $aviso->data['texto']);

        $this->assertCount(0, $otro->notifications);
        $this->assertCount(0, $empleado->notifications);
    }

    #[Group('RF-13')]
    public function test_tocar_el_aviso_lleva_a_mis_turnos_o_a_la_agenda(): void
    {
        $cliente = $this->cliente();
        $empleado = $this->empleado();

        $this->pedir($cliente, $this->vehiculo());
        $avisoEquipo = $empleado->notifications->first();
        $this->actingAs($empleado)->post("/notificaciones/{$avisoEquipo->id}/leer")->assertRedirect(route('turnos.index'));
        $this->assertNotNull($avisoEquipo->fresh()->read_at);

        $this->actingAs($empleado)->patch('/turnos/' . Turno::sole()->id . '/estado', ['estado' => 'confirmado']);
        $avisoCliente = $cliente->notifications->first();
        $this->actingAs($cliente)->post("/notificaciones/{$avisoCliente->id}/leer")->assertRedirect(route('turnos.mios'));
    }

    // ------------------------------------------------------- Detalle y menú

    #[Group('RF-13')]
    public function test_el_detalle_muestra_pedir_turno_solo_al_cliente_y_al_visitante_lo_invita(): void
    {
        $v = $this->vehiculo();
        $formulario = route('turnos.store', $v);

        $this->get("/vehiculos/{$v->id}")->assertOk()
            ->assertSee('Iniciar sesión para pedir turno')
            ->assertDontSee($formulario, false);

        $this->actingAs($this->cliente())->get("/vehiculos/{$v->id}")->assertOk()
            ->assertSee($formulario, false)
            ->assertSee('name="dia"', false)->assertSee('name="hora"', false)
            ->assertSee('Pedir turno');

        foreach (['empleado', 'admin'] as $rol) {
            $this->actingAs($this->{$rol}())->get("/vehiculos/{$v->id}")->assertOk()
                ->assertDontSee($formulario, false)
                ->assertDontSee('Pedir turno');
        }
    }

    #[Group('RF-13')]
    public function test_el_detalle_no_ofrece_turno_si_el_vehiculo_no_esta_disponible(): void
    {
        $v = $this->vehiculo(['estado' => 'reservado']);

        $this->actingAs($this->cliente())->get("/vehiculos/{$v->id}")
            ->assertDontSee(route('turnos.store', $v), false)
            ->assertSee('se piden solo cuando está disponible');
    }

    #[Group('RF-13')]
    public function test_si_ya_tiene_un_turno_activo_el_detalle_lo_muestra_en_lugar_del_formulario(): void
    {
        $cliente = $this->cliente();
        $v = $this->vehiculo();
        $this->turnoDe($cliente, $v, 'confirmado');

        $this->actingAs($cliente)->get("/vehiculos/{$v->id}")
            ->assertSee('Ya tenés un turno para este vehículo')
            ->assertSee('20/11/2026 10:30')
            ->assertDontSee(route('turnos.store', $v), false);
    }

    #[Group('RF-13')]
    public function test_el_menu_cambia_segun_el_rol(): void
    {
        $mios = route('turnos.mios');
        $agenda = route('turnos.index');

        $this->get('/vehiculos')->assertDontSee($mios, false)->assertDontSee($agenda, false);
        $this->actingAs($this->cliente())->get('/vehiculos')->assertSee($mios, false)->assertDontSee($agenda, false);
        foreach (['empleado', 'admin'] as $rol) {
            $this->actingAs($this->{$rol}())->get('/vehiculos')->assertSee($agenda, false)->assertDontSee($mios, false);
        }
    }

    #[Group('RF-13')]
    public function test_el_menu_del_equipo_muestra_los_turnos_pendientes(): void
    {
        $this->turnoDe($this->cliente(), $this->vehiculo(), 'pendiente');
        $this->turnoDe($this->cliente(), $this->vehiculo(), 'pendiente', '2026-11-21 10:00');
        $this->turnoDe($this->cliente(), $this->vehiculo(), 'confirmado');

        $this->actingAs($this->empleado())->get('/vehiculos')->assertSee('text-bg-warning">2<', false);
    }

    // -------------------------------------------- Relación con la baja del vehículo

    #[Group('RF-13')]
    public function test_un_vehiculo_con_turnos_no_se_puede_eliminar_y_se_explica_por_que(): void
    {
        $v = $this->vehiculo(['marca' => 'Ford', 'modelo' => 'Fiesta']);
        $this->turnoDe($this->cliente(), $v, 'cancelado');

        $this->actingAs($this->admin())->delete("/vehiculos/{$v->id}")
            ->assertRedirect(route('vehiculos.show', $v))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'No se puede eliminar Ford Fiesta porque tiene registros asociados: 1 turno.'));

        $this->assertModelExists($v);
        $this->assertSame(1, Turno::count());
    }

    // ------------------------------------------------------------ Escapado

    #[Group('RF-13')]
    public function test_los_datos_del_cliente_se_muestran_escapados_en_la_agenda(): void
    {
        $cliente = User::factory()->cliente()->create(['name' => '<script>alert(1)</script>']);
        $this->turnoDe($cliente, $this->vehiculo());

        $this->actingAs($this->empleado())->get('/turnos')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }
}
