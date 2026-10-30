<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * When enabled, passengers must pick a specific seat number on this
     * schedule; when disabled, bookings only consume seats by count.
     */
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->boolean('seat_selection_enabled')->default(true)->after('seat_arrangement_type_id');
        });

        Schema::table('schedule_groups', function (Blueprint $table) {
            $table->boolean('seat_selection_enabled')->default(true)->after('seat_arrangement_type_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn('seat_selection_enabled');
        });

        Schema::table('schedule_groups', function (Blueprint $table) {
            $table->dropColumn('seat_selection_enabled');
        });
    }
};
