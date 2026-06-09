<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether a recurring slot is currently offered to consumers.
     *
     * When the weekly schedule changes, slots are reconciled to the new hours. A stale
     * slot that still has active bookings can't be deleted (it would cascade-delete the
     * booking), so it is deactivated instead: kept for the existing booking, hidden from
     * new customers. Active by default so existing slots stay bookable.
     */
    public function up(): void
    {
        Schema::table('court_slots', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('end_time');
        });
    }

    public function down(): void
    {
        Schema::table('court_slots', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
