<?php

namespace App\Services;

use App\Models\AppointmentBooking;
use App\Models\AppointmentSlot;
use App\Models\CreditTransaction;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentBookingService
{
    public function book(User $user, AppointmentSlot $slot, bool $adminOverride = false): AppointmentBooking
    {
        return DB::transaction(function () use ($user, $slot, $adminOverride) {
            $user = User::lockForUpdate()->findOrFail($user->id);
            $slot = AppointmentSlot::with('service.packages')->lockForUpdate()->findOrFail($slot->id);
            $service = $slot->service;

            if ($user->isSuspended() || $slot->status !== 'available' || $slot->start_time->isPast() || $slot->booking()->exists()) {
                throw ValidationException::withMessages(['appointment' => 'This appointment is no longer available.']);
            }
            if (! $adminOverride && $slot->start_time->gt(now()->addDays($service->release_days))) {
                throw ValidationException::withMessages(['appointment' => 'This appointment is not open for booking yet.']);
            }
            if (! $adminOverride && $slot->start_time->lte(now()->addHours($service->booking_deadline_hours))) {
                throw ValidationException::withMessages(['appointment' => 'The booking deadline has passed.']);
            }
            $hasOverlap = AppointmentBooking::where('user_id', $user->id)->whereIn('status', ['booked', 'checked_in'])
                ->whereHas('slot', fn ($q) => $q->where('start_time', '<', $slot->end_time)->where('end_time', '>', $slot->start_time))->exists();
            if ($hasOverlap) throw ValidationException::withMessages(['appointment' => 'You already have another appointment at this time.']);
            $coachHasOverlap = AppointmentBooking::whereIn('status', ['booked', 'checked_in'])
                ->whereHas('slot', fn ($q) => $q->where('coach_id', $slot->coach_id)->where('start_time', '<', $slot->end_time)->where('end_time', '>', $slot->start_time))->exists();
            if ($coachHasOverlap) throw ValidationException::withMessages(['appointment' => 'This coach already has another appointment at this time.']);

            $eligiblePackageIds = $service->packages->pluck('id');
            $query = UserSubscription::with('package')->where('user_id', $user->id)
                ->where('status', 'active')->where('expires_at', '>', $slot->end_time)
                ->where(fn ($q) => $q->where('is_unlimited', true)->orWhere('credits_remaining', '>=', $service->credits_required));
            if ($eligiblePackageIds->isNotEmpty()) $query->whereIn('package_id', $eligiblePackageIds);
            $subscription = $query->orderBy('expires_at')->lockForUpdate()->first();
            if (! $subscription) throw ValidationException::withMessages(['appointment' => 'No eligible active package or insufficient credits.']);

            $charge = $subscription->isUnlimited() ? 0 : $service->credits_required;
            $booking = AppointmentBooking::updateOrCreate(
                ['appointment_slot_id' => $slot->id, 'user_id' => $user->id],
                ['user_subscription_id' => $subscription->id, 'status' => 'booked', 'credits_charged' => $charge, 'credit_refunded_at' => null, 'booked_at' => now(), 'cancelled_at' => null]
            );
            if ($charge) {
                $subscription->decrement('credits_remaining', $charge);
                $subscription->refresh();
                CreditTransaction::create(['user_id' => $user->id, 'user_subscription_id' => $subscription->id, 'type' => 'booking_deduction', 'amount' => -$charge, 'balance_after' => $subscription->credits_remaining, 'reason' => "Appointment booking #{$booking->id}"]);
            }
            $user->syncCreditSummary();
            return $booking;
        });
    }

    public function cancel(AppointmentBooking $booking, ?bool $refundOverride = null): void
    {
        DB::transaction(function () use ($booking, $refundOverride) {
            $booking = AppointmentBooking::with('slot.service')->lockForUpdate()->findOrFail($booking->id);
            if (! in_array($booking->status, ['booked', 'checked_in', 'cancelled', 'late_cancel'])) return;
            $cutoff = $booking->slot->service->cancellation_cutoff_hours;
            $policyRefundable = $booking->status === 'booked' && ($cutoff === null || now()->lt($booking->slot->start_time->copy()->subHours($cutoff)));
            $refundable = $refundOverride ?? $policyRefundable;

            if (in_array($booking->status, ['booked', 'checked_in'])) {
                $booking->update(['status' => $refundable ? 'cancelled' : 'late_cancel', 'cancelled_at' => now()]);
            }

            if ($refundable && ! $booking->credit_refunded_at && $booking->credits_charged > 0 && $booking->user_subscription_id) {
                $sub = UserSubscription::lockForUpdate()->find($booking->user_subscription_id);
                if ($sub) {
                    $sub->increment('credits_remaining', $booking->credits_charged);
                    $sub->refresh();
                    CreditTransaction::create(['user_id' => $booking->user_id, 'user_subscription_id' => $sub->id, 'type' => 'booking_refund', 'amount' => $booking->credits_charged, 'balance_after' => $sub->credits_remaining, 'reason' => "Appointment cancellation #{$booking->id}"]);
                    $booking->update(['credit_refunded_at' => now()]);
                    $booking->user()->first()?->syncCreditSummary();
                }
            }
        });
    }
}
