<?php

namespace Tests\Feature;

use App\Models\ClassBooking;
use App\Models\GymClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_coach_can_check_in_attendee(): void
    {
        $coach = User::factory()->create(['role' => 'coach']);
        $member = User::factory()->create();
        $class = GymClass::factory()->create(['coach_id' => $coach->id, 'start_time' => now()->addHour()]);
        $booking = ClassBooking::create([
            'user_id' => $member->id,
            'gym_class_id' => $class->id,
            'status' => 'booked',
        ]);

        $this->actingAs($coach)
            ->post(route('coach.classes.attendance', $class->id), [
                'booking_id' => $booking->id,
                'status' => 'checked_in',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('class_bookings', [
            'id' => $booking->id,
            'status' => 'checked_in',
        ]);
    }

    public function test_coach_can_mark_no_show(): void
    {
        $coach = User::factory()->create(['role' => 'coach']);
        $member = User::factory()->create();
        $class = GymClass::factory()->create(['coach_id' => $coach->id, 'start_time' => now()->addHour()]);
        $booking = ClassBooking::create([
            'user_id' => $member->id,
            'gym_class_id' => $class->id,
            'status' => 'booked',
        ]);

        $this->actingAs($coach)
            ->post(route('coach.classes.attendance', $class->id), [
                'booking_id' => $booking->id,
                'status' => 'no_show',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('class_bookings', [
            'id' => $booking->id,
            'status' => 'no_show',
        ]);
    }

    public function test_coach_cannot_update_attendance_on_another_coachs_class(): void
    {
        $coach1 = User::factory()->create(['role' => 'coach']);
        $coach2 = User::factory()->create(['role' => 'coach']);
        $member = User::factory()->create();
        $class = GymClass::factory()->create(['coach_id' => $coach1->id, 'start_time' => now()->addHour()]);
        $booking = ClassBooking::create([
            'user_id' => $member->id,
            'gym_class_id' => $class->id,
            'status' => 'booked',
        ]);

        $this->actingAs($coach2)
            ->post(route('coach.classes.attendance', $class->id), [
                'booking_id' => $booking->id,
                'status' => 'checked_in',
            ])
            ->assertForbidden();
    }

    public function test_admin_can_update_attendance_via_admin_endpoint(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'role' => 'admin']);
        $member = User::factory()->create();
        $class = GymClass::factory()->create(['start_time' => now()->addHour()]);
        $booking = ClassBooking::create([
            'user_id' => $member->id,
            'gym_class_id' => $class->id,
            'status' => 'booked',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.bookings.attendance', $booking->id), [
                'status' => 'checked_in',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('class_bookings', [
            'id' => $booking->id,
            'status' => 'checked_in',
        ]);
    }
}
