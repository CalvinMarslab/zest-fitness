<?php

namespace App\Http\Controllers;

use App\Models\AppointmentBooking;
use App\Models\AppointmentSlot;
use App\Services\AppointmentBookingService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AppointmentController extends Controller
{
    public function __construct(private AppointmentBookingService $bookingService) {}
    public function index(): Response
    {
        $user = auth()->user();
        $slots = AppointmentSlot::with(['service:id,name,description,color,duration_minutes,credits_required,release_days,booking_deadline_hours', 'coach:id,name', 'booking:id,appointment_slot_id'])
            ->where('status', 'available')->where('start_time', '>', now())->where('start_time', '<=', now()->addDays(60))
            ->whereHas('service', fn ($q) => $q->where('is_public', true)->where('is_active', true))
            ->orderBy('start_time')->get()->filter(fn ($slot) => ! $slot->booking)
            ->map(fn ($slot) => ['id' => $slot->id, 'service' => $slot->service->name, 'description' => $slot->service->description, 'color' => $slot->service->color, 'credits' => $slot->service->credits_required, 'coach' => $slot->coach->name, 'location' => $slot->location, 'start_time' => $slot->start_time->toIso8601String(), 'end_time' => $slot->end_time->toIso8601String()])->values();
        $bookings = AppointmentBooking::with(['slot.service:id,name,color', 'slot.coach:id,name'])->where('user_id', $user->id)->whereIn('status', ['booked', 'checked_in'])->whereHas('slot', fn ($q) => $q->where('start_time', '>', now()))->get();
        return Inertia::render('Appointments', ['slots' => $slots, 'bookings' => $bookings]);
    }
    public function store(AppointmentSlot $slot): RedirectResponse { $this->bookingService->book(auth()->user(), $slot); return back()->with('success', 'Appointment booked.'); }
    public function destroy(AppointmentBooking $booking): RedirectResponse { abort_unless($booking->user_id === auth()->id(), 403); $this->bookingService->cancel($booking); return back()->with('success', 'Appointment cancelled.'); }
}
