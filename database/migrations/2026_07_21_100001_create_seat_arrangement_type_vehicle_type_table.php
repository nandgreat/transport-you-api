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
        Schema::create('seat_arrangement_type_vehicle_type', function (Blueprint $table) {
            $table->id();
            // Explicit short FK names: the auto-generated names exceed MySQL's
            // 64-character identifier limit for this long pivot-table name.
            $table->foreignId('vehicle_type_id')
                  ->constrained('vehicle_types', 'id', 'vt_sat_vt_fk')->cascadeOnDelete();
            $table->foreignId('seat_arrangement_type_id')
                  ->constrained('seat_arrangement_types', 'id', 'vt_sat_sat_fk')->cascadeOnDelete();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['vehicle_type_id', 'seat_arrangement_type_id'], 'vt_sat_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seat_arrangement_type_vehicle_type');
    }
};
