<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Assigning the physical resources that actually run a scheduled trip:
     * a concrete vehicle (matching the trip's vehicle type) and a driver.
     * Both are nullable — a trip may be planned before its bus/driver is set.
     *
     * driver_id is introduced here referencing users and is repointed to the
     * dedicated drivers table by a later migration.
     */
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            if (! Schema::hasColumn('schedules', 'vehicle_id')) {
                $table->foreignId('vehicle_id')->nullable()->after('vehicle_type_id')
                      ->constrained('vehicles')->nullOnDelete();
            }
            if (! Schema::hasColumn('schedules', 'driver_id')) {
                $table->foreignId('driver_id')->nullable()->after('vehicle_id')
                      ->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('schedules', 'assigned_at')) {
                $table->timestamp('assigned_at')->nullable()->after('driver_id');
            }
        });
    }

    public function down(): void
    {
        // Drop the foreign keys defensively — rollback must not fail if a key is
        // already gone, and this stays correct on MySQL and PostgreSQL alike.
        $this->dropForeignIfExists('schedules', 'vehicle_id');
        $this->dropForeignIfExists('schedules', 'driver_id');

        $columns = array_values(array_filter(
            ['vehicle_id', 'driver_id', 'assigned_at'],
            fn ($column) => Schema::hasColumn('schedules', $column)
        ));

        if ($columns) {
            Schema::table('schedules', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }

    /**
     * Drop a column's conventional foreign key, ignoring the "doesn't exist"
     * error so the migration is safe to roll back from any state.
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
