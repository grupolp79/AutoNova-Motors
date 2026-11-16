<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\ArmaDatos;
use Tests\TestCase;

/**
 * Integridad de la base: claves foráneas (RESTRICT / CASCADE), unicidad y valores de los enum.
 * Corre sobre SQLite en memoria, que respeta las mismas reglas declaradas en las migraciones.
 * (Supuesto: MariaDB 10.4 las aplica igual; se verifica a mano con las consultas del documento de BD.)
 */
class IntegridadBaseTest extends TestCase
{
    use RefreshDatabase, ArmaDatos;

    /** Ejecuta $accion y devuelve el SQLSTATE del error de base, o null si no hubo error. */
    private function sqlstate(callable $accion): ?string
    {
        try {
            $accion();
        } catch (QueryException $e) {
            return $e->errorInfo[0] ?? 'sin-sqlstate';
        }

        return null;
    }

    // ----------------------------------------------------------- RESTRICT

    public function test_no_se_puede_borrar_un_vehiculo_con_leads(): void
    {
        $v = $this->vehiculo();
        $this->lead($v);

        $this->assertSame('23000', $this->sqlstate(fn () => $v->delete()));
        $this->assertDatabaseHas('vehiculos', ['id' => $v->id]);
    }

    public function test_no_se_puede_borrar_un_vehiculo_con_turnos_ni_con_resenas(): void
    {
        $conTurno = $this->vehiculo();
        $this->turno($conTurno);
        $conResena = $this->vehiculo();
        $this->resena($conResena);

        $this->assertSame('23000', $this->sqlstate(fn () => $conTurno->delete()));
        $this->assertSame('23000', $this->sqlstate(fn () => $conResena->delete()));
    }

    public function test_no_se_puede_borrar_un_usuario_con_leads(): void
    {
        $cliente = $this->cliente();
        $this->lead($this->vehiculo(), 'nuevo', null, $cliente);

        $this->assertSame('23000', $this->sqlstate(fn () => $cliente->delete()));
        $this->assertDatabaseHas('users', ['id' => $cliente->id]);
    }

    public function test_un_lead_no_puede_apuntar_a_un_vehiculo_o_usuario_inexistente(): void
    {
        $v = $this->vehiculo();
        $c = $this->cliente();

        $this->assertSame('23000', $this->sqlstate(
            fn () => Lead::create(['usuario_id' => $c->id, 'vehiculo_id' => 9999])
        ));
        $this->assertSame('23000', $this->sqlstate(
            fn () => Lead::create(['usuario_id' => 9999, 'vehiculo_id' => $v->id])
        ));
        $this->assertDatabaseCount('leads', 0);
    }

    // ------------------------------------------------------------ CASCADE

