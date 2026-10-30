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
            $table->foreignId('vehicle_id')->nullable()->after('vehicle_type_id')
                  ->constrained('vehicles')->nullOnDelete();
            $table->foreignId('driver_id')->nullable()->after('vehicle_id')
                  ->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable()->after('driver_id');
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropForeign(['vehicle_id']);
            $table->dropForeign(['driver_id']);
            $table->dropColumn(['vehicle_id', 'driver_id', 'assigned_at']);
        });
    }
};
