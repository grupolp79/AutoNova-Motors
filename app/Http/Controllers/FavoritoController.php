<?php

namespace App\Http\Controllers;

use App\Models\Vehiculo;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Favoritos (RF-11): el Cliente marca vehículos para volver a verlos.
 * La tabla 'favoritos' es una tabla pivote con clave primaria (usuario_id, vehiculo_id),
 * por eso un mismo vehículo no puede quedar dos veces como favorito del mismo cliente.
 */
class FavoritoController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('rol:cliente')];
    }

    /** Los favoritos del cliente que tiene la sesión iniciada. */
    public function index(Request $request)
    {
        $vehiculos = $request->user()->favoritos()
            ->with('fotos')
            ->orderBy('vehiculos.marca')->orderBy('vehiculos.modelo')->orderBy('vehiculos.id')
            ->paginate(12);

        return view('favoritos.index', ['vehiculos' => $vehiculos]);
    }

    public function store(Request $request, Vehiculo $vehiculo)
    {
        // Si ya era favorito no hace nada (no da error ni lo duplica)
        $request->user()->favoritos()->syncWithoutDetaching([$vehiculo->id]);

        return redirect()->back(302, [], route('vehiculos.show', $vehiculo))
            ->with('success', "Agregaste {$vehiculo->marca} {$vehiculo->modelo} a tus favoritos.");
    }

    public function destroy(Request $request, Vehiculo $vehiculo)
    {
        $quitados = $request->user()->favoritos()->detach($vehiculo->id);

        $volver = redirect()->back(302, [], route('favoritos.index'));

        return $quitados > 0
            ? $volver->with('success', "Quitaste {$vehiculo->marca} {$vehiculo->modelo} de tus favoritos.")
            : $volver;
    }
}
