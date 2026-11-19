<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\Resena;
use App\Models\Turno;
use App\Models\User;
use App\Notifications\RestablecerContrasena;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\ArmaDatos;
use Tests\TestCase;

/**
 * Registro de auditoría (extra C): cada acción importante deja UNA fila con quién, qué, sobre qué y cuándo.
 * No se guardan secretos, las filas no se editan ni se borran, y la pantalla es solo para el Admin.
 */
#[Group('Auditoria')]
class AuditoriaTest extends TestCase
{
    use RefreshDatabase, ArmaDatos;

    /** La única fila de auditoría que tiene que haber. */
    private function unico(): Auditoria
    {
        $this->assertSame(1, Auditoria::count(), 'Tiene que haber exactamente un registro de auditoría.');

        return Auditoria::sole();
    }

    private function datosDe($v, array $cambios = []): array
    {
        return $this->datosFormulario($cambios + [
            'marca' => $v->marca, 'modelo' => $v->modelo, 'anio' => $v->anio, 'precio' => $v->precio,
            'kilometraje' => $v->kilometraje, 'transmision' => $v->transmision,
            'combustible' => $v->combustible, 'estado' => $v->estado,
            // Sin fotos nuevas: estas pruebas miran solo los cambios de datos (las fotos se prueban en FotoVehiculoTest)
            'fotos' => '',
        ]);
    }

    // ------------------------------------------------------------------- Tabla

    public function test_la_tabla_tiene_las_columnas_esperadas_y_sin_updated_at(): void
    {
        $this->assertTrue(Schema::hasColumns('auditoria', [
            'id', 'usuario_id', 'accion', 'detalle', 'referencia_tipo', 'referencia_id', 'created_at',
        ]));
        $this->assertFalse(Schema::hasColumn('auditoria', 'updated_at'));
    }

    // ----------------------------------------------------------------- Vehículos

    public function test_crear_un_vehiculo_deja_un_registro(): void
    {
        $empleado = $this->empleado();

        $this->actingAs($empleado)->post('/vehiculos', $this->datosFormulario())->assertRedirect();

        $r = $this->unico();
        $this->assertSame('vehiculo.crear', $r->accion);
        $this->assertSame($empleado->id, $r->usuario_id);
        $this->assertSame('vehiculo', $r->referencia_tipo);
        $this->assertSame(\App\Models\Vehiculo::sole()->id, $r->referencia_id);
        $this->assertSame('Alta de Ford Fiesta 2019: U$S 11.500, estado Disponible.', $r->detalle);
        $this->assertNotNull($r->created_at);
    }

    public function test_editar_un_vehiculo_registra_el_cambio_de_precio_y_de_estado(): void
    {
        $empleado = $this->empleado();
        $v = $this->vehiculo(); // Toyota Corolla 2020, U$S 15.000, disponible

        $this->actingAs($empleado)->put("/vehiculos/{$v->id}", $this->datosDe($v, ['precio' => 14000, 'estado' => 'reservado']))
            ->assertRedirect();

        $r = $this->unico();
        $this->assertSame('vehiculo.editar', $r->accion);
        $this->assertSame($empleado->id, $r->usuario_id);
        $this->assertSame($v->id, $r->referencia_id);
        $this->assertSame('Toyota Corolla 2020. Precio: U$S 15.000 → U$S 14.000. Estado: Disponible → Reservado.', $r->detalle);
    }

    public function test_editar_sin_cambiar_datos_igual_deja_un_registro(): void
    {
        $v = $this->vehiculo();

        $this->actingAs($this->empleado())->put("/vehiculos/{$v->id}", $this->datosDe($v));

        $this->assertSame('Toyota Corolla 2020. Sin cambios.', $this->unico()->detalle);
    }

    public function test_eliminar_un_vehiculo_deja_un_registro_que_sobrevive_al_vehiculo(): void
    {
        $admin = $this->admin();
        $v = $this->vehiculo();

        $this->actingAs($admin)->delete("/vehiculos/{$v->id}")->assertRedirect(route('vehiculos.index'));

        $r = $this->unico();
        $this->assertSame('vehiculo.eliminar', $r->accion);
        $this->assertSame($admin->id, $r->usuario_id);
        $this->assertSame($v->id, $r->referencia_id);
        $this->assertSame('Baja de Toyota Corolla 2020 (U$S 15.000, estado Disponible).', $r->detalle);
        $this->assertModelMissing($v);
    }

