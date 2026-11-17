<?php

namespace App\Http\Controllers;

use App\Models\PrecioReferencia;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Validator;

/**
 * Tabla de precios de referencia (RF-04): consulta de solo lectura para Empleado y Admin,
 * para fundamentar una cotización al cliente.
 */
class PrecioReferenciaController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('rol:empleado,admin')];
    }

    public function index(Request $request)
    {
        // Un filtro inválido se ignora, como en el catálogo
        $validos = Validator::make($request->query(), [
            'q' => 'nullable|string|max:100',
            'anio' => 'nullable|integer|min:1900|max:2100',
        ])->valid();
        $filtros = array_filter($validos, fn ($valor) => $valor !== null && $valor !== '');

        $precios = PrecioReferencia::query()
            ->buscar($filtros['q'] ?? null)
            // Filas cuyo rango de años incluye el año pedido
            ->when(isset($filtros['anio']), fn ($q) => $q
                ->where('anio_desde', '<=', $filtros['anio'])
                ->where('anio_hasta', '>=', $filtros['anio']))
            ->orderBy('marca')->orderBy('modelo')->orderBy('anio_desde')
            ->paginate(20)
            ->withQueryString();

        return view('precios.index', [
            'precios' => $precios,
            'filtros' => $filtros,
            'total' => PrecioReferencia::count(),
        ]);
    }
}
