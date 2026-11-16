<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FavoritoSeeder extends Seeder
{
    public function run(): void
    {
        $vehiculos = Vehiculo::pluck('id')->all();

        // Cada cliente marca entre 2 y 5 vehículos distintos
        foreach (User::where('rol', 'cliente')->pluck('id') as $clienteId) {
            $elegidos = fake()->randomElements($vehiculos, fake()->numberBetween(2, 5));

            foreach ($elegidos as $vehiculoId) {
                // La tabla no tiene modelo (es solo una tabla pivote), se inserta directo
                DB::table('favoritos')->insert(['usuario_id' => $clienteId, 'vehiculo_id' => $vehiculoId]);
            }
        }
    }
}
