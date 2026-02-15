<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LogPengukuran extends Model
{
    use HasFactory;

    public $timestamps = false;           // kita pakai kolom diubah_pada
    protected $table = 'log_pengukuran';  // default sudah sama, tapi eksplisit oke

    // BIARKAN auto-increment dari migration bekerja:
    // Hapus $incrementing=false dan keyType string.
    // primary key default 'id' (bigint, increment).

    protected $fillable = [
        'nik_log',
        'posyandu_id_lama',
        'tanggal_ukur_lama',
        'berat_lama','tinggi_lama','lila_lama','lingkar_kepala_lama',
        'cara_ukur_lama','vit_a_lama',
        'asi_bulan_0_lama','asi_bulan_1_lama','asi_bulan_2_lama',
        'asi_bulan_3_lama','asi_bulan_4_lama','asi_bulan_5_lama','asi_bulan_6_lama',
        'kelas_ibu_balita_lama',
        'diubah_pada',
    ];

    protected $casts = [
        'tanggal_ukur_lama' => 'date',
        'diubah_pada'       => 'datetime',
        'asi_bulan_0_lama'  => 'boolean',
        'asi_bulan_1_lama'  => 'boolean',
        'asi_bulan_2_lama'  => 'boolean',
        'asi_bulan_3_lama'  => 'boolean',
        'asi_bulan_4_lama'  => 'boolean',
        'asi_bulan_5_lama'  => 'boolean',
        'asi_bulan_6_lama'  => 'boolean',
        'kelas_ibu_balita_lama' => 'boolean',
    ];

    public function anak()
    {
        return $this->belongsTo(Anak::class, 'nik_log', 'nik');
    }

    public function posyandu()
    {
        return $this->belongsTo(Posyandu::class, 'posyandu_id_lama', 'id');
    }
}
