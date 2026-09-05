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
    private const VALID_TRANSITIONS = [
        'booked' => ['checked_in', 'no_show'],
        'checked_in' => ['booked', 'no_show'],
        'no_show' => ['booked', 'checked_in'],
    ];

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
            'note' => 'nullable|string|max:500',
        ]);

        $newStatus = $data['status'];

        // Cannot update attendance for cancelled or waitlisted bookings
        if (in_array($booking->status, ['cancelled', 'late_cancel', 'waitlisted'])) {
            return back()->withErrors(['status' => 'Cannot update attendance for a cancelled or waitlisted booking.']);
        }

        $validNext = self::VALID_TRANSITIONS[$booking->status] ?? [];
        if (! in_array($newStatus, $validNext)) {
            return back()->withErrors(['status' => "Cannot transition from {$booking->status} to {$newStatus}."]);
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

        $class = GymClass::findOrFail($booking->gym_class_id);
        $result = $this->bookingService->promoteWaitlist($class);

        return match ($result['status']) {
            'promoted' => back()->with('success', 'Waitlist entry promoted to confirmed booking.'),
            'full' => back()->withErrors(['waitlist' => 'Class is at full capacity.']),
            'no_eligible_waitlist' => back()->with('success', 'No eligible waitlist entries to promote.'),
            default => back()->with('success', 'Waitlist processed.'),
        };
    }
}
