<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'template_id', 'name', 'coach', 'coach_id',
    'start_time', 'end_time', 'location',
    'capacity', 'exercises', 'wod_type', 'wod_duration', 'wod_config',
    'is_cancelled', 'status', 'booking_opens_at', 'booking_closes_at',
    'cancellation_cutoff_hours',
])]
class GymClass extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'booking_opens_at' => 'datetime',
            'booking_closes_at' => 'datetime',
            'capacity' => 'integer',
            'exercises' => 'array',
            'wod_config' => 'array',
            'is_cancelled' => 'boolean',
            'cancellation_cutoff_hours' => 'integer',
        ];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(ClassBooking::class);
    }

    public function confirmedBookings(): HasMany
    {
        return $this->hasMany(ClassBooking::class)
            ->whereIn('status', ['booked', 'checked_in']);
    }

    public function waitlistedBookings(): HasMany
    {
        return $this->hasMany(ClassBooking::class)
            ->where('status', 'waitlisted')
            ->orderBy('queue_position');
    }

    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'class_bookings')
            ->using(ClassBooking::class)
            ->withTimestamps();
    }

    public function coachUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    public function isFull(): bool
    {
        $count = $this->confirmedBookings()->count();

        return $count >= $this->capacity;
    }
}
