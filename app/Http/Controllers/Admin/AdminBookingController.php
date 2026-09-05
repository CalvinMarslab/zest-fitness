<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassBooking;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminBookingController extends Controller
{
    public function index(Request $request): Response
    {
        $bookings = ClassBooking::with([
            'user:id,name,email',
            'gymClass:id,name,start_time,capacity',
        ])
            ->when($request->search, fn ($q) => $q->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$request->search}%")
                ->orWhere('email', 'like', "%{$request->search}%")
            ))
            ->when($request->date, fn ($q) => $q->whereHas('gymClass', fn ($c) => $c->whereDate('start_time', $request->date)
            ))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Admin/Bookings', [
            'bookings' => $bookings,
            'filters' => $request->only('search', 'date'),
        ]);
    }

    public function destroy(ClassBooking $booking): RedirectResponse
    {
        // Refund credit when admin cancels a booking
        if (in_array($booking->status, ['booked', 'checked_in'])) {
            $booking->user->refundCredit();
        }
        $booking->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        return back();
    }

    public function updateAttendance(Request $request, ClassBooking $booking): RedirectResponse
    {
        $data = $request->validate([
            'status' => 'required|string|in:checked_in,no_show,booked',
        ]);

        $booking->status = $data['status'];

        if ($data['status'] === 'checked_in') {
            $booking->checked_in_at = now();
        }

        $booking->save();

        return back()->with('success', 'Attendance updated.');
    }

    public function promoteWaitlist(ClassBooking $booking): RedirectResponse
    {
        abort_if($booking->status !== 'waitlisted', 422, 'Booking is not on the waitlist.');

        \DB::transaction(function () use ($booking) {
            $user = User::lockForUpdate()->findOrFail($booking->user_id);

            $booking->update(['status' => 'booked', 'queue_position' => null]);

            if ($user->credits > 0) {
                $user->deductCredit();
            }
        });

        return back()->with('success', 'Waitlist entry promoted to confirmed booking.');
    }
}
