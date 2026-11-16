<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Turno extends Model
{
    const ESTADOS = ['pendiente' => 'Pendiente', 'confirmado' => 'Confirmado', 'cancelado' => 'Cancelado', 'realizado' => 'Realizado'];

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
}
