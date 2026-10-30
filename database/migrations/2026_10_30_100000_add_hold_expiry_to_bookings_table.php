<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // When a pending (unpaid) hold lapses; null once confirmed/cancelled.
            $table->timestamp('expires_at')->nullable()->after('status');
        });

        // Allow 'expired' as a booking status (Postgres enum = CHECK constraint).
        DB::statement('ALTER TABLE bookings DROP CONSTRAINT IF EXISTS bookings_status_check');
        DB::statement("ALTER TABLE bookings ADD CONSTRAINT bookings_status_check CHECK (status IN ('pending', 'confirmed', 'cancelled', 'expired'))");
    }

    public function down(): void
    {
        // Fold any expired rows back into a valid value before narrowing the set.
        DB::statement("UPDATE bookings SET status = 'cancelled' WHERE status = 'expired'");

        DB::statement('ALTER TABLE bookings DROP CONSTRAINT IF EXISTS bookings_status_check');
        DB::statement("ALTER TABLE bookings ADD CONSTRAINT bookings_status_check CHECK (status IN ('pending', 'confirmed', 'cancelled'))");

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('expires_at');
        });
    }
};
