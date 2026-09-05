<?php

namespace Tests\Feature;

use App\Models\ClassBooking;
use App\Models\GymClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private GymClass $gymClass;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['credits' => 5]);
        $this->gymClass = GymClass::factory()->create([
            'capacity' => 10,
            'start_time' => now()->addDay(),
        ]);
    }

    // ── Booking creation ─────────────────────────────────────────────────────

    public function test_user_can_book_a_class(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('bookings.store'), ['gym_class_id' => $this->gymClass->id]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('class_bookings', [
            'user_id' => $this->user->id,
            'gym_class_id' => $this->gymClass->id,
            'status' => 'booked',
        ]);

        $this->assertEquals(4, $this->user->fresh()->credits);
    }

    public function test_booking_deducts_one_credit(): void
    {
        $this->actingAs($this->user)
            ->post(route('bookings.store'), ['gym_class_id' => $this->gymClass->id]);

        $this->assertEquals(4, $this->user->fresh()->credits);
    }

    public function test_user_cannot_book_when_no_credits(): void
    {
        $this->user->update(['credits' => 0]);

        $response = $this->actingAs($this->user)
            ->post(route('bookings.store'), ['gym_class_id' => $this->gymClass->id]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('gym_class_id');

        $this->assertDatabaseMissing('class_bookings', [
            'user_id' => $this->user->id,
            'gym_class_id' => $this->gymClass->id,
        ]);
    }

    public function test_user_cannot_double_book_same_class(): void
    {
        ClassBooking::create([
            'user_id' => $this->user->id,
            'gym_class_id' => $this->gymClass->id,
            'status' => 'booked',
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('bookings.store'), ['gym_class_id' => $this->gymClass->id]);

        $response->assertRedirect();
        $response->assertSessionHas('success'); // "already booked" is a success flash

        // Credits should NOT be deducted again
        $this->assertEquals(5, $this->user->fresh()->credits);
    }

    public function test_full_class_puts_user_on_waitlist(): void
    {
        $fullClass = GymClass::factory()->create([
            'capacity' => 1,
            'start_time' => now()->addDay(),
        ]);

        $other = User::factory()->create(['credits' => 5]);
        ClassBooking::create([
            'user_id' => $other->id,
            'gym_class_id' => $fullClass->id,
            'status' => 'booked',
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('bookings.store'), ['gym_class_id' => $fullClass->id]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('class_bookings', [
            'user_id' => $this->user->id,
            'gym_class_id' => $fullClass->id,
            'status' => 'waitlisted',
            'queue_position' => 1,
        ]);

        // Credit should NOT be deducted for waitlist
        $this->assertEquals(5, $this->user->fresh()->credits);
    }

    public function test_user_cannot_book_cancelled_class(): void
    {
        $this->gymClass->update(['is_cancelled' => true]);

        $response = $this->actingAs($this->user)
            ->post(route('bookings.store'), ['gym_class_id' => $this->gymClass->id]);

        $response->assertSessionHasErrors('gym_class_id');
    }

    public function test_booking_requires_valid_gym_class_id(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('bookings.store'), ['gym_class_id' => 99999]);

        $response->assertSessionHasErrors('gym_class_id');
    }

    public function test_booking_requires_gym_class_id(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('bookings.store'), []);

        $response->assertSessionHasErrors('gym_class_id');
    }

    // ── Booking cancellation ─────────────────────────────────────────────────

    public function test_user_can_cancel_booking_before_cutoff_and_get_refund(): void
    {
        // Class is well in the future — before cutoff
        $futureClass = GymClass::factory()->create([
            'capacity' => 10,
            'start_time' => now()->addDays(2),
            'cancellation_cutoff_hours' => 2,
        ]);

        ClassBooking::create([
            'user_id' => $this->user->id,
            'gym_class_id' => $futureClass->id,
            'status' => 'booked',
        ]);
        $this->user->update(['credits' => 4]);

        $response = $this->actingAs($this->user)
            ->delete(route('bookings.destroy'), ['gym_class_id' => $futureClass->id]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('class_bookings', [
            'user_id' => $this->user->id,
            'gym_class_id' => $futureClass->id,
            'status' => 'cancelled',
        ]);

        $this->assertEquals(5, $this->user->fresh()->credits);
    }

    public function test_late_cancellation_does_not_refund_credit(): void
    {
        // Class starts in 1 hour — past the 2-hour cutoff
        $imminent = GymClass::factory()->create([
            'capacity' => 10,
            'start_time' => now()->addHour(),
            'cancellation_cutoff_hours' => 2,
        ]);

        ClassBooking::create([
            'user_id' => $this->user->id,
            'gym_class_id' => $imminent->id,
            'status' => 'booked',
        ]);
        $this->user->update(['credits' => 4]);

        $response = $this->actingAs($this->user)
            ->delete(route('bookings.destroy'), ['gym_class_id' => $imminent->id]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('class_bookings', [
            'user_id' => $this->user->id,
            'gym_class_id' => $imminent->id,
            'status' => 'late_cancel',
        ]);

        // Credit should NOT be refunded on late cancel
        $this->assertEquals(4, $this->user->fresh()->credits);
    }

    public function test_cancellation_promotes_waitlisted_member(): void
    {
        $fullClass = GymClass::factory()->create([
            'capacity' => 1,
            'start_time' => now()->addDays(2),
            'cancellation_cutoff_hours' => 1,
        ]);

        $firstUser = User::factory()->create(['credits' => 4]);
        $waitlistUser = User::factory()->create(['credits' => 5]);

        ClassBooking::create([
            'user_id' => $firstUser->id,
            'gym_class_id' => $fullClass->id,
            'status' => 'booked',
        ]);

        ClassBooking::create([
            'user_id' => $waitlistUser->id,
            'gym_class_id' => $fullClass->id,
            'status' => 'waitlisted',
            'queue_position' => 1,
        ]);

        // First user cancels
        $this->actingAs($firstUser)
            ->delete(route('bookings.destroy'), ['gym_class_id' => $fullClass->id]);

        // Waitlisted user should now be promoted
        $this->assertDatabaseHas('class_bookings', [
            'user_id' => $waitlistUser->id,
            'gym_class_id' => $fullClass->id,
            'status' => 'booked',
        ]);

        // Waitlisted user's credit should be deducted
        $this->assertEquals(4, $waitlistUser->fresh()->credits);
    }

    public function test_cancelling_waitlist_does_not_refund_credit(): void
    {
        ClassBooking::create([
            'user_id' => $this->user->id,
            'gym_class_id' => $this->gymClass->id,
            'status' => 'waitlisted',
            'queue_position' => 1,
        ]);

        // User starts with 5 credits — no credit was charged for waitlist
        $response = $this->actingAs($this->user)
            ->delete(route('bookings.destroy'), ['gym_class_id' => $this->gymClass->id]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('class_bookings', [
            'user_id' => $this->user->id,
            'gym_class_id' => $this->gymClass->id,
            'status' => 'cancelled',
        ]);

        // Credits unchanged
        $this->assertEquals(5, $this->user->fresh()->credits);
    }

    public function test_cancelling_nonexistent_booking_does_not_refund(): void
    {
        $response = $this->actingAs($this->user)
            ->delete(route('bookings.destroy'), ['gym_class_id' => $this->gymClass->id]);

        $response->assertRedirect();

        // Credits should stay the same
        $this->assertEquals(5, $this->user->fresh()->credits);
    }

    // ── Auth guard ───────────────────────────────────────────────────────────

    public function test_guest_cannot_book(): void
    {
        $response = $this->post(route('bookings.store'), ['gym_class_id' => $this->gymClass->id]);

        $response->assertRedirect(route('login'));
    }

    public function test_guest_cannot_cancel(): void
    {
        $response = $this->delete(route('bookings.destroy'), ['gym_class_id' => $this->gymClass->id]);

        $response->assertRedirect(route('login'));
    }
}
