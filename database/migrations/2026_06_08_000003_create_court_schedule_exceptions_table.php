<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Date-specific overrides of the weekly schedule (e.g. Eid, public holidays).
     *
     * For a given (court, date): either close the court entirely (`is_closed`) or
     * override its hours (`open_time`/`close_time`). When not set, the normal weekly
     * template applies.
     */
    public function up(): void
    {
        Schema::create('court_schedule_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('court_id')->constrained('courts')->cascadeOnDelete();
            $table->date('date');
            $table->boolean('is_closed')->default(false);
            $table->time('open_time')->nullable();
            $table->time('close_time')->nullable();
            $table->unsignedSmallInteger('slot_duration')->nullable(); // minutes; null => use template/default
            $table->string('reason')->nullable();
            $table->timestamps();

            // One exception per date per court.
            $table->unique(['court_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('court_schedule_exceptions');
    }
};
