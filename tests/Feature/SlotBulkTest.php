<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Court;
use App\Models\CourtSlot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SlotBulkTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_bulk_generate_slots_across_a_date_range(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $court = Court::factory()->create();

        // 3 days x 3 one-hour slots (09:00-12:00) = 9 slots.
        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/slots/bulk', [
                'court_id'         => $court->id,
                'start_date'       => Carbon::tomorrow()->format('Y-m-d'),
                'end_date'         => Carbon::tomorrow()->addDays(2)->format('Y-m-d'),
                'daily_start_time' => '09:00',
                'daily_end_time'   => '12:00',
                'slot_duration'    => 60,
            ])
            ->assertCreated()
            ->assertJsonPath('data.created_count', 9)
            ->assertJsonPath('data.skipped_count', 0);

        $this->assertDatabaseCount('court_slots', 9);
    }

    public function test_bulk_generation_skips_overlapping_slots(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $court = Court::factory()->create();
        $date = Carbon::tomorrow()->format('Y-m-d');

        // Pre-existing 09:00-10:00 slot should be skipped, leaving 10:00-11:00 and 11:00-12:00.
        CourtSlot::factory()->create([
            'court_id'   => $court->id,
            'date'       => $date,
            'start_time' => '09:00:00',
            'end_time'   => '10:00:00',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/slots/bulk', [
                'court_id'         => $court->id,
                'start_date'       => $date,
                'end_date'         => $date,
                'daily_start_time' => '09:00',
                'daily_end_time'   => '12:00',
                'slot_duration'    => 60,
            ])
            ->assertCreated()
            ->assertJsonPath('data.created_count', 2)
            ->assertJsonPath('data.skipped_count', 1);
    }

    public function test_bulk_generation_supports_different_hours_per_day(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $court = Court::factory()->create();

        // A 7-day window starting next Monday contains exactly one Mon and one Fri.
        $monday = Carbon::parse('next monday');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/slots/bulk', [
                'court_id'   => $court->id,
                'start_date' => $monday->format('Y-m-d'),
                'end_date'   => $monday->copy()->addDays(6)->format('Y-m-d'),
                'schedules'  => [
                    // Monday: 09:00-21:00, 1h => 12 slots
                    ['days_of_week' => [1], 'start_time' => '09:00', 'end_time' => '21:00', 'slot_duration' => 60],
                    // Friday: 08:00-12:00, 1h => 4 slots
                    ['days_of_week' => [5], 'start_time' => '08:00', 'end_time' => '12:00', 'slot_duration' => 60],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.created_count', 16); // 12 (Mon) + 4 (Fri)

        $this->assertDatabaseCount('court_slots', 16);
    }

    public function test_a_consumer_cannot_bulk_generate_slots(): void
    {
        $consumer = User::factory()->create();
        $court = Court::factory()->create();

        $this->actingAs($consumer, 'sanctum')
            ->postJson('/api/admin/slots/bulk', [
                'court_id'         => $court->id,
                'start_date'       => Carbon::tomorrow()->format('Y-m-d'),
                'end_date'         => Carbon::tomorrow()->format('Y-m-d'),
                'daily_start_time' => '09:00',
                'daily_end_time'   => '12:00',
            ])
            ->assertStatus(403);
    }
}
