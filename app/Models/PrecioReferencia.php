<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrecioReferencia extends Model
{
    // El nombre de la tabla no sigue la convención de Laravel (sería "precio_referencias")
    protected $table = 'precios_referencia';

    protected $fillable = ['marca', 'modelo', 'anio_desde', 'anio_hasta', 'precio_referencia'];
}
