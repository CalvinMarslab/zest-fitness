<?php

namespace Tests\Feature;

use App\Models\ClassBooking;
use App\Models\CreditTransaction;
use App\Models\GymClass;
use App\Models\Package;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 1B regression tests (20 required by spec).
 */
class Phase1BTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $member;

    private Package $trialPackage;

    private Package $paidPackage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->admin = User::factory()->create(['is_admin' => true, 'role' => 'admin']);
        $this->member = User::factory()->create(['is_admin' => false, 'role' => 'member', 'credits' => 0]);

        $this->trialPackage = Package::factory()->create([
            'name' => 'Trial 3',
            'is_trial' => true,
            'is_active' => true,
            'credits' => 3,
            'period_days' => 30,
            'price' => 0,
            'is_unlimited' => false,
        ]);

        $this->paidPackage = Package::factory()->create([
            'name' => 'Monthly 10',
            'is_trial' => false,
            'is_active' => true,
            'credits' => 10,
            'period_days' => 30,
            'price' => 199,
            'is_unlimited' => false,
        ]);
    }

    private function makeActiveSub(User $user, Package $package, int $remaining = 5): UserSubscription
    {
        return UserSubscription::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'credits_granted' => $package->credits,
            'credits_remaining' => $remaining,
            'status' => 'active',
            'is_unlimited' => false,
            'started_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);
    }

    private function makeClass(array $attrs = []): GymClass
    {
        return GymClass::factory()->create(array_merge([
            'start_time' => now()->addHours(5),
            'capacity' => 10,
            'is_cancelled' => false,
        ], $attrs));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 1. Member cannot self-activate a paid package
    // ─────────────────────────────────────────────────────────────────────────

    public function test_member_cannot_self_activate_paid_package(): void
    {
        $this->actingAs($this->member)
            ->post(route('packages.subscribe', $this->paidPackage->id))
            ->assertStatus(403);

        $this->assertDatabaseMissing('user_subscriptions', ['user_id' => $this->member->id]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. Member can self-activate a trial package
    // ─────────────────────────────────────────────────────────────────────────

    public function test_member_can_self_activate_trial_package(): void
    {
        $this->actingAs($this->member)
            ->post(route('packages.subscribe', $this->trialPackage->id))
            ->assertRedirect(route('packages'));

        $this->assertDatabaseHas('user_subscriptions', [
            'user_id' => $this->member->id,
            'package_id' => $this->trialPackage->id,
            'status' => 'active',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3. Trial package cannot be activated twice by same member
    // ─────────────────────────────────────────────────────────────────────────

    public function test_member_cannot_use_same_trial_twice(): void
    {
        UserSubscription::create([
            'user_id' => $this->member->id,
            'package_id' => $this->trialPackage->id,
            'credits_granted' => 3,
            'credits_remaining' => 3,
            'status' => 'active',
            'started_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        $this->actingAs($this->member)
            ->post(route('packages.subscribe', $this->trialPackage->id))
            ->assertStatus(403);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 4. Trial package activation creates a credit_transaction
    // ─────────────────────────────────────────────────────────────────────────

    public function test_trial_package_activation_creates_credit_transaction(): void
    {
        $this->actingAs($this->member)
            ->post(route('packages.subscribe', $this->trialPackage->id));

        $this->assertDatabaseHas('credit_transactions', [
            'user_id' => $this->member->id,
            'type' => 'package_assigned',
            'amount' => 3,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 5. Admin package assignment creates a credit_transaction
    // ─────────────────────────────────────────────────────────────────────────

    public function test_admin_package_assignment_creates_credit_transaction(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.users.subscriptions.store', $this->member->id), [
                'package_id' => $this->paidPackage->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('credit_transactions', [
            'user_id' => $this->member->id,
            'type' => 'package_assigned',
            'amount' => 10,
            'actor_user_id' => $this->admin->id,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 6. Booking deduction creates a credit_transaction
    // ─────────────────────────────────────────────────────────────────────────

    public function test_booking_deduction_creates_credit_transaction(): void
    {
        $this->makeActiveSub($this->member, $this->paidPackage, 5);
        $class = $this->makeClass();

        $this->actingAs($this->member)
            ->post(route('bookings.store'), ['gym_class_id' => $class->id])
            ->assertRedirect();

        $this->assertDatabaseHas('credit_transactions', [
            'user_id' => $this->member->id,
            'type' => 'booking_deduction',
            'amount' => -1,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 7. Normal booking cancellation creates a booking_refund transaction
    // ─────────────────────────────────────────────────────────────────────────

    public function test_booking_cancellation_creates_refund_transaction(): void
    {
        $sub = $this->makeActiveSub($this->member, $this->paidPackage, 4);
        $class = $this->makeClass(['start_time' => now()->addDays(2)]);

        $booking = ClassBooking::create([
            'user_id' => $this->member->id,
            'gym_class_id' => $class->id,
            'status' => 'booked',
            'credit_charged' => true,
            'user_subscription_id' => $sub->id,
            'booked_at' => now(),
        ]);

        $this->actingAs($this->member)
            ->delete(route('bookings.destroy'), ['gym_class_id' => $class->id])
            ->assertRedirect();

        $this->assertDatabaseHas('credit_transactions', [
            'user_id' => $this->member->id,
            'type' => 'booking_refund',
            'amount' => 1,
            'class_booking_id' => $booking->id,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 8. Admin credit adjustment creates admin_adjustment transaction
    // ─────────────────────────────────────────────────────────────────────────

    public function test_admin_credit_adjustment_creates_transaction(): void
    {
        $sub = $this->makeActiveSub($this->member, $this->paidPackage, 8);

        $this->actingAs($this->admin)
            ->patch(route('admin.users.credits.update', $this->member->id), [
                'subscription_id' => $sub->id,
                'adjustment' => 2,
                'notes' => 'Makeup class',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('credit_transactions', [
            'user_id' => $this->member->id,
            'type' => 'admin_adjustment',
            'amount' => 2,
            'actor_user_id' => $this->admin->id,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 9. Credit adjustment below zero is rejected
    // ─────────────────────────────────────────────────────────────────────────

    public function test_credit_adjustment_below_zero_is_rejected(): void
    {
        $sub = $this->makeActiveSub($this->member, $this->paidPackage, 2);

        $this->actingAs($this->admin)
            ->patch(route('admin.users.credits.update', $this->member->id), [
                'subscription_id' => $sub->id,
                'adjustment' => -5,
                'notes' => 'Should fail',
            ])
            ->assertSessionHasErrors('adjustment');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 10. Admin can view member profile (UserProfile page)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_admin_can_view_member_profile_page(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.users.show', $this->member->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/UserProfile')
                ->has('member')
                ->has('packages')
                ->has('upcomingBookings')
                ->has('recentBookings')
                ->has('creditHistory')
            );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 11. Non-admin cannot view member profile
    // ─────────────────────────────────────────────────────────────────────────

    public function test_non_admin_cannot_view_member_profile(): void
    {
        $this->actingAs($this->member)
            ->get(route('admin.users.show', $this->member->id))
            ->assertForbidden();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 12. Admin can book a class for a member
    // ─────────────────────────────────────────────────────────────────────────

    public function test_admin_can_book_class_for_member(): void
    {
        $this->makeActiveSub($this->member, $this->paidPackage, 5);
        $class = $this->makeClass();

        $this->actingAs($this->admin)
            ->post(route('admin.users.bookings.store', $this->member->id), [
                'gym_class_id' => $class->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('class_bookings', [
            'user_id' => $this->member->id,
            'gym_class_id' => $class->id,
            'status' => 'booked',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 13. Admin can cancel a booking for a member (force refund)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_admin_can_cancel_booking_for_member_with_force_refund(): void
    {
        $sub = $this->makeActiveSub($this->member, $this->paidPackage, 4);
        $class = $this->makeClass(['start_time' => now()->addDays(1)]);

        $booking = ClassBooking::create([
            'user_id' => $this->member->id,
            'gym_class_id' => $class->id,
            'status' => 'booked',
            'credit_charged' => true,
            'user_subscription_id' => $sub->id,
            'booked_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin.users.bookings.destroy', ['user' => $this->member->id, 'booking' => $booking->id]))
            ->assertRedirect();

        $this->assertDatabaseHas('class_bookings', ['id' => $booking->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('credit_transactions', [
            'user_id' => $this->member->id,
            'type' => 'booking_refund',
            'class_booking_id' => $booking->id,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 14. Admin can suspend a member
    // ─────────────────────────────────────────────────────────────────────────

    public function test_admin_can_suspend_member(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('admin.users.status.update', $this->member->id), ['status' => 'suspended'])
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $this->member->id, 'status' => 'suspended']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 15. Admin can reactivate a suspended member
    // ─────────────────────────────────────────────────────────────────────────

    public function test_admin_can_reactivate_suspended_member(): void
    {
        $this->member->update(['status' => 'suspended']);

        $this->actingAs($this->admin)
            ->patch(route('admin.users.status.update', $this->member->id), ['status' => 'active'])
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $this->member->id, 'status' => 'active']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 16. Hard delete blocked for member with booking history
    // ─────────────────────────────────────────────────────────────────────────

    public function test_admin_cannot_hard_delete_member_with_booking_history(): void
    {
        $class = $this->makeClass();
        ClassBooking::create([
            'user_id' => $this->member->id,
            'gym_class_id' => $class->id,
            'status' => 'cancelled',
            'booked_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin.users.destroy', $this->member->id))
            ->assertStatus(422);

        $this->assertDatabaseHas('users', ['id' => $this->member->id]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 17. Hard delete blocked for member with credit transaction history
    // ─────────────────────────────────────────────────────────────────────────

    public function test_admin_cannot_hard_delete_member_with_credit_history(): void
    {
        CreditTransaction::create([
            'user_id' => $this->member->id,
            'type' => 'admin_adjustment',
            'amount' => 1,
            'balance_after' => 1,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin.users.destroy', $this->member->id))
            ->assertStatus(422);

        $this->assertDatabaseHas('users', ['id' => $this->member->id]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 18. Admin can hard-delete a member with no history
    // ─────────────────────────────────────────────────────────────────────────

    public function test_admin_can_delete_member_with_no_history(): void
    {
        $clean = User::factory()->create(['role' => 'member', 'is_admin' => false]);

        $this->actingAs($this->admin)
            ->delete(route('admin.users.destroy', $clean->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('users', ['id' => $clean->id]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 19. Schedule returns cancellation metadata (early cancel → will refund)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_schedule_returns_cancellation_metadata_with_refund_for_early_cancel(): void
    {
        $sub = $this->makeActiveSub($this->member, $this->paidPackage, 4);
        $class = $this->makeClass(['start_time' => now()->addHours(5)]);

        ClassBooking::create([
            'user_id' => $this->member->id,
            'gym_class_id' => $class->id,
            'status' => 'booked',
            'credit_charged' => true,
            'user_subscription_id' => $sub->id,
            'booked_at' => now(),
        ]);

        $response = $this->actingAs($this->member)
            ->get(route('schedule'))
            ->assertOk();

        $data = $response->original->getData()['page']['props'] ?? [];
        $classes = $data['classes'] ?? [];
        $target = collect($classes)->firstWhere('id', $class->id);

        $this->assertNotNull($target, 'Class not found in schedule');
        $this->assertTrue($target['can_cancel'], 'can_cancel should be true');
        $this->assertTrue($target['will_refund_credit'], 'will_refund_credit should be true for early cancel');
        $this->assertArrayHasKey('cancellation_cutoff_at', $target);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 20. Schedule returns late-cancel metadata (within cutoff → no refund)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_schedule_returns_no_refund_for_late_cancellation(): void
    {
        $sub = $this->makeActiveSub($this->member, $this->paidPackage, 4);
        // Class starts in 30 minutes — within the 2-hour default cutoff
        $class = $this->makeClass(['start_time' => now()->addMinutes(30)]);

        ClassBooking::create([
            'user_id' => $this->member->id,
            'gym_class_id' => $class->id,
            'status' => 'booked',
            'credit_charged' => true,
            'user_subscription_id' => $sub->id,
            'booked_at' => now(),
        ]);

        $response = $this->actingAs($this->member)
            ->get(route('schedule'))
            ->assertOk();

        $data = $response->original->getData()['page']['props'] ?? [];
        $classes = $data['classes'] ?? [];
        $target = collect($classes)->firstWhere('id', $class->id);

        $this->assertNotNull($target, 'Class not found in schedule');
        $this->assertTrue($target['can_cancel'], 'can_cancel should be true');
        $this->assertFalse($target['will_refund_credit'], 'will_refund_credit should be false for late cancel');
        $this->assertTrue($target['is_late_cancellation'], 'is_late_cancellation should be true');
    }
}
