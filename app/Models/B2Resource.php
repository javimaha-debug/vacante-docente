<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class B2Resource extends Model
{
    protected $table = 'b2_resources';

    protected $fillable = [
        'type',
        'title',
        'description',
        'url',
        'level',
        'category',
        'duration_minutes',
        'source',
        'icon_emoji',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'b2_user_resources_favorites', 'resource_id', 'user_id')
            ->withTimestamps();
    }

    public static function getRecommendedFor(string $skill, ?string $category = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = static::where('is_active', true);
        if ($category) {
            $query->where('category', $category);
        }
        return $query->get();
    }
}
