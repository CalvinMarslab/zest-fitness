<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackageTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['credits' => 0]);
    }

    // ── Viewing packages ─────────────────────────────────────────────────────

    public function test_user_can_view_packages_page(): void
    {
        $response = $this->actingAs($this->user)->get(route('packages'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Packages'));
    }

    public function test_packages_page_shows_only_active_packages(): void
    {
        Package::create([
            'name' => 'Active Pack', 'credits' => 10, 'period_days' => 30,
            'price' => 29.99, 'is_active' => true, 'sort_order' => 1,
        ]);
        Package::create([
            'name' => 'Inactive Pack', 'credits' => 5, 'period_days' => 30,
            'price' => 14.99, 'is_active' => false, 'sort_order' => 2,
        ]);

        $response = $this->actingAs($this->user)->get(route('packages'));

        $response->assertInertia(fn ($page) => $page
            ->has('packages', 1)
            ->where('packages.0.name', 'Active Pack')
        );
    }

    public function test_packages_page_shows_active_subscription(): void
    {
        $pkg = Package::create([
            'name' => 'Monthly', 'credits' => 10, 'period_days' => 30,
            'price' => 29.99, 'is_active' => true, 'sort_order' => 1,
        ]);

        UserSubscription::create([
            'user_id'         => $this->user->id,
            'package_id'      => $pkg->id,
            'credits_granted' => 10,
            'started_at'      => now(),
            'expires_at'      => now()->addDays(30),
        ]);

        $response = $this->actingAs($this->user)->get(route('packages'));

        $response->assertInertia(fn ($page) => $page
            ->where('activeSubscription.package_name', 'Monthly')
        );
    }

    public function test_packages_page_shows_null_when_no_active_subscription(): void
    {
        $response = $this->actingAs($this->user)->get(route('packages'));

        $response->assertInertia(fn ($page) => $page
            ->where('activeSubscription', null)
        );
    }

    // ── Subscribing ──────────────────────────────────────────────────────────

    public function test_user_can_subscribe_to_package(): void
    {
        $pkg = Package::create([
            'name' => 'Monthly', 'credits' => 10, 'period_days' => 30,
            'price' => 29.99, 'is_active' => true, 'sort_order' => 1,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('packages.subscribe', $pkg));

        $response->assertRedirect(route('packages'));
        $response->assertSessionHas('success');

        // Credits should be added
        $this->assertEquals(10, $this->user->fresh()->credits);

        // Subscription record should exist
        $this->assertDatabaseHas('user_subscriptions', [
            'user_id'         => $this->user->id,
            'package_id'      => $pkg->id,
            'credits_granted' => 10,
        ]);
    }

    public function test_subscribing_adds_credits_to_existing_balance(): void
    {
        $this->user->update(['credits' => 3]);

        $pkg = Package::create([
            'name' => 'Monthly', 'credits' => 10, 'period_days' => 30,
            'price' => 29.99, 'is_active' => true, 'sort_order' => 1,
        ]);

        $this->actingAs($this->user)
            ->post(route('packages.subscribe', $pkg));

        $this->assertEquals(13, $this->user->fresh()->credits);
    }

    public function test_cannot_subscribe_to_inactive_package(): void
    {
        $pkg = Package::create([
            'name' => 'Old Pack', 'credits' => 10, 'period_days' => 30,
            'price' => 29.99, 'is_active' => false, 'sort_order' => 1,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('packages.subscribe', $pkg));

        $response->assertNotFound();

        $this->assertEquals(0, $this->user->fresh()->credits);
    }

    public function test_guest_cannot_view_packages(): void
    {
        $response = $this->get(route('packages'));

        $response->assertRedirect(route('login'));
    }

    public function test_guest_cannot_subscribe(): void
    {
        $pkg = Package::create([
            'name' => 'Monthly', 'credits' => 10, 'period_days' => 30,
            'price' => 29.99, 'is_active' => true, 'sort_order' => 1,
        ]);

        $response = $this->post(route('packages.subscribe', $pkg));

        $response->assertRedirect(route('login'));
    }
}
