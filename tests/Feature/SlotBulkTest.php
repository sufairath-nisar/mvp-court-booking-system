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
