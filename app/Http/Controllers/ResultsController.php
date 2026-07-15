<?php

namespace App\Http\Controllers;

use App\Models\ClassBooking;
use App\Models\WorkoutResult;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class ResultsController extends Controller
{
    public function index(Request $request): Response
    {
        $date = $request->query('date')
            ? Carbon::parse($request->query('date'))->toDateString()
            : Carbon::today()->toDateString();

        // Build 14-day window centred on today (today + 6 past + 7 future)
        $today = Carbon::today();
        $dates = collect(range(-6, 7))->map(fn ($d) => $today->copy()->addDays($d)->toDateString());

        $results = WorkoutResult::with('user:id,name')
            ->whereDate('result_date', $date)
            ->orderBy('created_at')
            ->get()
            ->map(fn ($r) => [
                'id'       => $r->id,
                'user_id'  => $r->user_id,
                'name'     => $r->user->name,
                'exercise' => $r->exercise,
                'value'    => $r->value,
                'notes'    => $r->notes,
            ]);

        // Exercises the coach set for the user's booked classes on the selected date
        $suggestions = ClassBooking::where('user_id', $request->user()->id)
            ->whereHas('gymClass', fn ($q) => $q->whereDate('start_time', $date))
            ->with('gymClass:id,name,exercises')
            ->get()
            ->flatMap(fn ($b) => $b->gymClass?->exercises ?? [])
            ->filter()
            ->unique()
            ->values();

        return Inertia::render('Results', [
            'results'      => $results,
            'selectedDate' => $date,
            'dates'        => $dates,
            'suggestions'  => $suggestions,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'exercise' => 'required|string|max:100',
            'value'    => 'required|string|max:100',
            'notes'    => 'nullable|string|max:500',
            'date'     => 'nullable|date',
        ]);

        WorkoutResult::create([
            'user_id'     => $request->user()->id,
            'result_date' => $data['date'] ?? Carbon::today()->toDateString(),
            'exercise'    => $data['exercise'],
            'value'       => $data['value'],
            'notes'       => $data['notes'] ?? null,
        ]);

        $date = $data['date'] ?? Carbon::today()->toDateString();
        return redirect()->route('results', ['date' => $date]);
    }

    public function destroy(Request $request, WorkoutResult $workoutResult): RedirectResponse
    {
        abort_if($workoutResult->user_id !== $request->user()->id, 403);
        $date = $workoutResult->result_date->toDateString();
        $workoutResult->delete();

        return redirect()->route('results', ['date' => $date]);
    }
}
