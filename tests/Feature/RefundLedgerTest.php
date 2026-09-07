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
use Tests\TestCase;

class RefundLedgerTest extends TestCase
{
    use RefreshDatabase;

    private BookingService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->svc = app(BookingService::class);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makeUser(int $credits = 0): User
    {
        return User::factory()->create(['credits' => $credits]);
    }

    private function makeSub(User $user, int $credits = 5, bool $unlimited = false): UserSubscription
    {
        $pkg = Package::create([
            'name' => 'Test Pack', 'credits' => $credits, 'period_days' => 30,
            'price' => 0, 'is_active' => true, 'sort_order' => 1, 'is_trial' => true,
            'is_unlimited' => $unlimited,
        ]);

        return UserSubscription::create([
            'user_id' => $user->id,
            'package_id' => $pkg->id,
            'credits_granted' => $credits,
            'credits_remaining' => $unlimited ? 999 : $credits,
            'is_unlimited' => $unlimited,
            'started_at' => now(),
            'expires_at' => now()->addDays(30),
            'status' => 'active',
        ]);
    }

    private function makeClass(bool $future = true): GymClass
    {
        return GymClass::factory()->create([
            'start_time' => $future ? now()->addHours(24) : now()->subHours(1),
            'capacity' => 20,
            'is_cancelled' => false,
        ]);
    }

    // ── Test 1: Normal refund ─────────────────────────────────────────────────

    public function test_normal_booking_refund_restores_credit_and_creates_transaction(): void
    {
        $user = $this->makeUser();
        $sub = $this->makeSub($user, 5);
        $class = $this->makeClass();

        $result = $this->svc->book($user, $class);
        $this->assertEquals('booked', $result['status']);

        $sub->refresh();
        $this->assertEquals(4, $sub->credits_remaining);

        $booking = ClassBooking::where('user_id', $user->id)
            ->where('gym_class_id', $class->id)
            ->first();

        $this->svc->cancel($booking, null, true);

        $sub->refresh();
        $this->assertEquals(5, $sub->credits_remaining, 'Subscription should be back to 5');

        $booking->refresh();
        $this->assertNotNull($booking->credit_refunded_at);

        $this->assertDatabaseCount('credit_transactions', 2); // deduction + refund
        $refund = CreditTransaction::where('class_booking_id', $booking->id)
            ->where('type', 'booking_refund')
            ->first();
        $this->assertNotNull($refund);
        $this->assertEquals(+1, $refund->amount);
        $this->assertEquals($sub->id, $refund->user_subscription_id);
    }

    // ── Test 2: Duplicate cancellation is idempotent ──────────────────────────

    public function test_duplicate_cancellation_does_not_double_refund(): void
    {
        $user = $this->makeUser();
        $sub = $this->makeSub($user, 5);
        $class = $this->makeClass();

        $this->svc->book($user, $class);
        $booking = ClassBooking::where('user_id', $user->id)->where('gym_class_id', $class->id)->first();

        $this->svc->cancel($booking, null, true);
        $booking->refresh();

        // Attempt a second cancel — should be idempotent
        $result = $this->svc->cancel($booking, null, true);
        $this->assertEquals('already_cancelled', $result['status']);

        $sub->refresh();
        $this->assertEquals(5, $sub->credits_remaining, 'Credit must not be refunded twice');

        // Still exactly one refund transaction
        $this->assertEquals(
            1,
            CreditTransaction::where('class_booking_id', $booking->id)
                ->where('type', 'booking_refund')
                ->count()
        );
    }

    // ── Test 3: Missing original subscription throws; no fake transaction ─────

