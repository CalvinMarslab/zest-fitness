<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkoutResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiWorkoutTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user  = User::factory()->create();
        $this->token = $this->user->createToken('Test Device')->plainTextToken;
    }

    private function authHeaders(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    // ── Logging workouts ─────────────────────────────────────────────────────

    public function test_can_log_workout(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/workouts', [
                'exercise' => 'Deadlift',
                'value'    => '120 kg',
                'duration' => 2700,
                'notes'    => 'Felt strong',
            ]);

        $response->assertCreated();
        $response->assertJsonStructure(['id', 'message']);

        // Should create a workout result
        $this->assertDatabaseHas('workout_results', [
            'user_id'  => $this->user->id,
            'exercise' => 'Deadlift',
            'value'    => '120 kg',
        ]);

        // Should also create an activity
        $this->assertDatabaseHas('activities', [
            'user_id'  => $this->user->id,
            'type'     => 'deadlift',
            'duration' => 45, // 2700 seconds = 45 minutes
        ]);
    }

    public function test_workout_with_optional_fields(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/workouts', [
                'exercise'    => 'Running',
                'value'       => '5 km in 25:00',
                'duration'    => 1500,
                'calories'    => 310,
                'heart_rate'  => 145,
                'distance'    => 5000,
                'notes'       => 'Morning run',
                'recorded_at' => '2026-04-05T08:00:00Z',
            ]);

        $response->assertCreated();

        $this->assertDatabaseHas('activities', [
            'user_id'  => $this->user->id,
            'type'     => 'running',
            'distance' => 5000,
        ]);

        // Notes should be built with calories and heart rate
        $result = WorkoutResult::where('user_id', $this->user->id)->first();
        $this->assertStringContainsString('310 kcal', $result->notes);
        $this->assertStringContainsString('145 bpm', $result->notes);
    }

    public function test_workout_recorded_at_sets_result_date(): void
    {
        $this->withHeaders($this->authHeaders())
            ->postJson('/api/workouts', [
                'exercise'    => 'Squat',
                'value'       => '100 kg',
                'duration'    => 1800,
                'recorded_at' => '2026-04-01T10:00:00Z',
            ]);

        // Verify the result was created for the correct date
        $result = \App\Models\WorkoutResult::where('user_id', $this->user->id)->first();
        $this->assertEquals('2026-04-01', $result->result_date->toDateString());
    }

    public function test_workout_requires_required_fields(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/workouts', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['exercise', 'value', 'duration']);
    }

    public function test_workout_validates_field_types(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/workouts', [
                'exercise' => 'Deadlift',
                'value'    => '120 kg',
                'duration' => -1,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('duration');
    }

    // ── Listing workouts ─────────────────────────────────────────────────────

    public function test_can_list_own_workouts(): void
    {
        WorkoutResult::create([
            'user_id' => $this->user->id, 'result_date' => '2026-04-06',
            'exercise' => 'Deadlift', 'value' => '120 kg',
        ]);
        WorkoutResult::create([
            'user_id' => $this->user->id, 'result_date' => '2026-04-05',
            'exercise' => 'Squat', 'value' => '100 kg',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/workouts');

        $response->assertOk();
        $response->assertJsonCount(2);
    }

    public function test_cannot_see_others_workouts(): void
    {
        $other = User::factory()->create();
        WorkoutResult::create([
            'user_id' => $other->id, 'result_date' => '2026-04-06',
            'exercise' => 'Bench Press', 'value' => '80 kg',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/workouts');

        $response->assertOk();
        $response->assertJsonCount(0);
    }

    public function test_workouts_limited_to_30(): void
    {
        for ($i = 0; $i < 35; $i++) {
            WorkoutResult::create([
                'user_id' => $this->user->id, 'result_date' => "2026-04-06",
                'exercise' => "Exercise $i", 'value' => "$i kg",
            ]);
        }

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/workouts');

        $response->assertOk();
        $response->assertJsonCount(30);
    }

    // ── Auth guards ──────────────────────────────────────────────────────────

    public function test_unauthenticated_cannot_log_workout(): void
    {
        $response = $this->postJson('/api/workouts', [
            'exercise' => 'Deadlift',
            'value'    => '120 kg',
            'duration' => 2700,
        ]);

        $response->assertUnauthorized();
    }

    public function test_unauthenticated_cannot_list_workouts(): void
    {
        $response = $this->getJson('/api/workouts');

        $response->assertUnauthorized();
    }
}
