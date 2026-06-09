<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Weekly recurring availability template for a court.
     *
     * One row per (court, day_of_week): the fixed open/close hours that repeat every
     * week. Concrete bookable slots (court_slots) are generated from this template.
     */
    public function up(): void
    {
        Schema::create('court_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('court_id')->constrained('courts')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 0 = Sunday ... 6 = Saturday
            $table->time('open_time');
            $table->time('close_time');
            $table->unsignedSmallInteger('slot_duration')->default(60); // minutes
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // One schedule row per weekday per court.
            $table->unique(['court_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('court_schedules');
    }
};
