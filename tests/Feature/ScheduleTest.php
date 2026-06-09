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

    public function test_creating_slots_generates_recurring_slots_from_the_schedule(): void
    {
        $admin = $this->admin();
        $court = Court::factory()->create();

        // Mon 09:00-12:00 (3 recurring slots) + Tue 09:00-11:00 (2) = 5 recurring slots.
        $this->setSchedule($admin, $court, [
            ['day_of_week' => 1, 'open_time' => '09:00', 'close_time' => '12:00', 'slot_duration' => 60],
            ['day_of_week' => 2, 'open_time' => '09:00', 'close_time' => '11:00', 'slot_duration' => 60],
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/slots', ['court_id' => $court->id])
            ->assertCreated()
            ->assertJsonPath('data.created_count', 5);

        $this->assertDatabaseCount('court_slots', 5);
        $this->assertDatabaseHas('court_slots', ['court_id' => $court->id, 'day_of_week' => 1, 'start_time' => '09:00:00']);
    }

    public function test_generating_slots_is_idempotent(): void
    {
        $admin = $this->admin();
        $court = Court::factory()->create();
        $this->setSchedule($admin, $court, [
            ['day_of_week' => 1, 'open_time' => '09:00', 'close_time' => '12:00', 'slot_duration' => 60],
        ]);

        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/slots', ['court_id' => $court->id])
            ->assertCreated()->assertJsonPath('data.created_count', 3);

        // Re-running creates nothing new; the 3 already exist.
        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/slots', ['court_id' => $court->id])
            ->assertCreated()
            ->assertJsonPath('data.created_count', 0)
            ->assertJsonPath('data.existing_count', 3);

        $this->assertDatabaseCount('court_slots', 3);
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
