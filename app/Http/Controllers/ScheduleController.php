<?php

namespace App\Http\Controllers;

use App\Models\ClassBooking;
use App\Models\GymClass;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleController extends Controller
{
    public function index(): Response
    {
        $bookedClassIds = array_flip(
            ClassBooking::where('user_id', auth()->id())
                ->pluck('gym_class_id')
                ->all()
        );

        $classes = GymClass::withCount('bookings')
            ->where('start_time', '>', now())
            ->orderBy('start_time')
            ->limit(100)
            ->get()
            ->map(fn(GymClass $class) => [
                'id'         => $class->id,
                'name'       => $class->name,
                'coach'      => $class->coach,
                'start_time' => $class->start_time->toIso8601String(),
                'capacity'   => $class->capacity,
                'spots_left' => max(0, $class->capacity - $class->bookings_count),
                'is_full'    => $class->bookings_count >= $class->capacity,
                'is_booked'  => isset($bookedClassIds[$class->id]),
            ]);

        return Inertia::render('Schedule', ['classes' => $classes]);
    }
}
