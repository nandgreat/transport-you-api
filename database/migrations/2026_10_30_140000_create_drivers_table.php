<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drivers are fleet records owned by an operator (tenant) — not login
     * accounts. They are assigned to scheduled trips alongside a vehicle.
     */
    public function up(): void
    {
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('licence_number')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            // Licence numbers are unique per operator when provided.
            $table->unique(['tenant_id', 'licence_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};
