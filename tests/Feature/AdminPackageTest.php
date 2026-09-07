<?php

namespace Tests\Feature;

use App\Models\CreditTransaction;
use App\Models\GymClass;
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

    // ── Test 1: Admin creates unlimited package → credits stored as 0 ─────────

    public function test_admin_can_create_unlimited_package(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.packages.store'), [
                'name' => 'Unlimited Monthly',
                'description' => 'Unlimited access for 30 days',
                'credits' => 0,
                'period_days' => 30,
                'price' => '299.00',
                'is_active' => true,
                'is_unlimited' => true,
                'sort_order' => 0,
            ]);

        $response->assertRedirect();

        $pkg = Package::where('name', 'Unlimited Monthly')->first();
        $this->assertNotNull($pkg);
        $this->assertTrue((bool) $pkg->is_unlimited);
        $this->assertEquals(0, $pkg->credits);
    }

    // ── Test 2: Backend normalizes credits to 0 even when frontend sends 999 ───

    public function test_backend_forces_credits_zero_for_unlimited_package_even_if_frontend_sends_999(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.packages.store'), [
                'name' => 'Unlimited Hyrox',
                'credits' => 999,
                'period_days' => 30,
                'price' => '399.00',
                'is_active' => true,
                'is_unlimited' => true,
                'sort_order' => 0,
            ]);

        $pkg = Package::where('name', 'Unlimited Hyrox')->first();
        $this->assertEquals(0, $pkg->credits, 'Backend must normalize unlimited package credits to 0');
        $this->assertTrue((bool) $pkg->is_unlimited);
    }

    // ── Test 3: Admin updates finite package → unlimited, credits become 0 ──────

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

        $fresh = $package->fresh();
        $this->assertTrue((bool) $fresh->is_unlimited);
        $this->assertEquals(0, $fresh->credits, 'Backend must normalize credits to 0 on update to unlimited');
    }

    // ── Test 4: Admin assigns unlimited package via route → subscription has credits=0 ──

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
        $this->assertEquals(0, $sub->credits_granted, 'Unlimited subscription must have credits_granted=0');
        $this->assertEquals(0, $sub->credits_remaining, 'Unlimited subscription must have credits_remaining=0');

        // User display credits should NOT be incremented
        $this->assertEquals(0, $member->fresh()->credits);
    }

    // ── Test 5: Unlimited assignment creates NO package_assigned transaction ────

    public function test_unlimited_assignment_creates_no_credit_transaction(): void
    {
        $package = Package::create([
            'name' => 'Unlimited Full',
            'credits' => 0,
            'period_days' => 30,
            'price' => 299,
            'is_active' => true,
            'is_unlimited' => true,
            'sort_order' => 0,
        ]);

        $member = User::factory()->create(['credits' => 0]);

        $this->actingAs($this->admin)
            ->post(route('admin.users.subscriptions.store', $member), [
                'package_id' => $package->id,
            ]);

        $this->assertEquals(0,
            CreditTransaction::where('user_id', $member->id)->count(),
            'Unlimited package assignment must create no credit transaction'
        );
    }

    // ── Test 6: Finite package assignment is unchanged (credits copy, ledger created) ──

    public function test_finite_package_assignment_creates_subscription_and_ledger(): void
    {
        $package = Package::create([
            'name' => '10-Class Pack',
            'credits' => 10,
            'period_days' => 60,
            'price' => 150,
            'is_active' => true,
            'is_unlimited' => false,
            'sort_order' => 0,
        ]);

        $member = User::factory()->create(['credits' => 0]);

        $this->actingAs($this->admin)
            ->post(route('admin.users.subscriptions.store', $member), [
                'package_id' => $package->id,
            ]);

        $sub = UserSubscription::where('user_id', $member->id)->first();
        $this->assertEquals(10, $sub->credits_granted);
        $this->assertEquals(10, $sub->credits_remaining);
        $this->assertFalse((bool) $sub->is_unlimited);

        $this->assertEquals(1,
            CreditTransaction::where('user_id', $member->id)
                ->where('type', 'package_assigned')
                ->count(),
            'Finite package assignment must create exactly one package_assigned transaction'
        );

        $tx = CreditTransaction::where('user_id', $member->id)->first();
        $this->assertEquals(10, $tx->amount);
    }

    // ── Test 7: Unlimited subscription can book without credits ───────────────

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

        $gymClass = GymClass::factory()->create([
            'capacity' => 10,
            'start_time' => now()->addDay(),
        ]);

        $result = app(BookingService::class)->book($member, $gymClass);

        $this->assertEquals('booked', $result['status']);
        $this->assertEquals(0, $member->fresh()->credits);
    }

    // ── Test 8: No code treats credits >= 999 as the unlimited sentinel ────────

    public function test_package_with_credits_999_and_is_unlimited_false_is_not_treated_as_unlimited(): void
    {
        // Simulate a legacy bad-data package (pre-fix migration would have found and corrected
        // these, but verify the model + booking service do not treat credits=999 as unlimited)
        $package = Package::factory()->create([
            'credits' => 999,
            'is_unlimited' => false,
        ]);

        $user = User::factory()->create(['credits' => 999]);
        $sub = UserSubscription::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'credits_granted' => 999,
            'credits_remaining' => 999,
            'started_at' => now()->subDay(),
            'expires_at' => now()->addDays(30),
            'status' => 'active',
            'is_unlimited' => false,
        ]);

        // isUnlimited() must return false — only the flag matters, not the credit count
        $this->assertFalse($sub->isUnlimited());
        $this->assertFalse((bool) $package->is_unlimited);

        // Booking should deduct a credit (finite behaviour), not treat it as unlimited
        $class = GymClass::factory()->create(['capacity' => 10, 'start_time' => now()->addDay()]);
        $result = app(BookingService::class)->book($user, $class);

        $this->assertEquals('booked', $result['status']);
        $this->assertDatabaseHas('class_bookings', [
            'user_id' => $user->id,
            'gym_class_id' => $class->id,
            'credit_charged' => 1,
        ]);
        $this->assertEquals(998, $sub->fresh()->credits_remaining);
    }
}
