<?php

namespace App\Notifications;

use App\Models\Turno;
use Illuminate\Notifications\Notification;

/**
 * Aviso para el equipo (Empleado y Admin): un cliente pidió un turno para conocer o probar un vehículo.
 * Igual que NuevaConsulta: se guarda solo en la base de datos (campanita), sin colas ni correo.
 */
class NuevoTurno extends Notification
{
    public function __construct(private Turno $turno)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** Contenido guardado en notifications.data. */
    public function toArray(object $notifiable): array
    {
        $vehiculo = $this->turno->vehiculo;

        return [
            'tipo' => 'nuevo_turno',
            'turno_id' => $this->turno->id,
            'titulo' => 'Nuevo turno pedido',
            'texto' => $this->turno->cliente->name . ' pidió un turno para ' . $vehiculo->marca . ' ' . $vehiculo->modelo
                . ' el ' . $this->turno->fecha_hora->format('d/m/Y \a \l\a\s H:i') . '.',
        ];
    }
}
