<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'coach', 'coach_id', 'day_of_week', 'start_time', 'capacity', 'is_active', 'description', 'location'])]
class ClassTemplate extends Model
{
    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'capacity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function instances(): HasMany
    {
        return $this->hasMany(GymClass::class, 'template_id');
    }

    public function coachUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }
}
