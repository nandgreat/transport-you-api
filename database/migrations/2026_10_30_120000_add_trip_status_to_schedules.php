<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Operational lifecycle of a dated trip, distinct from `status` (which
        // drives public trip search). A trip becomes a "scheduled trip" once it
        // has a confirmed booking; staff move it through these states.
        Schema::table('schedules', function (Blueprint $table) {
            $table->enum('trip_status', ['not_started', 'ongoing', 'completed', 'cancelled'])
                ->default('not_started')
                ->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn('trip_status');
        });

        // enum() leaves a CHECK constraint behind on Postgres; make sure it goes.
        DB::statement('ALTER TABLE schedules DROP CONSTRAINT IF EXISTS schedules_trip_status_check');
    }
};
