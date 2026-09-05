<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ClassBooking;
use App\Models\GymClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_authenticated_user_can_view_activities(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('activities'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('ActivityFeed'));
    }

    public function test_activities_shows_only_own_activities(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Activity::create([
            'user_id' => $user->id,
            'type' => 'running',
            'duration' => 30,
            'distance' => 5000,
        ]);

        Activity::create([
            'user_id' => $other->id,
            'type' => 'cycling',
            'duration' => 60,
            'distance' => 20000,
        ]);

        $response = $this->actingAs($user)->get(route('activities'));

        $response->assertInertia(fn ($page) => $page
            ->has('activities', 1)
            ->where('activities.0.type', 'running')
        );
    }

    public function test_activities_include_linked_class_name(): void
    {
        $user = User::factory()->create();
        $gymClass = GymClass::factory()->create(['name' => 'HIIT Blast']);
        $booking = ClassBooking::create([
            'user_id' => $user->id,
            'gym_class_id' => $gymClass->id,
        ]);

        Activity::create([
            'user_id' => $user->id,
            'class_booking_id' => $booking->id,
            'type' => 'hiit',
            'duration' => 45,
            'distance' => 0,
        ]);

        $response = $this->actingAs($user)->get(route('activities'));

        $response->assertInertia(fn ($page) => $page
            ->where('activities.0.class_name', 'HIIT Blast')
        );
    }

    public function test_activities_limited_to_50(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 55; $i++) {
            Activity::create([
                'user_id' => $user->id,
                'type' => 'run',
                'duration' => 30,
                'distance' => 5000,
            ]);
        }

        $response = $this->actingAs($user)->get(route('activities'));

        $response->assertInertia(fn ($page) => $page->has('activities', 50));
    }

    public function test_guest_cannot_view_activities(): void
    {
        $response = $this->get(route('activities'));

        $response->assertRedirect(route('login'));
    }
}
