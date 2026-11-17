<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\ArmaDatos;
use Tests\TestCase;

/**
 * RF-11: el Cliente marca vehículos como favoritos.
 * Permisos: solo el Cliente (visitante -> login, Empleado y Admin -> 403).
 */
class FavoritoTest extends TestCase
{
    use RefreshDatabase, ArmaDatos;

    private function favorito(User $cliente, $vehiculo): void
    {
        DB::table('favoritos')->insert(['usuario_id' => $cliente->id, 'vehiculo_id' => $vehiculo->id]);
    }

    private function cantidadDeFavoritos(): int
    {
        return DB::table('favoritos')->count();
    }

    // ----------------------------------------------------------------- Acceso

    #[Group('RF-11')]
    #[DataProvider('permisos')]
    public function test_matriz_de_permisos_de_favoritos(string $rol, string $metodo, string $ruta, int $esperado): void
    {
        $v = $this->vehiculo();
        $url = str_replace('{vehiculo}', (string) $v->id, $ruta);
        $actor = $rol === 'visitante' ? $this : $this->actingAs($this->{$rol}());

        $respuesta = $actor->call($metodo, $url);

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
            ['GET', '/favoritos', 302, 200, 403, 403],
            ['POST', '/vehiculos/{vehiculo}/favorito', 302, 302, 403, 403],
            ['DELETE', '/vehiculos/{vehiculo}/favorito', 302, 302, 403, 403],
        ];

        foreach ($tabla as [$metodo, $ruta, $visitante, $cliente, $empleado, $admin]) {
            foreach (compact('visitante', 'cliente', 'empleado', 'admin') as $rol => $codigo) {
                $filas["$rol $metodo $ruta"] = [$rol, $metodo, $ruta, $codigo];
            }
        }

