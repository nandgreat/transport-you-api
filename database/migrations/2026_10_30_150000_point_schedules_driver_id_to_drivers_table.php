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
        $this->dropForeignKeyIfExists('schedules', 'driver_id', 'schedules_driver_id_foreign');

        \Illuminate\Support\Facades\DB::table('schedules')->update(['driver_id' => null, 'assigned_at' => null]);

        Schema::table('schedules', function (Blueprint $table) {
            $table->foreign('driver_id')->references('id')->on('drivers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        $this->dropForeignKeyIfExists('schedules', 'driver_id', 'schedules_driver_id_foreign');

        Schema::table('schedules', function (Blueprint $table) {
            $table->foreign('driver_id')->references('id')->on('users')->nullOnDelete();
        });
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
