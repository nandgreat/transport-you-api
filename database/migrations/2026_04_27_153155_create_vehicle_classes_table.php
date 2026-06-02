<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vehicle_classes', function (Blueprint $table) {
            $table->id();
            $table->string('vehicle_class');
            $table->longText('vehicle_class_image');
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade')->default(1);
            $table->foreignId('status_id')->constrained('statuses')->onDelete('cascade')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_classes');
    }
};
