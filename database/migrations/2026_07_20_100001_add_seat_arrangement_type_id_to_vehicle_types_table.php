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
        Schema::table('vehicle_types', function (Blueprint $table) {
            $table->foreignId('seat_arrangement_type_id')
                  ->nullable()
                  ->after('vehicle_category_id')
                  ->constrained('seat_arrangement_types')
                  ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicle_types', function (Blueprint $table) {
            $table->dropForeign(['seat_arrangement_type_id']);
            $table->dropColumn('seat_arrangement_type_id');
        });
    }
};
