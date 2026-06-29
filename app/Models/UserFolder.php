<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserFolder extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'name', 'color', 'parent_id', 'tema_id', 'position',
    ];

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(UserFolder::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(UserFolder::class, 'parent_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(UserDocument::class, 'folder_id');
    }
}
