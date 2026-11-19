<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Resena;
use App\Models\User;
use App\Models\Vehiculo;
use App\Notifications\NuevaResena;
use App\Notifications\ResenaModerada;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\ArmaDatos;
use Tests\TestCase;

/**
 * RF-14: el Cliente deja una reseña después de comprar (consulta cerrada) y el Admin la modera.
 * En el detalle del vehículo solo se ven las aprobadas, con el primer nombre del cliente.
 */
class ResenaTest extends TestCase
{
    use RefreshDatabase, ArmaDatos;

    private const TEXTO = 'Muy buena atención y el auto estaba impecable.';

    /** Un cliente que compró: consulta cerrada sobre un vehículo vendido. Devuelve [cliente, consulta]. */
    private function compra(array $vehiculo = [], array $cliente = []): array
    {
        $c = User::factory()->cliente()->create($cliente);
        $v = $this->vehiculo($vehiculo + ['estado' => 'vendido']);

        return [$c, $this->lead($v, 'cerrado', null, $c)];
    }

    private function resenaDe(User $cliente, Vehiculo $vehiculo, string $estado = 'pendiente', string $texto = self::TEXTO): Resena
    {
        return Resena::create([
            'usuario_id' => $cliente->id, 'vehiculo_id' => $vehiculo->id,
            'texto' => $texto, 'estado_moderacion' => $estado,
        ]);
    }

    // ----------------------------------------------------------------- Acceso

    #[Group('RF-14')]
    #[DataProvider('permisos')]
    public function test_matriz_de_permisos_de_resenas(string $rol, string $metodo, string $ruta, int $esperado): void
    {
        [, $consulta] = $this->compra();
        $resena = $this->resenaDe($this->cliente(), $this->vehiculo());
        $url = str_replace(['{consulta}', '{resena}'], [(string) $consulta->id, (string) $resena->id], $ruta);
        $actor = $rol === 'visitante' ? $this : $this->actingAs($this->{$rol}());

        $respuesta = $actor->call($metodo, $url, ['texto' => self::TEXTO, 'estado' => 'aprobada']);

        $respuesta->assertStatus($esperado);
        if ($rol === 'visitante') {
            $respuesta->assertRedirect(route('login'));
        }
    }

    public static function permisos(): array
    {
        $filas = [];
        // [método, ruta, visitante, cliente, empleado, admin]. El "cliente" de esta tabla no es el dueño de la consulta.
        $tabla = [
            ['GET', '/mis-consultas/{consulta}/resena', 302, 403, 403, 403],
            ['POST', '/mis-consultas/{consulta}/resena', 302, 403, 403, 403],
            ['GET', '/resenas', 302, 403, 403, 200],
            ['PATCH', '/resenas/{resena}/moderar', 302, 403, 403, 302],
        ];

        foreach ($tabla as [$metodo, $ruta, $visitante, $cliente, $empleado, $admin]) {
            foreach (compact('visitante', 'cliente', 'empleado', 'admin') as $rol => $codigo) {
                $filas["$rol $metodo $ruta"] = [$rol, $metodo, $ruta, $codigo];
            }
        }

        return $filas;
    }

    #[Group('RF-14')]
    public function test_las_rutas_estan_declaradas_con_su_middleware(): void
    {
        $rutas = collect(app('router')->getRoutes()->getRoutes())->keyBy(fn ($r) => $r->getName());

        foreach (['resenas.create', 'resenas.store'] as $nombre) {
            $this->assertContains('rol:cliente', $rutas[$nombre]->gatherMiddleware(), $nombre);
        }
        foreach (['resenas.index', 'resenas.moderar'] as $nombre) {
            $this->assertContains('rol:admin', $rutas[$nombre]->gatherMiddleware(), $nombre);
        }
    }

