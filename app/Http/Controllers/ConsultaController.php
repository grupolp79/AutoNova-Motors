<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\User;
use App\Models\Vehiculo;
use App\Notifications\ConsultaActualizada;
use App\Notifications\NuevaConsulta;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Notification;

/**
 * Consultas (leads). RF-03, RF-06 y RF-09.
 *   Cliente:          consulta por un vehículo (store) y ve sus consultas (mias).
 *   Empleado y Admin: bandeja (index), detalle con historial (show) y avance de estado (avanzar).
 * Al crear una consulta se avisa al equipo y al avanzarla se avisa al cliente (campanita, NotificacionController).
 */
class ConsultaController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('rol:cliente', only: ['store', 'mias']),
            new Middleware('rol:empleado,admin', only: ['index', 'show', 'avanzar']),
        ];
    }

    /** El cliente envía una consulta por un vehículo: se crea un lead en estado 'nuevo'. */
    public function store(Request $request, Vehiculo $vehiculo)
    {
        if ($vehiculo->estado === 'vendido') {
            return redirect()->route('vehiculos.show', $vehiculo)
                ->with('error', 'Este vehículo ya fue vendido, no se pueden enviar consultas.');
        }

        // Una sola consulta abierta por cliente y vehículo: evita duplicados
        $abierta = Lead::where('usuario_id', $request->user()->id)
            ->where('vehiculo_id', $vehiculo->id)
            ->where('estado', '!=', 'cerrado')
            ->exists();

        if ($abierta) {
            return redirect()->route('consultas.mias')
                ->with('error', 'Ya tenés una consulta abierta por este vehículo. Podés seguirla acá.');
        }

        $datos = $request->validate([
            'mensaje' => ['nullable', 'string', 'max:500'],
        ]);

        $consulta = Lead::create([
            'usuario_id' => $request->user()->id,
            'vehiculo_id' => $vehiculo->id,
            'estado' => 'nuevo',
            'mensaje' => filled($datos['mensaje'] ?? null) ? trim($datos['mensaje']) : null,
        ]);

        // Aviso en la campanita de todo el equipo (Empleado y Admin): entró una consulta nueva
        Notification::send(User::whereIn('rol', ['empleado', 'admin'])->get(), new NuevaConsulta($consulta));

        return redirect()->route('consultas.mias')
            ->with('success', 'Tu consulta fue enviada. Un empleado se va a comunicar con vos.');
    }

    /** Consultas del cliente que inició sesión. Ve el estado, pero no las notas internas del equipo. */
    public function mias(Request $request)
    {
        $consultas = $request->user()->consultas()
            ->with('vehiculo.fotos')
            ->withCount('interacciones')
            ->latest()
            ->paginate(10);

        return view('consultas.mias', ['consultas' => $consultas]);
    }

    /** Bandeja del equipo: filtro por estado y por vehículo, más cantidades por estado. */
    public function index(Request $request)
    {
        $estado = $request->query('estado');
        if (! array_key_exists($estado ?? '', Lead::ESTADOS)) {
            $estado = null;
        }

        // Filtro por vehículo (?vehiculo=ID): solo se acepta si existe
        $vehiculo = null;
        if (ctype_digit((string) $request->query('vehiculo'))) {
            $vehiculo = Vehiculo::find((int) $request->query('vehiculo'));
        }

        $consultas = Lead::query()
            ->with(['cliente:id,name,email', 'vehiculo:id,marca,modelo,anio,estado'])
            ->withCount('interacciones')
            ->when($estado, fn ($q) => $q->where('estado', $estado))
            ->when($vehiculo, fn ($q) => $q->where('vehiculo_id', $vehiculo->id))
            // Primero las que esperan respuesta: estado más nuevo del ciclo primero y, dentro, la más reciente
            ->orderByRaw("CASE estado WHEN 'nuevo' THEN 1 WHEN 'contactado' THEN 2 WHEN 'negociando' THEN 3 ELSE 4 END")
            ->latest()
            ->paginate(15)
            ->withQueryString();

        // Totales de todas las consultas (no cambian con el filtro)
        $totales = Lead::query()->selectRaw('estado, count(*) as total')->groupBy('estado')->pluck('total', 'estado');

        return view('consultas.index', [
            'consultas' => $consultas,
            'estadoActual' => $estado,
            'vehiculoFiltro' => $vehiculo,
            'totales' => $totales,
        ]);
    }

    /** Detalle de una consulta con el historial de contactos (RF-09). */
    public function show(Lead $consulta)
    {
        $consulta->load(['cliente', 'vehiculo.fotos', 'interacciones']);

        return view('consultas.show', ['consulta' => $consulta]);
    }

    /** Pasa la consulta al estado siguiente (RF-03) y actualiza el estado del vehículo (RF-06). */
    public function avanzar(Lead $consulta)
    {
        $error = $consulta->avanzar();

        if ($error !== null) {
            return redirect()->route('consultas.show', $consulta)->with('error', $error);
        }

        // Aviso en la campanita del cliente: su consulta avanzó (solo si el avance se concretó)
        $consulta->cliente->notify(ConsultaActualizada::porEstado($consulta));

        $mensaje = match ($consulta->estado) {
            'contactado' => 'Consulta marcada como Contactada.',
            'negociando' => 'Consulta en negociación.' . ($consulta->vehiculo->fresh()->estado === 'reservado' ? ' El vehículo quedó Reservado.' : ''),
            'cerrado' => 'Consulta cerrada. El vehículo pasó a Vendido.',
        };

        return redirect()->route('consultas.show', $consulta)->with('success', $mensaje);
    }
}
