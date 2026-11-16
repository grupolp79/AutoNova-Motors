<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            // El cliente que consultó (users.rol = 'cliente') y el vehículo consultado.
            // restrictOnDelete: no se puede borrar un usuario o vehículo que tenga leads (se conserva el historial).
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('vehiculo_id')->constrained('vehiculos')->restrictOnDelete();
            $table->enum('estado', ['nuevo', 'contactado', 'negociando', 'cerrado'])->default('nuevo');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
