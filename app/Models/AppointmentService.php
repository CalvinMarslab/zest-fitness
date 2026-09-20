<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentService extends Model
{
    protected $guarded = [];
    protected $casts = ['is_public' => 'boolean', 'is_active' => 'boolean'];
    public function packages() { return $this->belongsToMany(Package::class); }
    public function slots() { return $this->hasMany(AppointmentSlot::class); }
}
