<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademicCalendarEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'event_type',
        'event_date',
        'time',
        'source_document_id',
        'is_confirmed',
        'is_estimated',
        'affects',
        'visibility',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'is_confirmed' => 'boolean',
            'is_estimated' => 'boolean',
        ];
    }

    public function sourceDocument(): BelongsTo
    {
        return $this->belongsTo(DetectedDocument::class, 'source_document_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
