<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class B2Achievement extends Model
{
    protected $table = 'b2_achievements';
    protected $fillable = [
        'user_id', 'badge_id', 'badge_name', 'badge_description', 'icon_emoji', 'unlocked_at',
    ];
    protected $casts = ['unlocked_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