    #[Group('RF-14')]
    public function test_el_empleado_no_entra_a_la_moderacion_ni_puede_moderar(): void
    {
        $resena = $this->resenaDe($this->cliente(), $this->vehiculo());
        $empleado = $this->empleado();

        $this->actingAs($empleado)->get('/resenas')->assertForbidden();
        $this->actingAs($empleado)->patch("/resenas/{$resena->id}/moderar", ['estado' => 'aprobada'])->assertForbidden();

        $this->assertSame('pendiente', $resena->fresh()->estado_moderacion);
    }

    #[Group('RF-14')]
    public function test_un_cliente_no_puede_moderar_ni_su_propia_resena(): void
    {
        [$cliente, $consulta] = $this->compra();
        $resena = $this->resenaDe($cliente, $consulta->vehiculo);

        $this->actingAs($cliente)->patch("/resenas/{$resena->id}/moderar", ['estado' => 'aprobada'])->assertForbidden();
        auth()->logout();
        $this->patch("/resenas/{$resena->id}/moderar", ['estado' => 'aprobada'])->assertRedirect(route('login'));

        $this->assertSame('pendiente', $resena->fresh()->estado_moderacion);
    }

    // ------------------------------------------------------------- Dejar reseña

    #[Group('RF-14')]
    public function test_el_cliente_que_compro_deja_su_resena_y_queda_pendiente(): void
    {
        [$cliente, $consulta] = $this->compra();

        $this->actingAs($cliente)->get("/mis-consultas/{$consulta->id}/resena")->assertOk()
            ->assertSee('Dejar reseña')->assertSee('name="texto"', false);

        $this->actingAs($cliente)->post("/mis-consultas/{$consulta->id}/resena", ['texto' => '  ' . self::TEXTO . '  '])
            ->assertRedirect(route('consultas.mias'))
            ->assertSessionHas('success', '¡Gracias por tu reseña! Se va a ver en la página del vehículo cuando el administrador la apruebe.');

        $resena = Resena::sole();
        $this->assertSame($cliente->id, $resena->usuario_id);
        $this->assertSame($consulta->vehiculo_id, $resena->vehiculo_id);
        $this->assertSame(self::TEXTO, $resena->texto);
        $this->assertSame('pendiente', $resena->estado_moderacion);
    }

    #[Group('RF-14')]
    public function test_un_cliente_sin_compra_no_puede_resenar_la_consulta_de_otro(): void
    {
        [, $consulta] = $this->compra();

        $this->actingAs($this->cliente())->post("/mis-consultas/{$consulta->id}/resena", ['texto' => self::TEXTO])->assertForbidden();

        $this->assertSame(0, Resena::count());
    }

    #[Group('RF-14')]
    #[DataProvider('consultasNoCerradas')]
    public function test_con_una_consulta_no_cerrada_no_se_puede_resenar(string $estado): void
    {
        $cliente = $this->cliente();
        $consulta = $this->lead($this->vehiculo(), $estado, null, $cliente);

        $this->actingAs($cliente)->get("/mis-consultas/{$consulta->id}/resena")
            ->assertRedirect(route('consultas.mias'))
            ->assertSessionHas('error', 'Podés dejar una reseña cuando se cierre la compra del vehículo.');
        $this->actingAs($cliente)->post("/mis-consultas/{$consulta->id}/resena", ['texto' => self::TEXTO])
            ->assertSessionHas('error', 'Podés dejar una reseña cuando se cierre la compra del vehículo.');

        $this->assertSame(0, Resena::count());
    }

    public static function consultasNoCerradas(): array
    {
        return ['nuevo' => ['nuevo'], 'contactado' => ['contactado'], 'negociando' => ['negociando']];
    }

    #[Group('RF-14')]
    public function test_una_sola_resena_por_cliente_y_vehiculo(): void
    {
        [$cliente, $consulta] = $this->compra();
        $this->resenaDe($cliente, $consulta->vehiculo, 'rechazada');

        $this->actingAs($cliente)->post("/mis-consultas/{$consulta->id}/resena", ['texto' => 'Otra reseña distinta de la primera.'])
            ->assertRedirect(route('consultas.mias'))
            ->assertSessionHas('error', 'Ya dejaste una reseña para este vehículo.');
        $this->actingAs($cliente)->get("/mis-consultas/{$consulta->id}/resena")
            ->assertSessionHas('error', 'Ya dejaste una reseña para este vehículo.');

        $this->assertSame(1, Resena::count());
    }

