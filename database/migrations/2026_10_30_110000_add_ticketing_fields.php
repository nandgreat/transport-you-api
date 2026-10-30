<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The "special booking id" generated at the point of booking; the ticket
        // QR codes are derived from it.
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('reference')->nullable()->unique()->after('id');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->string('passenger_name')->nullable()->after('seat_number');
            $table->string('passenger_phone')->nullable()->after('passenger_name');
            $table->timestamp('issued_at')->nullable()->after('status');

            // qr_code already exists as a nullable string; it now holds the unique
            // scannable code that each ticket's QR image encodes.
            $table->unique('qr_code');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropUnique('tickets_qr_code_unique');
            $table->dropColumn(['passenger_name', 'passenger_phone', 'issued_at']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropUnique('bookings_reference_unique');
            $table->dropColumn('reference');
        });
    }
};
