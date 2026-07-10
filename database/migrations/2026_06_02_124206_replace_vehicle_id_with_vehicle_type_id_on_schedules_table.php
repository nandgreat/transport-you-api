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
            $table->dropForeign(['vehicle_id']);
            $table->dropColumn('vehicle_id');

            $table->foreignId('vehicle_type_id')
                  ->after('route_id')
                  ->constrained('vehicle_types')
                  ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropForeign(['vehicle_type_id']);
            $table->dropColumn('vehicle_type_id');

            $table->foreignId('vehicle_id')
                  ->after('route_id')
                  ->constrained('vehicles')
                  ->onDelete('cascade');
        });
    }
};