    #[Group('RF-14')]
    #[DataProvider('textosInvalidos')]
    public function test_valida_el_texto_con_mensajes_en_espanol(string $texto, string $mensaje): void
    {
        [$cliente, $consulta] = $this->compra();

        $this->actingAs($cliente)->from("/mis-consultas/{$consulta->id}/resena")
            ->post("/mis-consultas/{$consulta->id}/resena", ['texto' => $texto])
            ->assertRedirect("/mis-consultas/{$consulta->id}/resena")
            ->assertSessionHasErrors(['texto' => $mensaje]);

        $this->assertSame(0, Resena::count());
    }

    public static function textosInvalidos(): array
    {
        return [
            'vacío' => ['', 'Escribí tu reseña antes de enviarla.'],
            'solo espacios' => ['      ', 'Escribí tu reseña antes de enviarla.'],
            'muy corto' => ['Muy bien', 'La reseña tiene que tener al menos 10 caracteres. Contanos un poco más.'],
            'muy largo' => [str_repeat('a', 501), 'La reseña puede tener hasta 500 caracteres. Acortala un poco y volvé a enviarla.'],
        ];
    }

    #[Group('RF-14')]
    public function test_acepta_10_y_500_caracteres(): void
    {
        [$cliente, $consulta] = $this->compra();
        [$otro, $otraConsulta] = $this->compra();

        $this->actingAs($cliente)->post("/mis-consultas/{$consulta->id}/resena", ['texto' => str_repeat('a', 10)])->assertSessionHasNoErrors();
        $this->actingAs($otro)->post("/mis-consultas/{$otraConsulta->id}/resena", ['texto' => str_repeat('b', 500)])->assertSessionHasNoErrors();

        $this->assertSame(2, Resena::count());
    }

    // ------------------------------------------------------------- Mis consultas

    #[Group('RF-14')]
    public function test_mis_consultas_ofrece_dejar_resena_solo_en_las_cerradas(): void
    {
        $cliente = $this->cliente();
        $cerrada = $this->lead($this->vehiculo(['estado' => 'vendido']), 'cerrado', null, $cliente);
        $abierta = $this->lead($this->vehiculo(), 'negociando', null, $cliente);

        $this->actingAs($cliente)->get('/mis-consultas')->assertOk()
            ->assertSee(route('resenas.create', $cerrada), false)
            ->assertDontSee(route('resenas.create', $abierta), false)
            ->assertSee('Dejar reseña');
    }

    #[Group('RF-14')]
    #[DataProvider('estadosDeModeracion')]
    public function test_si_ya_la_dejo_mis_consultas_muestra_el_estado_de_la_resena(string $estado, string $etiqueta, string $explicacion): void
    {
        [$cliente, $consulta] = $this->compra();
        $this->resenaDe($cliente, $consulta->vehiculo, $estado);

        $this->actingAs($cliente)->get('/mis-consultas')->assertOk()
            ->assertSee('Tu reseña')
            ->assertSee($etiqueta)
            ->assertSee($explicacion)
            ->assertSee(self::TEXTO)
            ->assertDontSee(route('resenas.create', $consulta), false);
    }

    public static function estadosDeModeracion(): array
    {
        return [
            'pendiente' => ['pendiente', 'Pendiente', 'Se va a ver en la página del vehículo cuando el administrador la apruebe.'],
            'aprobada' => ['aprobada', 'Aprobada', 'Ya se ve en la página del vehículo.'],
            'rechazada' => ['rechazada', 'Rechazada', 'El administrador decidió no publicarla.'],
        ];
    }

    // ---------------------------------------------------------------- Moderación

