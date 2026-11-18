<?php

namespace App\Http\Controllers;

use App\Models\Interaccion;
use App\Models\Lead;
use App\Notifications\ConsultaActualizada;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;

/**
 * Historial de contactos de una consulta (RF-09): llamadas, correos y visitas.
 * Lo cargan el Empleado y el Admin; el cliente no ve las notas internas.
 */
class InteraccionController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('rol:empleado,admin')];
    }

    public function store(Request $request, Lead $consulta)
    {
        // Una consulta cerrada es el registro de una venta: no se le agregan contactos
        if ($consulta->estaCerrado()) {
            return redirect()->route('consultas.show', $consulta)
                ->with('error', 'La consulta está cerrada, no se pueden agregar contactos.');
        }

        $datos = $request->validate([
            'tipo' => ['required', Rule::in(array_keys(Interaccion::TIPOS))],
            // Si no se indica la fecha se usa el momento actual; no puede ser futura
            'fecha' => ['nullable', 'date', 'before_or_equal:now'],
            'comentario' => ['nullable', 'string', 'max:500'],
        ]);

        $consulta->interacciones()->create([
            'tipo' => $datos['tipo'],
            'fecha' => $datos['fecha'] ?? now(),
            'comentario' => filled($datos['comentario'] ?? null) ? trim($datos['comentario']) : null,
        ]);

        // Aviso en la campanita del cliente: el equipo se comunicó (sin el comentario, que es interno)
        $consulta->cliente->notify(ConsultaActualizada::porContacto($consulta, $datos['tipo']));

        return redirect()->route('consultas.show', $consulta)
            ->with('success', 'Contacto registrado en el historial.');
    }
}
