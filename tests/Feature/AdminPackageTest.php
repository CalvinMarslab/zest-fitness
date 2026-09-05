<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\User;
use App\Models\UserSubscription;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPackageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);
    }

    // ── Creating unlimited package ────────────────────────────────────────────

    public function test_admin_can_create_unlimited_package(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.packages.store'), [
                'name' => 'Unlimited Monthly',
                'description' => 'Unlimited access for 30 days',
                'credits' => 999,
                'period_days' => 30,
                'price' => '299.00',
                'is_active' => true,
                'is_unlimited' => true,
                'sort_order' => 0,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('packages', [
            'name' => 'Unlimited Monthly',
            'is_unlimited' => 1,
        ]);
    }

    // ── Assigning unlimited package creates unlimited subscription ────────────

    public function test_assigning_unlimited_package_creates_unlimited_subscription(): void
    {
        $package = Package::create([
            'name' => 'Unlimited',
            'credits' => 0,
            'period_days' => 30,
            'price' => 299,
            'is_active' => true,
            'is_unlimited' => true,
            'sort_order' => 0,
        ]);

        $member = User::factory()->create(['credits' => 0]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.users.subscriptions.store', $member), [
                'package_id' => $package->id,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $sub = UserSubscription::where('user_id', $member->id)->first();
        $this->assertNotNull($sub);
        $this->assertTrue((bool) $sub->is_unlimited);

        // For unlimited packages, user display credits should NOT be incremented
        $this->assertEquals(0, $member->fresh()->credits);
    }

    // ── Unlimited subscription can book without credits ───────────────────────

    public function test_unlimited_subscription_member_can_book_without_credits(): void
    {
        $package = Package::create([
            'name' => 'Unlimited',
            'credits' => 0,
            'period_days' => 30,
            'price' => 299,
            'is_active' => true,
            'is_unlimited' => true,
            'sort_order' => 0,
        ]);

        $member = User::factory()->create(['credits' => 0]);
        UserSubscription::create([
            'user_id' => $member->id,
            'package_id' => $package->id,
            'credits_granted' => 0,
            'credits_remaining' => 0,
            'started_at' => now()->subDay(),
            'expires_at' => now()->addDays(30),
            'status' => 'active',
            'is_unlimited' => true,
        ]);

        $gymClass = \App\Models\GymClass::factory()->create([
            'capacity' => 10,
            'start_time' => now()->addDay(),
        ]);

        $service = app(BookingService::class);
        $result = $service->book($member, $gymClass);

        $this->assertEquals('booked', $result['status']);

        // No credits deducted
        $this->assertEquals(0, $member->fresh()->credits);
    }

    // ── Updating package to unlimited ─────────────────────────────────────────

    public function test_admin_can_update_package_to_unlimited(): void
    {
        $package = Package::create([
            'name' => 'Pro Plan',
            'credits' => 10,
            'period_days' => 30,
            'price' => 199,
            'is_active' => true,
            'is_unlimited' => false,
            'sort_order' => 0,
        ]);

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.packages.update', $package), [
                'is_unlimited' => true,
                'credits' => 999,
            ]);

        $response->assertRedirect();

        $this->assertTrue((bool) $package->fresh()->is_unlimited);
    }
}