    #[Group('RF-14')]
    public function test_la_moderacion_muestra_primero_las_pendientes(): void
    {
        $this->resenaDe($this->cliente(), $this->vehiculo(), 'aprobada', 'Texto de la aprobada.');
        $this->resenaDe($this->cliente(), $this->vehiculo(), 'pendiente', 'Texto de la pendiente.');
        $this->resenaDe($this->cliente(), $this->vehiculo(), 'rechazada', 'Texto de la rechazada.');

        $respuesta = $this->actingAs($this->admin())->get('/resenas')->assertOk()
            ->assertSee('Moderación de reseñas');
        $this->assertStringStartsWith('Texto de la pendiente.', strip_tags(explode('texto-resena">', $respuesta->getContent())[1]));
        $this->assertSame(1, substr_count($respuesta->getContent(), 'name="estado" value="aprobada"'), 'Solo la pendiente tiene botones');
        $respuesta->assertSee('data-total="pendiente">1<', false);
    }

    #[Group('RF-14')]
    #[DataProvider('decisiones')]
    public function test_el_admin_aprueba_o_rechaza(string $estado, string $mensaje): void
    {
        $resena = $this->resenaDe($this->cliente(), $this->vehiculo());

        $this->actingAs($this->admin())->from('/resenas')->patch("/resenas/{$resena->id}/moderar", ['estado' => $estado])
            ->assertRedirect('/resenas')
            ->assertSessionHas('success', $mensaje);

        $this->assertSame($estado, $resena->fresh()->estado_moderacion);
    }

    public static function decisiones(): array
    {
        return [
            'aprobar' => ['aprobada', 'Reseña aprobada: ya se ve en la página del vehículo.'],
            'rechazar' => ['rechazada', 'Reseña rechazada: no se va a publicar.'],
        ];
    }

    #[Group('RF-14')]
    public function test_una_resena_moderada_no_vuelve_a_pendiente_ni_cambia(): void
    {
        $admin = $this->admin();
        $cliente = $this->cliente();
        $aprobada = $this->resenaDe($cliente, $this->vehiculo(), 'aprobada');
        $rechazada = $this->resenaDe($this->cliente(), $this->vehiculo(), 'rechazada');

        // Volver a pendiente: no es una opción válida
        $this->actingAs($admin)->patch("/resenas/{$aprobada->id}/moderar", ['estado' => 'pendiente'])
            ->assertSessionHasErrors(['estado' => 'Una reseña solo se puede aprobar o rechazar.']);
        // Cambiar una ya moderada
        $this->actingAs($admin)->patch("/resenas/{$aprobada->id}/moderar", ['estado' => 'rechazada'])
            ->assertSessionHas('error', 'Esta reseña ya fue aprobada y no se puede cambiar.');
        $this->actingAs($admin)->patch("/resenas/{$rechazada->id}/moderar", ['estado' => 'aprobada'])
            ->assertSessionHas('error', 'Esta reseña ya fue rechazada y no se puede cambiar.');

        $this->assertSame('aprobada', $aprobada->fresh()->estado_moderacion);
        $this->assertSame('rechazada', $rechazada->fresh()->estado_moderacion);
        $this->assertCount(0, $cliente->notifications, 'Un intento rechazado no avisa al cliente.');
    }

    #[Group('RF-14')]
    public function test_un_estado_vacio_da_error_de_validacion(): void
    {
        $resena = $this->resenaDe($this->cliente(), $this->vehiculo());

        $this->actingAs($this->admin())->patch("/resenas/{$resena->id}/moderar", [])
            ->assertSessionHasErrors(['estado' => 'Elegí si la reseña se aprueba o se rechaza.']);
        $this->assertSame('pendiente', $resena->fresh()->estado_moderacion);
    }

    // ------------------------------------------------------- Detalle del vehículo

