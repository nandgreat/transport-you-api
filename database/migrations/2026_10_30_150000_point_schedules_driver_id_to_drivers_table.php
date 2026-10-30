<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Repoint schedules.driver_id from users to the dedicated drivers table.
     * Any pre-existing assignment is cleared, since the ids no longer correlate.
     */
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropForeign(['driver_id']);
        });

        \Illuminate\Support\Facades\DB::table('schedules')->update(['driver_id' => null, 'assigned_at' => null]);

        Schema::table('schedules', function (Blueprint $table) {
            $table->foreign('driver_id')->references('id')->on('drivers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropForeign(['driver_id']);
            $table->foreign('driver_id')->references('id')->on('users')->nullOnDelete();
        });
    }
};
