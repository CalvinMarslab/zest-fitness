<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use App\Models\GymClass;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class CoachDashboardController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();
        $today = Carbon::today();

        $classes = GymClass::withCount('bookings')
            ->where('coach_id', $user->id)
            ->where('start_time', '>=', $today)
            ->where('start_time', '<', $today->copy()->addDays(7))
            ->orderBy('start_time')
            ->get();

        return Inertia::render('Coach/Dashboard', [
            'classes' => $classes,
        ]);
    }
}
