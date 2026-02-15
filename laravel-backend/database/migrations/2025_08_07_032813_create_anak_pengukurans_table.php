<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anak_pengukuran', function (Blueprint $table) {
            $table->id();

            $table->foreignId('anak_id')
                  ->constrained('anak')
                  ->cascadeOnDelete();

            $table->date('tanggal_ukur');

            $table->foreignId('posyandu_id')
                  ->nullable()
                  ->constrained('posyandu')
                  ->nullOnDelete();

            $table->float('berat')->nullable();
            $table->float('tinggi')->nullable();
            $table->float('lila')->nullable();
            $table->float('lingkar_kepala')->nullable();
            $table->string('cara_ukur')->nullable();
            $table->string('vit_a')->nullable();

            $table->boolean('asi_bulan_0')->default(false);
            $table->boolean('asi_bulan_1')->default(false);
            $table->boolean('asi_bulan_2')->default(false);
            $table->boolean('asi_bulan_3')->default(false);
            $table->boolean('asi_bulan_4')->default(false);
            $table->boolean('asi_bulan_5')->default(false);
            $table->boolean('asi_bulan_6')->default(false);

            $table->boolean('kelas_ibu_balita')->default(false);

            $table->timestamps();

            $table->unique(['anak_id', 'tanggal_ukur']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anak_pengukuran');
    }
};
