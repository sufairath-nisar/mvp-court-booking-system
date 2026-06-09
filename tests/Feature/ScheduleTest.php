<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Court;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::ADMIN]);
    }

    private function setSchedule(User $admin, Court $court, array $rows): void
    {
        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/admin/courts/{$court->id}/schedule", ['schedule' => $rows])
            ->assertOk();
    }

    public function test_admin_can_set_and_read_the_weekly_schedule(): void
    {
        $admin = $this->admin();
        $court = Court::factory()->create();

        $this->setSchedule($admin, $court, [
            ['day_of_week' => 1, 'open_time' => '09:00', 'close_time' => '21:00', 'slot_duration' => 60],
            ['day_of_week' => 2, 'open_time' => '09:00', 'close_time' => '21:00', 'slot_duration' => 60],
        ]);

        $this->assertDatabaseHas('court_schedules', ['court_id' => $court->id, 'day_of_week' => 1]);

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/admin/courts/{$court->id}/schedule")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.day_name', 'Monday');
    }

    public function test_setting_the_schedule_generates_recurring_slots(): void
    {
        $admin = $this->admin();
        $court = Court::factory()->create();

        // Mon 09:00-12:00 (3 recurring slots) + Tue 09:00-11:00 (2) = 5 recurring slots.
        // Saving the schedule generates the slots automatically.
        $this->setSchedule($admin, $court, [
            ['day_of_week' => 1, 'open_time' => '09:00', 'close_time' => '12:00', 'slot_duration' => 60],
            ['day_of_week' => 2, 'open_time' => '09:00', 'close_time' => '11:00', 'slot_duration' => 60],
        ]);

        $this->assertDatabaseCount('court_slots', 5);
        $this->assertDatabaseHas('court_slots', ['court_id' => $court->id, 'day_of_week' => 1, 'start_time' => '09:00:00']);
    }

    public function test_generating_slots_is_idempotent(): void
    {
        $admin = $this->admin();
        $court = Court::factory()->create();

        // Setting the schedule already generates the 3 Monday slots.
        $this->setSchedule($admin, $court, [
            ['day_of_week' => 1, 'open_time' => '09:00', 'close_time' => '12:00', 'slot_duration' => 60],
        ]);
        $this->assertDatabaseCount('court_slots', 3);

        // POST /admin/slots re-syncs; the 3 already exist, nothing new is created.
        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/slots', ['court_id' => $court->id])
            ->assertCreated()
            ->assertJsonPath('data.created_count', 0)
            ->assertJsonPath('data.existing_count', 3);

        $this->assertDatabaseCount('court_slots', 3);
    }

    public function test_patch_updates_a_single_day_without_touching_the_others(): void
    {
        $admin = $this->admin();
        $court = Court::factory()->create();

        // Mon 09:00-12:00 (3) + Tue 09:00-11:00 (2) = 5 slots.
        $this->setSchedule($admin, $court, [
            ['day_of_week' => 1, 'open_time' => '09:00', 'close_time' => '12:00', 'slot_duration' => 60],
            ['day_of_week' => 2, 'open_time' => '09:00', 'close_time' => '11:00', 'slot_duration' => 60],
        ]);
        $this->assertDatabaseCount('court_slots', 5);

        // PATCH only Monday -> 09:00-13:00 (4 slots). Tuesday must be untouched (still 2).
        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/courts/{$court->id}/schedule", [
                'day_of_week' => 1, 'open_time' => '09:00', 'close_time' => '13:00', 'slot_duration' => 60,
            ])
            ->assertOk()
            ->assertJsonCount(2, 'data'); // both weekdays still present in the schedule

        $this->assertDatabaseCount('court_schedules', 2); // Tuesday not deleted
        $this->assertEquals(4, \App\Models\CourtSlot::where('court_id', $court->id)->where('day_of_week', 1)->count());
        $this->assertEquals(2, \App\Models\CourtSlot::where('court_id', $court->id)->where('day_of_week', 2)->count());
    }

    public function test_changing_the_schedule_regenerates_slots_to_the_new_hours(): void
    {
        $admin = $this->admin();
        $court = Court::factory()->create();

        // Monday 09:00-12:00 @60 -> 3 slots (09-10, 10-11, 11-12).
        $this->setSchedule($admin, $court, [
            ['day_of_week' => 1, 'open_time' => '09:00', 'close_time' => '12:00', 'slot_duration' => 60],
        ]);
        $this->assertDatabaseCount('court_slots', 3);

        // Shift to 09:30-12:30 @60 -> 3 NEW slots (09:30-10:30, 10:30-11:30, 11:30-12:30).
        // None of the old slots are booked, so the stale ones are deleted: still 3 total.
        $this->setSchedule($admin, $court, [
            ['day_of_week' => 1, 'open_time' => '09:30', 'close_time' => '12:30', 'slot_duration' => 60],
        ]);

        $this->assertDatabaseCount('court_slots', 3);
        $this->assertDatabaseHas('court_slots', ['court_id' => $court->id, 'day_of_week' => 1, 'start_time' => '09:30:00']);
        $this->assertDatabaseMissing('court_slots', ['court_id' => $court->id, 'day_of_week' => 1, 'start_time' => '09:00:00']);
    }

    public function test_a_booked_slot_survives_a_schedule_change_and_blocks_overlapping_new_slots(): void
    {
        $admin = $this->admin();
        $consumer = User::factory()->create();
        $court = Court::factory()->create();
        $monday = Carbon::parse('next monday')->format('Y-m-d');

        // Monday 09:00-11:00 @60 -> 09-10, 10-11.
        $this->setSchedule($admin, $court, [
            ['day_of_week' => 1, 'open_time' => '09:00', 'close_time' => '11:00', 'slot_duration' => 60],
        ]);

        // Consumer books the 09:00-10:00 slot for next Monday.
        $slotId = \App\Models\CourtSlot::where('court_id', $court->id)->where('start_time', '09:00:00')->first()->id;
        $this->actingAs($consumer, 'sanctum')
            ->postJson('/api/consumer/bookings', ['slot_id' => $slotId, 'booking_date' => $monday])
            ->assertCreated();

        // Admin shifts Monday to 09:30-11:30 @60 -> new 09:30-10:30, 10:30-11:30.
        $this->setSchedule($admin, $court, [
            ['day_of_week' => 1, 'open_time' => '09:30', 'close_time' => '11:30', 'slot_duration' => 60],
        ]);

        // The booking is NOT destroyed: its old slot is kept but deactivated.
        $this->assertDatabaseHas('bookings', ['slot_id' => $slotId, 'booking_date' => $monday, 'status' => 'booked']);
        $this->assertDatabaseHas('court_slots', ['id' => $slotId, 'is_active' => false]);

        // Availability for that Monday: 09:30-10:30 overlaps the booked 09:00-10:00 and is hidden;
        // only 10:30-11:30 remains.
        $this->actingAs($consumer, 'sanctum')
            ->getJson("/api/consumer/courts/{$court->id}/available-slots?date={$monday}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.start_time', '10:30');
    }

    public function test_overlapping_slot_cannot_be_booked_on_a_date_with_an_existing_booking(): void
    {
        $admin = $this->admin();
        $a = User::factory()->create();
        $b = User::factory()->create();
        $court = Court::factory()->create();
        $monday = Carbon::parse('next monday')->format('Y-m-d');

        // Two overlapping recurring slots exist on Monday: 09:00-10:00 and 09:30-10:30.
        $early = \App\Models\CourtSlot::factory()->create([
            'court_id' => $court->id, 'day_of_week' => 1, 'start_time' => '09:00:00', 'end_time' => '10:00:00',
        ]);
        $overlapping = \App\Models\CourtSlot::factory()->create([
            'court_id' => $court->id, 'day_of_week' => 1, 'start_time' => '09:30:00', 'end_time' => '10:30:00',
        ]);

        $this->actingAs($a, 'sanctum')
            ->postJson('/api/consumer/bookings', ['slot_id' => $early->id, 'booking_date' => $monday])
            ->assertCreated();

        // Booking the overlapping slot for the same date is rejected.
        $this->actingAs($b, 'sanctum')
            ->postJson('/api/consumer/bookings', ['slot_id' => $overlapping->id, 'booking_date' => $monday])
            ->assertStatus(422)
            ->assertJsonPath('message', 'This slot has already been booked for that date.');
    }

    public function test_consumer_sees_available_slots_for_a_date(): void
    {
        $admin = $this->admin();
        $consumer = User::factory()->create();
        $court = Court::factory()->create();
        $monday = Carbon::parse('next monday');

        $this->setSchedule($admin, $court, [
            ['day_of_week' => 1, 'open_time' => '09:00', 'close_time' => '12:00', 'slot_duration' => 60],
        ]);
        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/slots', ['court_id' => $court->id])->assertCreated();

        // 3 Monday slots available on a Monday...
        $this->actingAs($consumer, 'sanctum')
            ->getJson("/api/consumer/courts/{$court->id}/available-slots?date={$monday->format('Y-m-d')}")
            ->assertOk()
            ->assertJsonCount(3, 'data');

        // ...and none on a Tuesday (no Tuesday schedule).
        $this->actingAs($consumer, 'sanctum')
            ->getJson("/api/consumer/courts/{$court->id}/available-slots?date={$monday->copy()->addDay()->format('Y-m-d')}")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_booked_slot_drops_out_of_availability_for_that_date(): void
    {
        $admin = $this->admin();
        $consumer = User::factory()->create();
        $court = Court::factory()->create();
        $monday = Carbon::parse('next monday')->format('Y-m-d');

        $this->setSchedule($admin, $court, [
            ['day_of_week' => 1, 'open_time' => '09:00', 'close_time' => '12:00', 'slot_duration' => 60],
        ]);
        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/slots', ['court_id' => $court->id])->assertCreated();

        $slotId = $this->actingAs($consumer, 'sanctum')
            ->getJson("/api/consumer/courts/{$court->id}/available-slots?date={$monday}")
            ->json('data.0.id');

        $this->actingAs($consumer, 'sanctum')
            ->postJson('/api/consumer/bookings', ['slot_id' => $slotId, 'booking_date' => $monday])
            ->assertCreated();

        // Now only 2 of the 3 remain available that date.
        $this->actingAs($consumer, 'sanctum')
            ->getJson("/api/consumer/courts/{$court->id}/available-slots?date={$monday}")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_an_eid_closure_blocks_availability_and_booking(): void
    {
        $admin = $this->admin();
        $consumer = User::factory()->create();
        $court = Court::factory()->create();
        $monday = Carbon::parse('next monday')->format('Y-m-d');

        $this->setSchedule($admin, $court, [
            ['day_of_week' => 1, 'open_time' => '09:00', 'close_time' => '12:00', 'slot_duration' => 60],
        ]);
        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/slots', ['court_id' => $court->id])->assertCreated();
        $slotId = \App\Models\CourtSlot::where('court_id', $court->id)->first()->id;

        // Mark that Monday as Eid (closed).
        $this->actingAs($admin, 'sanctum')->postJson("/api/admin/courts/{$court->id}/schedule-exceptions", [
            'date' => $monday, 'is_closed' => true, 'reason' => 'Eid',
        ])->assertCreated();

        // No availability that date...
        $this->actingAs($consumer, 'sanctum')
            ->getJson("/api/consumer/courts/{$court->id}/available-slots?date={$monday}")
            ->assertOk()
            ->assertJsonCount(0, 'data');

        // ...and booking it is rejected.
        $this->actingAs($consumer, 'sanctum')
            ->postJson('/api/consumer/bookings', ['slot_id' => $slotId, 'booking_date' => $monday])
            ->assertStatus(422)
            ->assertJsonPath('message', 'The court is closed on the selected date.');
    }

    public function test_a_consumer_cannot_generate_slots(): void
    {
        $consumer = User::factory()->create();
        $court = Court::factory()->create();

        $this->actingAs($consumer, 'sanctum')
            ->postJson('/api/admin/slots', ['court_id' => $court->id])
            ->assertStatus(403);
    }

    public function test_a_consumer_cannot_manage_the_schedule(): void
    {
        $consumer = User::factory()->create();
        $court = Court::factory()->create();

        $this->actingAs($consumer, 'sanctum')
            ->putJson("/api/admin/courts/{$court->id}/schedule", [
                'schedule' => [['day_of_week' => 1, 'open_time' => '09:00', 'close_time' => '21:00']],
            ])
            ->assertStatus(403);
    }
}
