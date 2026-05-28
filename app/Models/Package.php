<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    protected $fillable = [
        'name', 'description', 'credits', 'period_days',
        'price', 'badge', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'credits'     => 'integer',
            'period_days' => 'integer',
            'price'       => 'decimal:2',
            'is_active'   => 'boolean',
            'sort_order'  => 'integer',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class);
    }

    /** Human-readable period label, e.g. "1 Month", "3 Months" */
    public function getPeriodLabelAttribute(): string
    {
        $months = round($this->period_days / 30);
        if ($months === 1) return '1 Month';
        if ($months < 12) return "{$months} Months";
        $years = round($months / 12);
        return $years === 1 ? '1 Year' : "{$years} Years";
    }
}
