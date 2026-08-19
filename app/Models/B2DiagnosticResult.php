<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class B2DiagnosticResult extends Model
{
    protected $table = 'b2_diagnostic_results';
    protected $fillable = [
        'user_id', 'reading_score', 'grammar_score', 'listening_score',
        'writing_score', 'speaking_score', 'total_score', 'band_assigned',
        'weaknesses', 'recommendations', 'completed_at',
    ];
    protected $casts = [
        'weaknesses' => 'array',
        'recommendations' => 'array',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
