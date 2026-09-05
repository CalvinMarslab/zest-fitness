<?php

namespace App\Services;

use App\Models\ClassBooking;
use App\Models\GymClass;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Support\Facades\DB;

class BookingService
{
    /**
     * Book a user into a class.
     *
     * @return array{status: string, position?: int, credits_remaining?: int}
     */
    public function book(User $user, GymClass $class): array
    {
        return DB::transaction(function () use ($user, $class) {
            $user = User::where('id', $user->id)->lockForUpdate()->firstOrFail();
            $class = GymClass::where('id', $class->id)->lockForUpdate()->firstOrFail();

            // Suspended user check
            if ($user->isSuspended()) {
                return ['status' => 'suspended'];
            }

            // Class must not be cancelled
            if ($class->is_cancelled || $class->status === 'cancelled') {
                return ['status' => 'cancelled'];
            }

            // Booking window checks
            if ($class->booking_opens_at && $class->booking_opens_at->isFuture()) {
                return ['status' => 'not_open', 'opens_at' => $class->booking_opens_at->format('d M H:i')];
            }
            if ($class->booking_closes_at && $class->booking_closes_at->isPast()) {
                return ['status' => 'closed'];
            }

            // Check for existing booking row (any status)
            $existing = ClassBooking::where('user_id', $user->id)
                ->where('gym_class_id', $class->id)
                ->lockForUpdate()
                ->first();

            // Already actively booked/waitlisted
            if ($existing && in_array($existing->status, ['booked', 'waitlisted', 'checked_in'])) {
                return ['status' => 'already_booked'];
            }

            // Find eligible subscription
            $sub = $this->findEligibleSubscription($user, $class);
            if (! $sub) {
                return ['status' => 'no_subscription'];
            }

            // Credit check
            if (! $sub->hasCredits()) {
                return ['status' => 'no_credits'];
            }

            // Capacity check (confirmed only)
            $confirmedCount = ClassBooking::where('gym_class_id', $class->id)
                ->whereIn('status', ['booked', 'checked_in'])
                ->lockForUpdate()
                ->count();

            if ($confirmedCount >= $class->capacity) {
                // Add to waitlist
                $queuePosition = (ClassBooking::where('gym_class_id', $class->id)
                    ->where('status', 'waitlisted')
                    ->max('queue_position') ?? 0) + 1;

                $bookingData = [
                    'status' => 'waitlisted',
                    'queue_position' => $queuePosition,
                    'cancelled_at' => null,
                    'checked_in_at' => null,
                    'booked_at' => now(),
                    'credit_charged' => false,
                    'user_subscription_id' => $sub->id,
                    'credit_refunded_at' => null,
                ];

                if ($existing) {
                    $existing->update($bookingData);
                } else {
                    ClassBooking::create(array_merge($bookingData, [
                        'user_id' => $user->id,
                        'gym_class_id' => $class->id,
                    ]));
                }

                return ['status' => 'waitlisted', 'position' => $queuePosition];
            }

            // Book the class
            $isUnlimited = $sub->isUnlimited();
            $bookingData = [
                'status' => 'booked',
                'queue_position' => null,
                'cancelled_at' => null,
                'checked_in_at' => null,
                'booked_at' => now(),
                'credit_charged' => ! $isUnlimited,
                'user_subscription_id' => $sub->id,
                'credit_refunded_at' => null,
            ];

            if ($existing) {
                $existing->update($bookingData);
            } else {
                ClassBooking::create(array_merge($bookingData, [
                    'user_id' => $user->id,
                    'gym_class_id' => $class->id,
                ]));
            }

            if (! $isUnlimited) {
                $this->deductCredit($user, $sub);
            }

            $sub->refresh();

            return ['status' => 'booked', 'credits_remaining' => $sub->credits_remaining];
        });
    }

    /**
     * Cancel a booking.
     *
     * @return array{status: string, refunded: bool}
     */
    public function cancel(ClassBooking $booking, ?User $actor = null, ?bool $forceRefund = null): array
    {
        return DB::transaction(function () use ($booking, $actor, $forceRefund) {
            $booking = ClassBooking::where('id', $booking->id)->lockForUpdate()->firstOrFail();

            // Idempotent check
            if ($booking->isCancelled()) {
                return ['status' => 'already_cancelled', 'refunded' => false];
            }

            // Waitlisted cancellation — no refund
            if ($booking->status === 'waitlisted') {
                $booking->update(['status' => 'cancelled', 'cancelled_at' => now()]);

                return ['status' => 'cancelled_waitlist', 'refunded' => false];
            }

            $originalStatus = $booking->status;
            $user = User::where('id', $booking->user_id)->lockForUpdate()->firstOrFail();
            $class = GymClass::where('id', $booking->gym_class_id)->lockForUpdate()->firstOrFail();

            // Determine if we refund
            $shouldRefund = false;
            $newStatus = 'cancelled';

            if ($forceRefund === true) {
                $shouldRefund = true;
            } elseif ($forceRefund === false) {
                $shouldRefund = false;
            } else {
                // Normal rules
                if ($booking->status === 'checked_in') {
                    // No refund after check-in without override
                    $shouldRefund = false;
                } else {
                    $cutoffHours = $class->cancellation_cutoff_hours
                        ?? (int) SystemSetting::get('cancellation_cutoff_hours', 2);
                    $cutoffTime = $class->start_time->copy()->subHours($cutoffHours);
                    $isLateCancellation = now()->gte($cutoffTime);

                    if ($isLateCancellation) {
                        $newStatus = 'late_cancel';
                        $lateCancelLosesCredit = SystemSetting::get('late_cancel_loses_credit', 'true') === 'true';
                        $shouldRefund = ! $lateCancelLosesCredit;
                    } else {
                        $shouldRefund = true;
                    }
                }
            }

            $booking->update(['status' => $newStatus, 'cancelled_at' => now()]);

            if ($shouldRefund) {
                $this->refundCredit($booking);
            }

            // Promote waitlist if a confirmed spot opened (check original status before the update)
            if (in_array($originalStatus, ['booked', 'checked_in'])) {
                $this->promoteWaitlist($class);
            }

            return ['status' => $newStatus, 'refunded' => $shouldRefund];
        });
    }

