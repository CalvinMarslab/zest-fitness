<?php

namespace App\Http\Controllers;

use App\Models\ClassBooking;
use App\Models\GymClass;
use App\Models\SystemSetting;
use App\Models\UserSubscription;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();
        $userId = $user->id;

        $cutoffHours = (int) SystemSetting::get('cancellation_cutoff_hours', 2);
        $lateCancelLosesCredit = SystemSetting::get('late_cancel_loses_credit', 'true') === 'true';

        // Determine if user has an unlimited active subscription
        $activeUnlimited = UserSubscription::where('user_id', $userId)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->where('is_unlimited', true)
            ->exists();

        // Get all active bookings for this user
        $userBookings = ClassBooking::where('user_id', $userId)
            ->whereIn('status', ['booked', 'waitlisted', 'checked_in'])
            ->get(['id', 'gym_class_id', 'status', 'queue_position', 'credit_charged', 'user_subscription_id'])
            ->keyBy('gym_class_id');

        $classes = GymClass::withCount([
            'bookings as confirmed_count' => fn ($q) => $q->whereIn('status', ['booked', 'checked_in']),
        ])
            ->where('is_cancelled', false)
            ->whereBetween('start_time', [now()->startOfDay(), now()->addDays(14)->endOfDay()])
            ->orderBy('start_time')
            ->get()
            ->map(function (GymClass $class) use ($userBookings, $cutoffHours, $lateCancelLosesCredit) {
                $booking = $userBookings->get($class->id);
                $confirmedCount = $class->confirmed_count ?? 0;
                $isBooked = $booking && $booking->status !== 'waitlisted';
                $isWaitlisted = $booking && $booking->status === 'waitlisted';

                // Compute cancellation metadata
                $classCutoffHours = $class->cancellation_cutoff_hours ?? $cutoffHours;
                $cutoffAt = $class->start_time->copy()->subHours($classCutoffHours);
                $isLateCancellation = now()->gte($cutoffAt);
                $creditCharged = $booking?->credit_charged ?? false;

                $canCancel = $isBooked || $isWaitlisted;
                $willRefundCredit = false;
                if ($canCancel) {
                    if ($isWaitlisted) {
                        $willRefundCredit = false; // waitlist never charged
                    } elseif (! $creditCharged) {
                        $willRefundCredit = false; // unlimited — nothing to refund
                    } elseif ($isLateCancellation) {
                        $willRefundCredit = ! $lateCancelLosesCredit;
                    } else {
                        $willRefundCredit = true;
                    }
                }

                return [
                    'id' => $class->id,
                    'name' => $class->name,
                    'coach' => $class->coach,
                    'start_time' => $class->start_time->toIso8601String(),
                    'capacity' => $class->capacity,
                    'spots_left' => max(0, $class->capacity - $confirmedCount),
                    'is_full' => $confirmedCount >= $class->capacity,
                    'is_booked' => $isBooked,
                    'is_waitlisted' => $isWaitlisted,
                    'queue_position' => $booking?->queue_position,
                    'booking_status' => $booking?->status,
                    'status' => $class->status ?? 'scheduled',
                    'location' => $class->location,
                    'date_label' => $class->start_time->format('Y-m-d'),
                    // Cancellation metadata
                    'can_cancel' => $canCancel,
                    'cancellation_cutoff_at' => $cutoffAt->toIso8601String(),
                    'is_late_cancellation' => $isLateCancellation,
                    'will_refund_credit' => $willRefundCredit,
                    'credit_charged' => $creditCharged,
                    'is_unlimited_booking' => $isBooked && ! $creditCharged,
                ];
            });

        return Inertia::render('Schedule', ['classes' => $classes]);
    }
}