    public function test_missing_original_subscription_throws_and_leaves_no_transaction(): void
    {
        $user = $this->makeUser();
        $sub = $this->makeSub($user, 5);
        $class = $this->makeClass();

        $this->svc->book($user, $class);
        $booking = ClassBooking::where('user_id', $user->id)->where('gym_class_id', $class->id)->first();

        // Simulate the subscription being deleted after booking
        $subId = $sub->id;
        $sub->delete();

        $this->expectException(\RuntimeException::class);

        try {
            $this->svc->cancel($booking, null, true);
        } finally {
            // Booking should still be cancelled (outer update ran before refundCredit)
            // but credit_refunded_at must remain null
            $booking->refresh();
            $this->assertNull($booking->credit_refunded_at, 'credit_refunded_at must remain null');

            // No +1 refund transaction must exist
            $this->assertEquals(
                0,
                CreditTransaction::where('type', 'booking_refund')->count(),
                'No fake refund transaction must be created'
            );

            // No other subscription should have been altered
            $this->assertDatabaseMissing('user_subscriptions', ['id' => $subId]);
        }
    }

    // ── Test 4: Unlimited booking produces no refund transaction ─────────────

    public function test_unlimited_booking_cancellation_produces_no_refund_transaction(): void
    {
        $user = $this->makeUser();
        $sub = $this->makeSub($user, 999, true); // unlimited
        $class = $this->makeClass();

        $result = $this->svc->book($user, $class);
        $this->assertEquals('booked', $result['status']);

        $booking = ClassBooking::where('user_id', $user->id)->where('gym_class_id', $class->id)->first();
        $this->assertFalse((bool) $booking->credit_charged, 'Unlimited bookings must not charge a credit');

        $this->svc->cancel($booking, null, true);

        $this->assertEquals(0, CreditTransaction::count(), 'No transactions for unlimited booking');
    }

    // ── Test 5: Waitlist cancellation produces no refund transaction ──────────

    public function test_waitlist_cancellation_produces_no_refund_transaction(): void
    {
        $user = $this->makeUser();
        $sub = $this->makeSub($user, 5);

        // Full class so user is waitlisted
        $class = GymClass::factory()->create([
            'start_time' => now()->addHours(24),
            'capacity' => 1,
            'is_cancelled' => false,
        ]);

        // Fill the class first
        $other = $this->makeUser();
        $this->makeSub($other, 5);
        $this->svc->book($other, $class);

        // Now our user goes on waitlist
        $result = $this->svc->book($user, $class);
        $this->assertEquals('waitlisted', $result['status']);

        $booking = ClassBooking::where('user_id', $user->id)->where('gym_class_id', $class->id)->first();
        $this->assertFalse((bool) $booking->credit_charged, 'Waitlisted bookings must not charge a credit');

        $this->svc->cancel($booking);

        // Only the other user's deduction should exist
        $this->assertEquals(
            0,
            CreditTransaction::where('user_id', $user->id)->count(),
            'Waitlist cancellation must not create any transaction for the user'
        );
    }

    // ── Test 6: Class cancellation creates class_cancel_refund transactions ───

    public function test_class_cancellation_creates_class_cancel_refund_transaction(): void
    {
        $user = $this->makeUser();
        $sub = $this->makeSub($user, 5);
        $class = $this->makeClass();

        $this->svc->book($user, $class);

        $booking = ClassBooking::where('user_id', $user->id)->where('gym_class_id', $class->id)->first();
        $this->assertTrue((bool) $booking->credit_charged);

        $this->svc->cancelClass($class);

        $sub->refresh();
        $this->assertEquals(5, $sub->credits_remaining, 'Credit restored after class cancel');

        $booking->refresh();
        $this->assertNotNull($booking->credit_refunded_at);

        $transactions = CreditTransaction::where('class_booking_id', $booking->id)->get();
        $this->assertEquals(2, $transactions->count(), 'Deduction + class_cancel_refund');

        $refund = $transactions->firstWhere('type', 'class_cancel_refund');
        $this->assertNotNull($refund, 'Must have a class_cancel_refund transaction');
        $this->assertEquals(+1, $refund->amount);
        $this->assertEquals($sub->id, $refund->user_subscription_id);
    }
}
