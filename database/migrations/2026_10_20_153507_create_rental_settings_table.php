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
        Schema::create('rental_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('return_buffer_hours')->nullable();
            $table->unsignedSmallInteger('min_free_window_hours')->nullable();
            $table->unsignedSmallInteger('min_rental_hours')->nullable();
            $table->unsignedSmallInteger('max_rental_days')->nullable();
            $table->unsignedSmallInteger('pending_expiry_hours')->nullable();
            $table->unsignedSmallInteger('confirm_validation_window_hours')->nullable();
            $table->unsignedSmallInteger('verification_warning_hours')->nullable();
            $table->unsignedInteger('vehicle_change_admin_threshold')->nullable();
            $table->unsignedSmallInteger('max_pending_orders')->nullable();
            $table->unsignedSmallInteger('max_vehicles_per_order')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rental_settings');
    }
};
