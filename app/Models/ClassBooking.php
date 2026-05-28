<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ClassBooking extends Model
{
    protected $fillable = ['user_id', 'gym_class_id'];

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
}