    // ------------------------------------------------------------- Consultas

    public function test_avanzar_una_consulta_registra_el_estado_anterior_y_el_nuevo(): void
    {
        $empleado = $this->empleado();
        $cliente = User::factory()->cliente()->create(['name' => 'Ana Pérez']);
        $lead = $this->lead($this->vehiculo(), 'contactado', null, $cliente);

        $this->actingAs($empleado)->patch("/consultas/{$lead->id}/estado")->assertRedirect();

        $r = $this->unico();
        $this->assertSame('consulta.avanzar', $r->accion);
        $this->assertSame($empleado->id, $r->usuario_id);
        $this->assertSame('consulta', $r->referencia_tipo);
        $this->assertSame($lead->id, $r->referencia_id);
        $this->assertSame("Consulta #{$lead->id} de Ana Pérez por Toyota Corolla: Contactado → Negociando. Vehículo: Reservado.", $r->detalle);
    }

    public function test_registrar_un_contacto_guarda_que_empleado_lo_hizo_sin_el_comentario(): void
    {
        $empleado = $this->empleado();
        $lead = $this->lead($this->vehiculo());

        $this->actingAs($empleado)->post("/consultas/{$lead->id}/interacciones", [
            'tipo' => 'llamada', 'fecha' => '2026-11-10 15:30', 'comentario' => 'Nota interna: no ofrecer descuento',
        ])->assertRedirect();

        $r = $this->unico();
        $this->assertSame('consulta.contacto', $r->accion);
        $this->assertSame($empleado->id, $r->usuario_id);
        $this->assertSame($lead->id, $r->referencia_id);
        $this->assertStringStartsWith('Llamada del 10/11/2026 15:30 en la consulta #' . $lead->id, $r->detalle);
        $this->assertStringNotContainsString('descuento', $r->detalle);
    }

    // ------------------------------------------------------------------ Turnos

    public function test_el_equipo_cambia_el_estado_de_un_turno_y_queda_registrado(): void
    {
        $admin = $this->admin();
        $turno = $this->turno($this->vehiculo());

        $this->actingAs($admin)->patch("/turnos/{$turno->id}/estado", ['estado' => 'confirmado'])->assertRedirect();

        $r = $this->unico();
        $this->assertSame('turno.estado', $r->accion);
        $this->assertSame($admin->id, $r->usuario_id);
        $this->assertSame('turno', $r->referencia_tipo);
        $this->assertSame($turno->id, $r->referencia_id);
        $this->assertStringContainsString('Pendiente → Confirmado (por el equipo).', $r->detalle);
    }

    public function test_el_cliente_cancela_su_turno_y_queda_registrado_a_su_nombre(): void
    {
        $turno = $this->turno($this->vehiculo());
        $cliente = $turno->cliente;

        $this->actingAs($cliente)->patch("/mis-turnos/{$turno->id}/cancelar")->assertRedirect();

        $r = $this->unico();
        $this->assertSame($cliente->id, $r->usuario_id);
        $this->assertStringContainsString('Pendiente → Cancelado (por el cliente).', $r->detalle);
    }

    // ----------------------------------------------------------------- Reseñas

    #[DataProvider('moderaciones')]
    public function test_moderar_una_resena_queda_registrado(string $estado, string $texto): void
    {
        $admin = $this->admin();
        $resena = Resena::create([
            'usuario_id' => $this->cliente()->id, 'vehiculo_id' => $this->vehiculo()->id,
            'texto' => 'Texto privado de la reseña.', 'estado_moderacion' => 'pendiente',
        ]);

        $this->actingAs($admin)->patch("/resenas/{$resena->id}/moderar", ['estado' => $estado])->assertRedirect();

        $r = $this->unico();
        $this->assertSame('resena.moderar', $r->accion);
        $this->assertSame($admin->id, $r->usuario_id);
        $this->assertSame('resena', $r->referencia_tipo);
        $this->assertStringEndsWith(": {$texto}.", $r->detalle);
        $this->assertStringNotContainsString('Texto privado', $r->detalle);
    }

    public static function moderaciones(): array
    {
        return ['aprobada' => ['aprobada', 'Aprobada'], 'rechazada' => ['rechazada', 'Rechazada']];
    }

