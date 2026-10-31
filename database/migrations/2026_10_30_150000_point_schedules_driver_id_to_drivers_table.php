<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Repoint schedules.driver_id from users to the dedicated drivers table.
     * Any pre-existing assignment is cleared, since the ids no longer correlate.
     */
    public function up(): void
    {
        $this->dropForeignIfExists('schedules', 'driver_id');

        DB::table('schedules')->update(['driver_id' => null, 'assigned_at' => null]);

        Schema::table('schedules', function (Blueprint $table) {
            $table->foreign('driver_id')->references('id')->on('drivers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        $this->dropForeignIfExists('schedules', 'driver_id');

        DB::table('schedules')->update(['driver_id' => null, 'assigned_at' => null]);

        Schema::table('schedules', function (Blueprint $table) {
            $table->foreign('driver_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Drop a column's conventional foreign key, ignoring the "doesn't exist"
     * error so the migration is safe on MySQL and PostgreSQL alike.
     */
    private function dropForeignIfExists(string $table, string $column): void
    {
        try {
            Schema::table($table, fn (Blueprint $t) => $t->dropForeign([$column]));
        } catch (\Throwable $e) {
            // Foreign key already absent — nothing to do.
        }
    }
};
