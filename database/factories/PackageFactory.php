<?php

namespace Database\Factories;

use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Package>
 */
class PackageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true).' Pack',
            'description' => fake()->sentence(),
            'credits' => 10,
            'period_days' => 30,
            'price' => '99.00',
            'is_active' => true,
            'is_trial' => false,
            'is_unlimited' => false,
            'weekly_booking_limit' => null,
            'sort_order' => 0,
        ];
    }

    public function unlimited(): static
    {
        return $this->state(['is_unlimited' => true, 'credits' => 0, 'weekly_booking_limit' => null]);
    }

    public function limitedPlan(int $weeklyLimit = 2): static
    {
        return $this->state(['is_unlimited' => false, 'weekly_booking_limit' => $weeklyLimit]);
    }
}
