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
     */
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            if (!Schema::hasColumn('schedules', 'vehicle_id')) {
                $table->foreignId('vehicle_id')->nullable()->after('vehicle_type_id')
                      ->constrained('vehicles')->nullOnDelete();
            }
            if (!Schema::hasColumn('schedules', 'driver_id')) {
                $table->foreignId('driver_id')->nullable()->after('vehicle_id')
                      ->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('schedules', 'assigned_at')) {
                $table->timestamp('assigned_at')->nullable()->after('driver_id');
            }
        });
    }

    public function down(): void
    {
        $this->dropForeignKeyIfExists('schedules', 'vehicle_id', 'schedules_vehicle_id_foreign');
        $this->dropForeignKeyIfExists('schedules', 'driver_id', 'schedules_driver_id_foreign');

        $columnsToDrop = array_values(array_filter(['vehicle_id', 'driver_id', 'assigned_at'], function ($column) {
            return Schema::hasColumn('schedules', $column);
        }));

        if (!empty($columnsToDrop)) {
            Schema::table('schedules', function (Blueprint $table) use ($columnsToDrop) {
                $table->dropColumn($columnsToDrop);
            });
        }
    }

    private function dropForeignKeyIfExists(string $table, string $column, ?string $defaultConstraint = null): void
    {
        try {
            $foreignKeys = collect(Schema::getForeignKeys($table));
            $match = $foreignKeys->first(function ($fk) use ($column, $defaultConstraint) {
                return in_array($column, $fk['columns'] ?? [])
                    || ($defaultConstraint && ($fk['name'] ?? null) === $defaultConstraint);
            });

            if ($match && !empty($match['name'])) {
                Schema::table($table, function (Blueprint $tableBlueprint) use ($match) {
                    $tableBlueprint->dropForeign($match['name']);
                });
                return;
            }

            if ($match) {
                Schema::table($table, function (Blueprint $tableBlueprint) use ($column) {
                    $tableBlueprint->dropForeign([$column]);
                });
                return;
            }
        } catch (\Throwable $e) {
            // Fall through
        }

        try {
            Schema::table($table, function (Blueprint $tableBlueprint) use ($column) {
                $tableBlueprint->dropForeign([$column]);
            });
        } catch (\Throwable $e) {
            // Safely ignore
        }
    }
};
