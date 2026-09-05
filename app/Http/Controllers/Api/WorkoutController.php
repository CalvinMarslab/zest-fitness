<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkoutResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class WorkoutController extends Controller
{
    /**
     * Log a completed Apple Watch workout.
     *
     * Expected body:
     * {
     *   "exercise":    "Deadlift",          // workout type / exercise name
     *   "value":       "120 kg",            // human-readable result
     *   "duration":    2700,                // seconds
     *   "calories":    310,                 // active kcal (optional)
     *   "heart_rate":  145,                 // avg bpm (optional)
     *   "distance":    0,                   // metres (optional)
     *   "notes":       "Felt strong today", // optional
     *   "recorded_at": "2026-03-29T08:00:00Z" // ISO8601; defaults to now
     * }
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'exercise' => 'required|string|max:100',
            'value' => 'required|string|max:100',
            'duration' => 'required|integer|min:0',
            'calories' => 'nullable|integer|min:0',
            'heart_rate' => 'nullable|integer|min:0',
            'distance' => 'nullable|integer|min:0',
            'notes' => 'nullable|string|max:500',
            'recorded_at' => 'nullable|date',
        ]);

        $recordedAt = isset($data['recorded_at'])
            ? Carbon::parse($data['recorded_at'])
            : Carbon::now();

        // Post to the Results Board for that day
        $result = WorkoutResult::create([
            'user_id' => $request->user()->id,
            'result_date' => $recordedAt->toDateString(),
            'exercise' => $data['exercise'],
            'value' => $data['value'],
            'notes' => $this->buildNotes($data),
        ]);

        // Also log to the Activity feed (duration / distance for leaderboard history)
        $request->user()->activities()->create([
            'type' => strtolower($data['exercise']),
            'duration' => (int) round($data['duration'] / 60), // convert s → min
            'distance' => $data['distance'] ?? 0,
            'notes' => $data['notes'] ?? null,
        ]);

        return response()->json([
            'id' => $result->id,
            'message' => 'Workout logged successfully',
        ], 201);
    }

    /**
     * Return the authenticated user's 30 most recent workout results.
     */
    public function index(Request $request): JsonResponse
    {
        $results = WorkoutResult::where('user_id', $request->user()->id)
            ->orderByDesc('result_date')
            ->orderByDesc('created_at')
            ->limit(30)
            ->get(['id', 'result_date', 'exercise', 'value', 'notes', 'created_at']);

        return response()->json($results);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function buildNotes(array $data): string
    {
        $parts = [];

        if (! empty($data['notes'])) {
            $parts[] = $data['notes'];
        }
        if (! empty($data['calories'])) {
            $parts[] = "🔥 {$data['calories']} kcal";
        }
        if (! empty($data['heart_rate'])) {
            $parts[] = "❤️ avg {$data['heart_rate']} bpm";
        }

        return implode(' · ', $parts);
    }
}
