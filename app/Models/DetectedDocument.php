<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DetectedDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_id',
        'title',
        'detected_at',
        'source_url',
        'document_type',
        'status',
        'pdf_url',
        'pdf_path',
        'superadmin_notes',
        'validated_by',
        'validated_at',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'detected_at' => 'datetime',
            'validated_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(MonitoredSource::class, 'source_id');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function calendarEvents(): HasMany
    {
        return $this->hasMany(AcademicCalendarEvent::class, 'source_document_id');
    }
}
