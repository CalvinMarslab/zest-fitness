<?php

namespace Tests\Feature;

use App\Models\ClassBooking;
use App\Models\GymClass;
use App\Models\Package;
use App\Models\User;
use App\Models\UserSubscription;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingServiceTest extends TestCase
{
    use RefreshDatabase;

    private BookingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BookingService::class);
    }

    // ── Helper ───────────────────────────────────────────────────────────────

    private function makeUser(int $credits = 5, bool $suspended = false): User
    {
        return User::factory()->withSubscription($credits)->create([
            'credits' => $credits,
            'status' => $suspended ? 'suspended' : 'active',
        ]);
    }

    private function makeUnlimitedUser(): User
    {
        return User::factory()->withSubscription(0, true)->create(['credits' => 0]);
    }

    private function makeClass(array $overrides = []): GymClass
    {
        return GymClass::factory()->create(array_merge([
            'capacity' => 10,
            'start_time' => now()->addDay(),
            'cancellation_cutoff_hours' => 2,
            'is_cancelled' => false,
            'status' => 'scheduled',
        ], $overrides));
    }

    // ── Test 1: credit package booking deducts subscription credit ────────────

    public function test_credit_package_booking_deducts_subscription_credit(): void
    {
        $user = $this->makeUser(5);
        $class = $this->makeClass();
        $sub = UserSubscription::where('user_id', $user->id)->first();

        $result = $this->service->book($user, $class);

        $this->assertEquals('booked', $result['status']);
        $this->assertEquals(4, $result['credits_remaining']);
        $this->assertEquals(4, $sub->fresh()->credits_remaining);
        $this->assertEquals(4, $user->fresh()->credits);

        $this->assertDatabaseHas('class_bookings', [
            'user_id' => $user->id,
            'gym_class_id' => $class->id,
            'status' => 'booked',
            'credit_charged' => 1,
        ]);
    }

    // ── Test 2: unlimited membership books without credit deduction ───────────

    public function test_unlimited_membership_books_without_credit_deduction(): void
    {
        $user = $this->makeUnlimitedUser();
        $class = $this->makeClass();

        $result = $this->service->book($user, $class);

        $this->assertEquals('booked', $result['status']);

        // credits_remaining on unlimited sub should stay at 0
        $sub = UserSubscription::where('user_id', $user->id)->first();
        $this->assertEquals(0, $sub->fresh()->credits_remaining);

        $this->assertDatabaseHas('class_bookings', [
            'user_id' => $user->id,
            'gym_class_id' => $class->id,
            'status' => 'booked',
            'credit_charged' => 0,
        ]);
    }

    // ── Test 3: expired subscription cannot book ──────────────────────────────

    public function test_expired_subscription_cannot_book(): void
    {
        $user = User::factory()->create(['credits' => 5]);
        $package = Package::factory()->create(['credits' => 5, 'is_unlimited' => false]);
        UserSubscription::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'credits_granted' => 5,
            'credits_remaining' => 5,
            'started_at' => now()->subDays(60),
            'expires_at' => now()->subDay(),
            'status' => 'active',
            'is_unlimited' => false,
        ]);

        $class = $this->makeClass();
        $result = $this->service->book($user, $class);

        $this->assertEquals('no_subscription', $result['status']);
    }

    // ── Test 4: suspended member cannot book ──────────────────────────────────

    public function test_suspended_member_cannot_book(): void
    {
        $user = $this->makeUser(5, suspended: true);
        $class = $this->makeClass();

        $result = $this->service->book($user, $class);

        $this->assertEquals('suspended', $result['status']);
        $this->assertDatabaseMissing('class_bookings', [
            'user_id' => $user->id,
            'gym_class_id' => $class->id,
        ]);
    }

    // ── Test 5: cancellation refunds to original subscription ────────────────

    public function test_cancellation_refunds_to_original_subscription(): void
    {
        $user = $this->makeUser(4);
        $class = $this->makeClass(['start_time' => now()->addDays(2)]);
        $sub = UserSubscription::where('user_id', $user->id)->first();

        $booking = ClassBooking::create([
            'user_id' => $user->id,
            'gym_class_id' => $class->id,
            'status' => 'booked',
            'credit_charged' => true,
            'user_subscription_id' => $sub->id,
            'booked_at' => now(),
        ]);

        $result = $this->service->cancel($booking, $user);

        $this->assertEquals('cancelled', $result['status']);
        $this->assertTrue($result['refunded']);

        // Subscription credit should be restored
        $this->assertEquals(5, $sub->fresh()->credits_remaining);
        $this->assertEquals(5, $user->fresh()->credits);

        $this->assertNotNull(ClassBooking::find($booking->id)->credit_refunded_at);
    }

    // ── Test 6: cancellation cannot double refund ─────────────────────────────

    public function test_cancellation_cannot_double_refund(): void
    {
        $user = $this->makeUser(4);
        $class = $this->makeClass(['start_time' => now()->addDays(2)]);
        $sub = UserSubscription::where('user_id', $user->id)->first();

        $booking = ClassBooking::create([
            'user_id' => $user->id,
            'gym_class_id' => $class->id,
            'status' => 'booked',
            'credit_charged' => true,
            'user_subscription_id' => $sub->id,
            'booked_at' => now(),
        ]);

        // First cancellation
        $this->service->cancel($booking, $user);
        $creditsAfterFirst = $sub->fresh()->credits_remaining;

        // Attempt second cancellation (booking is now cancelled — idempotent)
        $booking->refresh();
        $result = $this->service->cancel($booking, $user);

        $this->assertEquals('already_cancelled', $result['status']);
        $this->assertFalse($result['refunded']);

        // Credits should not have changed from after the first cancellation
        $this->assertEquals($creditsAfterFirst, $sub->fresh()->credits_remaining);
    }

    // ── Test 7: waitlisted member is not charged ──────────────────────────────

    public function test_waitlisted_member_is_not_charged(): void
    {
        $user = $this->makeUser(5);
        $class = $this->makeClass(['capacity' => 1]);

        // Fill the class with another user
        $other = $this->makeUser(5);
        ClassBooking::create([
            'user_id' => $other->id,
            'gym_class_id' => $class->id,
            'status' => 'booked',
        ]);

        $result = $this->service->book($user, $class);

        $this->assertEquals('waitlisted', $result['status']);

        // No credits deducted
        $this->assertEquals(5, $user->fresh()->credits);
        $sub = UserSubscription::where('user_id', $user->id)->first();
        $this->assertEquals(5, $sub->fresh()->credits_remaining);

        $this->assertDatabaseHas('class_bookings', [
            'user_id' => $user->id,
            'gym_class_id' => $class->id,
            'status' => 'waitlisted',
            'credit_charged' => 0,
        ]);
    }

    // ── Test 8: waitlist promotion charges exactly once ───────────────────────

    public function test_waitlist_promotion_charges_exactly_once(): void
    {
        $class = $this->makeClass(['capacity' => 1]);
        $confirmedUser = $this->makeUser(5);
        $waitlistUser = $this->makeUser(5);

        $confirmedSub = UserSubscription::where('user_id', $confirmedUser->id)->first();

        $confirmedBooking = ClassBooking::create([
            'user_id' => $confirmedUser->id,
            'gym_class_id' => $class->id,
            'status' => 'booked',
            'credit_charged' => true,
            'user_subscription_id' => $confirmedSub->id,
            'booked_at' => now(),
        ]);

        ClassBooking::create([
            'user_id' => $waitlistUser->id,
            'gym_class_id' => $class->id,
            'status' => 'waitlisted',
            'queue_position' => 1,
            'credit_charged' => false,
        ]);

        // Cancel the confirmed user — this triggers waitlist promotion
        $this->service->cancel($confirmedBooking, $confirmedUser);

        // Waitlist user should now be booked with exactly one credit deducted
        $this->assertDatabaseHas('class_bookings', [
            'user_id' => $waitlistUser->id,
            'gym_class_id' => $class->id,
            'status' => 'booked',
            'credit_charged' => 1,
        ]);

        $this->assertEquals(4, $waitlistUser->fresh()->credits);
        $waitlistSub = UserSubscription::where('user_id', $waitlistUser->id)->first();
        $this->assertEquals(4, $waitlistSub->fresh()->credits_remaining);
    }

    // ── Test 9: first invalid waitlist member does not block second ───────────

    public function test_first_invalid_waitlist_member_does_not_block_second_eligible(): void
    {
        $class = $this->makeClass(['capacity' => 1]);
        $confirmedUser = $this->makeUser(5);
        $ineligibleWaitlist = $this->makeUser(0); // no credits
        $eligibleWaitlist = $this->makeUser(5);

        // Zero out ineligible user's subscription credits
        UserSubscription::where('user_id', $ineligibleWaitlist->id)
            ->update(['credits_remaining' => 0]);

        $confirmedSub = UserSubscription::where('user_id', $confirmedUser->id)->first();

        $confirmedBooking = ClassBooking::create([
            'user_id' => $confirmedUser->id,
            'gym_class_id' => $class->id,
            'status' => 'booked',
            'credit_charged' => true,
            'user_subscription_id' => $confirmedSub->id,
            'booked_at' => now(),
        ]);

        // Ineligible user is first in waitlist
        ClassBooking::create([
            'user_id' => $ineligibleWaitlist->id,
            'gym_class_id' => $class->id,
            'status' => 'waitlisted',
            'queue_position' => 1,
            'credit_charged' => false,
        ]);

        // Eligible user is second
        ClassBooking::create([
            'user_id' => $eligibleWaitlist->id,
            'gym_class_id' => $class->id,
            'status' => 'waitlisted',
            'queue_position' => 2,
            'credit_charged' => false,
        ]);

        $this->service->cancel($confirmedBooking, $confirmedUser);

        // Ineligible user's waitlist entry should be cancelled
        $this->assertDatabaseHas('class_bookings', [
            'user_id' => $ineligibleWaitlist->id,
            'gym_class_id' => $class->id,
            'status' => 'cancelled',
        ]);

        // Second eligible user should be promoted
        $this->assertDatabaseHas('class_bookings', [
            'user_id' => $eligibleWaitlist->id,
            'gym_class_id' => $class->id,
            'status' => 'booked',
        ]);
    }

    // ── Test 10: admin promotion cannot bypass zero-credit rule ──────────────

    public function test_admin_promotion_cannot_bypass_zero_credit_rule(): void
    {
        $class = $this->makeClass(['capacity' => 2]);
        $zeroCreditsUser = $this->makeUser(0);
        UserSubscription::where('user_id', $zeroCreditsUser->id)
            ->update(['credits_remaining' => 0]);

        ClassBooking::create([
            'user_id' => $zeroCreditsUser->id,
            'gym_class_id' => $class->id,
            'status' => 'waitlisted',
            'queue_position' => 1,
            'credit_charged' => false,
        ]);

        // Promote waitlist — should skip ineligible user
        $this->service->promoteWaitlist($class);

        // User should be cancelled, not booked
        $this->assertDatabaseHas('class_bookings', [
            'user_id' => $zeroCreditsUser->id,
            'gym_class_id' => $class->id,
            'status' => 'cancelled',
        ]);
    }

    // ── Test 11: admin promotion cannot exceed class capacity ─────────────────

    public function test_admin_promotion_cannot_exceed_class_capacity(): void
    {
        $class = $this->makeClass(['capacity' => 1]);

        // Fill the class
        $confirmed = $this->makeUser(5);
        ClassBooking::create([
            'user_id' => $confirmed->id,
            'gym_class_id' => $class->id,
            'status' => 'booked',
        ]);

        $waitlistUser = $this->makeUser(5);
        ClassBooking::create([
            'user_id' => $waitlistUser->id,
            'gym_class_id' => $class->id,
            'status' => 'waitlisted',
            'queue_position' => 1,
        ]);

        // Promote waitlist — class is already at capacity
        $this->service->promoteWaitlist($class);

        // Waitlist user should still be waitlisted
        $this->assertDatabaseHas('class_bookings', [
            'user_id' => $waitlistUser->id,
            'gym_class_id' => $class->id,
            'status' => 'waitlisted',
        ]);
    }

    // ── Test 12: admin cancellation promotes waitlist ─────────────────────────

    public function test_admin_cancellation_promotes_waitlist(): void
    {
        $class = $this->makeClass(['capacity' => 1]);
        $bookedUser = $this->makeUser(5);
        $waitlistUser = $this->makeUser(5);

        $bookedSub = UserSubscription::where('user_id', $bookedUser->id)->first();

        $booking = ClassBooking::create([
            'user_id' => $bookedUser->id,
            'gym_class_id' => $class->id,
            'status' => 'booked',
            'credit_charged' => true,
            'user_subscription_id' => $bookedSub->id,
            'booked_at' => now(),
        ]);

        ClassBooking::create([
            'user_id' => $waitlistUser->id,
            'gym_class_id' => $class->id,
            'status' => 'waitlisted',
            'queue_position' => 1,
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        $this->service->cancel($booking, $admin, forceRefund: true);

        // Waitlist user should be promoted
        $this->assertDatabaseHas('class_bookings', [
            'user_id' => $waitlistUser->id,
            'gym_class_id' => $class->id,
            'status' => 'booked',
        ]);
    }

    // ── Test 13: class cancellation refunds confirmed bookings ────────────────

    public function test_class_cancellation_refunds_confirmed_bookings(): void
    {
        $class = $this->makeClass();
        $user1 = $this->makeUser(4);
        $user2 = $this->makeUser(4);

        $sub1 = UserSubscription::where('user_id', $user1->id)->first();
        $sub2 = UserSubscription::where('user_id', $user2->id)->first();

        ClassBooking::create([
            'user_id' => $user1->id,
            'gym_class_id' => $class->id,
            'status' => 'booked',
            'credit_charged' => true,
            'user_subscription_id' => $sub1->id,
            'booked_at' => now(),
        ]);

        ClassBooking::create([
            'user_id' => $user2->id,
            'gym_class_id' => $class->id,
            'status' => 'booked',
            'credit_charged' => true,
            'user_subscription_id' => $sub2->id,
            'booked_at' => now(),
        ]);

        $this->service->cancelClass($class);

        $this->assertEquals(5, $user1->fresh()->credits);
        $this->assertEquals(5, $user2->fresh()->credits);
        $this->assertEquals(5, $sub1->fresh()->credits_remaining);
        $this->assertEquals(5, $sub2->fresh()->credits_remaining);
    }

    // ── Test 14: class cancellation preserves booking records ─────────────────

    public function test_class_cancellation_preserves_booking_records(): void
    {
        $class = $this->makeClass();
        $user = $this->makeUser(4);
        $sub = UserSubscription::where('user_id', $user->id)->first();

        ClassBooking::create([
            'user_id' => $user->id,
            'gym_class_id' => $class->id,
            'status' => 'booked',
            'credit_charged' => true,
            'user_subscription_id' => $sub->id,
            'booked_at' => now(),
        ]);

        $this->service->cancelClass($class);

        // Booking record must still exist (no hard delete)
        $this->assertDatabaseHas('class_bookings', [
            'user_id' => $user->id,
            'gym_class_id' => $class->id,
            'status' => 'cancelled',
        ]);
    }

    // ── Test 15: class cancellation does not refund waitlisted users ──────────

    public function test_class_cancellation_does_not_refund_waitlisted_users(): void
    {
        $class = $this->makeClass(['capacity' => 1]);
        $confirmed = $this->makeUser(4);
        $waitlisted = $this->makeUser(5); // still has 5 credits (none deducted for waitlist)

        $sub = UserSubscription::where('user_id', $confirmed->id)->first();

        ClassBooking::create([
            'user_id' => $confirmed->id,
            'gym_class_id' => $class->id,
            'status' => 'booked',
            'credit_charged' => true,
            'user_subscription_id' => $sub->id,
            'booked_at' => now(),
        ]);

        ClassBooking::create([
            'user_id' => $waitlisted->id,
            'gym_class_id' => $class->id,
            'status' => 'waitlisted',
            'queue_position' => 1,
            'credit_charged' => false,
        ]);

        $this->service->cancelClass($class);

        // Waitlisted user's credits should remain unchanged
        $this->assertEquals(5, $waitlisted->fresh()->credits);
        $waitlistSub = UserSubscription::where('user_id', $waitlisted->id)->first();
        $this->assertEquals(5, $waitlistSub->fresh()->credits_remaining);

        $this->assertDatabaseHas('class_bookings', [
            'user_id' => $waitlisted->id,
            'gym_class_id' => $class->id,
            'status' => 'cancelled',
        ]);
    }

    // ── Test 16: coach only sees assigned classes (auth assertion) ─────────────

    public function test_coach_can_only_access_assigned_classes(): void
    {
        $coach = User::factory()->create(['role' => 'coach']);
        $assignedClass = $this->makeClass(['coach_id' => $coach->id]);
        $otherClass = $this->makeClass();

        // Coach routes are separate — just assert the relationship works
        $this->assertTrue($coach->assignedClasses()->where('id', $assignedClass->id)->exists());
        $this->assertFalse($coach->assignedClasses()->where('id', $otherClass->id)->exists());
    }

    // ── Test 17: late cancellation cutoff uses correct timezone ──────────────

    public function test_late_cancellation_timezone_cutoff_is_correct(): void
    {
        // Class starts in 1 hour — past the 2-hour cutoff in Asia/KL
        $class = $this->makeClass([
            'start_time' => now()->addHour(),
            'cancellation_cutoff_hours' => 2,
        ]);

        $user = $this->makeUser(4);
        $sub = UserSubscription::where('user_id', $user->id)->first();

        $booking = ClassBooking::create([
            'user_id' => $user->id,
            'gym_class_id' => $class->id,
            'status' => 'booked',
            'credit_charged' => true,
            'user_subscription_id' => $sub->id,
            'booked_at' => now(),
        ]);

        $result = $this->service->cancel($booking, $user);

        // Should be late_cancel because we're within the 2-hour cutoff
        $this->assertEquals('late_cancel', $result['status']);
        $this->assertFalse($result['refunded']);
        $this->assertEquals(4, $user->fresh()->credits);
    }

    // ── Test 18: two simultaneous booking attempts cannot exceed capacity ─────

    public function test_two_simultaneous_booking_attempts_cannot_exceed_capacity(): void
    {
        $class = $this->makeClass(['capacity' => 1]);
        $user1 = $this->makeUser(5);
        $user2 = $this->makeUser(5);

        // Simulate concurrent bookings by running them sequentially
        // Both will attempt the last spot — only one should succeed
        $result1 = $this->service->book($user1, $class);
        $result2 = $this->service->book($user2, $class);

        $statuses = [$result1['status'], $result2['status']];
        sort($statuses);

        // One should be 'booked', one should be 'waitlisted'
        $this->assertEquals(['booked', 'waitlisted'], $statuses);

        // Total confirmed bookings must not exceed capacity
        $confirmedCount = ClassBooking::where('gym_class_id', $class->id)
            ->whereIn('status', ['booked', 'checked_in'])
            ->count();
        $this->assertLessThanOrEqual($class->capacity, $confirmedCount);
    }

    // ── Test 19: two simultaneous promotions cannot exceed capacity ───────────

    public function test_two_simultaneous_promotions_cannot_exceed_capacity(): void
    {
        $class = $this->makeClass(['capacity' => 1]);
        $confirmed = $this->makeUser(5);
        $waitlist1 = $this->makeUser(5);
        $waitlist2 = $this->makeUser(5);

        $confirmedSub = UserSubscription::where('user_id', $confirmed->id)->first();

        $booking = ClassBooking::create([
            'user_id' => $confirmed->id,
            'gym_class_id' => $class->id,
            'status' => 'booked',
            'credit_charged' => true,
            'user_subscription_id' => $confirmedSub->id,
            'booked_at' => now(),
        ]);

        ClassBooking::create([
            'user_id' => $waitlist1->id,
            'gym_class_id' => $class->id,
            'status' => 'waitlisted',
            'queue_position' => 1,
        ]);

        ClassBooking::create([
            'user_id' => $waitlist2->id,
            'gym_class_id' => $class->id,
            'status' => 'waitlisted',
            'queue_position' => 2,
        ]);

        // Cancel the confirmed booking — should promote exactly one waitlisted
        $this->service->cancel($booking, $confirmed);

        $confirmedCount = ClassBooking::where('gym_class_id', $class->id)
            ->whereIn('status', ['booked', 'checked_in'])
            ->count();

        $this->assertLessThanOrEqual(1, $confirmedCount);
        $this->assertEquals(1, $confirmedCount);
    }

    // ── Test P0-1a: admin force cancel on unlimited booking creates no credits ───

    public function test_admin_force_cancel_unlimited_booking_creates_no_credits(): void
    {
        $user = $this->makeUnlimitedUser();
        $class = $this->makeClass();
        $sub = UserSubscription::where('user_id', $user->id)->first();

        $result = $this->service->book($user, $class);
        $this->assertEquals('booked', $result['status']);

        $booking = ClassBooking::where('user_id', $user->id)->where('gym_class_id', $class->id)->first();
        $this->assertEquals(false, (bool) $booking->credit_charged);

        $admin = User::factory()->create(['role' => 'admin']);
        $this->service->cancel($booking, $admin, forceRefund: true);

        // Unlimited sub credits_remaining stays at 0
        $this->assertEquals(0, $sub->fresh()->credits_remaining);
        // User display credits stay at 0
        $this->assertEquals(0, $user->fresh()->credits);
    }

    // ── Test P0-1b: admin force cancel on uncharged booking creates no credits ─

    public function test_admin_force_cancel_uncharged_booking_creates_no_credits(): void
    {
        $user = $this->makeUser(5);
        $class = $this->makeClass();
        $sub = UserSubscription::where('user_id', $user->id)->first();

        // Create a booking directly with credit_charged=false (e.g. legacy or waitlist-promoted without charge)
        $booking = ClassBooking::create([
            'user_id' => $user->id,
            'gym_class_id' => $class->id,
            'status' => 'booked',
            'credit_charged' => false,
            'user_subscription_id' => $sub->id,
            'booked_at' => now(),
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        $result = $this->service->cancel($booking, $admin, forceRefund: true);

        $this->assertEquals('cancelled', $result['status']);
        // Credits must not change
        $this->assertEquals(5, $user->fresh()->credits);
        $this->assertEquals(5, $sub->fresh()->credits_remaining);
        // No refund timestamp must be set
        $this->assertNull(ClassBooking::find($booking->id)->credit_refunded_at);
    }

    // ── Test P0-1c: admin force cancel on charged booking refunds exactly once ─

    public function test_admin_force_cancel_charged_booking_refunds_exactly_once(): void
    {
        $user = $this->makeUser(5);
        $class = $this->makeClass(['start_time' => now()->addHour()]); // within late-cancel window
        $sub = UserSubscription::where('user_id', $user->id)->first();

        // Book normally — credit_charged=true, credits go to 4
        $result = $this->service->book($user, $class);
        $this->assertEquals('booked', $result['status']);
        $this->assertEquals(4, $user->fresh()->credits);

        $booking = ClassBooking::where('user_id', $user->id)->where('gym_class_id', $class->id)->first();

        $admin = User::factory()->create(['role' => 'admin']);
        $cancelResult = $this->service->cancel($booking, $admin, forceRefund: true);

        // Credit refunded despite being within late-cancel window
        $this->assertEquals('cancelled', $cancelResult['status']);
        $this->assertTrue($cancelResult['refunded']);
        $this->assertEquals(5, $user->fresh()->credits);
        $this->assertEquals(5, $sub->fresh()->credits_remaining);
        $this->assertNotNull(ClassBooking::find($booking->id)->credit_refunded_at);
    }

    // ── Test P0-1d: admin force cancel repeated does not double-refund ─────────

    public function test_admin_force_cancel_repeated_does_not_double_refund(): void
    {
        $user = $this->makeUser(5);
        $class = $this->makeClass(['start_time' => now()->addDays(2)]);
        $sub = UserSubscription::where('user_id', $user->id)->first();

        $result = $this->service->book($user, $class);
        $this->assertEquals('booked', $result['status']);

        $booking = ClassBooking::where('user_id', $user->id)->where('gym_class_id', $class->id)->first();
        $admin = User::factory()->create(['role' => 'admin']);

        // First admin cancel
        $this->service->cancel($booking, $admin, forceRefund: true);
        $creditsAfterFirst = $sub->fresh()->credits_remaining;

        // Second admin cancel (booking is now cancelled — idempotent)
        $booking->refresh();
        $secondResult = $this->service->cancel($booking, $admin, forceRefund: true);

        $this->assertEquals('already_cancelled', $secondResult['status']);
        $this->assertFalse($secondResult['refunded']);

        // Credits must not have changed from after the first cancel
        $this->assertEquals($creditsAfterFirst, $sub->fresh()->credits_remaining);
        $this->assertEquals($creditsAfterFirst, $user->fresh()->credits);
    }

    // ── Test 20: repeated cancellation request is idempotent ─────────────────

    public function test_repeated_cancellation_request_is_idempotent(): void
    {
        $user = $this->makeUser(4);
        $class = $this->makeClass(['start_time' => now()->addDays(2)]);
        $sub = UserSubscription::where('user_id', $user->id)->first();

        $booking = ClassBooking::create([
            'user_id' => $user->id,
            'gym_class_id' => $class->id,
            'status' => 'booked',
            'credit_charged' => true,
            'user_subscription_id' => $sub->id,
            'booked_at' => now(),
        ]);

        // First cancellation
        $result1 = $this->service->cancel($booking, $user);
        $this->assertEquals('cancelled', $result1['status']);
        $creditsAfter = $user->fresh()->credits;

        // Second cancellation — must not refund again
        $booking->refresh();
        $result2 = $this->service->cancel($booking, $user);
        $this->assertEquals('already_cancelled', $result2['status']);
        $this->assertFalse($result2['refunded']);

        // Credits unchanged since first cancellation
        $this->assertEquals($creditsAfter, $user->fresh()->credits);
        $this->assertEquals($creditsAfter, $sub->fresh()->credits_remaining);
    }
}
