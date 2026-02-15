<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anak', function (Blueprint $table) {
            $table->id();

            $table->char('nik', 16)->unique();
            $table->integer('anak_ke')->nullable();
            $table->date('tgl_lahir');
            $table->enum('jenis_kelamin', ['L', 'P']);
            $table->string('nomor_KK')->nullable();
            $table->string('nama_anak');
            $table->integer('usia_hamil')->nullable();
            $table->float('berat_lahir')->nullable();
            $table->float('panjang_lahir')->nullable();
            $table->float('lingkar_kepala_lahir')->nullable();
            $table->boolean('kia')->default(false);
            $table->boolean('kia_bayi_kecil')->default(false);
            $table->boolean('imd')->default(false);
            $table->string('nama_ortu')->nullable();
            $table->string('nik_ortu')->nullable();
            $table->string('hp_ortu')->nullable();

            $table->foreignId('posyandu_id')
                  ->nullable()
                  ->constrained('posyandu')
                  ->nullOnDelete();

            $table->string('rt')->nullable();
            $table->string('rw')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anak');
    }
};
