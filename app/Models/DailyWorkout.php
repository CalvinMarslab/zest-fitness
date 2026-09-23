<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyWorkout extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['workout_date' => 'date', 'is_published' => 'boolean'];
    }
}
