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
        $userId = auth()->id();

        // Get all bookings for this user so we know status + waitlist position
        $userBookings = ClassBooking::where('user_id', $userId)
            ->whereIn('status', ['booked', 'waitlisted', 'checked_in'])
            ->get(['gym_class_id', 'status', 'queue_position'])
            ->keyBy('gym_class_id');

        $classes = GymClass::withCount([
            'bookings as confirmed_count' => fn ($q) => $q->whereIn('status', ['booked', 'checked_in']),
        ])
            ->where('is_cancelled', false)
            ->whereDate('start_time', today())
            ->orderBy('start_time')
            ->get()
            ->map(function (GymClass $class) use ($userBookings) {
                $booking = $userBookings->get($class->id);
                $confirmedCount = $class->confirmed_count ?? 0;

                return [
                    'id' => $class->id,
                    'name' => $class->name,
                    'coach' => $class->coach,
                    'start_time' => $class->start_time->toIso8601String(),
                    'capacity' => $class->capacity,
                    'spots_left' => max(0, $class->capacity - $confirmedCount),
                    'is_full' => $confirmedCount >= $class->capacity,
                    'is_booked' => $booking && $booking->status !== 'waitlisted',
                    'is_waitlisted' => $booking && $booking->status === 'waitlisted',
                    'queue_position' => $booking?->queue_position,
                    'booking_status' => $booking?->status,
                    'status' => $class->status ?? 'scheduled',
                    'location' => $class->location,
                ];
            });

        return Inertia::render('Schedule', ['classes' => $classes]);
    }
}