    #[Group('RF-14')]
    public function test_el_detalle_muestra_solo_las_aprobadas_con_el_primer_nombre(): void
    {
        $v = $this->vehiculo(['estado' => 'vendido']);
        $this->resenaDe(User::factory()->cliente()->create(['name' => 'Marta Zubizarreta']), $v, 'aprobada', 'Reseña APROBADA visible.');
        $this->resenaDe($this->cliente(), $v, 'pendiente', 'Reseña PENDIENTE oculta.');
        $this->resenaDe($this->cliente(), $v, 'rechazada', 'Reseña RECHAZADA oculta.');

        foreach ([null, 'cliente', 'empleado', 'admin'] as $rol) {
            $pedido = $rol ? $this->actingAs($this->{$rol}()) : $this;

            $pedido->get("/vehiculos/{$v->id}")->assertOk()
                ->assertSee('Reseñas de clientes')
                ->assertSee('Reseña APROBADA visible.')
                ->assertSee('Marta')
                ->assertDontSee('Zubizarreta')
                ->assertDontSee('Reseña PENDIENTE oculta.')
                ->assertDontSee('Reseña RECHAZADA oculta.');
        }
    }

    #[Group('RF-14')]
    public function test_sin_aprobadas_no_aparece_la_seccion(): void
    {
        $v = $this->vehiculo(['estado' => 'vendido']);
        $this->resenaDe($this->cliente(), $v, 'pendiente');
        $this->resenaDe($this->cliente(), $v, 'rechazada');

        $this->get("/vehiculos/{$v->id}")->assertOk()->assertDontSee('Reseñas de clientes');
        $this->get('/vehiculos/' . $this->vehiculo()->id)->assertOk()->assertDontSee('Reseñas de clientes');
    }

    #[Group('RF-14')]
    public function test_al_aprobarla_aparece_en_el_detalle(): void
    {
        [$cliente, $consulta] = $this->compra();
        $this->actingAs($cliente)->post("/mis-consultas/{$consulta->id}/resena", ['texto' => self::TEXTO]);
        auth()->logout();

        $this->get("/vehiculos/{$consulta->vehiculo_id}")->assertDontSee(self::TEXTO);

        $this->actingAs($this->admin())->patch('/resenas/' . Resena::sole()->id . '/moderar', ['estado' => 'aprobada']);
        auth()->logout();

        $this->get("/vehiculos/{$consulta->vehiculo_id}")->assertSee(self::TEXTO);
    }

    #[Group('RF-14')]
    public function test_un_texto_con_script_se_muestra_escapado(): void
    {
        $v = $this->vehiculo(['estado' => 'vendido']);
        $malicioso = '<script>alert("hola")</script> Buen auto, lo recomiendo.';
        $this->resenaDe($this->cliente(), $v, 'aprobada', $malicioso);
        $this->resenaDe($this->cliente(), $this->vehiculo(), 'pendiente', $malicioso);

        $this->get("/vehiculos/{$v->id}")
            ->assertDontSee('<script>alert("hola")</script>', false)
            ->assertSee('&lt;script&gt;alert(&quot;hola&quot;)&lt;/script&gt;', false);

        $this->actingAs($this->admin())->get('/resenas')
            ->assertDontSee('<script>alert("hola")</script>', false)
            ->assertSee('&lt;script&gt;', false);
    }

    // -------------------------------------------------------------------- Avisos

    #[Group('RF-14')]
    public function test_una_resena_nueva_avisa_solo_a_los_admins(): void
    {
        $admin = $this->admin();
        $empleado = $this->empleado();
        [$cliente, $consulta] = $this->compra(['marca' => 'Fiat', 'modelo' => 'Cronos'], ['name' => 'Marta Pérez']);

        $this->actingAs($cliente)->post("/mis-consultas/{$consulta->id}/resena", ['texto' => self::TEXTO]);

        $this->assertCount(1, $admin->notifications);
        $aviso = $admin->notifications->first();
        $this->assertSame(NuevaResena::class, $aviso->type);
        $this->assertSame('nueva_resena', $aviso->data['tipo']);
        $this->assertSame(Resena::sole()->id, $aviso->data['resena_id']);
        $this->assertSame('Marta Pérez dejó una reseña sobre Fiat Cronos.', $aviso->data['texto']);
        $this->assertCount(0, $empleado->notifications);
        $this->assertCount(0, $cliente->notifications);
    }

