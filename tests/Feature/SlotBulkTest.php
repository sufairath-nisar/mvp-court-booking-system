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

    public function test_an_admin_can_generate_slots_for_several_selected_dates(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $court = Court::factory()->create();

        $d1 = Carbon::tomorrow()->format('Y-m-d');
        $d2 = Carbon::tomorrow()->addDays(3)->format('Y-m-d');

        // Date 1: 09:00-12:00 (3 slots) + Date 2: 09:00-12:00 (3 slots) = 6.
        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/slots/bulk', [
                'court_id'      => $court->id,
                'slot_duration' => 60,
                'dates'         => [
                    ['date' => $d1, 'start_time' => '09:00', 'end_time' => '12:00'],
                    ['date' => $d2, 'start_time' => '09:00', 'end_time' => '12:00'],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.created_count', 6)
            ->assertJsonPath('data.skipped_count', 0);

        $this->assertDatabaseCount('court_slots', 6);
    }

    public function test_each_selected_date_can_have_its_own_time_window(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $court = Court::factory()->create();

        $d1 = Carbon::tomorrow()->format('Y-m-d');
        $d2 = Carbon::tomorrow()->addDays(1)->format('Y-m-d');

        // Date 1: 09:00-21:00 => 12 slots. Date 2: 08:00-12:00 => 4 slots. Total 16.
        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/slots/bulk', [
                'court_id' => $court->id,
                'dates'    => [
                    ['date' => $d1, 'start_time' => '09:00', 'end_time' => '21:00'],
                    ['date' => $d2, 'start_time' => '08:00', 'end_time' => '12:00'],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.created_count', 16);

        $this->assertDatabaseCount('court_slots', 16);
    }

    public function test_bulk_generation_skips_overlapping_slots(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $court = Court::factory()->create();
        $date = Carbon::tomorrow()->format('Y-m-d');

        // Pre-existing 09:00-10:00 should be skipped; 10:00-11:00 and 11:00-12:00 created.
        CourtSlot::factory()->create([
            'court_id'   => $court->id,
            'date'       => $date,
            'start_time' => '09:00:00',
            'end_time'   => '10:00:00',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/slots/bulk', [
                'court_id' => $court->id,
                'dates'    => [
                    ['date' => $date, 'start_time' => '09:00', 'end_time' => '12:00'],
                ],
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
                'court_id' => $court->id,
                'dates'    => [
                    ['date' => Carbon::tomorrow()->format('Y-m-d'), 'start_time' => '09:00', 'end_time' => '12:00'],
                ],
            ])
            ->assertStatus(403);
    }
}
