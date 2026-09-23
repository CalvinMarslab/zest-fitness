<?php

namespace Tests\Feature;

use App\Models\DailyWorkout;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DailyWorkoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_admin_can_create_and_update_one_workout_per_program_and_date(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);
        $payload = ['workout_date' => '2026-09-24', 'program' => 'hyrox', 'title' => 'Engine', 'workout' => '5 rounds', 'coach_notes' => null, 'is_published' => true];

        $this->actingAs($admin)->post(route('admin.daily-workouts.store'), $payload)->assertRedirect();
        $this->actingAs($admin)->post(route('admin.daily-workouts.store'), [...$payload, 'workout' => '6 rounds'])->assertRedirect();

        $this->assertDatabaseCount('daily_workouts', 1);
        $this->assertDatabaseHas('daily_workouts', ['program' => 'hyrox', 'workout' => '6 rounds']);
    }

    public function test_public_wod_only_contains_published_daily_workouts_for_today(): void
    {
        Carbon::setTestNow('2026-09-24 08:00:00');
        DailyWorkout::create(['workout_date' => today(), 'program' => 'hyrox', 'workout' => 'HYROX session', 'is_published' => true]);
        DailyWorkout::create(['workout_date' => today(), 'program' => 'crossfit', 'workout' => 'Draft', 'is_published' => false]);

        $this->withSession(['wod_unlocked' => true])->get(route('wod'))->assertOk()
            ->assertInertia(fn ($page) => $page->where('dailyWorkouts.0.program', 'hyrox')->missing('dailyWorkouts.1'));
    }

    public function test_logged_in_member_can_view_today_workout_without_passcode(): void
    {
        DailyWorkout::create(['workout_date' => today(), 'program' => 'crossfit', 'workout' => 'Member workout', 'is_published' => true]);
        $member = User::factory()->create(['role' => 'member']);

        $this->actingAs($member)->get(route('wod'))->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('locked', false)
                ->where('authenticated', true)
                ->where('dailyWorkouts.0.workout', 'Member workout'));
    }
}
