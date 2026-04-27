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
        Schema::table('routes', function (Blueprint $table) {
            $table->dropColumn(['origin', 'destination']);
            $table->foreignId('origin_id')->after('tenant_id')->constrained('locations')->onDelete('cascade');
            $table->foreignId('destination_id')->after('origin_id')->constrained('locations')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('routes', function (Blueprint $table) {
            $table->string('origin')->after('tenant_id');
            $table->string('destination')->after('origin');
            $table->dropForeign(['origin_id']);
            $table->dropForeign(['destination_id']);
            $table->dropColumn(['origin_id', 'destination_id']);
        });
    }
};
