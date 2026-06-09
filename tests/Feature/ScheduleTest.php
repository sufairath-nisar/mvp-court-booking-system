<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Court;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::ADMIN]);
    }

    /**
     * Slots are created by generating from the schedule: POST /admin/slots with court_id.
     *
     * @param array<string, mixed> $payload
     */
    private function generate(User $admin, Court $court, array $payload = []): TestResponse
    {
        return $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/slots', array_merge(['court_id' => $court->id], $payload));
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

    private function setSchedule(User $admin, Court $court, array $rows): void
    {
        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/admin/courts/{$court->id}/schedule", ['schedule' => $rows])
            ->assertOk();
    }

    public function test_generates_slots_from_the_weekly_schedule(): void
    {
        $admin = $this->admin();
        $court = Court::factory()->create();
        $monday = Carbon::parse('next monday');

        $this->setSchedule($admin, $court, [
            ['day_of_week' => 1, 'open_time' => '09:00', 'close_time' => '12:00', 'slot_duration' => 60], // Mon 3 slots
            ['day_of_week' => 2, 'open_time' => '09:00', 'close_time' => '11:00', 'slot_duration' => 60], // Tue 2 slots
        ]);

        $this->generate($admin, $court, [
            'start_date' => $monday->format('Y-m-d'),
            'end_date'   => $monday->copy()->addDay()->format('Y-m-d'),
        ])
            ->assertCreated()
            ->assertJsonPath('data.created_count', 5);

        $this->assertDatabaseCount('court_slots', 5);
    }

    public function test_an_eid_exception_closes_the_court_for_that_date(): void
    {
        $admin = $this->admin();
        $court = Court::factory()->create();
        $firstMonday = Carbon::parse('next monday');

        $this->setSchedule($admin, $court, [
            ['day_of_week' => 1, 'open_time' => '09:00', 'close_time' => '12:00', 'slot_duration' => 60],
        ]);

        $this->actingAs($admin, 'sanctum')->postJson("/api/admin/courts/{$court->id}/schedule-exceptions", [
            'date'      => $firstMonday->format('Y-m-d'),
            'is_closed' => true,
            'reason'    => 'Eid',
        ])->assertCreated();

        $this->generate($admin, $court, [
            'start_date' => $firstMonday->format('Y-m-d'),
            'end_date'   => $firstMonday->copy()->addDays(7)->format('Y-m-d'),
        ])
            ->assertCreated()
            ->assertJsonPath('data.created_count', 3); // Eid Monday skipped; only the 2nd Monday
    }

    public function test_an_exception_can_override_the_hours_for_a_date(): void
    {
        $admin = $this->admin();
        $court = Court::factory()->create();
        $monday = Carbon::parse('next monday');

        $this->setSchedule($admin, $court, [
            ['day_of_week' => 1, 'open_time' => '09:00', 'close_time' => '21:00', 'slot_duration' => 60], // 12 slots
        ]);

        $this->actingAs($admin, 'sanctum')->postJson("/api/admin/courts/{$court->id}/schedule-exceptions", [
            'date'       => $monday->format('Y-m-d'),
            'open_time'  => '09:00',
            'close_time' => '12:00',
            'reason'     => 'Eid half day',
        ])->assertCreated();

        $this->generate($admin, $court, [
            'start_date' => $monday->format('Y-m-d'),
            'end_date'   => $monday->format('Y-m-d'),
        ])
            ->assertCreated()
            ->assertJsonPath('data.created_count', 3); // override, not 12
    }

    public function test_generation_can_exclude_specific_dates(): void
    {
        $admin = $this->admin();
        $court = Court::factory()->create();
        $firstMonday = Carbon::parse('next monday');

        $this->setSchedule($admin, $court, [
            ['day_of_week' => 1, 'open_time' => '09:00', 'close_time' => '12:00', 'slot_duration' => 60],
        ]);

        $this->generate($admin, $court, [
            'start_date'    => $firstMonday->format('Y-m-d'),
            'end_date'      => $firstMonday->copy()->addDays(7)->format('Y-m-d'),
            'exclude_dates' => [$firstMonday->format('Y-m-d')],
        ])
            ->assertCreated()
            ->assertJsonPath('data.created_count', 3); // only the 2nd Monday
    }

    public function test_generate_defaults_to_a_rolling_horizon_when_no_dates_given(): void
    {
        $admin = $this->admin();
        $court = Court::factory()->create();

        $schedule = [];
        for ($day = 0; $day <= 6; $day++) {
            $schedule[] = ['day_of_week' => $day, 'open_time' => '09:00', 'close_time' => '12:00', 'slot_duration' => 60];
        }
        $this->setSchedule($admin, $court, $schedule);

        // No dates -> today..+30 = 31 days * 3 = 93.
        $this->generate($admin, $court)
            ->assertCreated()
            ->assertJsonPath('data.created_count', 93);
    }

    public function test_generate_horizon_can_be_overridden_with_days(): void
    {
        $admin = $this->admin();
        $court = Court::factory()->create();

        $schedule = [];
        for ($day = 0; $day <= 6; $day++) {
            $schedule[] = ['day_of_week' => $day, 'open_time' => '09:00', 'close_time' => '12:00', 'slot_duration' => 60];
        }
        $this->setSchedule($admin, $court, $schedule);

        // days=7 -> today..+7 = 8 days * 3 = 24.
        $this->generate($admin, $court, ['days' => 7])
            ->assertCreated()
            ->assertJsonPath('data.created_count', 24);
    }

    public function test_preview_returns_counts_without_saving_any_slots(): void
    {
        $admin = $this->admin();
        $court = Court::factory()->create();
        $monday = Carbon::parse('next monday');

        $this->setSchedule($admin, $court, [
            ['day_of_week' => 1, 'open_time' => '09:00', 'close_time' => '12:00', 'slot_duration' => 60],
        ]);

        $this->generate($admin, $court, [
            'start_date' => $monday->format('Y-m-d'),
            'end_date'   => $monday->format('Y-m-d'),
            'preview'    => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.preview', true)
            ->assertJsonPath('data.would_create', 3)
            ->assertJsonPath("data.by_date.{$monday->format('Y-m-d')}", 3);

        $this->assertDatabaseCount('court_slots', 0);
    }

    public function test_a_consumer_cannot_generate_slots(): void
    {
        $consumer = User::factory()->create();
        $court = Court::factory()->create();

        $this->actingAs($consumer, 'sanctum')
            ->postJson('/api/admin/slots', ['court_id' => $court->id, 'days' => 7])
            ->assertStatus(403);
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
