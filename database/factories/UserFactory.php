<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Create the user with an active subscription (credit-based by default).
     * Also sets user.credits to match for display sync.
     */
    public function withSubscription(int $credits = 5, bool $unlimited = false): static
    {
        return $this->afterCreating(function (User $user) use ($credits, $unlimited) {
            UserSubscription::create([
                'user_id' => $user->id,
                'credits_granted' => $credits,
                'credits_remaining' => $credits,
                'started_at' => now()->subDay(),
                'expires_at' => now()->addDays(30),
                'status' => 'active',
                'is_unlimited' => $unlimited,
            ]);

            if (! $unlimited) {
                $user->update(['credits' => $credits]);
            }
        });
    }
}
