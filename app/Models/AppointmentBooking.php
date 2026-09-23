<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentBooking extends Model
{
    protected $guarded = [];
    protected $casts = ['booked_at' => 'datetime', 'cancelled_at' => 'datetime', 'checked_in_at' => 'datetime', 'credit_refunded_at' => 'datetime'];
    public function slot() { return $this->belongsTo(AppointmentSlot::class, 'appointment_slot_id'); }
    public function user() { return $this->belongsTo(User::class); }
    public function subscription() { return $this->belongsTo(UserSubscription::class, 'user_subscription_id'); }
}
