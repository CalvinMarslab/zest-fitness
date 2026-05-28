<?php

namespace App\Http\Controllers;

use App\Models\ClassBooking;
use App\Models\GymClass;
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
            $user  = User::where('id', auth()->id())->lockForUpdate()->firstOrFail();
            $class = GymClass::lockForUpdate()->withCount('bookings')->findOrFail($request->gym_class_id);

            if ($class->bookings_count >= $class->capacity) {
                return ['status' => 'full', 'name' => $class->name];
            }

            if ($user->credits <= 0) {
                return ['status' => 'no_credits', 'name' => $class->name];
            }

            [$booking, $created] = [
                ClassBooking::firstOrCreate([
                    'user_id'      => $user->id,
                    'gym_class_id' => $class->id,
                ]),
                false,
            ];

            if (! $booking->wasRecentlyCreated) {
                return ['status' => 'already_booked', 'name' => $class->name];
            }

            $user->deductCredit();

            return ['status' => 'booked', 'name' => $class->name, 'credits' => $user->fresh()->credits];
        });

        return match ($result['status']) {
            'full'           => back()->withErrors(['gym_class_id' => 'This class is full.']),
            'no_credits'     => back()->withErrors(['gym_class_id' => 'You have no credits left.']),
            'already_booked' => back()->with('success', "You're already booked into {$result['name']}."),
            default          => back()->with('success', "Booked! You have {$result['credits']} credit(s) remaining."),
        };
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validate(self::GYM_CLASS_RULES);

        $deleted = ClassBooking::where('user_id', auth()->id())
            ->where('gym_class_id', $request->gym_class_id)
            ->delete();

        if ($deleted) {
            auth()->user()->refundCredit();
            return back()->with('success', 'Booking cancelled. Your credit has been refunded.');
        }

        return back()->with('success', 'Booking cancelled.');
    }
}
