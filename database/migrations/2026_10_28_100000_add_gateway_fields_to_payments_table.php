<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('currency', 10)->default('NGN')->after('amount');
            $table->string('authorization_url')->nullable()->after('transaction_reference');
            $table->string('access_code')->nullable()->after('authorization_url');
            $table->string('channel')->nullable()->after('access_code');
            $table->text('gateway_response')->nullable()->after('channel');
            $table->timestamp('paid_at')->nullable()->after('gateway_response');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['currency', 'authorization_url', 'access_code', 'channel', 'gateway_response', 'paid_at']);
        });
    }
};
