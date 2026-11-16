<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vehiculos', function (Blueprint $table) {
            $table->id();
            $table->string('marca', 60);
            $table->string('modelo', 60);
            $table->unsignedSmallInteger('anio');
            $table->decimal('precio', 10, 2);
            $table->unsignedInteger('kilometraje')->default(0);
            $table->enum('transmision', ['manual', 'automatica']);
            $table->enum('combustible', ['nafta', 'diesel', 'hibrido', 'electrico']);
            $table->enum('estado', ['disponible', 'reservado', 'vendido'])->default('disponible');
            $table->boolean('destacado')->default(false);
            $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehiculos');
    }
};
