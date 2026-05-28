<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkoutResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ResultsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    // ── Viewing results ──────────────────────────────────────────────────────

    public function test_user_can_view_results_page(): void
    {
        $response = $this->actingAs($this->user)->get(route('results'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Results'));
    }

    public function test_results_default_to_today(): void
    {
        $response = $this->actingAs($this->user)->get(route('results'));

        $response->assertInertia(fn ($page) => $page
            ->where('selectedDate', Carbon::today()->toDateString())
        );
    }

    public function test_results_filter_by_date(): void
    {
        $date = '2026-04-05';

        WorkoutResult::create([
            'user_id' => $this->user->id, 'result_date' => $date,
            'exercise' => 'Deadlift', 'value' => '120 kg',
        ]);
        WorkoutResult::create([
            'user_id' => $this->user->id, 'result_date' => '2026-04-04',
            'exercise' => 'Squat', 'value' => '100 kg',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('results', ['date' => $date]));

        $response->assertInertia(fn ($page) => $page
            ->has('results', 1)
            ->where('results.0.exercise', 'Deadlift')
        );
    }

    public function test_results_shows_all_users_results(): void
    {
        $other = User::factory()->create();
        $today = Carbon::today()->toDateString();

        WorkoutResult::create([
            'user_id' => $this->user->id, 'result_date' => $today,
            'exercise' => 'Deadlift', 'value' => '120 kg',
        ]);
        WorkoutResult::create([
            'user_id' => $other->id, 'result_date' => $today,
            'exercise' => 'Bench Press', 'value' => '80 kg',
        ]);

        $response = $this->actingAs($this->user)->get(route('results'));

        $response->assertInertia(fn ($page) => $page->has('results', 2));
    }

    // ── Creating results ─────────────────────────────────────────────────────

    public function test_user_can_create_result(): void
    {
        $response = $this->actingAs($this->user)->post(route('results.store'), [
            'exercise' => 'Deadlift',
            'value'    => '140 kg',
            'notes'    => 'PR!',
            'date'     => '2026-04-06',
        ]);

        $response->assertRedirect(route('results', ['date' => '2026-04-06']));

        $this->assertDatabaseHas('workout_results', [
            'user_id'  => $this->user->id,
            'exercise' => 'Deadlift',
            'value'    => '140 kg',
            'notes'    => 'PR!',
        ]);
    }

    public function test_result_defaults_to_today_when_no_date(): void
    {
        $this->actingAs($this->user)->post(route('results.store'), [
            'exercise' => 'Squat',
            'value'    => '100 kg',
        ]);

        // Verify the result was created for today's date
        $result = WorkoutResult::where('user_id', $this->user->id)->first();
        $this->assertEquals(Carbon::today()->toDateString(), $result->result_date->toDateString());
    }

    public function test_result_requires_exercise_and_value(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('results.store'), []);

        $response->assertSessionHasErrors(['exercise', 'value']);
    }

    public function test_result_validates_max_lengths(): void
    {
        $response = $this->actingAs($this->user)->post(route('results.store'), [
            'exercise' => str_repeat('a', 101),
            'value'    => str_repeat('b', 101),
            'notes'    => str_repeat('c', 501),
        ]);

        $response->assertSessionHasErrors(['exercise', 'value', 'notes']);
    }

    // ── Deleting results ─────────────────────────────────────────────────────

    public function test_user_can_delete_own_result(): void
    {
        $result = WorkoutResult::create([
            'user_id' => $this->user->id, 'result_date' => Carbon::today(),
            'exercise' => 'Deadlift', 'value' => '120 kg',
        ]);

        $response = $this->actingAs($this->user)
            ->delete(route('results.destroy', $result));

        $response->assertRedirect();
        $this->assertDatabaseMissing('workout_results', ['id' => $result->id]);
    }

    public function test_user_cannot_delete_others_result(): void
    {
        $other  = User::factory()->create();
        $result = WorkoutResult::create([
            'user_id' => $other->id, 'result_date' => Carbon::today(),
            'exercise' => 'Deadlift', 'value' => '120 kg',
        ]);

        $response = $this->actingAs($this->user)
            ->delete(route('results.destroy', $result));

        $response->assertForbidden();
        $this->assertDatabaseHas('workout_results', ['id' => $result->id]);
    }

    public function test_guest_cannot_access_results(): void
    {
        $response = $this->get(route('results'));
        $response->assertRedirect(route('login'));
    }
}
