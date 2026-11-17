<?php

namespace Database\Factories;

use App\Models\Vehiculo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehiculo>
 */
class VehiculoFactory extends Factory
{
    // Tipo de vehículo de cada modelo, para que la descripción tenga sentido (los que no están son compactos)
    private const TIPOS = [
        'Hilux' => 'pickup', 'Amarok' => 'pickup', 'S10' => 'pickup', 'Ranger' => 'pickup', 'Toro' => 'pickup', 'Frontier' => 'pickup',
        'RAV4' => 'suv', 'Tracker' => 'suv', 'EcoSport' => 'suv', 'Duster' => 'suv', '2008' => 'suv', 'Kicks' => 'suv',
        'Corolla' => 'sedan', 'Cruze' => 'sedan', 'Cronos' => 'sedan', 'Versa' => 'sedan', 'Logan' => 'sedan',
        'Partner' => 'utilitario',
    ];

    // Frases para armar las descripciones de ejemplo, agrupadas por tipo, condición, combustible y caja
    private const FRASES_TIPO = [
        'pickup' => [
            'Pick-up robusta, lista para el trabajo pesado y para salir a la ruta.',
            'Caja de carga amplia y buena altura para los caminos de tierra.',
            'Una camioneta que rinde igual en la obra que en la escapada del fin de semana.',
        ],
        'suv' => [
            'SUV cómoda y alta, con lugar de sobra para la familia y el equipaje.',
            'Buena posición de manejo y un baúl generoso para los viajes.',
            'Anda bien en la ciudad y en la ruta, con muy buena visibilidad.',
        ],
        'sedan' => [
            'Sedán amplio y confortable, ideal para viajes largos.',
            'Baúl grande y andar silencioso en la ruta.',
            'Cómodo para cinco personas, con muy buen confort de marcha.',
        ],
        'compacto' => [
            'Compacto, ágil y fácil de estacionar: ideal para la ciudad.',
            'Económico de mantener y muy práctico para el día a día.',
            'Chico por fuera y cómodo por dentro, perfecto para moverse en la ciudad.',
        ],
        'utilitario' => [
            'Utilitario práctico, con mucho espacio de carga para el trabajo.',
            'Puertas laterales corredizas y gran capacidad de carga.',
        ],
    ];

    private const FRASES_CONDICION = [
        '0km' => [
            'Unidad 0 km, lista para estrenar.',
            'Es 0 km: sin uso y para estrenar.',
        ],
        'usado' => [
            'Muy bien cuidado y con el service al día.',
            'Se entrega revisado y listo para transferir.',
            'Un solo dueño, siempre guardado en garaje.',
            'Usado en excelente estado general.',
        ],
    ];

    private const FRASES_COMBUSTIBLE = [
        'nafta' => ['Motor naftero de consumo moderado.', 'Motor a nafta, confiable y rendidor.'],
        'diesel' => ['Motor diésel con mucho torque y buen rendimiento en la ruta.', 'El motor diésel gasta poco en los viajes largos.'],
        'hibrido' => ['Híbrido: gasta muy poco en la ciudad y anda silencioso.', 'Motor híbrido, ideal para ahorrar combustible en el día a día.'],
        'electrico' => ['100 % eléctrico: cero combustible y mantenimiento mínimo.', 'Eléctrico, silencioso y se carga en casa.'],
    ];

    private const FRASES_CAJA = [
        'automatica' => ['Caja automática para manejar sin esfuerzo en el tránsito.', 'Con caja automática, muy cómodo en la ciudad.'],
        'manual' => ['Caja manual, simple y de mantenimiento económico.', 'Caja manual de cambios suaves.'],
    ];

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
            // Función: Laravel la ejecuta con los datos finales (incluidos los que pase un test),
            // así la descripción siempre corresponde al modelo, la caja y el combustible reales
            'descripcion' => fn (array $datos) => self::descripcion($datos),
        ];
    }

    /**
     * Arma una descripción natural con 3 frases: tipo de vehículo, condición (0 km o usado)
     * y, al azar, el combustible o la caja.
     */
    public static function descripcion(array $datos): string
    {
        $tipo = self::TIPOS[$datos['modelo']] ?? 'compacto';
        $condicion = ($datos['kilometraje'] ?? 0) == 0 ? '0km' : 'usado';

        $frases = [
            fake()->randomElement(self::FRASES_TIPO[$tipo]),
            fake()->randomElement(self::FRASES_CONDICION[$condicion]),
            fake()->boolean()
                ? fake()->randomElement(self::FRASES_COMBUSTIBLE[$datos['combustible']] ?? self::FRASES_COMBUSTIBLE['nafta'])
                : fake()->randomElement(self::FRASES_CAJA[$datos['transmision']] ?? self::FRASES_CAJA['manual']),
        ];

        return implode(' ', $frases);
    }
}
