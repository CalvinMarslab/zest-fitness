<?php

namespace Tests\Feature;

use App\Models\GymClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WodEditingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_admin_can_save_and_edit_a_structured_wod(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);
        $class = GymClass::factory()->create();

        $payload = [
            'wod_type' => 'AMRAP',
            'wod_config' => ['duration' => '20 min'],
            'exercises' => [[
                'name' => 'Wall Ball',
                'volume' => '20 reps',
                'target' => 'unbroken',
                'men_rx' => '9 kg',
                'men_sc' => '6 kg',
                'women_rx' => '6 kg',
                'women_sc' => '4 kg',
                'rest' => '30 sec',
            ]],
        ];

        $this->actingAs($admin)->patch(route('admin.classes.update', $class), $payload)->assertRedirect();

        $fresh = $class->fresh();
        $this->assertSame('AMRAP', $fresh->wod_type);
        $this->assertSame(['duration' => '20 min'], $fresh->wod_config);
        $this->assertSame('Wall Ball', $fresh->exercises[0]['name']);
        $this->assertSame('20 reps', $fresh->exercises[0]['volume']);

        $this->actingAs($admin)->patch(route('admin.classes.update', $class), [
            'wod_type' => 'For Time',
            'wod_config' => ['time_cap' => '15 min', 'rounds' => '3'],
            'exercises' => [[...$fresh->exercises[0], 'volume' => '30 reps']],
        ])->assertRedirect();

        $this->assertSame('For Time', $class->fresh()->wod_type);
        $this->assertSame('30 reps', $class->fresh()->exercises[0]['volume']);
    }

    public function test_unlocked_public_wod_contains_saved_structured_data(): void
    {
        Carbon::setTestNow('2026-09-20 08:00:00');
        GymClass::factory()->create([
            'start_time' => now()->setTime(18, 0),
            'wod_type' => 'EMOM',
            'wod_config' => ['every' => '1 min', 'duration' => '12 min'],
            'exercises' => [['name' => 'Burpee', 'volume' => '8 reps']],
            'is_cancelled' => false,
        ]);

        $this->withSession(['wod_unlocked' => true])->get(route('wod'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Wod')
                ->where('classes.0.wod_type', 'EMOM')
                ->where('classes.0.wod_config.duration', '12 min')
                ->where('classes.0.exercises.0.name', 'Burpee')
            );
    }
}
