<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassBooking;
use App\Models\CreditTransaction;
use App\Models\GymClass;
use App\Models\Package;
use App\Models\User;
use App\Models\UserSubscription;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserController extends Controller
{
    public function __construct(private readonly BookingService $bookingService) {}

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
            'subscriptions' => fn ($q) => $q->with('package', 'assignedBy:id,name')->orderByDesc('created_at'),
        ]);

        $now = now();

        $upcomingBookings = ClassBooking::where('user_id', $user->id)
            ->whereIn('status', ['booked', 'waitlisted', 'checked_in'])
            ->with('gymClass:id,name,coach,start_time,capacity')
            ->whereHas('gymClass', fn ($q) => $q->where('start_time', '>=', $now))
            ->orderBy('created_at')
            ->get()
            ->map(fn ($b) => $this->formatBookingForAdmin($b));

        $recentBookings = ClassBooking::where('user_id', $user->id)
            ->with('gymClass:id,name,coach,start_time,capacity')
            ->whereHas('gymClass', fn ($q) => $q->where('start_time', '<', $now))
            ->orderByDesc('created_at')
            ->limit(30)
            ->get()
            ->map(fn ($b) => $this->formatBookingForAdmin($b));

        $creditHistory = CreditTransaction::where('user_id', $user->id)
            ->with(['booking.gymClass:id,name,start_time', 'actor:id,name', 'subscription.package:id,name'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $packages = Package::where('is_active', true)->orderBy('sort_order')->get();

        $upcomingClasses = GymClass::where('start_time', '>=', $now)
            ->where('is_cancelled', false)
            ->orderBy('start_time')
            ->limit(30)
            ->withCount([
                'bookings as confirmed_count' => fn ($q) => $q->whereIn('status', ['booked', 'checked_in']),
            ])
            ->get(['id', 'name', 'coach', 'start_time', 'capacity'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'coach' => $c->coach,
                'start_time' => $c->start_time->toIso8601String(),
                'capacity' => $c->capacity,
                'confirmed_count' => $c->confirmed_count,
                'spots_left' => max(0, $c->capacity - $c->confirmed_count),
            ]);

        return Inertia::render('Admin/UserProfile', [
            'member' => $user,
            'packages' => $packages,
            'upcomingClasses' => $upcomingClasses,
            'upcomingBookings' => $upcomingBookings,
            'recentBookings' => $recentBookings,
            'creditHistory' => $creditHistory,
        ]);
    }

    private function formatBookingForAdmin(ClassBooking $booking): array
    {
        return [
            'id' => $booking->id,
            'gym_class_id' => $booking->gym_class_id,
            'status' => $booking->status,
            'queue_position' => $booking->queue_position,
            'credit_charged' => $booking->credit_charged,
            'booked_at' => $booking->booked_at?->toIso8601String(),
            'cancelled_at' => $booking->cancelled_at?->toIso8601String(),
            'checked_in_at' => $booking->checked_in_at?->toIso8601String(),
            'gym_class' => $booking->gymClass ? [
                'id' => $booking->gymClass->id,
                'name' => $booking->gymClass->name,
                'coach' => $booking->gymClass->coach,
                'start_time' => $booking->gymClass->start_time->toIso8601String(),
            ] : null,
        ];
    }

    public function searchMembers(Request $request): JsonResponse
    {
        $q = trim($request->string('q'));

        if (mb_strlen($q) < 2) {
            return response()->json(['members' => []]);
        }

        $members = User::where('is_admin', false)
            ->where('role', 'member')
            ->where(fn ($query) => $query
                ->where('name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")
                ->orWhere('phone', 'like', "%{$q}%")
            )
            ->orderBy('name')
            ->limit(20)
            ->with(['subscriptions' => fn ($sq) => $sq
                ->where('status', 'active')
                ->where('expires_at', '>', now())
                ->with('package:id,name,is_unlimited,weekly_booking_limit')
                ->orderByDesc('expires_at'),
            ])
            ->get(['id', 'name', 'email', 'phone'])
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'active_subs' => $u->subscriptions->map(fn ($s) => [
                    'package_name' => $s->package->name ?? '—',
                    'is_unlimited' => (bool) $s->is_unlimited,
                    'credits_remaining' => $s->credits_remaining,
                    'expires_at' => $s->expires_at->toIso8601String(),
                    'weekly_limit' => $s->package->weekly_booking_limit ?? null,
                ])->values(),
            ]);

        return response()->json(['members' => $members]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
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

        $hasHistory = $user->bookings()->exists() || $user->creditTransactions()->exists();
        abort_if($hasHistory, 422, 'Cannot delete a member with booking or credit history. Suspend the account instead.');

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
            // Unlimited packages always get credits=0; finite packages copy package credits.
            $granted = $package->is_unlimited ? 0 : $package->credits;

            $sub = UserSubscription::create([
                'user_id' => $user->id,
                'package_id' => $package->id,
                'credits_granted' => $granted,
                'credits_remaining' => $granted,
                'started_at' => $startedAt,
                'expires_at' => $expiresAt,
                'status' => 'active',
                'assigned_by' => auth()->id(),
                'is_unlimited' => $package->is_unlimited,
            ]);

            $user->syncCreditSummary();
            $user->refresh();

            if (! $package->is_unlimited) {
                CreditTransaction::create([
                    'user_id' => $user->id,
                    'user_subscription_id' => $sub->id,
                    'type' => 'package_assigned',
                    'amount' => $granted,
                    'balance_after' => $user->credits,
                    'reason' => "Package assigned: {$package->name}",
                    'actor_user_id' => auth()->id(),
                ]);
            }
        });

        return back()->with('success', "Assigned {$package->name} to {$user->name}.");
    }

    public function adjustCredits(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'subscription_id' => 'required|exists:user_subscriptions,id',
            'adjustment' => 'required|integer|not_in:0',
            'notes' => 'nullable|string|max:500',
        ]);

        $sub = UserSubscription::where('id', $data['subscription_id'])
            ->where('user_id', $user->id)
            ->where('is_unlimited', false)
            ->firstOrFail();

        $newCredits = $sub->credits_remaining + $data['adjustment'];
        if ($newCredits < 0) {
            return back()->withErrors(['adjustment' => "Cannot reduce below zero. Current balance: {$sub->credits_remaining}."]);
        }
        $sub->update(['credits_remaining' => $newCredits]);

        $user->syncCreditSummary();
        $user->refresh();

        CreditTransaction::create([
            'user_id' => $user->id,
            'user_subscription_id' => $sub->id,
            'type' => 'admin_adjustment',
            'amount' => $data['adjustment'],
            'balance_after' => $user->credits,
            'reason' => $data['notes'] ?? null,
            'actor_user_id' => auth()->id(),
        ]);

        return back()->with('success', 'Credits adjusted.');
    }

    public function bookForMember(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'gym_class_id' => 'required|integer|exists:gym_classes,id',
        ]);

        $class = GymClass::findOrFail($data['gym_class_id']);
        $result = $this->bookingService->book($user, $class);

        return back()->with('success', match ($result['status']) {
            'already_booked' => "{$user->name} is already booked into this class.",
            'waitlisted' => "Added {$user->name} to the waitlist (position #{$result['position']}).",
            'booked' => "Booked {$user->name} into the class.",
            default => "Booking result: {$result['status']}.",
        });
    }

    public function cancelBookingForMember(Request $request, User $user, ClassBooking $booking): RedirectResponse
    {
        abort_if($booking->user_id !== $user->id, 404);

        $this->bookingService->cancel($booking, auth()->user(), forceRefund: true);

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
