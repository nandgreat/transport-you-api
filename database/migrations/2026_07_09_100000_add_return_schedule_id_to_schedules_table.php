<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->unsignedBigInteger('return_schedule_id')->nullable()->after('schedule_group_id');
            $table->foreign('return_schedule_id')->references('id')->on('schedules')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropForeign(['return_schedule_id']);
            $table->dropColumn('return_schedule_id');
        });
    }
};
