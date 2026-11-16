<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interacciones', function (Blueprint $table) {
            $table->id();
            // Historial de contactos de un lead: si se borra el lead, se borran sus interacciones.
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->enum('tipo', ['llamada', 'correo', 'visita']);
            $table->dateTime('fecha');
            $table->text('comentario')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interacciones');
    }
};
