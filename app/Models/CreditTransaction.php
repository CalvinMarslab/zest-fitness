<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'user_subscription_id',
        'class_booking_id',
        'type',
        'amount',
        'balance_after',
        'reason',
        'actor_user_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'balance_after' => 'integer',
        ];
    }

    public const TYPES = [
        'package_assigned',
        'booking_deduction',
        'booking_refund',
        'class_cancel_refund',
        'admin_adjustment',
        'migration',
        'other',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(UserSubscription::class, 'user_subscription_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(ClassBooking::class, 'class_booking_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
