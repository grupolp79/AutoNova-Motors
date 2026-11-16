<?php

namespace Database\Seeders;

use App\Models\Turno;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Database\Seeder;

class TurnoSeeder extends Seeder
{
    public function run(): void
    {
        $clientes = User::where('rol', 'cliente')->pluck('id')->all();
        // Solo se agendan turnos para vehículos que todavía no se vendieron
        $vehiculos = Vehiculo::where('estado', '!=', 'vendido')->pluck('id')->all();

        for ($i = 0; $i < 20; $i++) {
            $fecha = now()->addDays(fake()->numberBetween(-20, 20))->setTime(fake()->numberBetween(9, 17), fake()->randomElement([0, 30]));

            // Turnos pasados: casi todos realizados; turnos futuros: pendientes o confirmados
            $estado = $fecha->isPast()
                ? fake()->randomElement(['realizado', 'realizado', 'realizado', 'cancelado'])
                : fake()->randomElement(['pendiente', 'confirmado']);

            Turno::create([
                'usuario_id' => fake()->randomElement($clientes),
                'vehiculo_id' => fake()->randomElement($vehiculos),
                'fecha_hora' => $fecha,
                'estado' => $estado,
            ]);
        }
    }
}
