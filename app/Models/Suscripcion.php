<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Suscripcion extends Model
{
    protected $table = 'suscripciones';

    protected $fillable = [
        'user_id', 'plan_codigo', 'stripe_subscription_id', 'stripe_customer_id', 'status',
        'current_period_start', 'current_period_end', 'cancel_at_period_end',
        'canceled_at', 'trial_start', 'trial_end', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'canceled_at' => 'datetime',
            'trial_start' => 'datetime',
            'trial_end' => 'datetime',
            'cancel_at_period_end' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
