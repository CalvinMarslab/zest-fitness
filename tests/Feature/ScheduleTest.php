<?php

namespace Tests\Feature;

use App\Models\ClassBooking;
use App\Models\GymClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_authenticated_user_can_view_schedule(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('schedule'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Schedule'));
    }

    public function test_schedule_shows_only_future_classes(): void
    {
        $user = User::factory()->create();

        $future = GymClass::factory()->create(['start_time' => now()->addDays(2)]);
        $past = GymClass::factory()->create(['start_time' => now()->subDay()]);

        $response = $this->actingAs($user)->get(route('schedule'));

        $response->assertInertia(fn ($page) => $page
            ->component('Schedule')
            ->has('classes', 1)
            ->where('classes.0.id', $future->id)
        );
    }

    public function test_schedule_shows_booking_status(): void
    {
        $user = User::factory()->create();
        $booked = GymClass::factory()->create(['start_time' => now()->addDay()]);
        $notBook = GymClass::factory()->create(['start_time' => now()->addDays(2)]);

        ClassBooking::create([
            'user_id' => $user->id,
            'gym_class_id' => $booked->id,
        ]);

        $response = $this->actingAs($user)->get(route('schedule'));

        $response->assertInertia(fn ($page) => $page
            ->component('Schedule')
            ->has('classes', 2)
        );

        // Verify the booked class has is_booked = true
        $classes = collect($response->original->getData()['page']['props']['classes']);
        $bookedClass = $classes->firstWhere('id', $booked->id);
        $this->assertTrue($bookedClass['is_booked']);

        $notBookedClass = $classes->firstWhere('id', $notBook->id);
        $this->assertFalse($notBookedClass['is_booked']);
    }

    public function test_schedule_shows_spots_left(): void
    {
        $user = User::factory()->create();

        $gymClass = GymClass::factory()->create([
            'capacity' => 10,
            'start_time' => now()->addDay(),
        ]);

        // Create 3 bookings from other users
        User::factory(3)->create()->each(fn ($u) => ClassBooking::create(['user_id' => $u->id, 'gym_class_id' => $gymClass->id])
        );

        $response = $this->actingAs($user)->get(route('schedule'));

        $response->assertInertia(fn ($page) => $page
            ->where('classes.0.spots_left', 7)
            ->where('classes.0.is_full', false)
        );
    }

    public function test_schedule_marks_full_class(): void
    {
        $user = User::factory()->create();

        $gymClass = GymClass::factory()->create([
            'capacity' => 1,
            'start_time' => now()->addDay(),
        ]);

        $other = User::factory()->create();
        ClassBooking::create(['user_id' => $other->id, 'gym_class_id' => $gymClass->id]);

        $response = $this->actingAs($user)->get(route('schedule'));

        $response->assertInertia(fn ($page) => $page
            ->where('classes.0.spots_left', 0)
            ->where('classes.0.is_full', true)
        );
    }

    public function test_guest_cannot_view_schedule(): void
    {
        $response = $this->get(route('schedule'));

        $response->assertRedirect(route('login'));
    }
}
