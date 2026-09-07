<?php

namespace App\Services;

use App\Models\ClassBooking;
use App\Models\CreditTransaction;
use App\Models\GymClass;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserSubscription;
use Carbon\Carbon;
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

            // Find eligible subscription (respects weekly booking cap)
            $sub = $this->findEligibleSubscription($user, $class);
            if (! $sub) {
                if ($this->hasSubBlockedByWeeklyCap($user, $class)) {
                    return ['status' => 'weekly_limit_reached'];
                }

                return ['status' => 'no_subscription'];
            }

            // Credit check (findEligibleSubscription already filters; guard for safety)
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
                $confirmedBooking = $existing;
            } else {
                $confirmedBooking = ClassBooking::create(array_merge($bookingData, [
                    'user_id' => $user->id,
                    'gym_class_id' => $class->id,
                ]));
            }

            if (! $isUnlimited) {
                $this->deductCredit($user, $sub, $confirmedBooking);
            }

            $sub->refresh();

            return ['status' => 'booked', 'credits_remaining' => $sub->credits_remaining];
        });
    }

    /**
     * Cancel a booking.
     *
     * $forceRefund = true  → ignore late-cancellation cutoff; refund if and only if credit_charged=true
     * $forceRefund = false → no refund regardless
     * $forceRefund = null  → apply normal cancellation rules
     *
     * @return array{status: string, refunded: bool}
     */
    public function cancel(ClassBooking $booking, ?User $actor = null, ?bool $forceRefund = null): array
    {
        return DB::transaction(function () use ($booking, $forceRefund) {
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
                // Ignore late-cancellation cutoff; refundCredit() guards credit_charged internally
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
     * Confirmed bookings get refunded if credit_charged=true; waitlisted do not.
     */
    public function cancelClass(GymClass $class): void
    {
        DB::transaction(function () use ($class) {
            $class = GymClass::where('id', $class->id)->lockForUpdate()->firstOrFail();

            // Mark class as cancelled
            $class->update(['is_cancelled' => true, 'status' => 'cancelled']);

            // Cancel confirmed bookings with refund (only if credit was actually charged)
            $confirmed = ClassBooking::where('gym_class_id', $class->id)
                ->whereIn('status', ['booked', 'checked_in'])
                ->lockForUpdate()
                ->get();

            foreach ($confirmed as $booking) {
                $booking->update(['status' => 'cancelled', 'cancelled_at' => now()]);
                $this->refundCredit($booking, 'class_cancel_refund');
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
     * Wrapped in its own transaction so standalone calls are also atomic.
     *
     * @return array{status: 'promoted'|'full'|'no_eligible_waitlist'}
     */
    public function promoteWaitlist(GymClass $class): array
    {
        return DB::transaction(function () use ($class) {
            $class = GymClass::where('id', $class->id)->lockForUpdate()->firstOrFail();

            $confirmedCount = ClassBooking::where('gym_class_id', $class->id)
                ->whereIn('status', ['booked', 'checked_in'])
                ->lockForUpdate()
                ->count();

            if ($confirmedCount >= $class->capacity) {
                return ['status' => 'full'];
            }

            while ($confirmedCount < $class->capacity) {
                $next = ClassBooking::where('gym_class_id', $class->id)
                    ->where('status', 'waitlisted')
                    ->orderBy('queue_position')
                    ->lockForUpdate()
                    ->first();

                if (! $next) {
                    return ['status' => 'no_eligible_waitlist'];
                }

                $waitlistUser = User::where('id', $next->user_id)->lockForUpdate()->first();

                if (! $waitlistUser || $waitlistUser->isSuspended()) {
                    // Skip ineligible user
                    $next->update(['status' => 'cancelled', 'cancelled_at' => now()]);

                    continue;
                }

                $sub = $this->findEligibleSubscription($waitlistUser, $class);
                if (! $sub || ! $sub->hasCredits()) {
                    // Skip — no credits or all subscriptions weekly-capped
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
                    $this->deductCredit($waitlistUser, $sub, $next);
                }

                return ['status' => 'promoted'];
            }

            return ['status' => 'full'];
        });
    }

    /**
     * Find the earliest-expiring active subscription with available credits
     * that has not hit its weekly booking cap for the given class's week.
     *
     * Phase 1A: all active non-expired packages are eligible for all classes.
     * Per-package class/category eligibility rules are reserved for Phase 1B.
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
            if (! $sub->hasCredits()) {
                continue;
            }

            $limit = $sub->package->weekly_booking_limit ?? null;
            if ($limit !== null && $this->weeklyBookingsUsed($user, $sub, $class) >= $limit) {
                continue;
            }

            return $sub;
        }

        return null;
    }

    /**
     * True if the user has an active subscription with credits whose only
     * blocker is the weekly booking cap for this class's calendar week.
     */
    private function hasSubBlockedByWeeklyCap(User $user, GymClass $class): bool
    {
        $subs = UserSubscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->with('package')
            ->get();

        foreach ($subs as $sub) {
            if (! $sub->hasCredits()) {
                continue;
            }
            $limit = $sub->package->weekly_booking_limit ?? null;
            if ($limit !== null && $this->weeklyBookingsUsed($user, $sub, $class) >= $limit) {
                return true;
            }
        }

        return false;
    }

    /**
     * Count this user's consumed (booked/checked_in/no_show) bookings funded by $sub
     * for classes whose start_time falls within the same Mon–Sun calendar week
     * as $forClass->start_time, evaluated in the app timezone (Asia/Kuala_Lumpur).
     *
     * Counts by class start_time, not by when the booking was created.
     * Cancelled, waitlisted, and late_cancel bookings are excluded.
     * no_show is counted — the slot was consumed and is not freed retroactively.
     * Week boundaries are computed in the app timezone then converted to UTC for DB comparison.
     */
    private function weeklyBookingsUsed(User $user, UserSubscription $sub, GymClass $forClass): int
    {
        $tz = config('app.timezone');
        $classTime = Carbon::parse($forClass->start_time)->setTimezone($tz);

        $weekStartUtc = $classTime->clone()->startOfWeek(Carbon::MONDAY)->utc();
        $weekEndUtc = $classTime->clone()->endOfWeek(Carbon::SUNDAY)->utc();

        return ClassBooking::where('user_id', $user->id)
            ->where('user_subscription_id', $sub->id)
            ->whereIn('status', ['booked', 'checked_in', 'no_show'])
            ->whereHas('gymClass', function ($q) use ($weekStartUtc, $weekEndUtc) {
                $q->whereBetween('start_time', [$weekStartUtc, $weekEndUtc]);
            })
            ->count();
    }

    /**
     * Deduct a credit from the subscription and sync the user display credits.
     */
    private function deductCredit(User $user, UserSubscription $sub, ClassBooking $booking): void
    {
        if ($sub->isUnlimited()) {
            return;
        }

        $sub->decrement('credits_remaining');
        $user->syncCreditSummary();

        $user->refresh();
        CreditTransaction::create([
            'user_id' => $user->id,
            'user_subscription_id' => $sub->id,
            'class_booking_id' => $booking->id,
            'type' => 'booking_deduction',
            'amount' => -1,
            'balance_after' => $user->credits,
        ]);
    }

    /**
     * Refund a credit back to the subscription. Idempotent.
     *
     * Semantics:
     * - credit_charged = false → never refund, regardless of any force flag
     * - credit_charged = true  → refund to the original subscription exactly once
     * - credit_refunded_at is the idempotency guard
     */
    private function refundCredit(ClassBooking $booking, string $transactionType = 'booking_refund'): void
    {
        // Idempotent guard
        if ($booking->credit_refunded_at !== null) {
            return;
        }

        // Never create credits that were not consumed (unlimited or waitlisted bookings)
        if (! $booking->credit_charged) {
            return;
        }

        // The subscription that originally held the credit must still exist.
        // If it is missing we cannot restore the credit — throw so the surrounding
        // DB transaction rolls back and the broken reference can be investigated.
        $sub = UserSubscription::where('id', $booking->user_subscription_id)->lockForUpdate()->first();

        if (! $sub) {
            throw new \RuntimeException(
                "Cannot refund booking #{$booking->id}: original subscription #{$booking->user_subscription_id} not found. Manual investigation required."
            );
        }

        $user = User::where('id', $booking->user_id)->lockForUpdate()->firstOrFail();

        $sub->increment('credits_remaining');
        $user->syncCreditSummary();
        $user->refresh();

        CreditTransaction::create([
            'user_id' => $user->id,
            'user_subscription_id' => $sub->id,
            'class_booking_id' => $booking->id,
            'type' => $transactionType,
            'amount' => +1,
            'balance_after' => $user->credits,
        ]);

        $booking->update(['credit_refunded_at' => now()]);
    }
}
