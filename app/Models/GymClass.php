<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'coach', 'start_time', 'capacity'])]
class GymClass extends Model
{
    use HasFactory;
    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'capacity' => 'integer',
        ];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(ClassBooking::class);
    }

    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'class_bookings')
                    ->using(ClassBooking::class)
                    ->withTimestamps();
    }

    public function isFull(): bool
    {
        return ($this->bookings_count ?? $this->bookings()->count()) >= $this->capacity;
    }
}
