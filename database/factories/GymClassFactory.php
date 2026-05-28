<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class GymClassFactory extends Factory
{
    public function definition(): array
    {
        $classes = [
            ['name' => 'Yoga Flow', 'coach' => 'Sarah'],
            ['name' => 'HIIT Blast', 'coach' => 'Marcus'],
            ['name' => 'Spin Cycle', 'coach' => 'Priya'],
            ['name' => 'Pilates Core', 'coach' => 'Emma'],
            ['name' => 'Boxing Fundamentals', 'coach' => 'Jake'],
            ['name' => 'Zumba Dance', 'coach' => 'Maria'],
            ['name' => 'CrossFit WOD', 'coach' => 'Daniel'],
            ['name' => 'Stretch & Recover', 'coach' => 'Sarah'],
            ['name' => 'Kettlebell Strength', 'coach' => 'Marcus'],
            ['name' => 'Barre Fusion', 'coach' => 'Emma'],
        ];

        $class = $this->faker->randomElement($classes);

        return [
            'name'       => $class['name'],
            'coach'      => $class['coach'],
            'start_time' => $this->faker->dateTimeBetween('now', '+14 days'),
            'capacity'   => $this->faker->numberBetween(8, 20),
        ];
    }
}
