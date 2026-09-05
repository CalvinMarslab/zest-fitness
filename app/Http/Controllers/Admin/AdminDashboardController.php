<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassBooking;
use App\Models\GymClass;
use App\Models\User;
use App\Models\WorkoutResult;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function index(): Response
    {
        $today = Carbon::today();

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'total_users' => User::count(),
                'total_classes' => GymClass::count(),
                'total_members' => User::where('is_admin', false)->count(),
                'total_admins' => User::where('is_admin', true)->count(),
                'upcoming_count' => GymClass::where('start_time', '>', now())->where('is_cancelled', false)->count(),
                'past_count' => GymClass::where('start_time', '<=', now())->count(),
                'bookings_today' => ClassBooking::whereHas('gymClass', fn ($q) => $q->whereDate('start_time', $today)
                )->count(),
                'results_today' => WorkoutResult::whereDate('result_date', $today)->count(),
                'upcoming_classes' => GymClass::where('start_time', '>=', now())
                    ->where('is_cancelled', false)
                    ->orderBy('start_time')
                    ->limit(5)
                    ->withCount('bookings')
                    ->get(['id', 'name', 'coach', 'start_time', 'capacity']),
                'recent_bookings' => ClassBooking::with(['user:id,name', 'gymClass:id,name,start_time'])
                    ->latest()
                    ->limit(5)
                    ->get(),
            ],
        ]);
    }
}
