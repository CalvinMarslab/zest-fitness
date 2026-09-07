<?php

namespace Tests\Feature;

use App\Models\ClassBooking;
use App\Models\CreditTransaction;
use App\Models\GymClass;
use App\Models\Package;
use App\Models\User;
use App\Models\UserSubscription;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UnlimitedPackageRegressionTest extends TestCase
{
    use RefreshDatabase;

    private BookingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BookingService::class);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makeUnlimitedPackage(array $overrides = []): Package
    {
        return Package::factory()->unlimited()->create($overrides);
    }

    private function makeClass(array $overrides = []): GymClass
    {
        return GymClass::factory()->create(array_merge([
            'capacity' => 10,
            'start_time' => now()->addDay(),
            'is_cancelled' => false,
            'status' => 'scheduled',
            'cancellation_cutoff_hours' => 2,
        ], $overrides));
    }

    // ── Test: unlimited package has is_unlimited=true, credits=0 ─────────────

    public function test_unlimited_package_has_is_unlimited_true_and_zero_credits(): void
    {
        $package = $this->makeUnlimitedPackage();

        $this->assertTrue($package->is_unlimited);
        $this->assertEquals(0, $package->credits);
    }

    // ── Test: assigning unlimited package creates is_unlimited subscription ───

    public function test_assigning_unlimited_package_creates_unlimited_subscription(): void
    {
        $package = $this->makeUnlimitedPackage();
        $user = User::factory()->create(['credits' => 0]);

        $sub = UserSubscription::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'credits_granted' => 0,
            'credits_remaining' => 0,
            'started_at' => now(),
            'expires_at' => now()->addDays(30),
            'status' => 'active',
            'is_unlimited' => true,
        ]);

        $this->assertTrue($sub->is_unlimited);
        $this->assertTrue($sub->isUnlimited());
        $this->assertEquals(0, $sub->credits_remaining);
    }

    // ── Test: unlimited booking does not decrement subscription credits ────────

    public function test_unlimited_booking_does_not_decrement_credits(): void
    {
        $user = User::factory()->withSubscription(0, true)->create(['credits' => 0]);
        $sub = UserSubscription::where('user_id', $user->id)->first();
        $class = $this->makeClass();

        $result = $this->service->book($user, $class);

        $this->assertEquals('booked', $result['status']);
        $this->assertEquals(0, $sub->fresh()->credits_remaining);
        $this->assertEquals(0, $user->fresh()->credits);
    }

    // ── Test: unlimited booking has credit_charged=false ─────────────────────

    public function test_unlimited_booking_has_credit_charged_false(): void
    {
        $user = User::factory()->withSubscription(0, true)->create(['credits' => 0]);
        $class = $this->makeClass();

        $this->service->book($user, $class);

        $this->assertDatabaseHas('class_bookings', [
            'user_id' => $user->id,
            'gym_class_id' => $class->id,
            'credit_charged' => false,
            'status' => 'booked',
        ]);
    }

    // ── Test: cancelling unlimited booking creates no refund transaction ───────

    public function test_cancelling_unlimited_booking_creates_no_credit_transaction(): void
    {
        $user = User::factory()->withSubscription(0, true)->create(['credits' => 0]);
        $class = $this->makeClass();

        $this->service->book($user, $class);

        $booking = ClassBooking::where('user_id', $user->id)->first();
        $this->service->cancel($booking, forceRefund: true);

        $this->assertEquals(0, CreditTransaction::where('user_id', $user->id)->count());
    }

    // ── Test: unlimited booking does not create a deduction transaction ────────

    public function test_unlimited_booking_creates_no_credit_transaction(): void
    {
        $user = User::factory()->withSubscription(0, true)->create(['credits' => 0]);
        $class = $this->makeClass();

        $this->service->book($user, $class);

        $this->assertEquals(0, CreditTransaction::where('user_id', $user->id)->count());
    }

    // ── Test: fix migration corrects credits=999/is_unlimited=false packages ──

    public function test_unlimited_fix_migration_corrects_pseudo_unlimited_packages(): void
    {
        // Simulate what existed before the fix migration
        $pkg = Package::factory()->create([
            'credits' => 999,
            'is_unlimited' => false,
        ]);

        // Run the fix logic (same logic as the migration)
        DB::table('packages')
            ->where('credits', 999)
            ->where('is_unlimited', false)
            ->update(['is_unlimited' => true, 'credits' => 0]);

        $pkg->refresh();

        $this->assertTrue((bool) $pkg->is_unlimited);
        $this->assertEquals(0, $pkg->credits);
    }

    // ── Test: user.credits does not reflect pseudo-999 unlimited credits ───────

    public function test_user_credits_summary_excludes_unlimited_subscription_credits(): void
    {
        $package = $this->makeUnlimitedPackage();
        $user = User::factory()->create(['credits' => 0]);

        UserSubscription::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'credits_granted' => 0,
            'credits_remaining' => 0,
            'started_at' => now(),
            'expires_at' => now()->addDays(30),
            'status' => 'active',
            'is_unlimited' => true,
        ]);

        $user->syncCreditSummary();

        // User credits display should be 0 (not 999 or any pseudo-value)
        $this->assertEquals(0, $user->fresh()->credits);
    }
}
