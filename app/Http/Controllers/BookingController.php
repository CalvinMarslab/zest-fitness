<?php

namespace App\Http\Controllers;

use App\Models\ClassBooking;
use App\Models\GymClass;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    private const GYM_CLASS_RULES = ['gym_class_id' => ['required', 'integer', 'exists:gym_classes,id']];

    public function store(Request $request): RedirectResponse
    {
        $request->validate(self::GYM_CLASS_RULES);

        $result = DB::transaction(function () use ($request) {
            /** @var User $user */
            $user = User::where('id', auth()->id())->lockForUpdate()->firstOrFail();
            $class = GymClass::lockForUpdate()->findOrFail($request->gym_class_id);

            // Class must not be cancelled
            if ($class->is_cancelled || $class->status === 'cancelled') {
                return ['status' => 'cancelled', 'name' => $class->name];
            }

            // Booking window checks
            if ($class->booking_opens_at && $class->booking_opens_at->isFuture()) {
                return ['status' => 'not_open', 'name' => $class->name, 'opens_at' => $class->booking_opens_at->format('d M H:i')];
            }
            if ($class->booking_closes_at && $class->booking_closes_at->isPast()) {
                return ['status' => 'closed', 'name' => $class->name];
            }

            // Check for existing booking row (any status)
            $existing = ClassBooking::where('user_id', $user->id)
                ->where('gym_class_id', $class->id)
                ->lockForUpdate()
                ->first();

            // Already actively booked/waitlisted
            if ($existing && in_array($existing->status, ['booked', 'waitlisted', 'checked_in'])) {
                return ['status' => 'already_booked', 'name' => $class->name];
            }

            // Credit / subscription check
            if ($user->credits <= 0) {
                return ['status' => 'no_credits', 'name' => $class->name];
            }

            // Capacity check (confirmed only)
            $confirmedCount = ClassBooking::where('gym_class_id', $class->id)
                ->whereIn('status', ['booked', 'checked_in'])
                ->count();

            if ($confirmedCount >= $class->capacity) {
                // Add to waitlist — update existing cancelled row or create new
                $queuePosition = ClassBooking::where('gym_class_id', $class->id)
                    ->where('status', 'waitlisted')
                    ->max('queue_position') + 1;

                if ($existing) {
                    $existing->update([
                        'status' => 'waitlisted',
                        'queue_position' => $queuePosition,
                        'cancelled_at' => null,
                    ]);
                } else {
                    ClassBooking::create([
                        'user_id' => $user->id,
                        'gym_class_id' => $class->id,
                        'status' => 'waitlisted',
                        'queue_position' => $queuePosition,
                    ]);
                }

                return ['status' => 'waitlisted', 'name' => $class->name, 'position' => $queuePosition];
            }

            // Book the class — update existing cancelled row or create new
            if ($existing) {
                $existing->update([
                    'status' => 'booked',
                    'queue_position' => null,
                    'cancelled_at' => null,
                    'checked_in_at' => null,
                ]);
            } else {
                ClassBooking::create([
                    'user_id' => $user->id,
                    'gym_class_id' => $class->id,
                    'status' => 'booked',
                ]);
            }

            $user->deductCredit();

            return ['status' => 'booked', 'name' => $class->name, 'credits' => $user->fresh()->credits];
        });

        return match ($result['status']) {
            'cancelled' => back()->withErrors(['gym_class_id' => 'This class has been cancelled.']),
            'not_open' => back()->withErrors(['gym_class_id' => "Booking opens on {$result['opens_at']}."]),
            'closed' => back()->withErrors(['gym_class_id' => 'Booking for this class is closed.']),
            'no_credits' => back()->withErrors(['gym_class_id' => 'You have no credits left.']),
            'already_booked' => back()->with('success', "You're already booked into {$result['name']}."),
            'waitlisted' => back()->with('success', "Added to waitlist for {$result['name']}. Position: #{$result['position']}."),
            default => back()->with('success', "Booked! You have {$result['credits']} credit(s) remaining."),
        };
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validate(self::GYM_CLASS_RULES);

        $result = DB::transaction(function () use ($request) {
            /** @var User $user */
            $user = User::where('id', auth()->id())->lockForUpdate()->firstOrFail();
            $class = GymClass::lockForUpdate()->findOrFail($request->gym_class_id);

            // Find the active booking
            $booking = ClassBooking::where('user_id', $user->id)
                ->where('gym_class_id', $class->id)
                ->whereIn('status', ['booked', 'waitlisted', 'checked_in'])
                ->lockForUpdate()
                ->first();

            if (! $booking) {
                return ['status' => 'not_found'];
            }

            // Waitlisted cancellation — no credit was charged
            if ($booking->status === 'waitlisted') {
                $booking->update(['status' => 'cancelled', 'cancelled_at' => now()]);

                return ['status' => 'cancelled_waitlist'];
            }

            // Check cutoff for confirmed bookings
            $cutoffHours = $class->cancellation_cutoff_hours
                ?? (int) SystemSetting::get('cancellation_cutoff_hours', 2);

            $cutoffTime = $class->start_time->copy()->subHours($cutoffHours);
            $isLateCancellation = now()->gte($cutoffTime);

            if ($isLateCancellation) {
                $booking->update(['status' => 'late_cancel', 'cancelled_at' => now()]);
                $lateCancelLosesCredit = SystemSetting::get('late_cancel_loses_credit', 'true') === 'true';
                if (! $lateCancelLosesCredit) {
                    $user->refundCredit();
                }
                $this->promoteWaitlist($class);

                return ['status' => 'late_cancel'];
            }

            // Normal cancellation — refund credit
            $booking->update(['status' => 'cancelled', 'cancelled_at' => now()]);
            $user->refundCredit();
            $this->promoteWaitlist($class);

            return ['status' => 'cancelled', 'credits' => $user->fresh()->credits];
        });

        return match ($result['status']) {
            'not_found' => back()->with('success', 'No active booking found.'),
            'cancelled_waitlist' => back()->with('success', 'Removed from waitlist.'),
            'late_cancel' => back()->with('success', 'Booking cancelled (late cancellation — credit not refunded).'),
            default => back()->with('success', "Booking cancelled. Your credit has been refunded. You have {$result['credits']} credit(s) remaining."),
        };
    }

    /**
     * Promote the first waitlisted member when a confirmed spot opens up.
     */
    private function promoteWaitlist(GymClass $class): void
    {
        $next = ClassBooking::where('gym_class_id', $class->id)
            ->where('status', 'waitlisted')
            ->orderBy('queue_position')
            ->lockForUpdate()
            ->first();

        if (! $next) {
            return;
        }

        /** @var User $waitlistUser */
        $waitlistUser = User::where('id', $next->user_id)->lockForUpdate()->first();

        if (! $waitlistUser || $waitlistUser->credits <= 0) {
            return;
        }

        $next->update(['status' => 'booked', 'queue_position' => null]);
        $waitlistUser->deductCredit();
    }
}
