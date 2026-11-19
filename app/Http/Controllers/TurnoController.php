<?php

namespace App\Http\Controllers;

use App\Models\Turno;
use App\Models\User;
use App\Models\Vehiculo;
use App\Notifications\NuevoTurno;
use App\Notifications\TurnoActualizado;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Turnos de prueba (RF-13).
 *   Cliente:          pide un turno desde el detalle (store), ve los suyos (mios) y los cancela (cancelar).
 *   Empleado y Admin: agenda con filtro por estado (index) y cambio de estado (cambiarEstado).
 * Al pedir un turno se avisa al equipo; cuando el equipo le cambia el estado, se avisa al cliente.
 */
class TurnoController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('rol:cliente', only: ['store', 'mios', 'cancelar']),
            new Middleware('rol:empleado,admin', only: ['index', 'cambiarEstado']),
        ];
    }

    /** El cliente pide un turno para un vehículo. Nace en estado 'pendiente'. */
    public function store(Request $request, Vehiculo $vehiculo)
    {
        if ($vehiculo->estado !== 'disponible') {
            return redirect()->route('vehiculos.show', $vehiculo)
                ->with('error', 'Solo se pueden pedir turnos de vehículos disponibles.');
        }

        $request->validate([
            // datetime-local manda la fecha así: 2026-11-20T10:30
            'fecha_hora' => ['required', 'date_format:Y-m-d\TH:i', 'after:now'],
        ], [
            'fecha_hora.required' => 'Elegí la fecha y la hora del turno.',
            'fecha_hora.date_format' => 'La fecha y hora no tienen un formato válido. Elegila de nuevo en el calendario.',
            'fecha_hora.after' => 'La fecha del turno tiene que ser futura.',
        ]);

        $fecha = Carbon::createFromFormat('Y-m-d\TH:i', $request->input('fecha_hora'))->startOfMinute();

        // Día y horario de atención (los valores están en el modelo Turno)
        if ($problema = Turno::problemaDeHorario($fecha)) {
            return back()->withErrors(['fecha_hora' => $problema])->withInput();
        }

        $cliente = $request->user();

        // Transacción con la fila del vehículo bloqueada: si dos clientes piden el mismo horario a la vez,
        // el segundo espera al primero y después ve que el horario ya está ocupado.
        $resultado = DB::transaction(function () use ($vehiculo, $cliente, $fecha) {
            Vehiculo::lockForUpdate()->find($vehiculo->id);

            // Un cliente no puede tener dos turnos activos para el mismo vehículo
            if (Turno::activos()->where('usuario_id', $cliente->id)->where('vehiculo_id', $vehiculo->id)->exists()) {
                return 'ya_tiene';
            }

            // Dos turnos activos del mismo vehículo no pueden caer en el mismo horario
            if (Turno::activos()->where('vehiculo_id', $vehiculo->id)->where('fecha_hora', $fecha)->exists()) {
                return 'ocupado';
            }

            return Turno::create([
                'usuario_id' => $cliente->id,
                'vehiculo_id' => $vehiculo->id,
                'fecha_hora' => $fecha,
                'estado' => 'pendiente',
            ]);
        });

        if ($resultado === 'ya_tiene') {
            return redirect()->route('turnos.mios')
                ->with('error', 'Ya tenés un turno activo para este vehículo. Si querés otro horario, cancelá el que tenés y pedí uno nuevo.');
        }
        if ($resultado === 'ocupado') {
            return back()->withErrors(['fecha_hora' => 'Ese horario ya está tomado para este vehículo. Elegí otro.'])->withInput();
        }

        // Aviso en la campanita de todo el equipo
        Notification::send(User::whereIn('rol', ['empleado', 'admin'])->get(), new NuevoTurno($resultado));

        return redirect()->route('turnos.mios')
            ->with('success', 'Pediste el turno. Te avisamos cuando el equipo lo confirme.');
    }

    /** Turnos del cliente que inició sesión, del más lejano en el calendario al más viejo. */
    public function mios(Request $request)
    {
        $turnos = Turno::where('usuario_id', $request->user()->id)
            ->with('vehiculo.fotos')
            ->orderByDesc('fecha_hora')
            ->paginate(10);

        return view('turnos.mios', ['turnos' => $turnos]);
    }

    /** El cliente cancela un turno suyo (solo si está pendiente o confirmado). */
    public function cancelar(Request $request, Turno $turno)
    {
        // Un cliente no puede tocar el turno de otro
        if ((int) $turno->usuario_id !== (int) $request->user()->id) {
            abort(403, 'Este turno no es tuyo.');
        }

        if (! $turno->puedePasarA('cancelado')) {
            return redirect()->route('turnos.mios')
                ->with('error', 'Este turno ya está ' . mb_strtolower(Turno::ESTADOS[$turno->estado]) . ' y no se puede cancelar.');
        }

        $turno->update(['estado' => 'cancelado']);

        return redirect()->route('turnos.mios')->with('success', 'Cancelaste el turno.');
    }

    /** Agenda del equipo: todos los turnos ordenados por fecha, con filtro por estado. */
    public function index(Request $request)
    {
        $estado = $request->query('estado');
        if (! array_key_exists($estado ?? '', Turno::ESTADOS)) {
            $estado = null;
        }

        $turnos = Turno::query()
            ->with(['cliente:id,name,email', 'vehiculo:id,marca,modelo,anio,estado'])
            ->when($estado, fn ($q) => $q->where('estado', $estado))
            ->orderBy('fecha_hora')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        // Cantidad por estado de todos los turnos (no cambia con el filtro)
        $totales = Turno::query()->selectRaw('estado, count(*) as total')->groupBy('estado')->pluck('total', 'estado');

        return view('turnos.index', [
            'turnos' => $turnos,
            'estadoActual' => $estado,
            'totales' => $totales,
        ]);
    }

    /** El equipo confirma, cancela o marca como realizado un turno. Solo se aceptan las transiciones válidas. */
    public function cambiarEstado(Request $request, Turno $turno)
    {
        $request->validate([
            'estado' => ['required', 'in:' . implode(',', array_keys(Turno::ESTADOS))],
        ], [
            'estado.required' => 'Falta el estado nuevo del turno.',
            'estado.in' => 'El estado elegido no es válido.',
        ]);

        $nuevo = $request->input('estado');
        $volver = redirect()->back(302, [], route('turnos.index'));

        if (! $turno->puedePasarA($nuevo)) {
            return $volver->with('error', 'Un turno ' . mb_strtolower(Turno::ESTADOS[$turno->estado])
                . ' no puede pasar a ' . mb_strtolower(Turno::ESTADOS[$nuevo]) . '.');
        }

        $turno->update(['estado' => $nuevo]);

        // Aviso en la campanita del cliente dueño del turno
        $turno->cliente->notify(new TurnoActualizado($turno));

        return $volver->with('success', 'El turno quedó ' . mb_strtolower(Turno::ESTADOS[$nuevo]) . '.');
    }
}
