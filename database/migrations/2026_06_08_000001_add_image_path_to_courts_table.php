<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add an optional image path to courts (relative path on the `public` disk).
     * Nullable + additive, so existing rows and the existing API are unaffected.
     */
    public function up(): void
    {
        Schema::table('courts', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('courts', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });
    }
};
