<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    // ── Admin access ─────────────────────────────────────────────────────────

    public function test_admin_user_can_access_admin_dashboard(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk();
    }

    public function test_role_admin_can_access_admin_dashboard(): void
    {
        $admin = User::factory()->create(['is_admin' => false, 'role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk();
    }

    public function test_member_cannot_access_admin_dashboard(): void
    {
        $member = User::factory()->create(['is_admin' => false, 'role' => 'member']);

        $this->actingAs($member)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_coach_cannot_access_admin_dashboard(): void
    {
        $coach = User::factory()->create(['is_admin' => false, 'role' => 'coach']);

        $this->actingAs($coach)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    // ── Coach access ─────────────────────────────────────────────────────────

    public function test_coach_can_access_coach_dashboard(): void
    {
        $coach = User::factory()->create(['role' => 'coach']);

        $this->actingAs($coach)
            ->get(route('coach.dashboard'))
            ->assertOk();
    }

    public function test_admin_can_access_coach_dashboard(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('coach.dashboard'))
            ->assertOk();
    }

    public function test_member_cannot_access_coach_dashboard(): void
    {
        $member = User::factory()->create(['role' => 'member']);

        $this->actingAs($member)
            ->get(route('coach.dashboard'))
            ->assertForbidden();
    }

    public function test_guest_cannot_access_coach_dashboard(): void
    {
        $this->get(route('coach.dashboard'))
            ->assertRedirect(route('login'));
    }

    // ── User role methods ─────────────────────────────────────────────────────

    public function test_is_admin_returns_true_for_is_admin_flag(): void
    {
        $user = User::factory()->create(['is_admin' => true, 'role' => 'member']);
        $this->assertTrue($user->isAdmin());
    }

    public function test_is_admin_returns_true_for_role_admin(): void
    {
        $user = User::factory()->create(['is_admin' => false, 'role' => 'admin']);
        $this->assertTrue($user->isAdmin());
    }

    public function test_is_coach_returns_true_for_coach_role(): void
    {
        $user = User::factory()->create(['role' => 'coach']);
        $this->assertTrue($user->isCoach());
        $this->assertFalse($user->isAdmin());
    }

    public function test_is_member_returns_true_for_member_role(): void
    {
        $user = User::factory()->create(['role' => 'member']);
        $this->assertTrue($user->isMember());
    }

    public function test_is_suspended_returns_true_for_suspended_status(): void
    {
        $user = User::factory()->create(['status' => 'suspended']);
        $this->assertTrue($user->isSuspended());
    }
}
