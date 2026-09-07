<?php

namespace App\Http\Controllers;

use App\Models\ClassBooking;
use App\Models\SystemSetting;
use Inertia\Inertia;
use Inertia\Response;

class MyBookingsController extends Controller
{
    public function index(): Response
    {
        $userId = auth()->id();

        $cutoffHours = (int) SystemSetting::get('cancellation_cutoff_hours', 2);
        $lateCancelLosesCredit = SystemSetting::get('late_cancel_loses_credit', 'true') === 'true';

        $all = ClassBooking::where('user_id', $userId)
            ->with('gymClass:id,name,coach,start_time,capacity,location,cancellation_cutoff_hours')
            ->orderByDesc('created_at')
            ->get();

        $now = now();

        $upcoming = $all
            ->filter(fn ($b) => in_array($b->status, ['booked', 'checked_in'])
                && $b->gymClass
                && $b->gymClass->start_time->isFuture())
            ->values()
            ->map(fn ($b) => $this->formatBooking($b, $cutoffHours, $lateCancelLosesCredit));

        $waitlisted = $all
            ->filter(fn ($b) => $b->status === 'waitlisted'
                && $b->gymClass
                && $b->gymClass->start_time->isFuture())
            ->sortBy('queue_position')
            ->values()
            ->map(fn ($b) => $this->formatBooking($b, $cutoffHours, $lateCancelLosesCredit));

        $past = $all
            ->filter(fn ($b) => $b->gymClass
                && $b->gymClass->start_time->isPast()
                && $b->gymClass->start_time->gte($now->copy()->subDays(30)))
            ->values()
            ->map(fn ($b) => $this->formatBooking($b, $cutoffHours, $lateCancelLosesCredit));

        return Inertia::render('MyBookings', compact('upcoming', 'waitlisted', 'past'));
    }

    private function formatBooking(ClassBooking $booking, int $defaultCutoffHours, bool $lateCancelLosesCredit): array
    {
        $gymClass = $booking->gymClass;
        $isWaitlisted = $booking->status === 'waitlisted';
        $creditCharged = $booking->credit_charged ?? false;

        $canCancel = $gymClass && $gymClass->start_time->isFuture()
            && in_array($booking->status, ['booked', 'waitlisted', 'checked_in']);

        $cutoffAt = null;
        $willRefundCredit = false;
        $isLateCancellation = false;

        if ($gymClass) {
            $classCutoffHours = $gymClass->cancellation_cutoff_hours ?? $defaultCutoffHours;
            $cutoffAt = $gymClass->start_time->copy()->subHours($classCutoffHours);
            $isLateCancellation = now()->gte($cutoffAt);

            if ($canCancel) {
                if ($isWaitlisted) {
                    $willRefundCredit = false;
                } elseif (! $creditCharged) {
                    $willRefundCredit = false;
                } elseif ($isLateCancellation) {
                    $willRefundCredit = ! $lateCancelLosesCredit;
                } else {
                    $willRefundCredit = true;
                }
            }
        }

        return [
            'id' => $booking->id,
            'gym_class_id' => $booking->gym_class_id,
            'status' => $booking->status,
            'queue_position' => $booking->queue_position,
            'credit_charged' => $creditCharged,
            'cancelled_at' => $booking->cancelled_at?->toIso8601String(),
            'checked_in_at' => $booking->checked_in_at?->toIso8601String(),
            // Cancellation metadata
            'can_cancel' => $canCancel,
            'will_refund_credit' => $willRefundCredit,
            'is_late_cancellation' => $isLateCancellation,
            'cancellation_cutoff_at' => $cutoffAt?->toIso8601String(),
            'gym_class' => $gymClass ? [
                'id' => $gymClass->id,
                'name' => $gymClass->name,
                'coach' => $gymClass->coach,
                'start_time' => $gymClass->start_time->toIso8601String(),
                'capacity' => $gymClass->capacity,
                'location' => $gymClass->location,
            ] : null,
        ];
    }
}
