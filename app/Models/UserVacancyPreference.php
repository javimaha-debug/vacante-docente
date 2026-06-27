<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserVacancyPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_list_id',
        'vacancy_id',
        'position',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    public function userList(): BelongsTo
    {
        return $this->belongsTo(UserList::class);
    }

    public function vacancy(): BelongsTo
    {
        return $this->belongsTo(Vacancy::class);
    }
}
