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
        Schema::table('log_pengukuran', function (Blueprint $table) {
            // Tambahkan kolom anak_id jika belum ada
            if (!Schema::hasColumn('log_pengukuran', 'anak_id')) {
                $table->unsignedBigInteger('anak_id')->nullable()->after('id');
                $table->foreign('anak_id')->references('id')->on('anak')->onDelete('cascade');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('log_pengukuran', function (Blueprint $table) {
            if (Schema::hasColumn('log_pengukuran', 'anak_id')) {
                $table->dropForeign(['anak_id']);
                $table->dropColumn('anak_id');
            }
        });
    }
};