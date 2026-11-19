<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Resena;
use App\Models\User;
use App\Notifications\NuevaResena;
use App\Notifications\ResenaModerada;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Notification;

/**
 * Reseñas con moderación (RF-14).
 *   Cliente: deja una reseña desde una consulta suya cerrada (create y store), es decir, sobre un auto que compró.
 *   Admin:   pantalla de moderación (index) donde aprueba o rechaza (moderar). El Empleado no entra.
 * En el detalle del vehículo solo se muestran las aprobadas (vista resenas/_lista).
 */
class ResenaController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('rol:cliente', only: ['create', 'store']),
            new Middleware('rol:admin', only: ['index', 'moderar']),
        ];
    }

    /** Formulario para dejar la reseña de una compra. */
    public function create(Request $request, Lead $consulta)
    {
        if ($error = $this->motivoParaNoResenar($request->user(), $consulta)) {
            return redirect()->route('consultas.mias')->with('error', $error);
        }

        $consulta->load('vehiculo.fotos');

        return view('resenas.create', ['consulta' => $consulta]);
    }

    /** Guarda la reseña como 'pendiente' y avisa a los Admin. */
    public function store(Request $request, Lead $consulta)
    {
        if ($error = $this->motivoParaNoResenar($request->user(), $consulta)) {
            return redirect()->route('consultas.mias')->with('error', $error);
        }

        $datos = $request->validate([
            'texto' => ['required', 'string', 'min:' . Resena::TEXTO_MIN, 'max:' . Resena::TEXTO_MAX],
        ], [
            'texto.required' => 'Escribí tu reseña antes de enviarla.',
            'texto.min' => 'La reseña tiene que tener al menos :min caracteres. Contanos un poco más.',
            'texto.max' => 'La reseña puede tener hasta :max caracteres. Acortala un poco y volvé a enviarla.',
        ]);

        $resena = Resena::create([
            'usuario_id' => $request->user()->id,
            'vehiculo_id' => $consulta->vehiculo_id,
            'texto' => trim($datos['texto']),
            'estado_moderacion' => 'pendiente',
        ]);

        // Aviso en la campanita de los Admin (son los únicos que moderan)
        Notification::send(User::where('rol', 'admin')->get(), new NuevaResena($resena));

        return redirect()->route('consultas.mias')
            ->with('success', '¡Gracias por tu reseña! Se va a ver en la página del vehículo cuando el administrador la apruebe.');
    }

    /** Pantalla de moderación: primero las pendientes y, dentro de cada grupo, las más nuevas. */
    public function index()
    {
        $resenas = Resena::query()
            ->with(['cliente:id,name,email', 'vehiculo:id,marca,modelo,anio'])
            ->orderByRaw("CASE estado_moderacion WHEN 'pendiente' THEN 1 ELSE 2 END")
            ->latest()
            ->orderByDesc('id')
            ->paginate(15);

        $totales = Resena::query()->selectRaw('estado_moderacion, count(*) as total')
            ->groupBy('estado_moderacion')->pluck('total', 'estado_moderacion');

        return view('resenas.index', ['resenas' => $resenas, 'totales' => $totales]);
    }

    /** El Admin aprueba o rechaza una reseña pendiente. Una reseña ya moderada no cambia más. */
    public function moderar(Request $request, Resena $resena)
    {
        $request->validate([
            'estado' => ['required', 'in:aprobada,rechazada'],
        ], [
            'estado.required' => 'Elegí si la reseña se aprueba o se rechaza.',
            'estado.in' => 'Una reseña solo se puede aprobar o rechazar.',
        ]);

        $volver = redirect()->back(302, [], route('resenas.index'));

        if (! $resena->estaPendiente()) {
            return $volver->with('error', 'Esta reseña ya fue ' . mb_strtolower(Resena::MODERACION[$resena->estado_moderacion]) . ' y no se puede cambiar.');
        }

        $resena->update(['estado_moderacion' => $request->input('estado')]);

        // Aviso en la campanita del cliente que la escribió
        $resena->cliente->notify(new ResenaModerada($resena));

        return $volver->with('success', $resena->estado_moderacion === 'aprobada'
            ? 'Reseña aprobada: ya se ve en la página del vehículo.'
            : 'Reseña rechazada: no se va a publicar.');
    }

    /**
     * Revisa si el cliente puede reseñar la compra de esta consulta.
     * Devuelve null si puede, o el motivo. Que la consulta sea de otro cliente da 403.
     */
    private function motivoParaNoResenar(User $cliente, Lead $consulta): ?string
    {
        if ((int) $consulta->usuario_id !== (int) $cliente->id) {
            abort(403, 'Esta consulta no es tuya.');
        }

        // Solo reseña quien compró: la consulta tiene que estar cerrada
        if (! $consulta->estaCerrado()) {
            return 'Podés dejar una reseña cuando se cierre la compra del vehículo.';
        }

        // Una sola reseña por cliente y vehículo
        $yaExiste = Resena::where('usuario_id', $cliente->id)->where('vehiculo_id', $consulta->vehiculo_id)->exists();
        if ($yaExiste) {
            return 'Ya dejaste una reseña para este vehículo.';
        }

        return null;
    }
}
