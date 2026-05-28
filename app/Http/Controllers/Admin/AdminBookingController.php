<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassBooking;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AdminBookingController extends Controller
{
    public function index(): Response
    {
        $bookings = ClassBooking::with([
            'user:id,name,email',
            'gymClass:id,name,start_time,capacity',
        ])
            ->latest()
            ->paginate(30);

        return Inertia::render('Admin/Bookings', ['bookings' => $bookings]);
    }

    public function destroy(ClassBooking $booking): RedirectResponse
    {
        // Refund credit when admin cancels a booking
        $booking->user->refundCredit();
        $booking->delete();

        return back();
    }
}
