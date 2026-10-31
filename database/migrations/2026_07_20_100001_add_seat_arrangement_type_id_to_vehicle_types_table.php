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
        if (! Schema::hasColumn('vehicle_types', 'seat_arrangement_type_id')) {
            Schema::table('vehicle_types', function (Blueprint $table) {
                $table->foreignId('seat_arrangement_type_id')
                      ->nullable()
                      ->after('vehicle_category_id')
                      ->constrained('seat_arrangement_types')
                      ->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Idempotent FK drop so rollback is safe against any state.
        $this->dropForeignIfExists('vehicle_types', 'seat_arrangement_type_id');

        if (Schema::hasColumn('vehicle_types', 'seat_arrangement_type_id')) {
            Schema::table('vehicle_types', fn (Blueprint $table) => $table->dropColumn('seat_arrangement_type_id'));
        }
    }

    private function dropForeignIfExists(string $table, string $column): void
    {
        if (! Schema::hasColumn($table, $column)) {
            return;
        }

        try {
            Schema::table($table, fn (Blueprint $t) => $t->dropForeign([$column]));
        } catch (\Throwable $e) {
            // Foreign key already absent — nothing to do.
        }
    }
};
