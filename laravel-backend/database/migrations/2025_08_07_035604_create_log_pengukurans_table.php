<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('log_pengukuran', function (Blueprint $table) {
            $table->id();

            $table->string('nik_log', 16);
            $table->unsignedBigInteger('posyandu_id_lama')->nullable();
            $table->date('tanggal_ukur_lama')->nullable();

            $table->float('berat_lama')->nullable();
            $table->float('tinggi_lama')->nullable();
            $table->float('lila_lama')->nullable();
            $table->float('lingkar_kepala_lama')->nullable();
            $table->string('cara_ukur_lama')->nullable();
            $table->string('vit_a_lama')->nullable();

            $table->boolean('asi_bulan_0_lama')->nullable();
            $table->boolean('asi_bulan_1_lama')->nullable();
            $table->boolean('asi_bulan_2_lama')->nullable();
            $table->boolean('asi_bulan_3_lama')->nullable();
            $table->boolean('asi_bulan_4_lama')->nullable();
            $table->boolean('asi_bulan_5_lama')->nullable();
            $table->boolean('asi_bulan_6_lama')->nullable();

            $table->boolean('kelas_ibu_balita_lama')->nullable();

            $table->timestamp('diubah_pada')->useCurrent();

            $table->foreign('nik_log')->references('nik')->on('anak')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_pengukuran');
    }
};
