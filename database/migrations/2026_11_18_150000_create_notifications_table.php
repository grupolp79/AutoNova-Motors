<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Avisos internos de la campanita (tabla estándar de Laravel para notificaciones por base de datos).
     * Cada fila es un aviso para un usuario: notifiable_type + notifiable_id indican a quién
     * (siempre un User), data guarda el contenido en JSON y read_at queda vacío hasta que lo lee.
     * notifiable_id no es una clave foránea (es polimórfica): la base no impide borrar el usuario,
     * pero la aplicación no borra usuarios.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
