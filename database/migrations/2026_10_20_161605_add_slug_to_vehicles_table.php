<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Kolom ditambah nullable, diisi untuk kendaraan yang sudah ada (merek + model, akhiran angka bila bentrok), lalu
     * dijadikan wajib dan unik.
     */
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('model');
        });

        $used = [];

        foreach (DB::table('vehicles')->orderBy('id')->get(['id', 'brand', 'model']) as $vehicle) {
            $base = Str::slug("{$vehicle->brand} {$vehicle->model}") ?: 'kendaraan';
            $slug = $base;

            for ($suffix = 2; isset($used[$slug]); $suffix++) {
                $slug = "{$base}-{$suffix}";
            }

            $used[$slug] = true;
            DB::table('vehicles')->where('id', $vehicle->id)->update(['slug' => $slug]);
        }

        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->unique()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
