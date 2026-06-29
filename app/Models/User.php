<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Policies\FeaturePolicy;
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
        'terms_accepted_at',
        'modo_activo',
        'ccaa_preferidas',
        'onboarding_completed',
        'last_active_at',
        'trial_ends_at',
        // 'storage_used_bytes' is NOT mass-assignable; it is maintained by the
        // document upload/delete flow via increment()/decrement().
        // 'is_admin' is intentionally NOT mass-assignable (privilege escalation).
        // 'role', 'plan', 'plan_status', 'plan_expires_at', 'stripe_customer_id'
        // and 'stripe_subscription_id' are also intentionally NOT mass-assignable;
        // they are billing/authorization fields set explicitly (forceFill / webhook).
    ];

    /**
     * Default attribute values (mirror the migration defaults) so freshly
     * instantiated models behave correctly before a database reload.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => 'user',
        'plan' => 'free',
        'plan_status' => 'none',
        'modo_activo' => 'bolsa',
        'onboarding_completed' => false,
        'storage_used_bytes' => 0,
        'storage_limit_bytes' => 2147483648, // 2 GB
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'stripe_customer_id',
        'stripe_subscription_id',
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
            'ccaa_preferidas' => 'array',
            'onboarding_completed' => 'boolean',
            'plan_expires_at' => 'datetime',
            'last_active_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'suspended_at' => 'datetime',
            'storage_used_bytes' => 'integer',
            'storage_limit_bytes' => 'integer',
        ];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(UserDocument::class);
    }

    public function folders(): HasMany
    {
        return $this->hasMany(UserFolder::class);
    }

    public function integrations(): HasMany
    {
        return $this->hasMany(UserIntegration::class);
    }

    /** Remaining storage in bytes (never negative). */
    public function storageRemaining(): int
    {
        return max(0, (int) $this->storage_limit_bytes - (int) $this->storage_used_bytes);
    }

    /** Whether an upload of $bytes would exceed the user's quota. */
    public function exceedsStorage(int $bytes): bool
    {
        return ((int) $this->storage_used_bytes + $bytes) > (int) $this->storage_limit_bytes;
    }

    /**
     * Whether the account is currently suspended.
     */
    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    public function suscripciones(): HasMany
    {
        return $this->hasMany(Suscripcion::class);
    }

    public function adminNotas(): HasMany
    {
        return $this->hasMany(AdminNota::class, 'user_id');
    }

    /**
     * Whether the user is on a paid plan with an active subscription.
     */
    public function isPaid(): bool
    {
        return $this->plan !== 'free' && $this->plan_status === 'active';
    }

    /**
     * Whether the user is a super administrator.
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === 'superadmin';
    }

    /**
     * Whether the user has administrative access (admin or superadmin).
     */
    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'superadmin'], true);
    }

    /**
     * Whether the user's current plan grants the given feature.
     */
    public function hasFeature(string $feature): bool
    {
        return app(FeaturePolicy::class)->hasFeature($this, $feature);
    }

    /**
     * Human-readable label for the user's plan.
     */
    public function planLabel(): string
    {
        return match ($this->plan) {
            'free' => 'Gratis',
            'interino' => 'Interino',
            'opositor' => 'Opositor',
            'docente_pro' => 'Docente Pro',
            'todo_en_uno' => 'Todo en Uno',
            default => 'Gratis',
        };
    }

    /**
     * Human-readable label for the user's plan status.
     */
    public function planStatusLabel(): string
    {
        return match ($this->plan_status) {
            'active' => 'Activo',
            'trialing' => 'Periodo de prueba',
            'past_due' => 'Pago pendiente',
            'canceled' => 'Cancelado',
            'none' => 'Sin suscripción',
            default => 'Sin suscripción',
        };
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

    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }
}
