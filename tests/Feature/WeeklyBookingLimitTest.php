<?php

namespace Tests\Feature;

use App\Models\ClassBooking;
use App\Models\GymClass;
use App\Models\Package;
use App\Models\User;
use App\Models\UserSubscription;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeeklyBookingLimitTest extends TestCase
{
    use RefreshDatabase;

    private BookingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BookingService::class);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makeLimitedUser(int $weeklyLimit = 2, int $credits = 10): array
    {
        $package = Package::factory()->limitedPlan($weeklyLimit)->create([
            'credits' => $credits,
            'period_days' => 30,
        ]);

        $user = User::factory()->create(['credits' => $credits]);

        $sub = UserSubscription::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'credits_granted' => $credits,
            'credits_remaining' => $credits,
            'started_at' => now()->subDay(),
            'expires_at' => now()->addDays(30),
            'status' => 'active',
            'is_unlimited' => false,
        ]);

        return [$user, $sub, $package];
    }

    private function makeUnlimitedUser(): array
    {
        $package = Package::factory()->unlimited()->create(['period_days' => 30]);

        $user = User::factory()->create(['credits' => 0]);

        $sub = UserSubscription::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'credits_granted' => 0,
            'credits_remaining' => 0,
            'started_at' => now()->subDay(),
            'expires_at' => now()->addDays(30),
            'status' => 'active',
            'is_unlimited' => true,
        ]);

        return [$user, $sub, $package];
    }

    private function makeClass(Carbon $startTime, int $capacity = 10): GymClass
    {
        return GymClass::factory()->create([
            'start_time' => $startTime,
            'capacity' => $capacity,
            'is_cancelled' => false,
            'status' => 'scheduled',
            'cancellation_cutoff_hours' => 2,
        ]);
    }

    private function wednesday(): Carbon
    {
        // Return next Wednesday at 10:00 in app timezone to have a stable mid-week anchor
        return Carbon::now(config('app.timezone'))->next(Carbon::WEDNESDAY)->setTime(10, 0);
    }

    // ── Test 1: limited-plan member can book first class ─────────────────────

    public function test_limited_plan_member_can_book_first_class_in_week(): void
    {
        [$user] = $this->makeLimitedUser(2);
        $class = $this->makeClass($this->wednesday());

        $result = $this->service->book($user, $class);

        $this->assertEquals('booked', $result['status']);
    }

    // ── Test 2: can book second class in the same week ────────────────────────

    public function test_limited_plan_member_can_book_second_class_in_week(): void
    {
        [$user] = $this->makeLimitedUser(2);
        $wed = $this->wednesday();

        $class1 = $this->makeClass($wed);
        $class2 = $this->makeClass($wed->clone()->addHours(3));

        $this->assertEquals('booked', $this->service->book($user, $class1)['status']);
        $this->assertEquals('booked', $this->service->book($user, $class2)['status']);
    }

    // ── Test 3: third booking in same week is rejected ────────────────────────

    public function test_third_booking_in_same_week_is_rejected(): void
    {
        [$user] = $this->makeLimitedUser(2, 20);
        $wed = $this->wednesday();

        $class1 = $this->makeClass($wed);
        $class2 = $this->makeClass($wed->clone()->addHours(3));
        $class3 = $this->makeClass($wed->clone()->addHours(6));

        $this->service->book($user, $class1);
        $this->service->book($user, $class2);

        $result = $this->service->book($user, $class3);

        $this->assertEquals('weekly_limit_reached', $result['status']);
    }

    // ── Test 4: next calendar week resets the limit ───────────────────────────

    public function test_next_calendar_week_allows_booking(): void
    {
        [$user] = $this->makeLimitedUser(2, 20);
        $wed = $this->wednesday();

        $class1 = $this->makeClass($wed);
        $class2 = $this->makeClass($wed->clone()->addHours(3));

        $this->service->book($user, $class1);
        $this->service->book($user, $class2);

        // Same class time but next week
        $nextWeekClass = $this->makeClass($wed->clone()->addWeek());
        $result = $this->service->book($user, $nextWeekClass);

        $this->assertEquals('booked', $result['status']);
    }

    // ── Test 5: waitlist does not consume weekly quota ────────────────────────

    public function test_waitlist_does_not_consume_weekly_quota(): void
    {
        [$user] = $this->makeLimitedUser(2, 20);
        $wed = $this->wednesday();

        // Fill the class to capacity so user is waitlisted
        $fullClass = $this->makeClass($wed, capacity: 1);
        $other = User::factory()->withSubscription(5)->create(['credits' => 5]);
        $this->service->book($other, $fullClass); // fills the only spot

        $result = $this->service->book($user, $fullClass);
        $this->assertEquals('waitlisted', $result['status']);

        // User still has 2 slots this week
        $class2 = $this->makeClass($wed->clone()->addHours(3));
        $class3 = $this->makeClass($wed->clone()->addHours(6));

        $this->assertEquals('booked', $this->service->book($user, $class2)['status']);
        $this->assertEquals('booked', $this->service->book($user, $class3)['status']);
    }

    // ── Test 6: auto-promotion skips capped member ────────────────────────────

    public function test_auto_promotion_skips_member_at_weekly_cap(): void
    {
        [$limitedUser] = $this->makeLimitedUser(2, 20);
        $occupier = User::factory()->withSubscription(5)->create(['credits' => 5]);
        $wed = $this->wednesday();

        // Full class — occupier books the only spot, limited user joins waitlist (cap not yet hit)
        $fullClass = $this->makeClass($wed->clone()->addHours(6), capacity: 1);
        $occupierResult = $this->service->book($occupier, $fullClass);
        $this->assertEquals('booked', $occupierResult['status']);

        $waitlistResult = $this->service->book($limitedUser, $fullClass);
        $this->assertEquals('waitlisted', $waitlistResult['status']);

        // NOW limited user fills their 2 weekly slots (with other classes)
        $class1 = $this->makeClass($wed);
        $class2 = $this->makeClass($wed->clone()->addHours(3));
        $this->service->book($limitedUser, $class1);
        $this->service->book($limitedUser, $class2);

        // Cancel the occupier's booking — triggers auto-promotion
        $occupierBooking = ClassBooking::where('user_id', $occupier->id)
            ->where('gym_class_id', $fullClass->id)
            ->first();
        $this->service->cancel($occupierBooking);

        // Limited user's waitlist booking should be cancelled (skipped due to weekly cap)
        $this->assertDatabaseHas('class_bookings', [
            'user_id' => $limitedUser->id,
            'gym_class_id' => $fullClass->id,
            'status' => 'cancelled',
        ]);

        // Spot stays empty (no other waitlisted users)
        $this->assertEquals(0,
            ClassBooking::where('gym_class_id', $fullClass->id)
                ->whereIn('status', ['booked', 'checked_in'])
                ->count()
        );
    }

    // ── Test 7: cancelling a confirmed booking frees one weekly slot ──────────

    public function test_cancelling_confirmed_booking_frees_weekly_slot(): void
    {
        [$user] = $this->makeLimitedUser(2, 20);
        $wed = $this->wednesday();

        $class1 = $this->makeClass($wed);
        $class2 = $this->makeClass($wed->clone()->addHours(3));
        $class3 = $this->makeClass($wed->clone()->addHours(6));

        $this->service->book($user, $class1);
        $this->service->book($user, $class2);

        // At cap — third booking rejected
        $this->assertEquals('weekly_limit_reached', $this->service->book($user, $class3)['status']);

        // Cancel first booking
        $booking1 = ClassBooking::where('user_id', $user->id)
            ->where('gym_class_id', $class1->id)
            ->first();
        $this->service->cancel($booking1, forceRefund: true);

        // Now third booking should succeed
        $result = $this->service->book($user, $class3);
        $this->assertEquals('booked', $result['status']);
    }

    // ── Test 8: no-show does not free quota retroactively ────────────────────

    public function test_no_show_does_not_free_weekly_quota(): void
    {
        [$user] = $this->makeLimitedUser(2, 20);
        $wed = $this->wednesday();

        $class1 = $this->makeClass($wed);
        $class2 = $this->makeClass($wed->clone()->addHours(3));
        $class3 = $this->makeClass($wed->clone()->addHours(6));

        $this->service->book($user, $class1);
        $this->service->book($user, $class2);

        // Mark first booking as no_show (does not free quota)
        ClassBooking::where('user_id', $user->id)
            ->where('gym_class_id', $class1->id)
            ->update(['status' => 'no_show']);

        $result = $this->service->book($user, $class3);

        $this->assertEquals('weekly_limit_reached', $result['status']);
    }

    // ── Test 9: unlimited package ignores weekly cap ──────────────────────────

    public function test_unlimited_package_with_no_weekly_limit_can_book_more_than_twice(): void
    {
        [$user] = $this->makeUnlimitedUser();
        $wed = $this->wednesday();

        for ($i = 0; $i < 5; $i++) {
            $class = $this->makeClass($wed->clone()->addHours($i * 2));
            $result = $this->service->book($user, $class);
            $this->assertEquals('booked', $result['status'], "Booking #{$i} should succeed for unlimited user");
        }
    }

    // ── Test 10: finite-credit package with no weekly limit books freely ───────

    public function test_finite_credit_package_with_no_weekly_limit_can_book_freely(): void
    {
        // Regular package, no weekly_booking_limit set
        $package = Package::factory()->create(['credits' => 20, 'weekly_booking_limit' => null]);
        $user = User::factory()->create(['credits' => 20]);
        UserSubscription::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'credits_granted' => 20,
            'credits_remaining' => 20,
            'started_at' => now()->subDay(),
            'expires_at' => now()->addDays(30),
            'status' => 'active',
            'is_unlimited' => false,
        ]);

        $wed = $this->wednesday();

        for ($i = 0; $i < 5; $i++) {
            $class = $this->makeClass($wed->clone()->addHours($i * 2));
            $result = $this->service->book($user, $class);
            $this->assertEquals('booked', $result['status'], "Booking #{$i} should succeed");
        }
    }

    // ── Test 11: multiple subscriptions — capped sub doesn't block uncapped ───

    public function test_capped_subscription_does_not_block_uncapped_subscription(): void
    {
        $limitedPkg = Package::factory()->limitedPlan(2)->create(['credits' => 10]);
        $unlimitedPkg = Package::factory()->unlimited()->create();

        $user = User::factory()->create(['credits' => 0]);

        // Limited sub (expires sooner — should be tried first)
        $limitedSub = UserSubscription::create([
            'user_id' => $user->id,
            'package_id' => $limitedPkg->id,
            'credits_granted' => 10,
            'credits_remaining' => 10,
            'started_at' => now()->subDay(),
            'expires_at' => now()->addDays(10),
            'status' => 'active',
            'is_unlimited' => false,
        ]);

        // Unlimited sub (expires later — fallback)
        $unlimitedSub = UserSubscription::create([
            'user_id' => $user->id,
            'package_id' => $unlimitedPkg->id,
            'credits_granted' => 0,
            'credits_remaining' => 0,
            'started_at' => now()->subDay(),
            'expires_at' => now()->addDays(30),
            'status' => 'active',
            'is_unlimited' => true,
        ]);

        $wed = $this->wednesday();

        // Exhaust limited sub's weekly cap
        $class1 = $this->makeClass($wed);
        $class2 = $this->makeClass($wed->clone()->addHours(3));
        $this->service->book($user, $class1);
        $this->service->book($user, $class2);

        // Third booking should fall through to unlimited sub
        $class3 = $this->makeClass($wed->clone()->addHours(6));
        $result = $this->service->book($user, $class3);

        $this->assertEquals('booked', $result['status']);

        // Verify it used the unlimited subscription
        $booking3 = ClassBooking::where('user_id', $user->id)
            ->where('gym_class_id', $class3->id)
            ->first();
        $this->assertEquals($unlimitedSub->id, $booking3->user_subscription_id);
    }

    // ── Test 12: timezone/week boundary is evaluated in app timezone ──────────

    public function test_week_boundary_is_evaluated_in_app_timezone(): void
    {
        [$user] = $this->makeLimitedUser(2, 20);
        $tz = config('app.timezone'); // Asia/Kuala_Lumpur (UTC+8)

        // Pin to a known Wednesday in KL time as week anchor
        $wednesday = Carbon::now($tz)->next(Carbon::WEDNESDAY)->setTime(10, 0, 0);

        // Same Wednesday but one week later (clearly a different KL week)
        $nextWednesday = $wednesday->clone()->addWeek();

        // Book 2 classes in the current week (Wednesday + Thursday this week)
        $class1 = $this->makeClass($wednesday->clone()->utc());
        $class2 = $this->makeClass($wednesday->clone()->addHours(3)->utc());
        $this->assertEquals('booked', $this->service->book($user, $class1)['status']);
        $this->assertEquals('booked', $this->service->book($user, $class2)['status']);

        // Third class this week → rejected (at cap)
        $class3ThisWeek = $this->makeClass($wednesday->clone()->addHours(6)->utc());
        $this->assertEquals('weekly_limit_reached', $this->service->book($user, $class3ThisWeek)['status']);

        // Next week's class → allowed (fresh weekly quota)
        $nextWeekClass = $this->makeClass($nextWednesday->clone()->utc());
        $result = $this->service->book($user, $nextWeekClass);
        $this->assertEquals('booked', $result['status']);
    }
}
