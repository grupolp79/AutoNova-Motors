<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class Lead extends Model
{
    // Ciclo de vida del lead. Las claves coinciden con el enum de la migración.
    const ESTADOS = ['nuevo' => 'Nuevo', 'contactado' => 'Contactado', 'negociando' => 'Negociando', 'cerrado' => 'Cerrado'];

    // Cada estado avanza solo al siguiente: no se puede saltear pasos ni volver atrás. 'cerrado' es el final.
    const SIGUIENTE = ['nuevo' => 'contactado', 'contactado' => 'negociando', 'negociando' => 'cerrado'];

    protected $fillable = ['usuario_id', 'vehiculo_id', 'estado', 'mensaje'];

    // El cliente que hizo la consulta (la FK se llama usuario_id, no user_id)
    public function cliente()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function vehiculo()
    {
        return $this->belongsTo(Vehiculo::class);
    }

    public function interacciones()
    {
        return $this->hasMany(Interaccion::class)->orderBy('fecha');
    }

    // Un lead cerrado es una venta concretada: queda como historial y no se modifica más
    public function estaCerrado(): bool
    {
        return $this->estado === 'cerrado';
    }

    /** Estado al que pasa si se lo avanza (null si ya está cerrado). */
    public function siguienteEstado(): ?string
    {
        return self::SIGUIENTE[$this->estado] ?? null;
    }

    /**
     * Pasa el lead al estado siguiente y mantiene coherente el estado del vehículo (RF-06):
     *   negociando -> el vehículo queda 'reservado' (si estaba disponible)
     *   cerrado    -> el vehículo queda 'vendido'
     * Todo en una transacción: si algo falla no queda el lead avanzado con el vehículo sin actualizar.
     * No se puede avanzar un lead si el vehículo ya figura como vendido (evita vender dos veces el mismo auto).
     *
     * Devuelve null si salió bien, o el texto del motivo si no se pudo.
     */
    public function avanzar(): ?string
    {
        $siguiente = $this->siguienteEstado();
        if ($siguiente === null) {
            return 'La consulta ya está cerrada.';
        }

        return DB::transaction(function () use ($siguiente) {
            // lockForUpdate bloquea la fila del vehículo: dos empleados no pueden cerrar la venta a la vez
            $vehiculo = Vehiculo::lockForUpdate()->findOrFail($this->vehiculo_id);

            if ($vehiculo->estado === 'vendido') {
                return 'El vehículo ya figura como vendido, así que esta consulta no se puede avanzar.';
            }

            $this->estado = $siguiente;
            $this->save();

            if ($siguiente === 'negociando' && $vehiculo->estado === 'disponible') {
                $vehiculo->update(['estado' => 'reservado']);
            }
            if ($siguiente === 'cerrado') {
                $vehiculo->update(['estado' => 'vendido']);
            }

            return null;
        });
    }

    /**
     * Ventas por mes para el reporte gerencial.
     * Una venta es un lead en estado 'cerrado'; su mes es el de updated_at (la tabla no tiene
     * una columna aparte para la fecha de cierre) y su monto es el precio publicado del vehículo.
     *
     * Devuelve $meses filas, del más viejo al más nuevo (el último es el mes de $hasta).
     * Cada fila compara contra el mes anterior: variacion_ventas / variacion_monto (diferencia)
     * y variacion_ventas_pct / variacion_monto_pct (null si el mes anterior no tuvo ventas).
     * Se agrupa en PHP y no con funciones de fecha de SQL, para que ande igual en MariaDB y SQLite.
     */
    public static function ventasPorMes(int $meses = 6, ?Carbon $hasta = null): Collection
    {
        $ultimo = ($hasta ?? now())->copy()->startOfMonth();
        // Se trae un mes más para poder comparar el primero con el anterior
        $desde = $ultimo->copy()->subMonths($meses);

        $cerrados = static::with('vehiculo:id,precio')
            ->where('estado', 'cerrado')
            ->where('updated_at', '>=', $desde)
            ->where('updated_at', '<', $ultimo->copy()->addMonth())
            ->get()
            ->groupBy(fn (Lead $lead) => $lead->updated_at->format('Y-m'));

        $filas = collect();
        for ($i = $meses; $i >= 0; $i--) {
            $mes = $ultimo->copy()->subMonths($i);
            $grupo = $cerrados->get($mes->format('Y-m'), collect());

            $filas->push([
                'mes' => $mes,
                'ventas' => $grupo->count(),
                'monto' => (float) $grupo->sum(fn (Lead $lead) => $lead->vehiculo->precio),
            ]);
        }

        $pct = fn ($actual, $anterior) => $anterior > 0 ? round(($actual - $anterior) / $anterior * 100, 1) : null;

        return $filas->map(function (array $fila, int $i) use ($filas, $pct) {
            $ant = $i > 0 ? $filas[$i - 1] : ['ventas' => 0, 'monto' => 0.0];

            return $fila + [
                'variacion_ventas' => $fila['ventas'] - $ant['ventas'],
                'variacion_ventas_pct' => $pct($fila['ventas'], $ant['ventas']),
                'variacion_monto' => $fila['monto'] - $ant['monto'],
                'variacion_monto_pct' => $pct($fila['monto'], $ant['monto']),
            ];
        })->slice(1)->values();
    }
}
