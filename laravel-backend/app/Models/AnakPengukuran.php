<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnakPengukuran extends Model
{
    use HasFactory;

    protected $table = 'anak_pengukuran';

    protected $fillable = [
        'anak_id',
        'tanggal_ukur',
        'posyandu_id',
        'berat',
        'tinggi',
        'lila',
        'lingkar_kepala',
        'cara_ukur',
        'vit_a',
        'asi_bulan_0',
        'asi_bulan_1',
        'asi_bulan_2',
        'asi_bulan_3',
        'asi_bulan_4',
        'asi_bulan_5',
        'asi_bulan_6',
        'kelas_ibu_balita',
    ];

    protected $casts = [
        'tanggal_ukur' => 'date',
        'asi_bulan_0' => 'boolean',
        'asi_bulan_1' => 'boolean',
        'asi_bulan_2' => 'boolean',
        'asi_bulan_3' => 'boolean',
        'asi_bulan_4' => 'boolean',
        'asi_bulan_5' => 'boolean',
        'asi_bulan_6' => 'boolean',
        'kelas_ibu_balita' => 'boolean',
    ];

    public function anak()
    {
        return $this->belongsTo(Anak::class, 'anak_id');
    }

    public function posyandu()
    {
        return $this->belongsTo(Posyandu::class);
    }
}
