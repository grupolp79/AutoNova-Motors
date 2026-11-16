<?php

namespace Database\Seeders;

use App\Models\PrecioReferencia;
use Illuminate\Database\Seeder;

class PrecioReferenciaSeeder extends Seeder
{
    public function run(): void
    {
        // Mismas marcas y modelos que genera VehiculoFactory
        $catalogo = [
            'Toyota' => ['Corolla', 'Yaris', 'Hilux', 'RAV4'],
            'Volkswagen' => ['Gol', 'Polo', 'Golf', 'Amarok'],
            'Chevrolet' => ['Onix', 'Cruze', 'Tracker', 'S10'],
            'Ford' => ['Fiesta', 'Focus', 'Ranger', 'EcoSport'],
            'Renault' => ['Sandero', 'Duster', 'Kwid', 'Logan'],
            'Peugeot' => ['208', '308', '2008', 'Partner'],
            'Fiat' => ['Argo', 'Cronos', 'Toro', 'Palio'],
            'Nissan' => ['Versa', 'Kicks', 'Frontier', 'March'],
        ];

        foreach ($catalogo as $marca => $modelos) {
            foreach ($modelos as $modelo) {
                // Precio base estable para cada modelo (siempre da lo mismo al volver a sembrar)
                $base = 7000 + (crc32($marca . $modelo) % 90) * 100;

                // Dos rangos de años por modelo: los más viejos valen menos
                PrecioReferencia::create(['marca' => $marca, 'modelo' => $modelo, 'anio_desde' => 2012, 'anio_hasta' => 2017, 'precio_referencia' => $base]);
                PrecioReferencia::create(['marca' => $marca, 'modelo' => $modelo, 'anio_desde' => 2018, 'anio_hasta' => 2026, 'precio_referencia' => round($base * 1.7, -2)]);
            }
        }
    }
}
