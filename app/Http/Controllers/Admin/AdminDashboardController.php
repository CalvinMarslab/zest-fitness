<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassBooking;
use App\Models\GymClass;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function index(): Response
    {
        $tz = config('app.timezone');
        $today = Carbon::today($tz);
        $now = Carbon::now($tz);

        $todayClasses = GymClass::whereDate('start_time', $today)
            ->orderBy('start_time')
            ->withCount([
                'bookings as confirmed_count' => fn ($q) => $q->whereIn('status', ['booked', 'checked_in']),
                'bookings as waitlist_count' => fn ($q) => $q->where('status', 'waitlisted'),
                'bookings as checkin_count' => fn ($q) => $q->where('status', 'checked_in'),
            ])
            ->get(['id', 'template_id', 'name', 'coach', 'start_time', 'capacity', 'is_cancelled'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'template_id' => $c->template_id,
                'name' => $c->name,
                'coach' => $c->coach,
                'start_time' => $c->start_time->toIso8601String(),
                'capacity' => $c->capacity,
                'is_cancelled' => (bool) $c->is_cancelled,
                'confirmed_count' => $c->confirmed_count,
                'waitlist_count' => $c->waitlist_count,
                'checkin_count' => $c->checkin_count,
                'is_full' => $c->confirmed_count >= $c->capacity,
                'spots_left' => max(0, $c->capacity - $c->confirmed_count),
            ]);

        $activeMembers = User::where('is_admin', false)
            ->where('role', 'member')
            ->whereHas('subscriptions', fn ($q) => $q
                ->where('status', 'active')
                ->where('expires_at', '>', $now)
            )
            ->count();

        $bookingsToday = ClassBooking::whereHas('gymClass', fn ($q) => $q->whereDate('start_time', $today))
            ->whereIn('status', ['booked', 'checked_in'])
            ->count();

        $checkinsToday = ClassBooking::whereHas('gymClass', fn ($q) => $q->whereDate('start_time', $today))
            ->where('status', 'checked_in')
            ->count();

        $expiringSoon = UserSubscription::where('status', 'active')
            ->whereBetween('expires_at', [$now, $now->copy()->addDays(14)])
            ->with(['user:id,name,email', 'package:id,name'])
            ->orderBy('expires_at')
            ->get()
            ->map(fn ($s) => [
                'user_id' => $s->user_id,
                'user_name' => $s->user->name ?? '—',
                'user_email' => $s->user->email ?? '—',
                'package_name' => $s->package->name ?? '—',
                'expires_at' => $s->expires_at->toIso8601String(),
                'credits_remaining' => $s->credits_remaining,
                'is_unlimited' => (bool) $s->is_unlimited,
            ]);

        $suspendedWithBookings = User::where('status', 'suspended')
            ->whereHas('bookings', fn ($q) => $q
                ->whereHas('gymClass', fn ($q2) => $q2
                    ->where('start_time', '>', $now)
                    ->where('is_cancelled', false)
                )
                ->whereIn('status', ['booked', 'waitlisted'])
            )
            ->count();

        return Inertia::render('Admin/Dashboard', [
            'todayClasses' => $todayClasses,
            'stats' => [
                'today_classes_count' => $todayClasses->count(),
                'bookings_today' => $bookingsToday,
                'checkins_today' => $checkinsToday,
                'active_members' => $activeMembers,
                'total_members' => User::where('is_admin', false)->count(),
                'expiring_soon_count' => $expiringSoon->count(),
            ],
            'expiringSoon' => $expiringSoon->take(10)->values(),
            'alerts' => [
                'suspended_with_bookings' => $suspendedWithBookings,
                'expiring_soon' => $expiringSoon->count(),
            ],
        ]);
    }
}
