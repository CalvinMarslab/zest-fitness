<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentSlot extends Model
{
    protected $guarded = [];
    protected $casts = ['start_time' => 'datetime', 'end_time' => 'datetime'];
    public function service() { return $this->belongsTo(AppointmentService::class, 'appointment_service_id'); }
    public function coach() { return $this->belongsTo(User::class, 'coach_id'); }
    public function booking() { return $this->hasOne(AppointmentBooking::class)->whereIn('status', ['booked', 'checked_in']); }
}
