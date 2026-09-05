<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use App\Models\ClassBooking;
use App\Models\GymClass;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CoachClassController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();

        // Admins see all classes; coaches see their own
        $query = GymClass::withCount('bookings')->orderBy('start_time');

        if (! $user->isAdmin()) {
            $query->where('coach_id', $user->id);
        }

        $classes = $query->get();

        return Inertia::render('Coach/Classes', [
            'classes' => $classes,
        ]);
    }

    public function show(GymClass $gymClass): Response
    {
        $user = auth()->user();

        // Non-admin coaches may only view their own classes
        if (! $user->isAdmin() && $gymClass->coach_id !== $user->id) {
            abort(403);
        }

        $gymClass->load([
            'bookings' => fn ($q) => $q->with('user:id,name,email')->active(),
        ]);

        $attendees = $gymClass->bookings->where('status', '!=', 'waitlisted')->values();
        $waitlisted = $gymClass->bookings->where('status', 'waitlisted')->sortBy('queue_position')->values();

        return Inertia::render('Coach/ClassDetail', [
            'gymClass' => $gymClass,
            'attendees' => $attendees,
            'waitlist' => $waitlisted,
        ]);
    }

    public function updateAttendance(Request $request, GymClass $gymClass): RedirectResponse
    {
        $user = auth()->user();

        if (! $user->isAdmin() && $gymClass->coach_id !== $user->id) {
            abort(403);
        }

        $data = $request->validate([
            'booking_id' => 'required|integer|exists:class_bookings,id',
            'status' => 'required|string|in:checked_in,no_show,booked',
        ]);

        $booking = ClassBooking::where('gym_class_id', $gymClass->id)
            ->findOrFail($data['booking_id']);

        $booking->status = $data['status'];

        if ($data['status'] === 'checked_in') {
            $booking->checked_in_at = now();
        }

        $booking->save();

        return back()->with('success', 'Attendance updated.');
    }
}
