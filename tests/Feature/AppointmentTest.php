<?php

namespace Tests\Feature;

use App\Models\AppointmentBooking;
use App\Models\AppointmentService;
use App\Models\AppointmentSlot;
use App\Models\Package;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentTest extends TestCase
{
    use RefreshDatabase;

    private User $member;
    private User $coach;
    private Package $package;
    private UserSubscription $subscription;
    private AppointmentService $service;
    private AppointmentSlot $slot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->member = User::factory()->create(['role' => 'member', 'status' => 'active', 'credits' => 5]);
        $this->coach = User::factory()->create(['role' => 'coach', 'status' => 'active']);
        $this->package = Package::factory()->create(['credits' => 5, 'is_unlimited' => false, 'is_active' => true]);
        $this->subscription = UserSubscription::create(['user_id' => $this->member->id, 'package_id' => $this->package->id, 'credits_granted' => 5, 'credits_remaining' => 5, 'started_at' => now()->subDay(), 'expires_at' => now()->addMonth(), 'status' => 'active', 'is_unlimited' => false]);
        $this->service = AppointmentService::create(['name' => 'Personal Training', 'duration_minutes' => 60, 'interval_minutes' => 30, 'credits_required' => 2, 'release_days' => 30, 'booking_deadline_hours' => 2, 'cancellation_cutoff_hours' => 24, 'is_public' => true, 'is_active' => true]);
        $this->service->packages()->attach($this->package);
        $this->slot = AppointmentSlot::create(['appointment_service_id' => $this->service->id, 'coach_id' => $this->coach->id, 'start_time' => now()->addDays(3), 'end_time' => now()->addDays(3)->addHour(), 'location' => 'Zest Athletic', 'status' => 'available']);
    }

    public function test_member_can_book_and_credits_are_deducted(): void
    {
        $this->actingAs($this->member)->post(route('appointments.store', $this->slot))->assertRedirect();
        $this->assertDatabaseHas('appointment_bookings', ['appointment_slot_id' => $this->slot->id, 'user_id' => $this->member->id, 'status' => 'booked', 'credits_charged' => 2]);
        $this->assertSame(3, $this->subscription->fresh()->credits_remaining);
        $this->assertSame(3, $this->member->fresh()->credits);
    }

    public function test_member_cancellation_before_cutoff_refunds_credits(): void
    {
        $this->actingAs($this->member)->post(route('appointments.store', $this->slot));
        $booking = AppointmentBooking::firstOrFail();
        $this->actingAs($this->member)->delete(route('appointments.destroy', $booking))->assertRedirect();
        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertSame(5, $this->subscription->fresh()->credits_remaining);
    }

    public function test_ineligible_package_cannot_book(): void
    {
        $other = Package::factory()->create();
        $this->service->packages()->sync([$other->id]);
        $this->actingAs($this->member)->post(route('appointments.store', $this->slot))->assertSessionHasErrors('appointment');
        $this->assertDatabaseCount('appointment_bookings', 0);
    }

    public function test_admin_can_book_for_member_and_mark_attendance(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);
        $this->actingAs($admin)->post(route('admin.appointment-slots.book', $this->slot), ['user_id' => $this->member->id])->assertRedirect();
        $booking = AppointmentBooking::firstOrFail();
        $this->actingAs($admin)->patch(route('admin.appointment-bookings.attendance', $booking), ['status' => 'checked_in'])->assertRedirect();
        $this->assertSame('checked_in', $booking->fresh()->status);
        $this->assertNotNull($booking->fresh()->checked_in_at);
    }

    public function test_admin_can_cancel_without_refunding_credits(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);
        $this->actingAs($admin)->post(route('admin.appointment-slots.book', $this->slot), ['user_id' => $this->member->id]);
        $booking = AppointmentBooking::firstOrFail();

        $this->actingAs($admin)->delete(route('admin.appointment-bookings.destroy', $booking), ['refund' => false])->assertRedirect();

        $this->assertSame('late_cancel', $booking->fresh()->status);
        $this->assertSame(3, $this->subscription->fresh()->credits_remaining);
        $this->assertNull($booking->fresh()->credit_refunded_at);
    }

    public function test_admin_can_cancel_and_refund_credits_only_once(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);
        $this->actingAs($admin)->post(route('admin.appointment-slots.book', $this->slot), ['user_id' => $this->member->id]);
        $booking = AppointmentBooking::firstOrFail();

        $this->actingAs($admin)->delete(route('admin.appointment-bookings.destroy', $booking), ['refund' => true])->assertRedirect();
        $this->actingAs($admin)->delete(route('admin.appointment-bookings.destroy', $booking), ['refund' => true])->assertRedirect();

        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertSame(5, $this->subscription->fresh()->credits_remaining);
        $this->assertNotNull($booking->fresh()->credit_refunded_at);
    }
}
