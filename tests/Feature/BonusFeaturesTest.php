<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Court;
use App\Models\CourtSlot;
use App\Models\User;
use App\Notifications\BookingCancelledNotification;
use App\Notifications\BookingConfirmedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BonusFeaturesTest extends TestCase
{
    use RefreshDatabase;

    // --- Feature 1: booking notifications ------------------------------------

    public function test_a_notification_is_sent_when_a_booking_is_created(): void
    {
        Notification::fake();

        $consumer = User::factory()->create();
        $slot = CourtSlot::factory()->create();

        $this->actingAs($consumer, 'sanctum')
            ->postJson('/api/consumer/bookings', ['slot_id' => $slot->id])
            ->assertCreated();

        Notification::assertSentTo($consumer, BookingConfirmedNotification::class);
    }

    public function test_a_notification_is_sent_when_a_booking_is_cancelled(): void
    {
        Notification::fake();

        $consumer = User::factory()->create();
        $slot = CourtSlot::factory()->create();

        $bookingId = $this->actingAs($consumer, 'sanctum')
            ->postJson('/api/consumer/bookings', ['slot_id' => $slot->id])
            ->json('data.id');

        $this->actingAs($consumer, 'sanctum')
            ->patchJson("/api/consumer/bookings/{$bookingId}/cancel")
            ->assertOk();

        Notification::assertSentTo($consumer, BookingCancelledNotification::class);
    }

    // --- Feature 3: court image upload ---------------------------------------

    public function test_an_admin_can_upload_a_court_image(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $court = Court::factory()->create();
        $file = UploadedFile::fake()->create('court.jpg', 100, 'image/jpeg');

        $this->actingAs($admin, 'sanctum')
            ->post("/api/admin/courts/{$court->id}/image", ['image' => $file], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $path = $court->fresh()->image_path;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_court_image_upload_rejects_non_image_files(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $court = Court::factory()->create();
        $file = UploadedFile::fake()->create('notes.txt', 10, 'text/plain');

        $this->actingAs($admin, 'sanctum')
            ->post("/api/admin/courts/{$court->id}/image", ['image' => $file], ['Accept' => 'application/json'])
            ->assertStatus(422);
    }

    public function test_a_consumer_cannot_upload_a_court_image(): void
    {
        Storage::fake('public');

        $consumer = User::factory()->create();
        $court = Court::factory()->create();
        $file = UploadedFile::fake()->create('court.jpg', 100, 'image/jpeg');

        $this->actingAs($consumer, 'sanctum')
            ->post("/api/admin/courts/{$court->id}/image", ['image' => $file], ['Accept' => 'application/json'])
            ->assertStatus(403);
    }
}
