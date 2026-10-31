<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->dropForeignKeyIfExists('schedules', 'vehicle_id', 'schedules_vehicle_id_foreign');

        if (Schema::hasColumn('schedules', 'vehicle_id')) {
            Schema::table('schedules', function (Blueprint $table) {
                $table->dropColumn('vehicle_id');
            });
        }

        if (!Schema::hasColumn('schedules', 'vehicle_type_id')) {
            Schema::table('schedules', function (Blueprint $table) {
                $table->foreignId('vehicle_type_id')
                      ->after('route_id')
                      ->constrained('vehicle_types')
                      ->onDelete('restrict');
            });
        }
    }

    public function down(): void
    {
        $this->dropForeignKeyIfExists('schedules', 'vehicle_type_id', 'schedules_vehicle_type_id_foreign');

        if (Schema::hasColumn('schedules', 'vehicle_type_id')) {
            Schema::table('schedules', function (Blueprint $table) {
                $table->dropColumn('vehicle_type_id');
            });
        }

        if (!Schema::hasColumn('schedules', 'vehicle_id')) {
            Schema::table('schedules', function (Blueprint $table) {
                $table->foreignId('vehicle_id')
                      ->after('route_id')
                      ->constrained('vehicles')
                      ->onDelete('cascade');
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
            // Fall through to try-catch dropForeign
        }

        try {
            Schema::table($table, function (Blueprint $tableBlueprint) use ($column) {
                $tableBlueprint->dropForeign([$column]);
            });
        } catch (\Throwable $e) {
            // Constraint does not exist, safely ignore
        }
    }
};
