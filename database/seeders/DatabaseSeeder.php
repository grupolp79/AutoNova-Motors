<?php

namespace Database\Seeders;

use App\Models\Vehiculo;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        Vehiculo::factory(40)->create()->each(function (Vehiculo $v) {
            $cantidad = fake()->numberBetween(2, 4);

            for ($i = 1; $i <= $cantidad; $i++) {
                // Fotos reales de Commons (las mismas de FotosVehiculosSeeder); si el modelo no está, una de relleno
                $ruta = FotosVehiculosSeeder::urlFoto($v->marca, $v->modelo, $i - 1)
                    ?? 'https://placehold.co/800x500?text=' . urlencode("{$v->marca} {$v->modelo} {$i}");

                $v->fotos()->create([
                    'ruta'  => $ruta,
                    'orden' => $i,
                ]);
            }
        });

        // El orden importa: los leads necesitan usuarios y vehículos; las reseñas necesitan leads cerrados
        $this->call([
            UsuarioSeeder::class,
            LeadSeeder::class,
            TurnoSeeder::class,
            ResenaSeeder::class,
            FavoritoSeeder::class,
            PrecioReferenciaSeeder::class,
        ]);
    }
}
