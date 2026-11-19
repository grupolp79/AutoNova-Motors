<?php

namespace Database\Seeders;

use App\Models\Turno;
use App\Models\User;
use App\Models\Vehiculo;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * 20 turnos de ejemplo que respetan las mismas reglas que la pantalla de turnos (RF-13):
 *   - lunes a sábado, entre Turno::HORA_APERTURA y Turno::HORA_CIERRE, cada Turno::MINUTOS_POR_TURNO minutos;
 *   - los activos (pendiente o confirmado) son futuros y solo de vehículos disponibles;
 *   - un cliente no tiene dos turnos activos del mismo vehículo, y un vehículo no tiene dos activos en el mismo horario;
 *   - hay al menos un turno de cada estado.
 * Se puede correr solo (php artisan db:seed --class=TurnoSeeder): primero borra los turnos que había.
 */
class TurnoSeeder extends Seeder
{
    // Cuántos turnos de cada estado se crean (suman 20)
    private const CANTIDADES = ['pendiente' => 6, 'confirmado' => 5, 'realizado' => 6, 'cancelado' => 3];

    public function run(): void
    {
        // Ninguna otra tabla apunta a turnos, así que se pueden borrar sin tocar el resto de los datos
        Turno::query()->delete();

        $clientes = User::where('rol', 'cliente')->pluck('id')->all();
        $disponibles = Vehiculo::where('estado', 'disponible')->pluck('id')->all();
        $todos = Vehiculo::pluck('id')->all();

        if (! $clientes || ! $todos) {
            $this->command?->warn('TurnoSeeder: faltan clientes o vehículos, no se crearon turnos.');
            return;
        }

        // Para no repetir: "cliente-vehículo" y "vehículo-horario" de los turnos activos ya creados
        $clienteVehiculo = [];
        $vehiculoHorario = [];

        foreach (self::CANTIDADES as $estado => $cantidad) {
            $activo = in_array($estado, Turno::ACTIVOS, true);
            // Realizados en el pasado; activos en el futuro; cancelados de los dos
            $futuro = $activo || ($estado === 'cancelado' && fake()->boolean());

            for ($i = 0; $i < $cantidad; $i++) {
                // Sin vehículos disponibles no se pueden crear activos: se crea un cancelado en su lugar
                $estadoFinal = ($activo && ! $disponibles) ? 'cancelado' : $estado;
                $usarActivo = $estadoFinal !== 'cancelado' && $activo;

                // Se prueba hasta encontrar una combinación que no choque con las reglas
                do {
                    $cliente = fake()->randomElement($clientes);
                    $vehiculo = fake()->randomElement($usarActivo ? $disponibles : $todos);
                    $fecha = $this->horarioValido($futuro);
                    $choca = $usarActivo && (
                        isset($clienteVehiculo["$cliente-$vehiculo"]) ||
                        isset($vehiculoHorario[$vehiculo . '-' . $fecha->format('Y-m-d H:i')])
                    );
                } while ($choca);

                if ($usarActivo) {
                    $clienteVehiculo["$cliente-$vehiculo"] = true;
                    $vehiculoHorario[$vehiculo . '-' . $fecha->format('Y-m-d H:i')] = true;
                }

                Turno::create([
                    'usuario_id' => $cliente,
                    'vehiculo_id' => $vehiculo,
                    'fecha_hora' => $fecha,
                    'estado' => $estadoFinal,
                ]);
            }
        }
    }

    /** Un día y horario de atención al azar, entre 1 y 20 días hacia adelante o hacia atrás. */
    private function horarioValido(bool $futuro): Carbon
    {
        do {
            $dias = fake()->numberBetween(1, 20);
            $dia = today()->addDays($futuro ? $dias : -$dias);
        } while (! in_array($dia->dayOfWeekIso, Turno::DIAS_ATENCION, true));

        // Todos los horarios del día: 09:00, 09:30, ... 18:00
        $horarios = [];
        $hora = $dia->copy()->setTimeFromTimeString(Turno::HORA_APERTURA);
        $cierre = $dia->copy()->setTimeFromTimeString(Turno::HORA_CIERRE);
        while ($hora <= $cierre) {
            $horarios[] = $hora->copy();
            $hora->addMinutes(Turno::MINUTOS_POR_TURNO);
        }

        return fake()->randomElement($horarios);
    }
}
