<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Anak extends Model
{
    use HasFactory;

    protected $table = 'anak';

    protected $fillable = [
        'nik',
        'anak_ke',
        'tgl_lahir',
        'jenis_kelamin',
        'nomor_KK',
        'nama_anak',
        'usia_hamil',
        'berat_lahir',
        'panjang_lahir',
        'lingkar_kepala_lahir',
        'kia',
        'kia_bayi_kecil',
        'imd',
        'nama_ortu',
        'nik_ortu',
        'hp_ortu',
        'posyandu_id',
        'rt',
        'rw',
    ];

    public function posyandu()
    {
        return $this->belongsTo(Posyandu::class);
    }

    public function pengukuran()
    {
        return $this->hasMany(AnakPengukuran::class, 'anak_id');
    }
}
