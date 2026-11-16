<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    // Ciclo de vida del lead. Las claves coinciden con el enum de la migración.
    const ESTADOS = ['nuevo' => 'Nuevo', 'contactado' => 'Contactado', 'negociando' => 'Negociando', 'cerrado' => 'Cerrado'];

    protected $fillable = ['usuario_id', 'vehiculo_id', 'estado'];

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
}
