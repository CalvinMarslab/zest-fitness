<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class ActivityController extends Controller
{
    public function index(): Response
    {
        $activities = auth()->user()
            ->activities()
            ->with('classBooking.gymClass:id,name')
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn($activity) => [
                'id'         => $activity->id,
                'type'       => $activity->type,
                'duration'   => $activity->duration,
                'distance'   => $activity->distance,
                'class_name' => $activity->classBooking?->gymClass?->name,
                'recorded_at'=> $activity->created_at->toIso8601String(),
            ]);

        return Inertia::render('ActivityFeed', ['activities' => $activities]);
    }
}