    /**
     * Cancel all bookings for a class (e.g. when class is cancelled).
     * Confirmed bookings get refunded; waitlisted do not.
     */
    public function cancelClass(GymClass $class): void
    {
        DB::transaction(function () use ($class) {
            $class = GymClass::where('id', $class->id)->lockForUpdate()->firstOrFail();

            // Mark class as cancelled
            $class->update(['is_cancelled' => true, 'status' => 'cancelled']);

            // Cancel confirmed bookings with refund (force refund)
            $confirmed = ClassBooking::where('gym_class_id', $class->id)
                ->whereIn('status', ['booked', 'checked_in'])
                ->lockForUpdate()
                ->get();

            foreach ($confirmed as $booking) {
                $booking->update(['status' => 'cancelled', 'cancelled_at' => now()]);
                $this->refundCredit($booking);
            }

            // Cancel waitlisted bookings without refund
            ClassBooking::where('gym_class_id', $class->id)
                ->where('status', 'waitlisted')
                ->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        });
    }

    /**
     * Promote the next eligible waitlisted member when a spot opens up.
     * Skips ineligible users (suspended, no credits, no subscription).
     */
    public function promoteWaitlist(GymClass $class): void
    {
        // Count current confirmed bookings
        $confirmedCount = ClassBooking::where('gym_class_id', $class->id)
            ->whereIn('status', ['booked', 'checked_in'])
            ->count();

        while ($confirmedCount < $class->capacity) {
            $next = ClassBooking::where('gym_class_id', $class->id)
                ->where('status', 'waitlisted')
                ->orderBy('queue_position')
                ->lockForUpdate()
                ->first();

            if (! $next) {
                break;
            }

            $waitlistUser = User::where('id', $next->user_id)->lockForUpdate()->first();

            if (! $waitlistUser || $waitlistUser->isSuspended()) {
                // Skip ineligible user
                $next->update(['status' => 'cancelled', 'cancelled_at' => now()]);
                continue;
            }

            $sub = $this->findEligibleSubscription($waitlistUser, $class);
            if (! $sub || ! $sub->hasCredits()) {
                // Skip ineligible user
                $next->update(['status' => 'cancelled', 'cancelled_at' => now()]);
                continue;
            }

            $isUnlimited = $sub->isUnlimited();

            $next->update([
                'status' => 'booked',
                'queue_position' => null,
                'booked_at' => now(),
                'credit_charged' => ! $isUnlimited,
                'user_subscription_id' => $sub->id,
            ]);

            if (! $isUnlimited) {
                $this->deductCredit($waitlistUser, $sub);
            }

            $confirmedCount++;
            // Promote only one per cancellation in normal flow
            break;
        }
    }

    /**
     * Find the earliest-expiring active subscription with available credits.
     */
    private function findEligibleSubscription(User $user, GymClass $class): ?UserSubscription
    {
        $subs = UserSubscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->orderBy('expires_at', 'asc')
            ->with('package')
            ->get();

        foreach ($subs as $sub) {
            if ($sub->hasCredits()) {
                return $sub;
            }
        }

        return null;
    }

    /**
     * Deduct a credit from the subscription and sync user display credits.
     */
    private function deductCredit(User $user, UserSubscription $sub): void
    {
        if ($sub->isUnlimited()) {
            return;
        }

        $sub->decrement('credits_remaining');
        $user->decrement('credits');
    }

    /**
     * Refund a credit back to the subscription. Idempotent.
     */
    private function refundCredit(ClassBooking $booking): void
    {
        // Idempotent guard
        if ($booking->credit_refunded_at !== null) {
            return;
        }

        // No credit was charged (waitlist or unlimited)
        if (! $booking->credit_charged) {
            return;
        }

        $user = User::where('id', $booking->user_id)->first();

        if ($booking->user_subscription_id) {
            $sub = UserSubscription::where('id', $booking->user_subscription_id)->lockForUpdate()->first();
            if ($sub) {
                $sub->increment('credits_remaining');
            }
        }

        if ($user) {
            $user->increment('credits');
        }

        $booking->update(['credit_refunded_at' => now()]);
    }
}
