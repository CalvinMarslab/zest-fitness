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
            'capacity'   => 10,
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
            'user_id'      => $this->user->id,
            'gym_class_id' => $this->gymClass->id,
        ]);

        // Credit should be deducted
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
            'user_id'      => $this->user->id,
            'gym_class_id' => $this->gymClass->id,
        ]);
    }

    public function test_user_cannot_double_book_same_class(): void
    {
        ClassBooking::create([
            'user_id'      => $this->user->id,
            'gym_class_id' => $this->gymClass->id,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('bookings.store'), ['gym_class_id' => $this->gymClass->id]);

        $response->assertRedirect();
        $response->assertSessionHas('success'); // "already booked" is a success flash

        // Credits should NOT be deducted again
        $this->assertEquals(5, $this->user->fresh()->credits);
    }

    public function test_user_cannot_book_full_class(): void
    {
        $fullClass = GymClass::factory()->create([
            'capacity'   => 1,
            'start_time' => now()->addDay(),
        ]);

        // Fill the class with another user
        $other = User::factory()->create();
        ClassBooking::create([
            'user_id'      => $other->id,
            'gym_class_id' => $fullClass->id,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('bookings.store'), ['gym_class_id' => $fullClass->id]);

        $response->assertRedirect();
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

    public function test_user_can_cancel_booking_and_get_refund(): void
    {
        ClassBooking::create([
            'user_id'      => $this->user->id,
            'gym_class_id' => $this->gymClass->id,
        ]);
        $this->user->update(['credits' => 4]); // simulate post-booking balance

        $response = $this->actingAs($this->user)
            ->delete(route('bookings.destroy'), ['gym_class_id' => $this->gymClass->id]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('class_bookings', [
            'user_id'      => $this->user->id,
            'gym_class_id' => $this->gymClass->id,
        ]);

        // Credit should be refunded
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
