<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'nombre_gva',
        'ccaa_id',
        'colectivo_id',
        'direccion_origen',
        'lat_origen',
        'lng_origen',
        'preferencias_filtro',
        'notificaciones_email',
        'avatar_url',
        'locale',
        'is_admin',
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
            'preferencias_filtro' => 'array',
            'notificaciones_email' => 'boolean',
            'lat_origen' => 'decimal:8',
            'lng_origen' => 'decimal:8',
            'is_admin' => 'boolean',
        ];
    }

    public function lists(): HasMany
    {
        return $this->hasMany(UserList::class);
    }

    public function ccaa(): BelongsTo
    {
        return $this->belongsTo(Ccaa::class);
    }

    public function colectivo(): BelongsTo
    {
        return $this->belongsTo(Colectivo::class);
    }

    public function especialidades(): HasMany
    {
        return $this->hasMany(UserEspecialidad::class);
    }

    public function historial(): HasMany
    {
        return $this->hasMany(UserHistorial::class);
    }

    public function valoraciones(): HasMany
    {
        return $this->hasMany(CentroValoracion::class);
    }

    public function anuncios(): HasMany
    {
        return $this->hasMany(TablonAnuncio::class);
    }
}
