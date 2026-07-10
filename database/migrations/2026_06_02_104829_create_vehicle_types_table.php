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
        Schema::create('vehicle_types', function (Blueprint $table) {
            $table->id();
            $table->string('vehicle_name');
            $table->string('image_url')->nullable();
            $table->unsignedSmallInteger('capacity');
            $table->foreignId('vehicle_category_id')->constrained('vehicle_categories')->onDelete('restrict');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->unsignedSmallInteger('year');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_types');
    }
};