        return $filas;
    }

    #[Group('RF-11')]
    public function test_un_pedido_rechazado_por_rol_no_modifica_datos(): void
    {
        $dueno = $this->cliente();
        $guardado = $this->vehiculo();
        $otro = $this->vehiculo();
        $this->favorito($dueno, $guardado);

        foreach (['empleado', 'admin'] as $rol) {
            $this->actingAs($this->{$rol}())->post("/vehiculos/{$otro->id}/favorito")->assertForbidden();
            $this->actingAs($this->{$rol}())->delete("/vehiculos/{$guardado->id}/favorito")->assertForbidden();
        }
        auth()->logout();
        $this->post("/vehiculos/{$otro->id}/favorito");
        $this->delete("/vehiculos/{$guardado->id}/favorito");

        $this->assertSame(1, $this->cantidadDeFavoritos());
        $this->assertDatabaseHas('favoritos', ['usuario_id' => $dueno->id, 'vehiculo_id' => $guardado->id]);
    }

    #[Group('RF-11')]
    public function test_las_rutas_estan_declaradas_con_su_middleware(): void
    {
        $rutas = collect(app('router')->getRoutes()->getRoutes())->keyBy(fn ($r) => $r->getName());

        foreach (['favoritos.index', 'favoritos.store', 'favoritos.destroy'] as $nombre) {
            $this->assertContains('rol:cliente', $rutas[$nombre]->gatherMiddleware(), $nombre);
        }
    }

    // ------------------------------------------------------- Agregar y quitar

    #[Group('RF-11')]
    public function test_el_cliente_agrega_un_vehiculo_a_favoritos(): void
    {
        $cliente = $this->cliente();
        $v = $this->vehiculo(['marca' => 'Ford', 'modelo' => 'Fiesta']);

        $this->actingAs($cliente)->post("/vehiculos/{$v->id}/favorito")
            ->assertRedirect(route('vehiculos.show', $v))
            ->assertSessionHas('success', 'Agregaste Ford Fiesta a tus favoritos.');

        $this->assertDatabaseHas('favoritos', ['usuario_id' => $cliente->id, 'vehiculo_id' => $v->id]);
    }

    #[Group('RF-11')]
    public function test_agregar_dos_veces_no_duplica_ni_da_error(): void
    {
        $cliente = $this->cliente();
        $v = $this->vehiculo();

        $this->actingAs($cliente)->post("/vehiculos/{$v->id}/favorito")->assertRedirect();
        $this->actingAs($cliente)->post("/vehiculos/{$v->id}/favorito")->assertRedirect();

        $this->assertSame(1, $this->cantidadDeFavoritos());
    }

    #[Group('RF-11')]
    public function test_vuelve_a_la_pagina_desde_la_que_se_pidio(): void
    {
        $cliente = $this->cliente();
        $v = $this->vehiculo();

        $this->actingAs($cliente)->from('/favoritos')->post("/vehiculos/{$v->id}/favorito")
            ->assertRedirect('/favoritos');
    }

    #[Group('RF-11')]
    public function test_el_cliente_quita_un_favorito(): void
    {
        $cliente = $this->cliente();
        $v = $this->vehiculo(['marca' => 'Ford', 'modelo' => 'Fiesta']);
        $this->favorito($cliente, $v);

        $this->actingAs($cliente)->delete("/vehiculos/{$v->id}/favorito")
            ->assertRedirect(route('favoritos.index'))
            ->assertSessionHas('success', 'Quitaste Ford Fiesta de tus favoritos.');

        $this->assertSame(0, $this->cantidadDeFavoritos());
        // El vehículo en sí no se toca
        $this->assertDatabaseHas('vehiculos', ['id' => $v->id]);
    }

    #[Group('RF-11')]
    public function test_quitar_algo_que_no_era_favorito_no_da_error_ni_mensaje(): void
    {
        $v = $this->vehiculo();

        $this->actingAs($this->cliente())->delete("/vehiculos/{$v->id}/favorito")
            ->assertRedirect()
            ->assertSessionMissing('success');
    }

    #[Group('RF-11')]
    public function test_los_favoritos_de_un_cliente_no_afectan_a_los_de_otro(): void
    {
        $ana = $this->cliente();
        $beto = $this->cliente();
        $v = $this->vehiculo();
        $this->favorito($ana, $v);
        $this->favorito($beto, $v);

        $this->actingAs($ana)->delete("/vehiculos/{$v->id}/favorito");

        $this->assertDatabaseMissing('favoritos', ['usuario_id' => $ana->id, 'vehiculo_id' => $v->id]);
        $this->assertDatabaseHas('favoritos', ['usuario_id' => $beto->id, 'vehiculo_id' => $v->id]);
    }

    #[Group('RF-11')]
    public function test_un_vehiculo_que_no_existe_da_404(): void
    {
        $cliente = $this->actingAs($this->cliente());

        $cliente->post('/vehiculos/9999/favorito')->assertNotFound();
        $cliente->delete('/vehiculos/9999/favorito')->assertNotFound();
    }

    #[Group('RF-11')]
    public function test_tambien_se_puede_guardar_un_vehiculo_vendido(): void
    {
        $cliente = $this->cliente();
        $v = $this->vehiculo(['estado' => 'vendido']);

        $this->actingAs($cliente)->post("/vehiculos/{$v->id}/favorito")->assertRedirect();

        $this->assertDatabaseHas('favoritos', ['usuario_id' => $cliente->id, 'vehiculo_id' => $v->id]);
    }

    // ------------------------------------------------------------------ Listado

    #[Group('RF-11')]
    public function test_mis_favoritos_muestra_solo_los_propios_ordenados_por_marca_y_modelo(): void
    {
        $cliente = $this->cliente();
        $otro = $this->cliente();
        $this->favorito($cliente, $this->vehiculo(['marca' => 'Toyota', 'modelo' => 'Yaris']));
        $this->favorito($cliente, $this->vehiculo(['marca' => 'Ford', 'modelo' => 'Fiesta']));
        $this->favorito($cliente, $this->vehiculo(['marca' => 'Toyota', 'modelo' => 'Corolla']));
        $this->favorito($otro, $this->vehiculo(['marca' => 'Nissan', 'modelo' => 'Kicks']));

        $this->actingAs($cliente)->get('/favoritos')
            ->assertOk()
            ->assertSeeInOrder(['Ford Fiesta', 'Toyota Corolla', 'Toyota Yaris'])
            ->assertDontSee('Kicks');
    }

    #[Group('RF-11')]
    public function test_cada_favorito_tiene_enlace_al_detalle_y_boton_para_quitarlo(): void
    {
        $cliente = $this->cliente();
        $v = $this->vehiculo();
        $this->favorito($cliente, $v);

        $this->actingAs($cliente)->get('/favoritos')
            ->assertSee(route('vehiculos.show', $v), false)
            ->assertSee(route('favoritos.destroy', $v), false)
            ->assertSee('Quitar de favoritos');
    }

    #[Group('RF-11')]
    public function test_mis_favoritos_pagina_de_a_12(): void
    {
        $cliente = $this->cliente();
        foreach (range(1, 14) as $i) {
            $this->favorito($cliente, $this->vehiculo(['modelo' => 'Modelo ' . str_pad((string) $i, 2, '0', STR_PAD_LEFT)]));
        }

        $this->actingAs($cliente)->get('/favoritos')
            ->assertViewHas('vehiculos', fn ($p) => $p->count() === 12 && $p->total() === 14);
        $this->actingAs($cliente)->get('/favoritos?page=2')
            ->assertViewHas('vehiculos', fn ($p) => $p->count() === 2);
    }

    #[Group('RF-11')]
    public function test_mis_favoritos_vacio_invita_a_ver_el_catalogo(): void
    {
        $this->actingAs($this->cliente())->get('/favoritos')
            ->assertOk()
            ->assertSee('Todavía no guardaste ningún vehículo')
            ->assertSee(route('vehiculos.index'), false);
    }

    #[Group('RF-11')]
    public function test_mis_favoritos_conserva_los_vehiculos_vendidos_con_su_estado(): void
    {
        $cliente = $this->cliente();
        $this->favorito($cliente, $this->vehiculo(['modelo' => 'Yaris', 'estado' => 'vendido']));

        $this->actingAs($cliente)->get('/favoritos')->assertSee('Yaris')->assertSee('Vendido');
    }

    // ---------------------------------------------------- Detalle y menú

    #[Group('RF-11')]
    public function test_el_detalle_invita_al_visitante_a_iniciar_sesion(): void
    {
        $v = $this->vehiculo();

        $this->get("/vehiculos/{$v->id}")
            ->assertSee('Guardar en favoritos')
            ->assertDontSee(route('favoritos.store', $v), false);
    }

    #[Group('RF-11')]
    public function test_el_detalle_ofrece_guardar_o_quitar_segun_si_ya_es_favorito(): void
    {
        $ana = $this->cliente();
        $beto = $this->cliente();
        $v = $this->vehiculo();
        $this->favorito($ana, $v);

        $this->actingAs($ana)->get("/vehiculos/{$v->id}")
            ->assertSee('Quitar de favoritos')->assertDontSee('Guardar en favoritos')
            ->assertSee(route('favoritos.destroy', $v), false);

        // Para otro cliente el mismo vehículo todavía no es favorito
        $this->actingAs($beto)->get("/vehiculos/{$v->id}")
            ->assertSee('Guardar en favoritos')->assertDontSee('Quitar de favoritos')
            ->assertSee(route('favoritos.store', $v), false);
    }

    #[Group('RF-11')]
    public function test_el_equipo_no_ve_los_botones_de_favoritos(): void
    {
        $v = $this->vehiculo();

        foreach (['empleado', 'admin'] as $rol) {
            $this->actingAs($this->{$rol}())->get("/vehiculos/{$v->id}")
                ->assertOk()->assertDontSee('Guardar en favoritos')->assertDontSee('Quitar de favoritos');
        }
    }

    #[Group('RF-11')]
    public function test_el_enlace_del_menu_lo_ve_solo_el_cliente(): void
    {
        $enlace = route('favoritos.index');

        $this->get('/vehiculos')->assertDontSee($enlace, false);
        foreach (['empleado', 'admin'] as $rol) {
            $this->actingAs($this->{$rol}())->get('/vehiculos')->assertDontSee($enlace, false);
        }
        $this->actingAs($this->cliente())->get('/vehiculos')->assertSee($enlace, false)->assertSee('Favoritos');
    }

    #[Group('RF-11')]
    public function test_un_cliente_recien_registrado_puede_guardar_favoritos(): void
    {
        $v = $this->vehiculo();

        $this->post('/registro', [
            'name' => 'Cliente Nuevo', 'email' => 'nuevo@example.com',
            'password' => 'una-clave-larga', 'password_confirmation' => 'una-clave-larga',
        ])->assertRedirect();

        $this->post("/vehiculos/{$v->id}/favorito")->assertRedirect();

        $this->assertSame(1, $this->cantidadDeFavoritos());
    }
}
