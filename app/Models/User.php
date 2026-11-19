<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'rol',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Reglas de la contraseña, las mismas en todos lados: registro, recuperación y alta de usuarios por el Admin.
     * 72 es el máximo que admite bcrypt; 'confirmed' exige el campo password_confirmation igual.
     */
    const REGLAS_CONTRASENA = ['required', 'string', 'min:8', 'max:72', 'confirmed'];

    /**
     * Correo de "¿Olvidaste tu contraseña?". Laravel llama a este método desde Password::sendResetLink();
     * se reemplaza para mandar el texto en español (App\Notifications\RestablecerContrasena).
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Notifications\RestablecerContrasena($token));
    }

    // Roles del sistema: cliente, empleado y admin (columna users.rol)
    public function esAdmin(): bool
    {
        return $this->rol === 'admin';
    }

    public function esEmpleado(): bool
    {
        return $this->rol === 'empleado';
    }

    // Empleado y Admin pueden dar de alta y modificar vehículos
    public function puedeGestionar(): bool
    {
        return $this->esEmpleado() || $this->esAdmin();
    }

    /**
     * Vehículos que el usuario marcó como favoritos.
     * Relación muchos a muchos por la tabla 'favoritos' (no tiene modelo propio).
     * Se indican las claves porque se llaman usuario_id (no user_id) y vehiculo_id.
     * La tabla no tiene created_at/updated_at, por eso no se usa withTimestamps().
     */
    public function favoritos()
    {
        return $this->belongsToMany(Vehiculo::class, 'favoritos', 'usuario_id', 'vehiculo_id');
    }

    // Consultas (leads) que hizo el usuario cuando es cliente. La clave foránea es usuario_id.
    public function consultas()
    {
        return $this->hasMany(Lead::class, 'usuario_id');
    }

    // Turnos de prueba que pidió el usuario cuando es cliente (RF-13)
    public function turnos()
    {
        return $this->hasMany(Turno::class, 'usuario_id');
    }

    // Reseñas que dejó el usuario cuando es cliente (RF-14)
    public function resenas()
    {
        return $this->hasMany(Resena::class, 'usuario_id');
    }
}