    #[Group('RF-14')]
    public function test_una_resena_invalida_o_duplicada_no_genera_aviso(): void
    {
        $admin = $this->admin();
        [$cliente, $consulta] = $this->compra();

        $this->actingAs($cliente)->post("/mis-consultas/{$consulta->id}/resena", ['texto' => 'corta']);
        $this->resenaDe($cliente, $consulta->vehiculo);
        $this->actingAs($cliente)->post("/mis-consultas/{$consulta->id}/resena", ['texto' => self::TEXTO]);

        $this->assertCount(0, $admin->notifications);
    }

    #[Group('RF-14')]
    #[DataProvider('avisosDeModeracion')]
    public function test_al_moderar_se_avisa_al_cliente_que_la_escribio(string $estado, string $titulo, string $texto): void
    {
        [$cliente, $consulta] = $this->compra(['marca' => 'Fiat', 'modelo' => 'Cronos']);
        $otro = $this->cliente();
        $resena = $this->resenaDe($cliente, $consulta->vehiculo);

        $this->actingAs($this->admin())->patch("/resenas/{$resena->id}/moderar", ['estado' => $estado]);

        $this->assertCount(1, $cliente->notifications);
        $aviso = $cliente->notifications->first();
        $this->assertSame(ResenaModerada::class, $aviso->type);
        $this->assertSame($estado, $aviso->data['estado']);
        $this->assertSame($titulo, $aviso->data['titulo']);
        $this->assertSame($texto, $aviso->data['texto']);
        $this->assertCount(0, $otro->notifications);
    }

    public static function avisosDeModeracion(): array
    {
        return [
            'aprobada' => ['aprobada', 'Tu reseña fue aprobada', 'Tu reseña sobre Fiat Cronos ya se ve en la página del vehículo. ¡Gracias!'],
            'rechazada' => ['rechazada', 'Tu reseña no fue aprobada', 'Tu reseña sobre Fiat Cronos no se va a publicar.'],
        ];
    }

    #[Group('RF-14')]
    public function test_tocar_el_aviso_lleva_a_la_moderacion_o_a_mis_consultas(): void
    {
        $admin = $this->admin();
        [$cliente, $consulta] = $this->compra();

        $this->actingAs($cliente)->post("/mis-consultas/{$consulta->id}/resena", ['texto' => self::TEXTO]);
        $this->actingAs($admin)->post('/notificaciones/' . $admin->notifications->first()->id . '/leer')
            ->assertRedirect(route('resenas.index'));

        $this->actingAs($admin)->patch('/resenas/' . Resena::sole()->id . '/moderar', ['estado' => 'aprobada']);
        $this->actingAs($cliente)->post('/notificaciones/' . $cliente->notifications->first()->id . '/leer')
            ->assertRedirect(route('consultas.mias'));
    }

    // ---------------------------------------------------------------------- Menú

    #[Group('RF-14')]
    public function test_el_enlace_de_resenas_del_menu_lo_ve_solo_el_admin_con_las_pendientes(): void
    {
        $enlace = route('resenas.index');
        $this->resenaDe($this->cliente(), $this->vehiculo(), 'pendiente');
        $this->resenaDe($this->cliente(), $this->vehiculo(), 'aprobada');

        $this->get('/vehiculos')->assertDontSee($enlace, false);
        foreach (['cliente', 'empleado'] as $rol) {
            $this->actingAs($this->{$rol}())->get('/vehiculos')->assertDontSee($enlace, false);
        }

        $html = $this->actingAs($this->admin())->get('/vehiculos')->assertSee($enlace, false)->getContent();
        $this->assertMatchesRegularExpression('#' . preg_quote($enlace, '#') . '">\s*Reseñas\s*<span class="badge rounded-pill text-bg-warning">1</span>#', $html);
    }
}
