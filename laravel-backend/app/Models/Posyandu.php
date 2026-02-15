<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Posyandu extends Model
{
    use HasFactory;

    protected $table = 'posyandu';

    protected $fillable = [
        'desa',
        'nama'
    ];

    public function anak()
    {
        return $this->hasMany(Anak::class);
    }

    public function pengukuranAnak()
    {
        return $this->hasMany(AnakPengukuran::class);
    }
}
