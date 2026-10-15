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
        Schema::table('transactions', function (Blueprint $table) {
            $table->timestamp('confirmed_at')->nullable()->after('proof_path');
        });

        Schema::table('company_profiles', function (Blueprint $table) {
            $table->text('whatsapp_confirmation_template')->nullable()->after('operating_hours');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_profiles', function (Blueprint $table) {
            $table->dropColumn('whatsapp_confirmation_template');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('confirmed_at');
        });
    }
};
