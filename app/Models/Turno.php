<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Turno para conocer o probar un vehículo (RF-13).
 * Lo pide el Cliente; Empleado y Admin lo confirman, lo cancelan o lo marcan como realizado.
 */
class Turno extends Model
{
    const ESTADOS = ['pendiente' => 'Pendiente', 'confirmado' => 'Confirmado', 'cancelado' => 'Cancelado', 'realizado' => 'Realizado'];

    // Un turno "activo" todavía va a ocurrir: ocupa el horario del vehículo y cuenta para no duplicar
    const ACTIVOS = ['pendiente', 'confirmado'];

    // Cambios de estado permitidos. Cancelado y realizado son finales (no tienen salida).
    const TRANSICIONES = [
        'pendiente' => ['confirmado', 'cancelado'],
        'confirmado' => ['realizado', 'cancelado'],
    ];

    // ---- Horario de atención para pedir turnos (cambiar acá si cambia el horario) ----
    // Días según Carbon::dayOfWeekIso: 1 = lunes ... 6 = sábado, 7 = domingo
    const DIAS_ATENCION = [1, 2, 3, 4, 5, 6];
    const HORA_APERTURA = '09:00';
    const HORA_CIERRE = '18:00';
    // Los turnos se dan cada 30 minutos (10:00, 10:30, 11:00...)
    const MINUTOS_POR_TURNO = 30;

    protected $fillable = ['usuario_id', 'vehiculo_id', 'fecha_hora', 'estado'];

    protected function casts(): array
    {
        return ['fecha_hora' => 'datetime'];
    }

    public function cliente()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function vehiculo()
    {
        return $this->belongsTo(Vehiculo::class);
    }

    /** Solo los turnos pendientes o confirmados: Turno::activos()->... */
    public function scopeActivos($query)
    {
        return $query->whereIn('estado', self::ACTIVOS);
    }

    public function estaActivo(): bool
    {
        return in_array($this->estado, self::ACTIVOS, true);
    }

    /** Estados a los que puede pasar desde el actual (vacío si ya es final). */
    public function siguientes(): array
    {
        return self::TRANSICIONES[$this->estado] ?? [];
    }

    public function puedePasarA(string $estado): bool
    {
        return in_array($estado, $this->siguientes(), true);
    }

    /**
     * Revisa que la fecha caiga en el horario de atención.
     * Devuelve null si está bien, o el texto del problema (se muestra como error del formulario).
     */
    public static function problemaDeHorario(Carbon $fecha): ?string
    {
        if (! in_array($fecha->dayOfWeekIso, self::DIAS_ATENCION, true)) {
            return 'Los turnos son de lunes a sábado. Elegí otro día.';
        }

        $hora = $fecha->format('H:i');
        // Comparar "HH:MM" como texto funciona porque siempre tienen 2 dígitos
        if ($hora < self::HORA_APERTURA || $hora > self::HORA_CIERRE) {
            return 'Los turnos son entre las ' . self::HORA_APERTURA . ' y las ' . self::HORA_CIERRE . '. Elegí otro horario.';
        }

        if ($fecha->minute % self::MINUTOS_POR_TURNO !== 0) {
            return 'Los turnos se dan cada ' . self::MINUTOS_POR_TURNO . ' minutos (por ejemplo 10:00 o 10:30).';
        }

        return null;
    }
}
