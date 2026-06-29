<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ResourceLink extends Model
{
    protected $fillable = ['title', 'description', 'url', 'category', 'icon', 'position', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('id');
    }
}
