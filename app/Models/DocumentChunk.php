<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentChunk extends Model
{
    protected $fillable = [
        'user_document_id', 'user_id', 'chunk_index', 'page_number',
        'content', 'token_count', 'embedding',
    ];

    protected function casts(): array
    {
        return [
            'chunk_index' => 'integer',
            'page_number' => 'integer',
            'token_count' => 'integer',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(UserDocument::class, 'user_document_id');
    }

    /**
     * Decode the stored embedding (pgvector literal "[..]" or JSON) to a float array.
     *
     * @return array<int, float>
     */
    public function embeddingVector(): array
    {
        if (! $this->embedding) {
            return [];
        }
        $decoded = json_decode($this->embedding, true);

        return is_array($decoded) ? array_map('floatval', $decoded) : [];
    }
}
