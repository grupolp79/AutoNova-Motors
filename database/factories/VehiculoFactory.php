<?php

namespace Database\Factories;

use App\Models\Vehiculo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehiculo>
 */
class VehiculoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
{
    $catalogo = [
        'Toyota'     => ['Corolla', 'Yaris', 'Hilux', 'RAV4'],
        'Volkswagen' => ['Gol', 'Polo', 'Golf', 'Amarok'],
        'Chevrolet'  => ['Onix', 'Cruze', 'Tracker', 'S10'],
        'Ford'       => ['Fiesta', 'Focus', 'Ranger', 'EcoSport'],
        'Renault'    => ['Sandero', 'Duster', 'Kwid', 'Logan'],
        'Peugeot'    => ['208', '308', '2008', 'Partner'],
        'Fiat'       => ['Argo', 'Cronos', 'Toro', 'Palio'],
        'Nissan'     => ['Versa', 'Kicks', 'Frontier', 'March'],
    ];

    $marca = fake()->randomElement(array_keys($catalogo));
    // Alrededor del 15% son 0 km: kilometraje 0 y año actual o el anterior
    $es0km = fake()->boolean(15);
    $anio = $es0km
        ? fake()->numberBetween(date('Y') - 1, date('Y'))
        : fake()->numberBetween(2012, 2025);

    return [
        'marca'       => $marca,
        'modelo'      => fake()->randomElement($catalogo[$marca]),
        'anio'        => $anio,
        'precio'      => (int) round(6000 + ($anio - 2012) * 2000 + fake()->numberBetween(0, 6000), -2),
        'kilometraje' => $es0km ? 0 : (int) round((2026 - $anio) * fake()->numberBetween(8000, 18000), -2),
        'transmision' => fake()->randomElement(['manual', 'automatica']),
        'combustible' => fake()->randomElement(['nafta', 'nafta', 'diesel', 'hibrido', 'electrico']),
        'estado'      => fake()->randomElement(['disponible', 'disponible', 'disponible', 'reservado', 'vendido']),
        'destacado'   => fake()->boolean(15),
    ];
}
}
