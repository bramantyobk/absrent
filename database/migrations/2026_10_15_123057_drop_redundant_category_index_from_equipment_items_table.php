<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indeks tunggal category berlebih: unique (category, name) sudah diawali kolom category.
     */
    public function up(): void
    {
        Schema::table('equipment_items', function (Blueprint $table) {
            $table->dropIndex(['category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('equipment_items', function (Blueprint $table) {
            $table->index('category');
        });
    }
};
