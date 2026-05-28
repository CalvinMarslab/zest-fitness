<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSubscription extends Model
{
    protected $fillable = [
        'user_id', 'package_id', 'credits_granted', 'started_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'credits_granted' => 'integer',
            'started_at'      => 'datetime',
            'expires_at'      => 'datetime',
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

    public function isActive(): bool
    {
        return $this->expires_at->isFuture();
    }
}
