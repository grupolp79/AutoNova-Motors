<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Interaccion extends Model
{
    // Laravel pluralizaría "Interaccion" como "interaccions": se indica el nombre real de la tabla
    protected $table = 'interacciones';

    const TIPOS = ['llamada' => 'Llamada', 'correo' => 'Correo', 'visita' => 'Visita'];

    protected $fillable = ['lead_id', 'tipo', 'fecha', 'comentario'];

    protected function casts(): array
    {
        return ['fecha' => 'datetime'];
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }
}
