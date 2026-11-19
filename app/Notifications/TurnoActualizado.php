<?php

namespace App\Notifications;

use App\Models\Turno;
use Illuminate\Notifications\Notification;

/**
 * Aviso para el cliente dueño del turno: el equipo lo confirmó, lo canceló o lo marcó como realizado.
 * Solo dice el estado nuevo, el vehículo y la fecha: no lleva datos internos del equipo.
 */
class TurnoActualizado extends Notification
{
    // Título del aviso según el estado nuevo
    private const TITULOS = [
        'confirmado' => 'Tu turno está confirmado',
        'cancelado' => 'Tu turno fue cancelado',
        'realizado' => 'Gracias por tu visita',
    ];

    public function __construct(private Turno $turno)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $vehiculo = $this->turno->vehiculo;
        $estado = $this->turno->estado;

        return [
            'tipo' => 'estado_turno',
            'turno_id' => $this->turno->id,
            'estado' => $estado,
            'titulo' => self::TITULOS[$estado] ?? 'Tu turno cambió de estado',
            'texto' => 'Tu turno para ' . $vehiculo->marca . ' ' . $vehiculo->modelo . ' del '
                . $this->turno->fecha_hora->format('d/m/Y \a \l\a\s H:i') . ' ahora está ' . mb_strtolower(Turno::ESTADOS[$estado]) . '.',
        ];
    }
}
