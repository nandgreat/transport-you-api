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
        Schema::table('schedules', function (Blueprint $table) {
            $table->foreignId('seat_arrangement_type_id')
                  ->nullable()
                  ->after('vehicle_type_id')
                  ->constrained('seat_arrangement_types')
                  ->nullOnDelete();
        });

        Schema::table('schedule_groups', function (Blueprint $table) {
            $table->foreignId('seat_arrangement_type_id')
                  ->nullable()
                  ->after('vehicle_type_id')
                  ->constrained('seat_arrangement_types')
                  ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('seat_arrangement_type_id');
        });

        Schema::table('schedule_groups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('seat_arrangement_type_id');
        });
    }
};
