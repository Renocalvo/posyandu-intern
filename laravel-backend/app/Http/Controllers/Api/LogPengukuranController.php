<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LogPengukuran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\Resource;

class LogPengukuranController extends Controller
{
    public function index()
    {
        $log = LogPengukuran::with(['anak','posyandu'])
            ->orderByDesc('diubah_pada')
            ->get();

        return new Resource(true, 'Daftar Log Pengukuran', $log);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nik_log'            => 'required|exists:anak,nik',
            'posyandu_id_lama'   => 'nullable|exists:posyandu,id',
            'tanggal_ukur_lama'  => 'required|date',

            'berat_lama'         => 'nullable|numeric',
            'tinggi_lama'        => 'nullable|numeric',
            'lila_lama'          => 'nullable|numeric',
            'lingkar_kepala_lama'=> 'nullable|numeric',

            'cara_ukur_lama'     => 'nullable|string',
            'vit_a_lama'         => 'nullable|string',

            'asi_bulan_0_lama'   => 'nullable|boolean',
            'asi_bulan_1_lama'   => 'nullable|boolean',
            'asi_bulan_2_lama'   => 'nullable|boolean',
            'asi_bulan_3_lama'   => 'nullable|boolean',
            'asi_bulan_4_lama'   => 'nullable|boolean',
            'asi_bulan_5_lama'   => 'nullable|boolean',
            'asi_bulan_6_lama'   => 'nullable|boolean',

            'kelas_ibu_balita_lama' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $data = $validator->validated();
        $data['diubah_pada'] = now();

        $log = LogPengukuran::create($data);

        return new Resource(true, 'Log pengukuran berhasil ditambahkan', $log);
    }

    public function show($id)
    {
        $log = LogPengukuran::with(['anak','posyandu'])->find($id);

        if (!$log) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        return new Resource(true, 'Detail Log Pengukuran', $log);
    }

    public function destroy($id)
    {
        $log = LogPengukuran::find($id);

        if (!$log) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        $log->delete();

        return new Resource(true, 'Log berhasil dihapus', null);
    }

    public function byNik($nik)
    {
        $log = LogPengukuran::with(['anak','posyandu'])
            ->where('nik_log', $nik)
            ->orderBy('tanggal_ukur_lama', 'desc')
            ->get();

        if ($log->isEmpty()) {
            return response()->json(['message' => 'Tidak ada log untuk anak dengan NIK tersebut'], 404);
        }

        return new Resource(true, 'Log pengukuran berdasarkan NIK', $log);
    }
}
