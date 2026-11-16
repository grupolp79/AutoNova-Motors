<?php

namespace Database\Seeders;

use App\Models\Lead;
use App\Models\Resena;
use Illuminate\Database\Seeder;

class ResenaSeeder extends Seeder
{
    public function run(): void
    {
        $textos = [
            'Muy buena atención, el auto estaba tal cual como en las fotos.',
            'Compra sin problemas, me ayudaron con todos los papeles.',
            'Excelente trato y el vehículo en muy buen estado.',
            'Tardaron un poco en responder pero la compra salió bien.',
            'Recomendable, volvería a comprar en esta automotora.',
        ];

        // RF-14: solo puede reseñar quien cerró una compra. Se parte de los leads cerrados
        // y alrededor del 70% de esos clientes dejó su reseña.
        foreach (Lead::where('estado', 'cerrado')->get() as $lead) {
            if (! fake()->boolean(70)) {
                continue;
            }

            Resena::create([
                'usuario_id' => $lead->usuario_id,
                'vehiculo_id' => $lead->vehiculo_id,
                'texto' => fake()->randomElement($textos),
                'estado_moderacion' => fake()->randomElement(['aprobada', 'aprobada', 'aprobada', 'pendiente', 'rechazada']),
            ]);
        }
    }
}
