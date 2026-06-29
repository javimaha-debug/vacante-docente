<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsage extends Model
{
    protected $table = 'ai_usage';

    protected $fillable = [
        'user_id', 'date', 'messages_count', 'tokens_input', 'tokens_output', 'voyage_calls',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'messages_count' => 'integer',
            'tokens_input' => 'integer',
            'tokens_output' => 'integer',
            'voyage_calls' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
