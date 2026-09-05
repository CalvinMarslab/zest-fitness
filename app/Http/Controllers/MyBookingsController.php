<?php

namespace App\Http\Controllers;

use App\Models\ClassBooking;
use Inertia\Inertia;
use Inertia\Response;

class MyBookingsController extends Controller
{
    public function index(): Response
    {
        $userId = auth()->id();

        $all = ClassBooking::where('user_id', $userId)
            ->with('gymClass:id,name,coach,start_time,capacity,location')
            ->orderByDesc('created_at')
            ->get();

        $now = now();

        $upcoming = $all
            ->filter(fn ($b) => in_array($b->status, ['booked', 'checked_in'])
                && $b->gymClass
                && $b->gymClass->start_time->isFuture())
            ->values()
            ->map(fn ($b) => $this->formatBooking($b));

        $waitlisted = $all
            ->filter(fn ($b) => $b->status === 'waitlisted'
                && $b->gymClass
                && $b->gymClass->start_time->isFuture())
            ->sortBy('queue_position')
            ->values()
            ->map(fn ($b) => $this->formatBooking($b));

        $past = $all
            ->filter(fn ($b) => $b->gymClass
                && $b->gymClass->start_time->isPast()
                && $b->gymClass->start_time->gte($now->copy()->subDays(30)))
            ->values()
            ->map(fn ($b) => $this->formatBooking($b));

        return Inertia::render('MyBookings', compact('upcoming', 'waitlisted', 'past'));
    }

    private function formatBooking(ClassBooking $booking): array
    {
        return [
            'id' => $booking->id,
            'gym_class_id' => $booking->gym_class_id,
            'status' => $booking->status,
            'queue_position' => $booking->queue_position,
            'cancelled_at' => $booking->cancelled_at?->toIso8601String(),
            'checked_in_at' => $booking->checked_in_at?->toIso8601String(),
            'gym_class' => $booking->gymClass ? [
                'id' => $booking->gymClass->id,
                'name' => $booking->gymClass->name,
                'coach' => $booking->gymClass->coach,
                'start_time' => $booking->gymClass->start_time->toIso8601String(),
                'capacity' => $booking->gymClass->capacity,
                'location' => $booking->gymClass->location,
            ] : null,
        ];
    }
}
