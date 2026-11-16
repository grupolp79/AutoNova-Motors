<?php

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Métodos de rol del modelo User (RF-05 y RF-10). No usan la base de datos: el usuario se arma en memoria.
 */
class UserRolTest extends TestCase
{
    #[DataProvider('roles')]
    public function test_cada_rol_tiene_los_permisos_que_le_corresponden(string $rol, bool $admin, bool $empleado, bool $gestiona): void
    {
        $usuario = User::factory()->make(['rol' => $rol]);

        $this->assertSame($admin, $usuario->esAdmin());
        $this->assertSame($empleado, $usuario->esEmpleado());
        $this->assertSame($gestiona, $usuario->puedeGestionar());
    }

    public static function roles(): array
    {
        return [
            'cliente' => ['cliente', false, false, false],
            'empleado' => ['empleado', false, true, true],
            'admin' => ['admin', true, false, true],
        ];
    }

    public function test_la_contrasena_nunca_sale_en_la_serializacion(): void
    {
        $usuario = User::factory()->make(['rol' => 'cliente']);

        $this->assertArrayNotHasKey('password', $usuario->toArray());
        $this->assertArrayNotHasKey('remember_token', $usuario->toArray());
    }
}
