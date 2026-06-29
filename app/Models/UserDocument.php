<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class UserDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'folder_id', 'name', 'disk_path', 'mime_type', 'size_bytes',
        'type', 'source', 'external_id', 'external_url', 'processing_status',
        'page_count', 'word_count', 'thumbnail_path', 'tema_id', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'page_count' => 'integer',
            'word_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(UserFolder::class, 'folder_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(UserDocumentTag::class, 'user_document_tag_pivot', 'document_id', 'tag_id');
    }
}
