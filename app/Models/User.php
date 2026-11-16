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
     * Vehículos que el usuario marcó como favoritos.
     * Relación muchos a muchos por la tabla 'favoritos' (no tiene modelo propio).
     * Se indican las claves porque se llaman usuario_id (no user_id) y vehiculo_id.
     * La tabla no tiene created_at/updated_at, por eso no se usa withTimestamps().
     */
    public function favoritos()
    {
        return $this->belongsToMany(Vehiculo::class, 'favoritos', 'usuario_id', 'vehiculo_id');
    }
}
