<?php

namespace App\Http\Controllers;

use App\Models\Vehiculo;
use Illuminate\Http\Request;

/**
 * Comparador (RF-08): cualquiera (también el visitante) elige entre 2 y 3 vehículos y los ve lado a lado.
 * La selección se guarda en la sesión como una lista de ids (clave 'comparar'): no usa tablas.
 * No lleva middleware de rol porque el catálogo es público; los formularios igual llevan @csrf.
 */
class ComparadorController extends Controller
{
    // Clave de la sesión y cantidades permitidas
    const SESION = 'comparar';
    const MAXIMO = 3;
    const MINIMO = 2;

    /** Ids elegidos en la sesión actual (lo usan también las vistas para el botón y el contador). */
    public static function elegidos(): array
    {
        return session(self::SESION, []);
    }

    /** Pantalla de comparación lado a lado. */
    public function index(Request $request)
    {
        $ids = self::elegidos();

        // Si un vehículo elegido se eliminó después, simplemente no viene en la consulta
        $vehiculos = Vehiculo::with('fotos')->whereIn('id', $ids)->get()
            // Se respeta el orden en que se fueron agregando
            ->sortBy(fn ($v) => array_search($v->id, $ids))
            ->values();

        // Se limpia la sesión de los ids que ya no existen, así el contador queda bien
        $request->session()->put(self::SESION, $vehiculos->pluck('id')->all());

        return view('comparador.index', [
            'vehiculos' => $vehiculos,
            'mejores' => $this->mejores($vehiculos),
        ]);
    }

    public function agregar(Request $request, Vehiculo $vehiculo)
    {
        $ids = self::elegidos();
        $nombre = "{$vehiculo->marca} {$vehiculo->modelo}";
        $volver = redirect()->back(302, [], route('vehiculos.show', $vehiculo));

        // No se repiten vehículos
        if (in_array($vehiculo->id, $ids)) {
            return $volver->with('success', "{$nombre} ya estaba en la comparación.");
        }

        // Tope de 3
        if (count($ids) >= self::MAXIMO) {
            return $volver->with('error', 'Ya elegiste ' . self::MAXIMO . ' vehículos para comparar, que es el máximo. Quitá uno antes de agregar otro.');
        }

        $ids[] = $vehiculo->id;
        $request->session()->put(self::SESION, $ids);

        return $volver->with('success', "Agregaste {$nombre} a la comparación.");
    }

    /** Recibe el id (no el modelo) para poder quitar también un vehículo que ya se eliminó. */
    public function quitar(Request $request, int $id)
    {
        $ids = array_values(array_diff(self::elegidos(), [$id]));
        $request->session()->put(self::SESION, $ids);

        return redirect()->back(302, [], route('comparador.index'))
            ->with('success', 'Quitaste el vehículo de la comparación.');
    }

    public function vaciar(Request $request)
    {
        $request->session()->forget(self::SESION);

        return redirect()->route('vehiculos.index')->with('success', 'Vaciaste la comparación.');
    }

    /**
     * Mejor valor de cada dato que se marca en la tabla: menor precio, menor kilometraje y año más nuevo.
     * Si todos tienen el mismo valor no se marca ninguno (no hay uno mejor que otro).
     * Devuelve, por ejemplo, ['precio' => 15000, 'kilometraje' => null, 'anio' => 2022].
     */
    private function mejores($vehiculos): array
    {
        $mejor = function (string $campo, bool $menor) use ($vehiculos) {
            $valores = $vehiculos->pluck($campo)->map(fn ($v) => (float) $v);
            if ($valores->count() < self::MINIMO || $valores->min() == $valores->max()) {
                return null;
            }

            return $menor ? $valores->min() : $valores->max();
        };

        return [
            'precio' => $mejor('precio', true),
            'kilometraje' => $mejor('kilometraje', true),
            'anio' => $mejor('anio', false),
        ];
    }
}
