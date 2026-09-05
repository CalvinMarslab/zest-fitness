<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassBooking;
use App\Models\GymClass;
use App\Services\BookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminBookingController extends Controller
{
    public function __construct(private readonly BookingService $bookingService) {}

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
        $this->bookingService->cancel($booking, auth()->user(), forceRefund: true);

        return back()->with('success', 'Booking cancelled and credit refunded.');
    }

    public function updateAttendance(Request $request, ClassBooking $booking): RedirectResponse
    {
        $data = $request->validate([
            'status' => 'required|string|in:checked_in,no_show,booked',
        ]);

        // Enforce valid status transitions
        $currentStatus = $booking->status;
        $newStatus = $data['status'];

        // Prevent transitioning from cancelled/late_cancel without note
        if (in_array($currentStatus, ['cancelled', 'late_cancel'])) {
            return back()->withErrors(['status' => 'Cannot update attendance for a cancelled booking.']);
        }

        $booking->status = $newStatus;

        if ($newStatus === 'checked_in') {
            $booking->checked_in_at = now();
        }

        $booking->save();

        return back()->with('success', 'Attendance updated.');
    }

    public function promoteWaitlist(ClassBooking $booking): RedirectResponse
    {
        abort_if($booking->status !== 'waitlisted', 422, 'Booking is not on the waitlist.');

        $class = GymClass::lockForUpdate()->findOrFail($booking->gym_class_id);

        $confirmedCount = ClassBooking::where('gym_class_id', $class->id)
            ->whereIn('status', ['booked', 'checked_in'])
            ->count();

        abort_if($confirmedCount >= $class->capacity, 422, 'Class is at full capacity.');

        $this->bookingService->promoteWaitlist($class);

        return back()->with('success', 'Waitlist entry promoted to confirmed booking.');
    }
}
