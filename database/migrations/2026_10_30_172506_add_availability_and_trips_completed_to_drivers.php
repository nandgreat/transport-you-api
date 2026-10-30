<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drivers carry an operational availability — distinct from their account
     * `status` (active/inactive) — that tracks whether they can be dispatched,
     * plus a running count of trips they have completed.
     */
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->enum('availability', ['available', 'on_trip', 'unavailable'])
                ->default('available')
                ->after('status');
            $table->unsignedInteger('trips_completed')->default(0)->after('availability');
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn(['availability', 'trips_completed']);
        });
    }
};