    // ---------------------------------------------------------------- Usuarios

    public function test_crear_un_usuario_queda_registrado_sin_la_contrasena(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/usuarios', [
            'name' => 'Laura Gómez', 'email' => 'laura@autonova.test', 'rol' => 'empleado',
            'password' => 'provisoria-123', 'password_confirmation' => 'provisoria-123',
        ])->assertRedirect();

        $r = $this->unico();
        $nueva = User::where('email', 'laura@autonova.test')->sole();
        $this->assertSame('usuario.crear', $r->accion);
        $this->assertSame($admin->id, $r->usuario_id);
        $this->assertSame('usuario', $r->referencia_tipo);
        $this->assertSame($nueva->id, $r->referencia_id);
        $this->assertSame('Cuenta de Laura Gómez (laura@autonova.test) con rol Empleado.', $r->detalle);
        $this->assertStringNotContainsString('provisoria-123', json_encode($r->getAttributes()));
    }

    // -------------------------------------------------- Recuperar contraseña

    public function test_el_cambio_de_contrasena_por_recuperacion_queda_registrado_sin_secretos(): void
    {
        Notification::fake();
        $ana = User::factory()->cliente()->create(['name' => 'Ana Pérez', 'email' => 'ana@correo.test', 'password' => 'clave-vieja-123']);

        $this->post('/olvide-contrasena', ['email' => 'ana@correo.test']);
        $codigo = null;
        Notification::assertSentTo($ana, RestablecerContrasena::class, function ($aviso) use (&$codigo, $ana) {
            preg_match('#/restablecer-contrasena/([^?]+)#', $aviso->toMail($ana)->viewData['enlace'], $partes);
            $codigo = $partes[1];

            return true;
        });

        // Pedir el enlace no es un cambio: todavía no hay registro
        $this->assertSame(0, Auditoria::count());

        $this->post('/restablecer-contrasena', [
            'token' => $codigo, 'email' => 'ana@correo.test',
            'password' => 'clave-nueva-456', 'password_confirmation' => 'clave-nueva-456',
        ])->assertRedirect(route('login'));

        $r = $this->unico();
        $this->assertSame('contrasena.recuperar', $r->accion);
        $this->assertSame($ana->id, $r->usuario_id);
        $this->assertSame($ana->id, $r->referencia_id);

        $fila = json_encode($r->getAttributes());
        foreach (['clave-vieja-123', 'clave-nueva-456', $codigo, $ana->fresh()->password, $ana->fresh()->remember_token] as $secreto) {
            $this->assertStringNotContainsString($secreto, $fila);
        }
    }

    // ------------------------------------------- Nada de registros falsos

    public function test_si_la_accion_principal_falla_no_queda_registro(): void
    {
        $admin = $this->admin();
        $empleado = $this->empleado();

        // Vehículo con datos inválidos
        $this->actingAs($empleado)->post('/vehiculos', $this->datosFormulario(['precio' => -5]))->assertSessionHasErrors('precio');

        // Vehículo que no se puede borrar (tiene un turno)
        $conTurno = $this->vehiculo();
        $this->turno($conTurno);
        $this->actingAs($admin)->delete("/vehiculos/{$conTurno->id}")->assertSessionHas('error');

        // Consulta que no puede avanzar (vehículo ya vendido) y contacto en una consulta cerrada
        $this->actingAs($empleado)->patch('/consultas/' . $this->lead($this->vehiculo(['estado' => 'vendido']), 'negociando')->id . '/estado');
        $this->actingAs($empleado)->post('/consultas/' . $this->lead($this->vehiculo(), 'cerrado')->id . '/interacciones', ['tipo' => 'llamada']);

        // Transición de turno inválida y reseña ya moderada
        $realizado = $this->turno($this->vehiculo());
        $realizado->update(['estado' => 'realizado']);
        $this->actingAs($admin)->patch("/turnos/{$realizado->id}/estado", ['estado' => 'cancelado'])->assertSessionHas('error');
        $resena = $this->resena($this->vehiculo()); // ya aprobada
        $this->actingAs($admin)->patch("/resenas/{$resena->id}/moderar", ['estado' => 'rechazada'])->assertSessionHas('error');

        // Usuario con correo repetido y recuperación con un código inventado
        $this->actingAs($admin)->post('/usuarios', [
            'name' => 'Otro', 'email' => $empleado->email, 'rol' => 'empleado',
            'password' => 'provisoria-123', 'password_confirmation' => 'provisoria-123',
        ])->assertSessionHasErrors('email');
        auth()->logout();
        $this->post('/restablecer-contrasena', [
            'token' => 'inventado', 'email' => $empleado->email, 'password' => 'clave-nueva-456', 'password_confirmation' => 'clave-nueva-456',
        ])->assertSessionHasErrors('email');

        // Pedidos rechazados por rol
        $this->actingAs($this->cliente())->post('/vehiculos', $this->datosFormulario())->assertForbidden();
        $this->actingAs($empleado)->delete("/vehiculos/{$conTurno->id}")->assertForbidden();

        $this->assertSame(0, Auditoria::count());
    }

    public function test_si_falla_la_auditoria_tampoco_queda_la_accion(): void
    {
        // La acción y su registro van en la misma transacción: si no se puede guardar el registro, se deshace todo
        $v = $this->vehiculo();
        Schema::drop('auditoria');

        try {
            $this->withoutExceptionHandling()->actingAs($this->admin())->delete("/vehiculos/{$v->id}");
            $this->fail('Se esperaba un error al no poder escribir la auditoría.');
        } catch (\Illuminate\Database\QueryException) {
            // esperado
        }

        $this->assertModelExists($v);
    }

    // ----------------------------------------------------------- Sin secretos

    public function test_ningun_registro_guarda_contrasenas_ni_tokens(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/usuarios', [
            'name' => 'Laura', 'email' => 'laura@autonova.test', 'rol' => 'admin',
            'password' => 'secreto-super-123', 'password_confirmation' => 'secreto-super-123',
        ]);
        $this->actingAs($admin)->post('/vehiculos', $this->datosFormulario());

        $todo = json_encode(Auditoria::all()->map->getAttributes());
        $this->assertStringNotContainsString('secreto-super-123', $todo);
        $this->assertStringNotContainsString('$2y$', $todo); // ningún hash de contraseña
        $this->assertStringNotContainsStringIgnoringCase('password', $todo);
        $this->assertStringNotContainsStringIgnoringCase('token', $todo);
    }

    // ------------------------------------------ No se edita ni se borra

    public function test_un_registro_no_se_puede_editar_ni_borrar_desde_la_aplicacion(): void
    {
        $r = Auditoria::registrar('vehiculo.crear', 'Detalle original.', null, $this->admin());

        try {
            $r->update(['detalle' => 'Detalle cambiado.']);
            $this->fail('No se tendría que poder editar.');
        } catch (LogicException $e) {
            $this->assertSame('El registro de auditoría no se puede modificar.', $e->getMessage());
        }

        try {
            $r->delete();
            $this->fail('No se tendría que poder borrar.');
        } catch (LogicException $e) {
            $this->assertSame('El registro de auditoría no se puede borrar.', $e->getMessage());
        }

        $this->assertSame('Detalle original.', $r->fresh()->detalle);
    }

    public function test_no_hay_rutas_para_editar_ni_borrar_la_auditoria(): void
    {
        $r = Auditoria::registrar('vehiculo.crear', 'Detalle.', null, $admin = $this->admin());
        $actor = $this->actingAs($admin);

        $actor->put("/auditoria/{$r->id}")->assertNotFound();
        $actor->delete("/auditoria/{$r->id}")->assertNotFound();
        $actor->post('/auditoria')->assertStatus(405);

        $rutas = collect(app('router')->getRoutes()->getRoutes())->filter(fn ($ruta) => str_starts_with($ruta->uri(), 'auditoria'));
        $this->assertSame(['GET|HEAD auditoria'], $rutas->map(fn ($ruta) => implode('|', $ruta->methods()) . ' ' . $ruta->uri())->values()->all());
        $this->assertSame(1, Auditoria::count());
    }

    public function test_si_se_borra_un_usuario_su_auditoria_queda_sin_usuario(): void
    {
        // La clave foránea es nullOnDelete: no bloquea el borrado ni borra el historial
        $persona = $this->empleado();
        $r = Auditoria::registrar('vehiculo.crear', 'Detalle.', null, $persona);

        $persona->delete();

        $this->assertNull($r->fresh()->usuario_id);
        $this->assertSame(1, Auditoria::count());
    }

    // ----------------------------------------------------------- Pantalla

    #[DataProvider('perfiles')]
    public function test_permisos_de_la_pantalla(string $rol, int $esperado): void
    {
        $actor = $rol === 'visitante' ? $this : $this->actingAs($this->{$rol}());

        $respuesta = $actor->get('/auditoria')->assertStatus($esperado);
        if ($rol === 'visitante') {
            $respuesta->assertRedirect(route('login'));
        }
    }

    public static function perfiles(): array
    {
        return ['visitante' => ['visitante', 302], 'cliente' => ['cliente', 403], 'empleado' => ['empleado', 403], 'admin' => ['admin', 200]];
    }

    public function test_la_pantalla_muestra_quien_que_sobre_que_y_cuando_de_la_mas_nueva_a_la_mas_vieja(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Admin Pruebas']);
        $vieja = Auditoria::registrar('vehiculo.crear', 'Registro VIEJO.', $this->vehiculo(), $admin);
        $this->travel(5)->minutes();
        Auditoria::registrar('usuario.crear', 'Registro NUEVO.', $admin, $admin);

        $this->actingAs($admin)->get('/auditoria')->assertOk()
            ->assertSeeInOrder(['Registro NUEVO.', 'Registro VIEJO.'])
            ->assertSee('Admin Pruebas')
            ->assertSee('Creó un vehículo')
            ->assertSee('Vehículo #' . $vieja->referencia_id)
            ->assertSee($vieja->created_at->format('d/m/Y H:i'));
    }

    public function test_filtra_por_usuario_y_por_accion(): void
    {
        $admin = $this->admin();
        $ana = User::factory()->empleado()->create(['name' => 'Ana Empleada']);
        $beto = User::factory()->empleado()->create(['name' => 'Beto Empleado']);
        Auditoria::registrar('vehiculo.crear', 'ANA-CREA', null, $ana);
        Auditoria::registrar('vehiculo.editar', 'ANA-EDITA', null, $ana);
        Auditoria::registrar('vehiculo.crear', 'BETO-CREA', null, $beto);

        $this->actingAs($admin)->get("/auditoria?usuario={$ana->id}")
            ->assertSee('ANA-CREA')->assertSee('ANA-EDITA')->assertDontSee('BETO-CREA');
        $this->actingAs($admin)->get('/auditoria?accion=vehiculo.crear')
            ->assertSee('ANA-CREA')->assertSee('BETO-CREA')->assertDontSee('ANA-EDITA');
        $this->actingAs($admin)->get("/auditoria?usuario={$beto->id}&accion=vehiculo.crear")
            ->assertSee('BETO-CREA')->assertDontSee('ANA-CREA');

        // Valores inventados se ignoran: se ven todos
        $this->actingAs($admin)->get('/auditoria?usuario=abc&accion=borrar.todo')
            ->assertSee('ANA-CREA')->assertSee('ANA-EDITA')->assertSee('BETO-CREA');
    }

    public function test_pagina_de_a_20_y_conserva_los_filtros(): void
    {
        $admin = $this->admin();
        foreach (range(1, 23) as $i) {
            Auditoria::registrar('vehiculo.crear', "Registro {$i}", null, $admin);
        }

        $this->actingAs($admin)->get('/auditoria')
            ->assertViewHas('registros', fn ($p) => $p->count() === 20 && $p->total() === 23);
        $this->actingAs($admin)->get('/auditoria?page=2')
            ->assertViewHas('registros', fn ($p) => $p->count() === 3);
        $this->actingAs($admin)->get('/auditoria?accion=vehiculo.crear')
            ->assertSee('accion=vehiculo.crear&amp;page=2', false);
    }

    public function test_el_detalle_se_muestra_escapado(): void
    {
        $admin = $this->admin();
        Auditoria::registrar('usuario.crear', 'Cuenta de <script>alert(1)</script>.', null, $admin);

        $this->actingAs($admin)->get('/auditoria')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_el_menu_muestra_auditoria_solo_al_admin(): void
    {
        $enlace = route('auditoria.index');

        $this->get('/vehiculos')->assertDontSee($enlace, false);
        foreach (['cliente', 'empleado'] as $rol) {
            $this->actingAs($this->{$rol}())->get('/vehiculos')->assertDontSee($enlace, false);
        }
        $this->actingAs($this->admin())->get('/vehiculos')->assertSee($enlace, false);
    }
}
