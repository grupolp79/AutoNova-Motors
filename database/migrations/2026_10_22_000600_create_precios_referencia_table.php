<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabla de precios de referencia que mantiene el equipo a mano (RF-04).
        // Un precio vale para un rango de años de una marca y modelo.
        Schema::create('precios_referencia', function (Blueprint $table) {
            $table->id();
            $table->string('marca', 60);
            $table->string('modelo', 60);
            $table->unsignedSmallInteger('anio_desde');
            $table->unsignedSmallInteger('anio_hasta');
            $table->decimal('precio_referencia', 10, 2);
            $table->timestamps();

            $table->index(['marca', 'modelo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('precios_referencia');
    }
};
