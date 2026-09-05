<?php

namespace App\Http\Controllers;

use App\Models\ClassBooking;
use App\Models\GymClass;
use App\Services\BookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    private const GYM_CLASS_RULES = ['gym_class_id' => ['required', 'integer', 'exists:gym_classes,id']];

    public function __construct(private readonly BookingService $bookingService) {}

    public function store(Request $request): RedirectResponse
    {
        $request->validate(self::GYM_CLASS_RULES);

        /** @var \App\Models\User $user */
        $user = auth()->user();
        $class = GymClass::findOrFail($request->gym_class_id);

        $result = $this->bookingService->book($user, $class);

        return match ($result['status']) {
            'suspended' => back()->withErrors(['gym_class_id' => 'Your account is suspended. Please contact the gym.']),
            'cancelled' => back()->withErrors(['gym_class_id' => 'This class has been cancelled.']),
            'not_open' => back()->withErrors(['gym_class_id' => "Booking opens on {$result['opens_at']}."]),
            'closed' => back()->withErrors(['gym_class_id' => 'Booking for this class is closed.']),
            'no_subscription' => back()->withErrors(['gym_class_id' => 'You need an active membership to book.']),
            'no_credits' => back()->withErrors(['gym_class_id' => 'You have no credits left.']),
            'already_booked' => back()->with('success', "You're already booked into this class."),
            'waitlisted' => back()->with('success', "Added to waitlist. Position: #{$result['position']}."),
            default => back()->with('success', "Booked! You have {$result['credits_remaining']} credit(s) remaining."),
        };
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validate(self::GYM_CLASS_RULES);

        /** @var \App\Models\User $user */
        $user = auth()->user();

        $booking = ClassBooking::where('user_id', $user->id)
            ->where('gym_class_id', $request->gym_class_id)
            ->whereIn('status', ['booked', 'waitlisted', 'checked_in'])
            ->first();

        if (! $booking) {
            return back()->with('success', 'No active booking found.');
        }

        $result = $this->bookingService->cancel($booking, $user);

        return match ($result['status']) {
            'already_cancelled' => back()->with('success', 'Booking is already cancelled.'),
            'cancelled_waitlist' => back()->with('success', 'Removed from waitlist.'),
            'late_cancel' => back()->with('success', 'Booking cancelled (late cancellation — credit not refunded).'),
            default => back()->with('success', 'Booking cancelled. Your credit has been refunded.'),
        };
    }
}