    public function test_borrar_un_lead_borra_sus_interacciones(): void
    {
        $lead = $this->lead($this->vehiculo());
        DB::table('interacciones')->insert([
            ['lead_id' => $lead->id, 'tipo' => 'llamada', 'fecha' => now(), 'comentario' => 'Primer contacto', 'created_at' => now(), 'updated_at' => now()],
            ['lead_id' => $lead->id, 'tipo' => 'correo', 'fecha' => now(), 'comentario' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);
        $this->assertDatabaseCount('interacciones', 2);

        $lead->delete();

        $this->assertDatabaseCount('interacciones', 0);
    }

    public function test_borrar_un_vehiculo_sin_historial_borra_sus_fotos_y_favoritos(): void
    {
        $v = $this->vehiculo();
        $v->fotos()->create(['ruta' => 'https://ejemplo.com/a.jpg', 'orden' => 0]);
        $this->cliente()->favoritos()->attach($v->id);

        $v->delete();

        $this->assertDatabaseCount('vehiculo_fotos', 0);
        $this->assertDatabaseCount('favoritos', 0);
    }

    public function test_borrar_un_usuario_borra_sus_favoritos_pero_no_los_vehiculos(): void
    {
        $v = $this->vehiculo();
        $c = $this->cliente();
        $c->favoritos()->attach($v->id);

        $c->delete();

        $this->assertDatabaseCount('favoritos', 0);
        $this->assertDatabaseHas('vehiculos', ['id' => $v->id]);
    }

    // ------------------------------------------------- Unicidad y valores

    public function test_el_correo_de_un_usuario_es_unico(): void
    {
        User::factory()->create(['email' => 'repetido@ejemplo.com']);

        $this->assertSame('23000', $this->sqlstate(
            fn () => User::factory()->create(['email' => 'repetido@ejemplo.com'])
        ));
    }

    public function test_un_favorito_no_se_puede_repetir(): void
    {
        $v = $this->vehiculo();
        $c = $this->cliente();
        $c->favoritos()->attach($v->id);

        // La clave primaria compuesta (usuario_id, vehiculo_id) lo impide
        $this->assertSame('23000', $this->sqlstate(fn () => $c->favoritos()->attach($v->id)));
        $this->assertDatabaseCount('favoritos', 1);
    }

    #[DataProvider('enumsInvalidos')]
    public function test_los_campos_enum_rechazan_valores_fuera_de_la_lista(string $tabla, string $campo, string $valor): void
    {
        $v = $this->vehiculo();
        $c = $this->cliente();
        $base = match ($tabla) {
            'vehiculos' => ['marca' => 'X', 'modelo' => 'Y', 'anio' => 2020, 'precio' => 1, 'kilometraje' => 0, 'transmision' => 'manual', 'combustible' => 'nafta', 'estado' => 'disponible'],
            'users' => ['name' => 'Z', 'email' => 'z@ejemplo.com', 'password' => 'x', 'rol' => 'cliente'],
            'leads' => ['usuario_id' => $c->id, 'vehiculo_id' => $v->id, 'estado' => 'nuevo'],
            'turnos' => ['usuario_id' => $c->id, 'vehiculo_id' => $v->id, 'fecha_hora' => now(), 'estado' => 'pendiente'],
            'resenas' => ['usuario_id' => $c->id, 'vehiculo_id' => $v->id, 'texto' => 't', 'estado_moderacion' => 'pendiente'],
        };

        $sqlstate = $this->sqlstate(fn () => DB::table($tabla)->insert([$campo => $valor] + $base));

        $this->assertNotNull($sqlstate, "La tabla $tabla aceptó '$valor' en $campo");
    }

    public static function enumsInvalidos(): array
    {
        return [
            'vehiculos.estado' => ['vehiculos', 'estado', 'regalado'],
            'vehiculos.transmision' => ['vehiculos', 'transmision', 'cvt'],
            'vehiculos.combustible' => ['vehiculos', 'combustible', 'gas'],
            'users.rol' => ['users', 'rol', 'superadmin'],
            'leads.estado' => ['leads', 'estado', 'perdido'],
            'turnos.estado' => ['turnos', 'estado', 'olvidado'],
            'resenas.estado_moderacion' => ['resenas', 'estado_moderacion', 'borrador'],
        ];
    }

    public function test_los_valores_por_defecto_de_las_migraciones(): void
    {
        $v = Vehiculo::create(['marca' => 'A', 'modelo' => 'B', 'anio' => 2020, 'precio' => 1, 'transmision' => 'manual', 'combustible' => 'nafta'])->fresh();
        $u = User::create(['name' => 'N', 'email' => 'n@ejemplo.com', 'password' => 'x'])->fresh();
        $lead = Lead::create(['usuario_id' => $u->id, 'vehiculo_id' => $v->id])->fresh();

        $this->assertSame('disponible', $v->estado);
        $this->assertSame(0, (int) $v->kilometraje);
        $this->assertFalse($v->destacado);
        $this->assertSame('cliente', $u->rol);
        $this->assertSame('nuevo', $lead->estado);
    }
}
