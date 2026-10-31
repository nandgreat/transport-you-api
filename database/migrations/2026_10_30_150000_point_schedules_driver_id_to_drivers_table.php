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
        if ($this->hasForeignKey('schedules', 'schedules_driver_id_foreign')) {
            Schema::table('schedules', fn (Blueprint $table) => $table->dropForeign(['driver_id']));
        }

        DB::table('schedules')->update(['driver_id' => null, 'assigned_at' => null]);

        Schema::table('schedules', function (Blueprint $table) {
            $table->foreign('driver_id')->references('id')->on('drivers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if ($this->hasForeignKey('schedules', 'schedules_driver_id_foreign')) {
            Schema::table('schedules', fn (Blueprint $table) => $table->dropForeign(['driver_id']));
        }

        DB::table('schedules')->update(['driver_id' => null, 'assigned_at' => null]);

        Schema::table('schedules', function (Blueprint $table) {
            $table->foreign('driver_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    private function hasForeignKey(string $table, string $name): bool
    {
        return count(DB::select(
            "select 1 from information_schema.table_constraints
             where table_name = ? and constraint_name = ? and constraint_type = 'FOREIGN KEY'",
            [$table, $name]
        )) > 0;
    }
};
