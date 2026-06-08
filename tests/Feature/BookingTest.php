<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\CourtSlot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_consumer_can_book_an_available_slot(): void
    {
        $consumer = User::factory()->create();
        $slot = CourtSlot::factory()->create([
            'date'       => Carbon::tomorrow()->format('Y-m-d'),
            'start_time' => '08:00:00',
            'end_time'   => '09:00:00',
        ]);

        $this->actingAs($consumer, 'sanctum')
            ->postJson('/api/consumer/bookings', ['slot_id' => $slot->id])
            ->assertCreated()
            ->assertJsonPath('data.status', 'booked');

        $this->assertDatabaseHas('bookings', ['slot_id' => $slot->id, 'status' => 'booked']);
        $this->assertTrue($slot->refresh()->is_booked);
    }

    public function test_a_slot_cannot_be_double_booked(): void
    {
        $first  = User::factory()->create();
        $second = User::factory()->create();
        $slot   = CourtSlot::factory()->create();

        $this->actingAs($first, 'sanctum')
            ->postJson('/api/consumer/bookings', ['slot_id' => $slot->id])
            ->assertCreated();

        $this->actingAs($second, 'sanctum')
            ->postJson('/api/consumer/bookings', ['slot_id' => $slot->id])
            ->assertStatus(422)
            ->assertJsonPath('message', 'This slot has already been booked.');
    }

    public function test_a_consumer_can_cancel_before_slot_start_and_slot_is_freed(): void
    {
        $consumer = User::factory()->create();
        $slot = CourtSlot::factory()->create([
            'date'       => Carbon::tomorrow()->format('Y-m-d'),
            'start_time' => '08:00:00',
            'end_time'   => '09:00:00',
        ]);

        $booking = $this->actingAs($consumer, 'sanctum')
            ->postJson('/api/consumer/bookings', ['slot_id' => $slot->id])
            ->json('data.id');

        $this->actingAs($consumer, 'sanctum')
            ->patchJson("/api/consumer/bookings/{$booking}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertFalse($slot->refresh()->is_booked);
    }

    public function test_a_consumer_cannot_cancel_another_users_booking(): void
    {
        $owner    = User::factory()->create();
        $stranger = User::factory()->create();
        $slot     = CourtSlot::factory()->create();

        $booking = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/consumer/bookings', ['slot_id' => $slot->id])
            ->json('data.id');

        $this->actingAs($stranger, 'sanctum')
            ->patchJson("/api/consumer/bookings/{$booking}/cancel")
            ->assertStatus(403);

        $this->assertSame(BookingStatus::BOOKED, $slot->bookings()->first()->status);
    }
}
