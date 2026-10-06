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
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('email');
            $table->string('role', 20)->default('user')->index()->after('password');
            $table->boolean('is_active')->default(true)->after('role');
            $table->boolean('is_on_duty')->default(false)->after('is_active');
            $table->timestamp('last_assigned_at')->nullable()->after('is_on_duty');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn(['phone', 'role', 'is_active', 'is_on_duty', 'last_assigned_at']);
        });
    }
};
