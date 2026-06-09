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

    public function test_admin_can_set_and_read_the_weekly_schedule(): void
    {
        $admin = $this->admin();
        $court = Court::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/admin/courts/{$court->id}/schedule", [
                'schedule' => [
                    ['day_of_week' => 1, 'open_time' => '09:00', 'close_time' => '21:00', 'slot_duration' => 60],
                    ['day_of_week' => 2, 'open_time' => '09:00', 'close_time' => '21:00', 'slot_duration' => 60],
                ],
            ])
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assertDatabaseHas('court_schedules', ['court_id' => $court->id, 'day_of_week' => 1]);

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/admin/courts/{$court->id}/schedule")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.day_name', 'Monday');
    }

    public function test_generates_slots_from_the_weekly_schedule(): void
    {
        $admin = $this->admin();
        $court = Court::factory()->create();
        $monday = Carbon::parse('next monday');

        // Monday 09:00-12:00 (3 slots) + Tuesday 09:00-11:00 (2 slots).
        $this->actingAs($admin, 'sanctum')->putJson("/api/admin/courts/{$court->id}/schedule", [
            'schedule' => [
                ['day_of_week' => 1, 'open_time' => '09:00', 'close_time' => '12:00', 'slot_duration' => 60],
                ['day_of_week' => 2, 'open_time' => '09:00', 'close_time' => '11:00', 'slot_duration' => 60],
            ],
        ])->assertOk();

        // Range covering exactly one Monday and one Tuesday.
        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/courts/{$court->id}/generate-slots", [
                'start_date' => $monday->format('Y-m-d'),
                'end_date'   => $monday->copy()->addDay()->format('Y-m-d'),
            ])
            ->assertCreated()
            ->assertJsonPath('data.created_count', 5); // 3 (Mon) + 2 (Tue)

        $this->assertDatabaseCount('court_slots', 5);
    }

    public function test_an_eid_exception_closes_the_court_for_that_date(): void
    {
        $admin = $this->admin();
        $court = Court::factory()->create();
        $firstMonday = Carbon::parse('next monday');

        // Every Monday: 09:00-12:00 => 3 slots.
        $this->actingAs($admin, 'sanctum')->putJson("/api/admin/courts/{$court->id}/schedule", [
            'schedule' => [
                ['day_of_week' => 1, 'open_time' => '09:00', 'close_time' => '12:00', 'slot_duration' => 60],
            ],
        ])->assertOk();

        // First Monday is Eid -> closed.
        $this->actingAs($admin, 'sanctum')->postJson("/api/admin/courts/{$court->id}/schedule-exceptions", [
            'date'      => $firstMonday->format('Y-m-d'),
            'is_closed' => true,
            'reason'    => 'Eid',
        ])->assertCreated();

        // Range spans the Eid Monday AND the following Monday.
        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/courts/{$court->id}/generate-slots", [
                'start_date' => $firstMonday->format('Y-m-d'),
                'end_date'   => $firstMonday->copy()->addDays(7)->format('Y-m-d'),
            ])
            ->assertCreated()
            ->assertJsonPath('data.created_count', 3); // Eid Monday skipped; only the 2nd Monday generates
    }

    public function test_an_exception_can_override_the_hours_for_a_date(): void
    {
        $admin = $this->admin();
        $court = Court::factory()->create();
        $monday = Carbon::parse('next monday');

        // Normal Monday: 09:00-21:00 => 12 slots.
        $this->actingAs($admin, 'sanctum')->putJson("/api/admin/courts/{$court->id}/schedule", [
            'schedule' => [
                ['day_of_week' => 1, 'open_time' => '09:00', 'close_time' => '21:00', 'slot_duration' => 60],
            ],
        ])->assertOk();

        // This Monday only runs 09:00-12:00 (half day) => 3 slots.
        $this->actingAs($admin, 'sanctum')->postJson("/api/admin/courts/{$court->id}/schedule-exceptions", [
            'date'       => $monday->format('Y-m-d'),
            'open_time'  => '09:00',
            'close_time' => '12:00',
            'reason'     => 'Eid half day',
        ])->assertCreated();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/courts/{$court->id}/generate-slots", [
                'start_date' => $monday->format('Y-m-d'),
                'end_date'   => $monday->format('Y-m-d'),
            ])
            ->assertCreated()
            ->assertJsonPath('data.created_count', 3); // override, not 12
    }

    public function test_a_consumer_cannot_manage_the_schedule(): void
    {
        $consumer = User::factory()->create();
        $court = Court::factory()->create();

        $this->actingAs($consumer, 'sanctum')
            ->putJson("/api/admin/courts/{$court->id}/schedule", [
                'schedule' => [
                    ['day_of_week' => 1, 'open_time' => '09:00', 'close_time' => '21:00'],
                ],
            ])
            ->assertStatus(403);
    }
}
