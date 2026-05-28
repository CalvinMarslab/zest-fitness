<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkoutResult extends Model
{
    protected $fillable = ['user_id', 'result_date', 'exercise', 'value', 'notes'];

    protected function casts(): array
    {
        return ['result_date' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
