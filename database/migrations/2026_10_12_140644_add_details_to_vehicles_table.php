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
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('drive_type', 20)->nullable()->after('is_available');
            $table->decimal('energy_capacity', 5, 1)->unsigned()->nullable()->after('drive_type');
            $table->text('description')->nullable()->after('energy_capacity');
            $table->unsignedInteger('odometer_km')->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn(['drive_type', 'energy_capacity', 'description', 'odometer_km']);
        });
    }
};
