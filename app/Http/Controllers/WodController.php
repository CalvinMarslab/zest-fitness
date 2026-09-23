<?php

namespace App\Http\Controllers;

use App\Models\GymClass;
use App\Models\DailyWorkout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class WodController extends Controller
{
    public function index(): Response
    {
        if (! session('wod_unlocked')) {
            return Inertia::render('Wod', ['locked' => true, 'classes' => [], 'dailyWorkouts' => [], 'date' => Carbon::today()->toDateString()]);
        }

        $today = Carbon::today();

        $classes = GymClass::whereDate('start_time', $today)
            ->where('is_cancelled', false)
            ->orderBy('start_time')
            ->get()
            ->map(fn (GymClass $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'coach' => $c->coach,
                'start_time' => $c->start_time->toIso8601String(),
                'wod_type' => $c->wod_type,
                'wod_config' => $c->wod_config ?? [],
                'exercises' => $c->exercises ?? [],
            ]);

        return Inertia::render('Wod', [
            'locked' => false,
            'classes' => $classes,
            'dailyWorkouts' => DailyWorkout::whereDate('workout_date', $today)
                ->where('is_published', true)
                ->orderBy('program', 'desc')
                ->get(['id', 'program', 'title', 'workout']),
            'date' => $today->toDateString(),
        ]);
    }

    public function logout(): RedirectResponse
    {
        session()->forget('wod_unlocked');

        return redirect()->route('wod');
    }

    public function verify(Request $request): RedirectResponse
    {
        $data = $request->validate(['passcode' => 'required|string|size:4']);

        if ($data['passcode'] === (string) config('services.wod_passcode')) {
            session(['wod_unlocked' => true]);

            return redirect()->route('wod');
        }

        return back()->with('wod_error', 'Wrong passcode. Try again.');
    }
}
