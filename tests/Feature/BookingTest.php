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

    /**
     * Make a recurring slot whose weekday matches a known future date, and return both.
     *
     * @return array{0: CourtSlot, 1: string}
     */
    private function slotForUpcomingDate(string $start = '09:00:00', string $end = '10:00:00'): array
    {
        $date = Carbon::tomorrow();
        $slot = CourtSlot::factory()->create([
            'day_of_week' => $date->dayOfWeek,
            'start_time'  => $start,
            'end_time'    => $end,
        ]);

        return [$slot, $date->format('Y-m-d')];
    }

    public function test_a_consumer_can_book_a_slot_for_a_date(): void
    {
        $consumer = User::factory()->create();
        [$slot, $date] = $this->slotForUpcomingDate();

        $this->actingAs($consumer, 'sanctum')
            ->postJson('/api/consumer/bookings', ['slot_id' => $slot->id, 'booking_date' => $date])
            ->assertCreated()
            ->assertJsonPath('data.status', 'booked')
            ->assertJsonPath('data.booking_date', $date);

        $this->assertDatabaseHas('bookings', [
            'slot_id'      => $slot->id,
            'booking_date' => $date,
            'status'       => 'booked',
        ]);
    }

    public function test_a_slot_cannot_be_double_booked_on_the_same_date(): void
    {
        $first  = User::factory()->create();
        $second = User::factory()->create();
        [$slot, $date] = $this->slotForUpcomingDate();

        $this->actingAs($first, 'sanctum')
            ->postJson('/api/consumer/bookings', ['slot_id' => $slot->id, 'booking_date' => $date])
            ->assertCreated();

        $this->actingAs($second, 'sanctum')
            ->postJson('/api/consumer/bookings', ['slot_id' => $slot->id, 'booking_date' => $date])
            ->assertStatus(422)
            ->assertJsonPath('message', 'This slot has already been booked for that date.');
    }

    public function test_the_same_slot_can_be_booked_on_different_dates(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        // A slot on a known weekday; two different upcoming dates of that weekday.
        $monday = Carbon::parse('next monday');
        $slot = CourtSlot::factory()->create(['day_of_week' => 1, 'start_time' => '09:00:00', 'end_time' => '10:00:00']);

        $this->actingAs($a, 'sanctum')
            ->postJson('/api/consumer/bookings', ['slot_id' => $slot->id, 'booking_date' => $monday->format('Y-m-d')])
            ->assertCreated();

        $this->actingAs($b, 'sanctum')
            ->postJson('/api/consumer/bookings', ['slot_id' => $slot->id, 'booking_date' => $monday->copy()->addWeek()->format('Y-m-d')])
            ->assertCreated();

        $this->assertDatabaseCount('bookings', 2);
    }

    public function test_a_slot_cannot_be_booked_on_a_mismatched_weekday(): void
    {
        $consumer = User::factory()->create();
        // Slot is a Monday slot; try to book it on a Tuesday.
        $slot = CourtSlot::factory()->create(['day_of_week' => 1, 'start_time' => '09:00:00', 'end_time' => '10:00:00']);
        $tuesday = Carbon::parse('next tuesday')->format('Y-m-d');

        $this->actingAs($consumer, 'sanctum')
            ->postJson('/api/consumer/bookings', ['slot_id' => $slot->id, 'booking_date' => $tuesday])
            ->assertStatus(422)
            ->assertJsonPath('message', 'This slot is not available on the selected date.');
    }

    public function test_a_consumer_can_cancel_before_slot_start(): void
    {
        $consumer = User::factory()->create();
        [$slot, $date] = $this->slotForUpcomingDate();

        $bookingId = $this->actingAs($consumer, 'sanctum')
            ->postJson('/api/consumer/bookings', ['slot_id' => $slot->id, 'booking_date' => $date])
            ->json('data.id');

        $this->actingAs($consumer, 'sanctum')
            ->patchJson("/api/consumer/bookings/{$bookingId}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_a_consumer_cannot_cancel_another_users_booking(): void
    {
        $owner    = User::factory()->create();
        $stranger = User::factory()->create();
        [$slot, $date] = $this->slotForUpcomingDate();

        $bookingId = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/consumer/bookings', ['slot_id' => $slot->id, 'booking_date' => $date])
            ->json('data.id');

        $this->actingAs($stranger, 'sanctum')
            ->patchJson("/api/consumer/bookings/{$bookingId}/cancel")
            ->assertStatus(403);

        $this->assertSame(BookingStatus::BOOKED, $slot->bookings()->first()->status);
    }
}
