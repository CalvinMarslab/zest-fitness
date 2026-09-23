<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DailyWorkout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminDailyWorkoutController extends Controller
{
    public function index(Request $request): Response
    {
        $date = $request->date('date')?->toDateString() ?? now()->toDateString();

        return Inertia::render('Admin/DailyWorkouts', [
            'date' => $date,
            'workouts' => DailyWorkout::whereDate('workout_date', $date)->get()->keyBy('program'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'workout_date' => 'required|date',
            'program' => ['required', Rule::in(['hyrox', 'crossfit'])],
            'title' => 'nullable|string|max:150',
            'workout' => 'required|string|max:20000',
            'coach_notes' => 'nullable|string|max:5000',
            'is_published' => 'required|boolean',
        ]);

        $workout = DailyWorkout::whereDate('workout_date', $data['workout_date'])
            ->where('program', $data['program'])
            ->first() ?? new DailyWorkout;

        $workout->fill([...$data, 'updated_by' => $request->user()->id])->save();

        return back()->with('success', ucfirst($data['program']).' workout saved.');
    }
}
