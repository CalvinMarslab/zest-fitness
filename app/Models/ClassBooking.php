<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ClassBooking extends Model
{
    protected $fillable = [
        'user_id', 'gym_class_id', 'status',
        'queue_position', 'cancelled_at', 'checked_in_at',
        'user_subscription_id', 'credit_charged', 'credit_refunded_at', 'booked_at',
    ];

    protected function casts(): array
    {
        return [
            'cancelled_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'credit_charged' => 'boolean',
            'credit_refunded_at' => 'datetime',
            'booked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function gymClass(): BelongsTo
    {
        return $this->belongsTo(GymClass::class);
    }

    public function activity(): HasOne
    {
        return $this->hasOne(Activity::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(UserSubscription::class, 'user_subscription_id');
    }

    public function isCancelled(): bool
    {
        return in_array($this->status, ['cancelled', 'late_cancel']);
    }

    public function isRefundable(): bool
    {
        return in_array($this->status, ['booked', 'checked_in'])
            && $this->credit_charged
            && ! $this->credit_refunded_at;
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->whereIn('status', ['booked', 'checked_in']);
    }

    public function scopeWaitlisted(Builder $query): Builder
    {
        return $query->where('status', 'waitlisted');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', ['booked', 'waitlisted', 'checked_in']);
    }
}
