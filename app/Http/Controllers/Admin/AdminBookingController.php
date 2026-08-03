<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassBooking;
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
            ->when($request->search, fn ($q) => $q->whereHas('user', fn ($u) =>
                $u->where('name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%")
            ))
            ->when($request->date, fn ($q) => $q->whereHas('gymClass', fn ($c) =>
                $c->whereDate('start_time', $request->date)
            ))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Admin/Bookings', [
            'bookings' => $bookings,
            'filters'  => $request->only('search', 'date'),
        ]);
    }

    public function destroy(ClassBooking $booking): RedirectResponse
    {
        // Refund credit when admin cancels a booking
        $booking->user->refundCredit();
        $booking->delete();

        return back();
    }
}
