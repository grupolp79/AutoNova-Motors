<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UsuarioSeeder extends Seeder
{
    // Contraseña de prueba para TODAS las cuentas de ejemplo (solo para desarrollo).
    // El modelo User la guarda hasheada gracias al cast 'hashed'.
    private const CLAVE_PRUEBA = 'autonova123';

    public function run(): void
    {
        // 1 admin y 2 empleados
        User::create(['name' => 'Admin AutoNova', 'email' => 'admin@autonova.test', 'password' => self::CLAVE_PRUEBA, 'rol' => 'admin']);
        User::create(['name' => 'Lucía Fernández', 'email' => 'empleado1@autonova.test', 'password' => self::CLAVE_PRUEBA, 'rol' => 'empleado']);
        User::create(['name' => 'Martín Rodríguez', 'email' => 'empleado2@autonova.test', 'password' => self::CLAVE_PRUEBA, 'rol' => 'empleado']);

        // 12 clientes
        $clientes = [
            'Sofía Pereira', 'Diego Techera', 'Valentina Acosta', 'Matías Silva',
            'Camila Rodríguez', 'Nicolás Barrios', 'Florencia Méndez', 'Agustín Cabrera',
            'Natalia Ferreira', 'Joaquín Suárez', 'Carolina Píriz', 'Santiago Olivera',
        ];

        foreach ($clientes as $i => $nombre) {
            User::create([
                'name' => $nombre,
                'email' => 'cliente' . ($i + 1) . '@correo.test',
                'password' => self::CLAVE_PRUEBA,
                'rol' => 'cliente',
            ]);
        }
    }
}
