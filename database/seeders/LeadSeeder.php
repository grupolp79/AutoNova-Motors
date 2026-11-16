<?php

namespace Database\Seeders;

use App\Models\Interaccion;
use App\Models\Lead;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Database\Seeder;

class LeadSeeder extends Seeder
{
    public function run(): void
    {
        $clientes = User::where('rol', 'cliente')->pluck('id')->all();

        $comentarios = [
            'llamada' => ['Se llamó al cliente, queda en pasar por la automotora.', 'Consulta por financiación y entrega de su auto usado.', 'No atendió, se deja mensaje.'],
            'correo' => ['Se envió la ficha del vehículo con fotos adicionales.', 'El cliente pidió cotización por correo.', 'Se respondió la consulta sobre el estado del vehículo.'],
            'visita' => ['Vino a ver el vehículo, hizo la prueba de manejo.', 'Visita a la automotora, quedó conforme con el estado.', 'Segunda visita para definir la compra.'],
        ];

        foreach (Vehiculo::all() as $v) {
            // Se mantiene la coherencia entre el estado del vehículo y el de sus leads (RF-06):
            // vendido  -> tiene un lead cerrado
            // reservado -> tiene un lead negociando
            // disponible -> 0 a 2 leads abiertos (nuevo / contactado / negociando)
            $estados = match ($v->estado) {
                'vendido' => ['cerrado'],
                'reservado' => ['negociando'],
                default => array_map(
                    fn () => fake()->randomElement(['nuevo', 'nuevo', 'contactado', 'negociando']),
                    array_fill(0, fake()->numberBetween(0, 2), null)
                ),
            };

            foreach ($estados as $estado) {
                $creado = now()->subDays(fake()->numberBetween(7, 60));

                $lead = new Lead([
                    'usuario_id' => fake()->randomElement($clientes),
                    'vehiculo_id' => $v->id,
                    'estado' => $estado,
                ]);
                $lead->created_at = $creado;
                $lead->updated_at = $creado;
                $lead->save();

                // Cuanto más avanzado el lead, más interacciones tiene en su historial
                $cantidad = match ($estado) {
                    'nuevo' => 0,
                    'contactado' => fake()->numberBetween(1, 2),
                    'negociando' => fake()->numberBetween(2, 4),
                    'cerrado' => fake()->numberBetween(3, 5),
                };

                for ($i = 0; $i < $cantidad; $i++) {
                    $tipo = fake()->randomElement(['llamada', 'correo', 'visita']);

                    Interaccion::create([
                        'lead_id' => $lead->id,
                        'tipo' => $tipo,
                        // Las interacciones ocurren después de crearse el lead, nunca en el futuro
                        'fecha' => $creado->copy()->addDays($i + 1)->setTime(fake()->numberBetween(9, 18), 0),
                        'comentario' => fake()->randomElement($comentarios[$tipo]),
                    ]);
                }
            }
        }
    }
}
