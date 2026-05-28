<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class TrainingController extends Controller
{
    public function index(): Response
    {
        $programs = [
            [
                'id'          => 'crossfit',
                'name'        => 'CrossFit',
                'tagline'     => 'Constantly varied, high-intensity, functional movement',
                'color'       => 'red',
                'icon'        => '🏋️',
                'description' => 'Build broad, general, and inclusive fitness through varied workouts targeting all ten physical domains.',
                'schedule'    => [
                    ['day' => 'Monday',    'focus' => 'Strength + Metcon',   'workout' => [
                        ['type' => 'Strength', 'name' => 'Back Squat',       'sets' => '5×5 @ 80% 1RM'],
                        ['type' => 'Metcon',   'name' => 'AMRAP 12',         'detail' => '10 Pull-ups · 20 Push-ups · 30 Air Squats'],
                    ]],
                    ['day' => 'Tuesday',   'focus' => 'Olympic Lifting',     'workout' => [
                        ['type' => 'Skill',    'name' => 'Clean & Jerk',     'sets' => '7×1 @ 75–85%'],
                        ['type' => 'Metcon',   'name' => 'For Time',         'detail' => '21-15-9 Power Cleans (60/42 kg) + Ring Dips'],
                    ]],
                    ['day' => 'Wednesday', 'focus' => 'Gymnastics + Cardio', 'workout' => [
                        ['type' => 'Skill',    'name' => 'Handstand Walk',   'sets' => '4×10 m'],
                        ['type' => 'Metcon',   'name' => '"Cindy" AMRAP 20', 'detail' => '5 Pull-ups · 10 Push-ups · 15 Air Squats'],
                    ]],
                    ['day' => 'Thursday',  'focus' => 'Active Recovery',     'workout' => [
                        ['type' => 'Aerobic',  'name' => 'Zone 2 Row',       'sets' => '45 min easy pace'],
                        ['type' => 'Mobility', 'name' => 'Hip & Shoulder',   'sets' => '20 min mobility flow'],
                    ]],
                    ['day' => 'Friday',    'focus' => 'Strength + Metcon',   'workout' => [
                        ['type' => 'Strength', 'name' => 'Deadlift',         'sets' => '5×3 @ 85% 1RM'],
                        ['type' => 'Metcon',   'name' => '"DT" 5 Rounds',    'detail' => '12 Deadlifts · 9 Hang Power Cleans · 6 Push Jerks (70/47 kg)'],
                    ]],
                    ['day' => 'Saturday',  'focus' => 'Team / Long WOD',     'workout' => [
                        ['type' => 'Metcon',   'name' => 'Partner WOD',      'detail' => '2000 m Row · 100 Wall Balls · 50 Box Jumps · 1000 m Run (split reps)'],
                    ]],
                    ['day' => 'Sunday',    'focus' => 'Rest Day',            'workout' => [
                        ['type' => 'Rest',     'name' => 'Recovery',         'sets' => 'Light walk, stretching, or complete rest'],
                    ]],
                ],
            ],
            [
                'id'          => 'hyrox',
                'name'        => 'Hyrox',
                'tagline'     => 'The world series of fitness racing',
                'color'       => 'yellow',
                'icon'        => '🏃',
                'description' => 'Prepare for 8 km of running broken up by 8 functional fitness stations — all under race conditions.',
                'schedule'    => [
                    ['day' => 'Monday',    'focus' => 'Race Sim + Ski Erg',  'workout' => [
                        ['type' => 'Cardio',   'name' => 'Ski Erg Intervals', 'sets' => '8×1 min on / 1 min off'],
                        ['type' => 'Strength', 'name' => 'Sled Push',         'sets' => '6×20 m @ race weight'],
                    ]],
                    ['day' => 'Tuesday',   'focus' => 'Run + Strength',      'workout' => [
                        ['type' => 'Cardio',   'name' => 'Tempo Run',         'sets' => '6 km @ threshold pace'],
                        ['type' => 'Strength', 'name' => 'Sandbag Lunges',    'sets' => '4×20 m + Wall Balls 4×25'],
                    ]],
                    ['day' => 'Wednesday', 'focus' => 'Station Clusters',    'workout' => [
                        ['type' => 'Metcon',   'name' => '4 Rounds',          'detail' => '200 m Run · 20 Burpee Broad Jumps · 200 m Sled Pull · 20 Rowing Cals'],
                    ]],
                    ['day' => 'Thursday',  'focus' => 'Active Recovery',     'workout' => [
                        ['type' => 'Aerobic',  'name' => 'Easy Run',          'sets' => '30 min @ 65% HR max'],
                        ['type' => 'Mobility', 'name' => 'Legs & Hips',       'sets' => '20 min foam roll + stretch'],
                    ]],
                    ['day' => 'Friday',    'focus' => 'Full Race Stations',  'workout' => [
                        ['type' => 'Metcon',   'name' => 'Station Circuit',   'detail' => '1 km Row · 50 m Sled Push · 50 m Sled Pull · 80 m Burpee Broad Jumps · 1 km SkiErg · 200 m Farmers Carry · 100 Wall Balls · 75–200 m Sandbag Lunges'],
                    ]],
                    ['day' => 'Saturday',  'focus' => 'Long Run',            'workout' => [
                        ['type' => 'Cardio',   'name' => 'Long Easy Run',     'sets' => '10–14 km @ conversational pace'],
                    ]],
                    ['day' => 'Sunday',    'focus' => 'Rest Day',            'workout' => [
                        ['type' => 'Rest',     'name' => 'Recovery',          'sets' => 'Light walk, stretching, or complete rest'],
                    ]],
                ],
            ],
        ];

        return Inertia::render('Training', ['programs' => $programs]);
    }
}
