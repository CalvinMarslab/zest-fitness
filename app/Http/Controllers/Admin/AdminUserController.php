<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassBooking;
use App\Models\GymClass;
use App\Models\Package;
use App\Models\User;
use App\Models\UserSubscription;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserController extends Controller
{
    public function index(): Response
    {
        $select = ['id', 'name', 'email', 'credits', 'is_admin', 'role', 'status', 'created_at'];

        $team = User::withCount(['bookings', 'activities'])
            ->where(fn ($q) => $q->where('is_admin', true)->orWhere('role', 'admin'))
            ->orderBy('name')
            ->get($select);

        $customers = User::withCount(['bookings', 'activities'])
            ->where('is_admin', false)
            ->where('role', 'member')
            ->orderBy('name')
            ->get($select);

        $now = now();
        $threeMonthsLater = now()->addMonths(3);
        $cq = fn () => User::where('is_admin', false)->where('role', 'member');

        $stats = [
            'total' => $cq()->count(),
            'active' => $cq()->whereHas('subscriptions', fn ($q) => $q->where('expires_at', '>', $threeMonthsLater)->where('status', 'active'))->count(),
            'expiring_soon' => $cq()->whereHas('subscriptions', fn ($q) => $q->where('expires_at', '>', $now)->where('expires_at', '<=', $threeMonthsLater)->where('status', 'active'))
                ->whereDoesntHave('subscriptions', fn ($q) => $q->where('expires_at', '>', $threeMonthsLater)->where('status', 'active'))
                ->count(),
            'expired' => $cq()->whereDoesntHave('subscriptions', fn ($q) => $q->where('expires_at', '>', $now)->where('status', 'active'))->count(),
        ];

        return Inertia::render('Admin/Users', compact('team', 'customers', 'stats'));
    }

    public function show(User $user): Response
    {
        $user->load([
            'subscriptions.package',
            'bookings' => fn ($q) => $q->with('gymClass:id,name,start_time,capacity')
                ->orderByDesc('created_at')
                ->limit(50),
        ]);

        $packages = Package::where('is_active', true)->orderBy('sort_order')->get();

        return Inertia::render('Admin/UserProfile', [
            'member' => $user,
            'packages' => $packages,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'credits' => 'sometimes|integer|min:0',
            'is_admin' => 'sometimes|boolean',
            'role' => 'sometimes|string|in:admin,coach,member',
            'phone' => 'sometimes|nullable|string|max:30',
            'notes' => 'sometimes|nullable|string',
        ]);

        $user->forceFill($data)->save();

        return back();
    }

    public function destroy(User $user): RedirectResponse
    {
        abort_if($user->id === auth()->id(), 422, 'Cannot delete yourself.');
        $user->delete();

        return back();
    }

    public function assignSubscription(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'package_id' => 'required|exists:packages,id',
            'started_at' => 'sometimes|date',
        ]);

        $package = Package::findOrFail($data['package_id']);
        $startedAt = isset($data['started_at']) ? Carbon::parse($data['started_at']) : now();
        $expiresAt = $startedAt->copy()->addDays($package->period_days);

        DB::transaction(function () use ($user, $package, $startedAt, $expiresAt) {
            $sub = UserSubscription::create([
                'user_id' => $user->id,
                'package_id' => $package->id,
                'credits_granted' => $package->credits,
                'credits_remaining' => $package->credits,
                'started_at' => $startedAt,
                'expires_at' => $expiresAt,
                'status' => 'active',
                'assigned_by' => auth()->id(),
            ]);

            // Add credits to user balance
            $user->increment('credits', $package->credits);
        });

        return back()->with('success', "Assigned {$package->name} to {$user->name}.");
    }

    public function adjustCredits(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'adjustment' => 'required|integer',
            'notes' => 'sometimes|nullable|string|max:500',
        ]);

        $newCredits = max(0, $user->credits + $data['adjustment']);
        $user->update(['credits' => $newCredits]);

        return back()->with('success', "Credits adjusted. New balance: {$newCredits}.");
    }

    public function bookForMember(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'gym_class_id' => 'required|integer|exists:gym_classes,id',
        ]);

        DB::transaction(function () use ($user, $data) {
            $userLocked = User::lockForUpdate()->findOrFail($user->id);
            $class = GymClass::lockForUpdate()->findOrFail($data['gym_class_id']);

            $existing = ClassBooking::where('user_id', $userLocked->id)
                ->where('gym_class_id', $class->id)
                ->whereIn('status', ['booked', 'waitlisted', 'checked_in'])
                ->first();

            if ($existing) {
                return;
            }

            ClassBooking::create([
                'user_id' => $userLocked->id,
                'gym_class_id' => $class->id,
                'status' => 'booked',
            ]);

            if ($userLocked->credits > 0) {
                $userLocked->deductCredit();
            }
        });

        return back()->with('success', "Booked {$user->name} into class.");
    }

    public function cancelBookingForMember(Request $request, User $user, ClassBooking $booking): RedirectResponse
    {
        abort_if($booking->user_id !== $user->id, 404);

        DB::transaction(function () use ($user, $booking) {
            $wasConfirmed = in_array($booking->status, ['booked', 'checked_in']);
            $booking->update(['status' => 'cancelled', 'cancelled_at' => now()]);

            if ($wasConfirmed) {
                $user->refundCredit();
            }
        });

        return back()->with('success', 'Booking cancelled and credit refunded.');
    }

    public function updateStatus(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'status' => 'required|string|in:active,suspended',
        ]);

        $user->update(['status' => $data['status']]);

        return back()->with('success', "User status updated to {$data['status']}.");
    }
}
