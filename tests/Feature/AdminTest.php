<?php

namespace Tests\Feature;

use App\Models\ClassBooking;
use App\Models\GymClass;
use App\Models\Package;
use App\Models\User;
use App\Models\UserSubscription;
use App\Models\WorkoutResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->regularUser = User::factory()->create(['is_admin' => false]);
    }

    // ── Middleware: non-admin rejection ───────────────────────────────────────

    public function test_non_admin_cannot_access_admin_dashboard(): void
    {
        $response = $this->actingAs($this->regularUser)->get(route('admin.dashboard'));
        $response->assertForbidden();
    }

    public function test_guest_cannot_access_admin_dashboard(): void
    {
        $response = $this->get(route('admin.dashboard'));
        $response->assertRedirect(route('login'));
    }

    // ── Dashboard ────────────────────────────────────────────────────────────

    public function test_admin_can_view_dashboard(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Dashboard')
            ->has('stats')
        );
    }

    public function test_dashboard_shows_correct_stats(): void
    {
        $gymClass = GymClass::factory()->create(['start_time' => Carbon::today()->addHours(2)]);
        ClassBooking::create(['user_id' => $this->regularUser->id, 'gym_class_id' => $gymClass->id]);
        WorkoutResult::create([
            'user_id' => $this->regularUser->id, 'result_date' => Carbon::today(),
            'exercise' => 'Deadlift', 'value' => '100 kg',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->where('stats.total_users', 2) // admin + regular
            ->where('stats.total_classes', 1)
            ->where('stats.bookings_today', 1)
            ->where('stats.results_today', 1)
        );
    }

    // ── User management ──────────────────────────────────────────────────────

    public function test_admin_can_view_users(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.users.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Admin/Users'));
    }

    public function test_admin_can_update_user_credits(): void
    {
        $response = $this->actingAs($this->admin)
            ->patch(route('admin.users.update', $this->regularUser), ['credits' => 50]);

        $response->assertRedirect();
        $this->assertEquals(50, $this->regularUser->fresh()->credits);
    }

    public function test_admin_can_toggle_admin_flag(): void
    {
        $response = $this->actingAs($this->admin)
            ->patch(route('admin.users.update', $this->regularUser), ['is_admin' => true]);

        $response->assertRedirect();
        $this->assertTrue($this->regularUser->fresh()->is_admin);
    }

    public function test_admin_can_delete_user(): void
    {
        $response = $this->actingAs($this->admin)
            ->delete(route('admin.users.destroy', $this->regularUser));

        $response->assertRedirect();
        $this->assertDatabaseMissing('users', ['id' => $this->regularUser->id]);
    }

    public function test_admin_cannot_delete_self(): void
    {
        $response = $this->actingAs($this->admin)
            ->delete(route('admin.users.destroy', $this->admin));

        $response->assertStatus(422);
        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
    }

    public function test_non_admin_cannot_manage_users(): void
    {
        $response = $this->actingAs($this->regularUser)
            ->get(route('admin.users.index'));
        $response->assertForbidden();

        $response = $this->actingAs($this->regularUser)
            ->patch(route('admin.users.update', $this->admin), ['credits' => 999]);
        $response->assertForbidden();
    }

    // ── Class management ─────────────────────────────────────────────────────

    public function test_admin_can_view_classes(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.classes.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Admin/Classes'));
    }

    public function test_admin_can_create_single_class(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.classes.store'), [
                'name' => 'Morning Yoga',
                'coach' => 'Sarah',
                'capacity' => 15,
                'recurring' => false,
                'start_time' => now()->addDay()->toDateTimeString(),
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('gym_classes', ['name' => 'Morning Yoga', 'coach' => 'Sarah']);
    }

    public function test_admin_can_create_recurring_classes(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.classes.store'), [
                'name' => 'HIIT Blast',
                'coach' => 'Marcus',
                'capacity' => 20,
                'recurring' => true,
                'days' => [1, 3, 5], // Mon, Wed, Fri
                'start_hour' => '09:00',
                'end_hour' => '10:00',
                'weeks' => 2,
            ]);

        $response->assertRedirect();

        // Should have created multiple classes (at least 1 for each selected day across 2 weeks)
        $count = GymClass::where('name', 'HIIT Blast')->count();
        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function test_admin_can_update_class(): void
    {
        $gymClass = GymClass::factory()->create(['name' => 'Old Name']);

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.classes.update', $gymClass), ['name' => 'New Name']);

        $response->assertRedirect();
        $this->assertEquals('New Name', $gymClass->fresh()->name);
    }

    public function test_admin_can_delete_class(): void
    {
        $gymClass = GymClass::factory()->create();

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.classes.destroy', $gymClass));

        $response->assertRedirect();
        $this->assertDatabaseMissing('gym_classes', ['id' => $gymClass->id]);
    }

    public function test_class_creation_validates_required_fields(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.classes.store'), []);

        $response->assertSessionHasErrors(['name', 'coach', 'capacity', 'recurring']);
    }

    // ── Booking management ───────────────────────────────────────────────────

    public function test_admin_can_view_bookings(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.bookings.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Admin/Bookings'));
    }

    public function test_admin_can_cancel_booking_with_refund(): void
    {
        $gymClass = GymClass::factory()->create();

        // Give the user an active subscription with 4 credits remaining (1 already used)
        $package = Package::factory()->create(['credits' => 5, 'is_unlimited' => false]);
        $sub = UserSubscription::create([
            'user_id' => $this->regularUser->id,
            'package_id' => $package->id,
            'credits_granted' => 5,
            'credits_remaining' => 4,
            'started_at' => now()->subDay(),
            'expires_at' => now()->addDays(30),
            'status' => 'active',
            'is_unlimited' => false,
        ]);
        $this->regularUser->update(['credits' => 4]);

        $booking = ClassBooking::create([
            'user_id' => $this->regularUser->id,
            'gym_class_id' => $gymClass->id,
            'status' => 'booked',
            'credit_charged' => true,
            'user_subscription_id' => $sub->id,
            'booked_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.bookings.destroy', $booking));

        $response->assertRedirect();
        // Admin cancel uses status-based cancellation (no hard delete)
        $this->assertDatabaseHas('class_bookings', ['id' => $booking->id, 'status' => 'cancelled']);

        // Credit should be refunded — subscription goes from 4 → 5, display synced
        $this->assertEquals(5, $this->regularUser->fresh()->credits);
    }

    // ── Package management ───────────────────────────────────────────────────

    public function test_admin_can_view_packages(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.packages.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Admin/Packages'));
    }

    public function test_admin_can_create_package(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.packages.store'), [
                'name' => 'Starter Pack',
                'credits' => 10,
                'period_days' => 30,
                'price' => 29.99,
                'is_active' => true,
                'sort_order' => 1,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('packages', ['name' => 'Starter Pack', 'credits' => 10]);
    }

    public function test_admin_can_update_package(): void
    {
        $pkg = Package::create([
            'name' => 'Old', 'credits' => 5, 'period_days' => 30,
            'price' => 19.99, 'is_active' => true, 'sort_order' => 1,
        ]);

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.packages.update', $pkg), ['name' => 'Updated', 'price' => 24.99]);

        $response->assertRedirect();
        $this->assertEquals('Updated', $pkg->fresh()->name);
    }

    public function test_admin_can_delete_package(): void
    {
        $pkg = Package::create([
            'name' => 'ToDelete', 'credits' => 5, 'period_days' => 30,
            'price' => 9.99, 'is_active' => true, 'sort_order' => 1,
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.packages.destroy', $pkg));

        $response->assertRedirect();
        $this->assertDatabaseMissing('packages', ['id' => $pkg->id]);
    }

    public function test_package_creation_validates_required_fields(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.packages.store'), []);

        $response->assertSessionHasErrors(['name', 'credits', 'period_days', 'price']);
    }

    // ── P0-2: Class cancellation via update triggers booking lifecycle ─────────

    public function test_cancelling_class_via_update_triggers_booking_lifecycle(): void
    {
        $gymClass = GymClass::factory()->create([
            'start_time' => now()->addDay(),
            'status' => 'scheduled',
            'is_cancelled' => false,
        ]);

        $package = Package::factory()->create(['credits' => 5, 'is_unlimited' => false]);

        $user1 = User::factory()->create(['credits' => 4]);
        $sub1 = UserSubscription::create([
            'user_id' => $user1->id,
            'package_id' => $package->id,
            'credits_granted' => 5,
            'credits_remaining' => 4,
            'started_at' => now()->subDay(),
            'expires_at' => now()->addDays(30),
            'status' => 'active',
            'is_unlimited' => false,
        ]);

        $user2 = User::factory()->create(['credits' => 4]);
        $sub2 = UserSubscription::create([
            'user_id' => $user2->id,
            'package_id' => $package->id,
            'credits_granted' => 5,
            'credits_remaining' => 4,
            'started_at' => now()->subDay(),
            'expires_at' => now()->addDays(30),
            'status' => 'active',
            'is_unlimited' => false,
        ]);

        $booking1 = ClassBooking::create([
            'user_id' => $user1->id,
            'gym_class_id' => $gymClass->id,
            'status' => 'booked',
            'credit_charged' => true,
            'user_subscription_id' => $sub1->id,
            'booked_at' => now(),
        ]);

        $booking2 = ClassBooking::create([
            'user_id' => $user2->id,
            'gym_class_id' => $gymClass->id,
            'status' => 'booked',
            'credit_charged' => true,
            'user_subscription_id' => $sub2->id,
            'booked_at' => now(),
        ]);

        $waitlistUser = User::factory()->create(['credits' => 5]);
        $booking3 = ClassBooking::create([
            'user_id' => $waitlistUser->id,
            'gym_class_id' => $gymClass->id,
            'status' => 'waitlisted',
            'credit_charged' => false,
            'queue_position' => 1,
        ]);

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.classes.update', $gymClass), ['status' => 'cancelled']);

        $response->assertRedirect();

        // All bookings cancelled
        $this->assertDatabaseHas('class_bookings', ['id' => $booking1->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('class_bookings', ['id' => $booking2->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('class_bookings', ['id' => $booking3->id, 'status' => 'cancelled']);

        // Confirmed bookings have credit_refunded_at set
        $this->assertNotNull(ClassBooking::find($booking1->id)->credit_refunded_at);
        $this->assertNotNull(ClassBooking::find($booking2->id)->credit_refunded_at);

        // Waitlisted booking does NOT have credit_refunded_at set
        $this->assertNull(ClassBooking::find($booking3->id)->credit_refunded_at);

        // Class is marked cancelled
        $this->assertTrue((bool) $gymClass->fresh()->is_cancelled);
    }

    // ── P1-2: Coach ID — class with coach_id is visible to that coach ─────────

    public function test_class_created_with_coach_id_is_visible_to_coach(): void
    {
        $coach = User::factory()->create(['role' => 'coach']);
        $assignedClass = GymClass::factory()->create([
            'coach_id' => $coach->id,
            'start_time' => now()->addDay(),
        ]);
        $otherClass = GymClass::factory()->create([
            'start_time' => now()->addDay(),
        ]);

        $response = $this->actingAs($coach)->get(route('coach.classes.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Coach/Classes')
            ->has('classes', 1) // only the assigned class
        );
    }

    // ── P1-3: Attendance state transitions ────────────────────────────────────

    public function test_attendance_valid_transition_allowed(): void
    {
        $gymClass = GymClass::factory()->create();
        $booking = ClassBooking::create([
            'user_id' => $this->regularUser->id,
            'gym_class_id' => $gymClass->id,
            'status' => 'booked',
        ]);

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.bookings.attendance', $booking), ['status' => 'checked_in']);

        $response->assertRedirect();
        $this->assertEquals('checked_in', $booking->fresh()->status);
    }

    public function test_attendance_invalid_transition_is_rejected(): void
    {
        $gymClass = GymClass::factory()->create();
        $booking = ClassBooking::create([
            'user_id' => $this->regularUser->id,
            'gym_class_id' => $gymClass->id,
            'status' => 'booked',
        ]);

        // 'booked' → 'booked' is not a valid transition
        $response = $this->actingAs($this->admin)
            ->patch(route('admin.bookings.attendance', $booking), ['status' => 'booked']);

        $response->assertSessionHasErrors('status');
        $this->assertEquals('booked', $booking->fresh()->status);
    }

    public function test_attendance_update_rejected_on_cancelled_booking(): void
    {
        $gymClass = GymClass::factory()->create();
        $booking = ClassBooking::create([
            'user_id' => $this->regularUser->id,
            'gym_class_id' => $gymClass->id,
            'status' => 'cancelled',
        ]);

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.bookings.attendance', $booking), ['status' => 'checked_in']);

        $response->assertSessionHasErrors('status');
        $this->assertEquals('cancelled', $booking->fresh()->status);
    }
}
