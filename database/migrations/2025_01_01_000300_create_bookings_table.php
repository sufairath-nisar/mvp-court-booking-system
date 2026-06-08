<?php

use App\Enums\BookingStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Columns match the requirement exactly:
     * id, user_id, court_id, slot_id, booking_date, status ('booked'|'cancelled'),
     * created_at, updated_at.
     *
     * Double booking is prevented in BookingService via a transaction that locks the
     * slot row (lockForUpdate) before checking/setting is_booked.
     */
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('court_id')->constrained('courts')->cascadeOnDelete();
            $table->foreignId('slot_id')->constrained('court_slots')->cascadeOnDelete();
            $table->date('booking_date');
            $table->enum('status', BookingStatus::values())->default(BookingStatus::BOOKED->value)->index();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
