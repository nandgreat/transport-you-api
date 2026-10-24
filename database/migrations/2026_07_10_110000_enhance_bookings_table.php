<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedBigInteger('return_booking_id')->nullable()->after('schedule_id');
            $table->json('passengers')->nullable()->after('total_amount');
            $table->text('notes')->nullable()->after('passengers');

            $table->foreign('return_booking_id')->references('id')->on('bookings')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['return_booking_id']);
            $table->dropColumn(['return_booking_id', 'passengers', 'notes']);
        });
    }
};
