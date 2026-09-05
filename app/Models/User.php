<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'credits', 'role', 'phone', 'status', 'notes', 'joined_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public function bookings(): HasMany
    {
        return $this->hasMany(ClassBooking::class);
    }

    public function gymClasses(): BelongsToMany
    {
        return $this->belongsToMany(GymClass::class, 'class_bookings')
            ->using(ClassBooking::class)
            ->withTimestamps();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class);
    }

    public function activeSubscription(): ?UserSubscription
    {
        return $this->subscriptions()
            ->where('expires_at', '>', now())
            ->where('status', 'active')
            ->latest('expires_at')
            ->first();
    }

    public function assignedClasses(): HasMany
    {
        return $this->hasMany(GymClass::class, 'coach_id');
    }

    public function coachTemplates(): HasMany
    {
        return $this->hasMany(ClassTemplate::class, 'coach_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin' || (bool) $this->is_admin;
    }

    public function isCoach(): bool
    {
        return $this->role === 'coach';
    }

    public function isMember(): bool
    {
        return $this->role === 'member';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    /**
     * Rebuild the user's display credit count from active subscription totals.
     * Call this after any credit deduction or refund so the two sources stay in sync.
     */
    public function syncCreditSummary(): void
    {
        $total = $this->subscriptions()
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->where('is_unlimited', false)
            ->sum('credits_remaining');
        $this->update(['credits' => $total]);
    }

    public function deductCredit(): bool
    {
        if ($this->credits <= 0) {
            return false;
        }
        $this->decrement('credits');

        return true;
    }

    public function refundCredit(): void
    {
        $this->increment('credits');
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'credits' => 'integer',
            'is_admin' => 'boolean',
            'joined_at' => 'date',
        ];
    }
}
