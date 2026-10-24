<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Capacity is no longer stored: it is derived from the seat arrangement
     * that applies to a schedule.
     */
    public function up(): void
    {
        Schema::table('vehicle_types', function (Blueprint $table) {
            $table->dropColumn('capacity');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('capacity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicle_types', function (Blueprint $table) {
            $table->unsignedSmallInteger('capacity')->nullable()->after('vehicle_category_id');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->integer('capacity')->nullable()->after('registration_number');
        });
    }
};
