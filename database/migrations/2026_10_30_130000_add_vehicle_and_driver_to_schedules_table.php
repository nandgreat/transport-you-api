<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        // Drop foreign keys only if present, so rollback is safe on any driver
        // and whatever table driver_id currently points at.
        foreach (['vehicle_id', 'driver_id'] as $column) {
            if ($this->hasForeignKey('schedules', "schedules_{$column}_foreign")) {
                Schema::table('schedules', fn (Blueprint $table) => $table->dropForeign([$column]));
            }
        }

        $columns = array_values(array_filter(
            ['vehicle_id', 'driver_id', 'assigned_at'],
            fn ($column) => Schema::hasColumn('schedules', $column)
        ));

        if ($columns) {
            Schema::table('schedules', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }

    /**
     * Cross-database foreign-key existence check (MySQL + PostgreSQL), without
     * relying on doctrine/dbal or the Laravel 11+ Schema::getForeignKeys().
     */
    private function hasForeignKey(string $table, string $name): bool
    {
        return count(DB::select(
            "select 1 from information_schema.table_constraints
             where table_name = ? and constraint_name = ? and constraint_type = 'FOREIGN KEY'",
            [$table, $name]
        )) > 0;
    }
};
