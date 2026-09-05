<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserSubscription extends Model
{
    protected $fillable = [
        'user_id', 'package_id', 'credits_granted', 'started_at', 'expires_at',
        'credits_remaining', 'status', 'assigned_by', 'is_unlimited',
    ];

    protected function casts(): array
    {
        return [
            'credits_granted' => 'integer',
            'credits_remaining' => 'integer',
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_unlimited' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(ClassBooking::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->expires_at->isFuture();
    }

    public function isUnlimited(): bool
    {
        return $this->is_unlimited || ($this->package && $this->package->is_unlimited);
    }

    public function hasCredits(): bool
    {
        return $this->isUnlimited() || $this->credits_remaining > 0;
    }

    public function canBook(): bool
    {
        return $this->isActive() && $this->hasCredits();
    }
}
