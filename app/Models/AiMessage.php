<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiMessage extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'conversation_id', 'role', 'content', 'chunks_used',
        'tokens_input', 'tokens_output', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'chunks_used' => 'array',
            'tokens_input' => 'integer',
            'tokens_output' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }
}
