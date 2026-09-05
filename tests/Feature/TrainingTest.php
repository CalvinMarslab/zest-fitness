<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrainingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_user_can_view_training_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('training'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Training')
            ->has('programs', 2)
        );
    }

    public function test_training_includes_crossfit_and_hyrox(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('training'));

        $response->assertInertia(fn ($page) => $page
            ->where('programs.0.id', 'crossfit')
            ->where('programs.1.id', 'hyrox')
        );
    }

    public function test_guest_cannot_view_training(): void
    {
        $response = $this->get(route('training'));

        $response->assertRedirect(route('login'));
    }
}
