<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'class_booking_id', 'type', 'duration', 'distance', 'polyline_data', 'notes'])]
class Activity extends Model
{
    protected function casts(): array
    {
        return [
            'duration' => 'integer',
            'distance' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function classBooking(): BelongsTo
    {
        return $this->belongsTo(ClassBooking::class);
    }
}
