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
        Schema::create('order_vehicle_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_vehicle_id')->constrained('vehicles')->restrictOnDelete();
            $table->foreignId('to_vehicle_id')->constrained('vehicles')->restrictOnDelete();
            $table->string('reason', 30);
            $table->string('from_order_status', 20);
            $table->text('note')->nullable();
            $table->unsignedInteger('old_unit_price');
            $table->unsignedInteger('new_unit_price');
            $table->integer('suggested_amount');
            $table->integer('final_amount');
            $table->string('old_vehicle_status', 10)->nullable();
            $table->text('old_vehicle_note')->nullable();
            $table->foreignId('old_vehicle_inspection_id')->nullable()->constrained('vehicle_inspections')->nullOnDelete();
            $table->foreignId('old_vehicle_status_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('old_vehicle_status_at')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['from_vehicle_id', 'old_vehicle_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_vehicle_changes');
    }
};
