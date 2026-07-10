<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->string('external_ref')->nullable()->after('tenant_id');
            $table->unique(['tenant_id', 'external_ref']);
        });

        Schema::table('routes', function (Blueprint $table) {
            $table->string('external_ref')->nullable()->after('tenant_id');
            $table->unique(['tenant_id', 'external_ref']);
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'external_ref']);
            $table->dropColumn('external_ref');
        });

        Schema::table('routes', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'external_ref']);
            $table->dropColumn('external_ref');
        });
    }
};
