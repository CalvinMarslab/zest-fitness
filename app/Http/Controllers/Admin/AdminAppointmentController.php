<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppointmentBooking;
use App\Models\AppointmentService;
use App\Models\AppointmentSlot;
use App\Models\Package;
use App\Models\User;
use App\Services\AppointmentBookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdminAppointmentController extends Controller
{
    public function __construct(private AppointmentBookingService $bookingService) {}
    public function index(): Response
    {
        return Inertia::render('Admin/Appointments', [
            'services' => AppointmentService::with('packages:id,name')->withCount('slots')->orderBy('name')->get(),
            'slots' => AppointmentSlot::with(['service:id,name,color', 'coach:id,name', 'booking.user:id,name'])->where('start_time', '>', now()->subDay())->orderBy('start_time')->limit(200)->get(),
            'coaches' => User::whereIn('role', ['coach', 'admin'])->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'packages' => Package::where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'members' => User::where('role', 'member')->where('status', 'active')->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }
    public function storeService(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => 'required|string|max:100', 'description' => 'nullable|string|max:1000', 'color' => 'nullable|string|max:20', 'duration_minutes' => 'required|integer|min:15|max:480', 'interval_minutes' => 'required|integer|min:5|max:240', 'credits_required' => 'required|integer|min:1|max:100', 'release_days' => 'required|integer|min:1|max:365', 'booking_deadline_hours' => 'required|integer|min:0|max:720', 'cancellation_cutoff_hours' => 'nullable|integer|min:0|max:720', 'is_public' => 'required|boolean', 'package_ids' => 'array', 'package_ids.*' => 'integer|exists:packages,id']);
        $packages = $data['package_ids'] ?? []; unset($data['package_ids']);
        $service = AppointmentService::create($data); $service->packages()->sync($packages);
        return back()->with('success', 'Appointment service created.');
    }
    public function updateService(Request $request, AppointmentService $service): RedirectResponse
    {
        $data = $request->validate(['name' => 'sometimes|string|max:100', 'description' => 'nullable|string|max:1000', 'color' => 'nullable|string|max:20', 'duration_minutes' => 'sometimes|integer|min:15|max:480', 'interval_minutes' => 'sometimes|integer|min:5|max:240', 'credits_required' => 'sometimes|integer|min:1|max:100', 'release_days' => 'sometimes|integer|min:1|max:365', 'booking_deadline_hours' => 'sometimes|integer|min:0|max:720', 'cancellation_cutoff_hours' => 'nullable|integer|min:0|max:720', 'is_public' => 'sometimes|boolean', 'is_active' => 'sometimes|boolean', 'package_ids' => 'sometimes|array', 'package_ids.*' => 'integer|exists:packages,id']);
        if (array_key_exists('package_ids', $data)) { $service->packages()->sync($data['package_ids']); unset($data['package_ids']); }
        $service->update($data); return back();
    }
    public function storeSlot(Request $request): RedirectResponse
    {
        $data = $request->validate(['appointment_service_id' => 'required|exists:appointment_services,id', 'coach_id' => ['required', Rule::exists('users', 'id')->where(fn ($q) => $q->whereIn('role', ['coach', 'admin']))], 'start_time' => 'required|date|after:now', 'location' => 'nullable|string|max:100']);
        $service = AppointmentService::findOrFail($data['appointment_service_id']);
        $start = Carbon::parse($data['start_time']);
        $end = $start->copy()->addMinutes($service->duration_minutes);
        $duplicate = AppointmentSlot::where('coach_id', $data['coach_id'])->where('start_time', $start)->exists();
        if ($duplicate) throw ValidationException::withMessages(['start_time' => 'This coach already has availability at this exact time.']);
        AppointmentSlot::create([...$data, 'end_time' => $end]);
        return back()->with('success', 'Appointment availability added.');
    }
    public function destroySlot(AppointmentSlot $slot): RedirectResponse { abort_if($slot->booking()->exists(), 422, 'Booked slots cannot be deleted.'); $slot->delete(); return back(); }
    public function bookForMember(Request $request, AppointmentSlot $slot): RedirectResponse
    {
        $data = $request->validate(['user_id' => 'required|exists:users,id']);
        $this->bookingService->book(User::findOrFail($data['user_id']), $slot, true);
        return back()->with('success', 'Appointment booked for member.');
    }
    public function updateAttendance(Request $request, AppointmentBooking $booking): RedirectResponse
    {
        $data = $request->validate(['status' => 'required|in:booked,checked_in,no_show']);
        $booking->update(['status' => $data['status'], 'checked_in_at' => $data['status'] === 'checked_in' ? now() : null]);
        return back()->with('success', 'Appointment attendance updated.');
    }
    public function cancelBooking(Request $request, AppointmentBooking $booking): RedirectResponse
    {
        $data = $request->validate(['refund' => 'required|boolean']);
        $this->bookingService->cancel($booking, $data['refund']);

        return back()->with('success', $data['refund']
            ? 'Appointment cancelled and credits refunded.'
            : 'Appointment cancelled without refund.');
    }
}
