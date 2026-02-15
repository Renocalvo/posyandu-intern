<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PosyanduSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $data = [

            ['desa' => 'Oro-oro Ombo', 'nama' => 'Melati 1'],
            ['desa' => 'Oro-oro Ombo', 'nama' => 'Melati 2'],
            ['desa' => 'Oro-oro Ombo', 'nama' => 'Melati 3'],
            ['desa' => 'Oro-oro Ombo', 'nama' => 'Melati 4'],
            ['desa' => 'Oro-oro Ombo', 'nama' => 'Melati 5'],
            ['desa' => 'Oro-oro Ombo', 'nama' => 'Melati 6'],
            ['desa' => 'Oro-oro Ombo', 'nama' => 'Melati 7'],

            ['desa' => 'Ngaglik', 'nama' => 'Azalea 1'],
            ['desa' => 'Ngaglik', 'nama' => 'Azalea 2'],
            ['desa' => 'Ngaglik', 'nama' => 'Azalea 3A'],
            ['desa' => 'Ngaglik', 'nama' => 'Azalea 3B'],
            ['desa' => 'Ngaglik', 'nama' => 'Azalea 4'],
            ['desa' => 'Ngaglik', 'nama' => 'Azalea 5'],
            ['desa' => 'Ngaglik', 'nama' => 'Azalea 6'],
            ['desa' => 'Ngaglik', 'nama' => 'Azalea 7'],
            ['desa' => 'Ngaglik', 'nama' => 'Azalea 8'],
            ['desa' => 'Ngaglik', 'nama' => 'Azalea 9'],
            ['desa' => 'Ngaglik', 'nama' => 'Azalea 10'],
            ['desa' => 'Ngaglik', 'nama' => 'Azalea 11'],
            ['desa' => 'Ngaglik', 'nama' => 'Azalea 12'],
            ['desa' => 'Ngaglik', 'nama' => 'Azalea 13'],
            ['desa' => 'Ngaglik', 'nama' => 'Azalea 14'],
            ['desa' => 'Ngaglik', 'nama' => 'Azalea 15'],

            ['desa' => 'Pesanggrahan', 'nama' => 'Kelengkeng 1'],
            ['desa' => 'Pesanggrahan', 'nama' => 'Kelengkeng 2'],
            ['desa' => 'Pesanggrahan', 'nama' => 'Mawar'],
            ['desa' => 'Pesanggrahan', 'nama' => 'Anggrek'],
            ['desa' => 'Pesanggrahan', 'nama' => 'Gladiol'],
            ['desa' => 'Pesanggrahan', 'nama' => 'Seruni'],
            ['desa' => 'Pesanggrahan', 'nama' => 'Elbra'],
            ['desa' => 'Pesanggrahan', 'nama' => 'Bougenville'],
            ['desa' => 'Pesanggrahan', 'nama' => 'Flamboyan'],
            ['desa' => 'Pesanggrahan', 'nama' => 'Lely'],
            ['desa' => 'Pesanggrahan', 'nama' => 'Melati'],
            ['desa' => 'Pesanggrahan', 'nama' => 'Cempaka'],
            ['desa' => 'Pesanggrahan', 'nama' => 'Teratai'],

            ['desa' => 'Songgokerto', 'nama' => 'Harmoni 1'],
            ['desa' => 'Songgokerto', 'nama' => 'Harmoni 2'],
            ['desa' => 'Songgokerto', 'nama' => 'Harmoni 3'],
            ['desa' => 'Songgokerto', 'nama' => 'Harmoni 4'],
            ['desa' => 'Songgokerto', 'nama' => 'Harmoni 5'],
            ['desa' => 'Songgokerto', 'nama' => 'Harmoni 6'],
            ['desa' => 'Songgokerto', 'nama' => 'Harmoni 7'],
            ['desa' => 'Songgokerto', 'nama' => 'Harmoni 8'],
            ['desa' => 'Songgokerto', 'nama' => 'Harmoni 9'],

            ['desa' => 'Sumberejo', 'nama' => 'Anggrek 1'],
            ['desa' => 'Sumberejo', 'nama' => 'Anggrek 2'],
            ['desa' => 'Sumberejo', 'nama' => 'Anggrek 3'],
            ['desa' => 'Sumberejo', 'nama' => 'Anggrek 4'],
            ['desa' => 'Sumberejo', 'nama' => 'Anggrek 5'],
            ['desa' => 'Sumberejo', 'nama' => 'Anggrek 6'],
        ];

        foreach ($data as &$row) {
            $row['created_at'] = $now;
            $row['updated_at'] = $now;
        }

        DB::table('posyandu')->insert($data);
    }
}
