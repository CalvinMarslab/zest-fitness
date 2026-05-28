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

#[Fillable(['name', 'email', 'password', 'credits'])]
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
            ->latest('expires_at')
            ->first();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
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
            'credits'  => 'integer',
            'is_admin' => 'boolean',
        ];
    }
}
