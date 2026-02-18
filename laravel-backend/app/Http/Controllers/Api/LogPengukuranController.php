<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LogPengukuran;
use App\Models\Anak;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\Resource;

class LogPengukuranController extends Controller
{
    /**
     * GET /api/log-pengukuran
     * List semua log pengukuran
     * Optional: ?nik=xxx untuk filter berdasarkan NIK
     */
    public function index(Request $request)
    {
        $query = LogPengukuran::with(['anak', 'posyandu']);

        // Filter by NIK jika ada parameter
        if ($request->filled('nik')) {
            $query->where('nik_log', $request->nik);
        }

        // Filter by anak_id jika ada parameter
        if ($request->filled('anak_id')) {
            $query->where('anak_id', $request->anak_id);
        }

        $log = $query->orderByDesc('diubah_pada')->get();

        return new Resource(true, 'Daftar Log Pengukuran', $log);
    }

    /**
     * POST /api/log-pengukuran
     * Manual store log (jarang dipakai, biasanya otomatis dari update)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'anak_id'            => 'nullable|exists:anak,id',
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

        // Jika anak_id tidak ada, cari berdasarkan nik_log
        if (!isset($data['anak_id'])) {
            $anak = Anak::where('nik', $data['nik_log'])->first();
            if ($anak) {
                $data['anak_id'] = $anak->id;
            }
        }

        $log = LogPengukuran::create($data);

        return new Resource(true, 'Log pengukuran berhasil ditambahkan', $log);
    }

    /**
     * GET /api/log-pengukuran/{id}
     * Detail 1 log berdasarkan ID
     */
    public function show($id)
    {
        $log = LogPengukuran::with(['anak', 'posyandu'])->find($id);

        if (!$log) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        return new Resource(true, 'Detail Log Pengukuran', $log);
    }

    /**
     * PUT/PATCH /api/log-pengukuran/{id}
     * Update log (jarang dipakai, karena log biasanya immutable)
     */
    public function update(Request $request, $id)
    {
        $log = LogPengukuran::find($id);

        if (!$log) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        $validator = Validator::make($request->all(), [
            'anak_id'            => 'nullable|exists:anak,id',
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
        
        // Jika anak_id tidak ada, cari berdasarkan nik_log
        if (!isset($data['anak_id'])) {
            $anak = Anak::where('nik', $data['nik_log'])->first();
            if ($anak) {
                $data['anak_id'] = $anak->id;
            }
        }

        $log->update($data);

        return new Resource(true, 'Log pengukuran berhasil diperbarui', $log);
    }

    /**
     * DELETE /api/log-pengukuran/{id}
     * Hapus 1 log berdasarkan ID
     */
    public function destroy($id)
    {
        $log = LogPengukuran::find($id);

        if (!$log) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        $log->delete();

        return new Resource(true, 'Log berhasil dihapus', null);
    }

    /**
     * GET /api/log-pengukuran/nik/{nik}
     * Ambil semua log untuk NIK tertentu
     */
    public function byNik($nik)
    {
        $log = LogPengukuran::with(['anak', 'posyandu'])
            ->where('nik_log', $nik)
            ->orderBy('diubah_pada', 'desc')
            ->get();

        if ($log->isEmpty()) {
            return response()->json([
                'message' => 'Tidak ada log untuk anak dengan NIK tersebut'
            ], 404);
        }

        return new Resource(true, 'Log pengukuran berdasarkan NIK', $log);
    }

    /**
     * GET /api/log-pengukuran/anak/{anak_id}
     * Ambil semua log untuk anak_id tertentu
     */
    public function byAnakId($anak_id)
    {
        $anak = Anak::find($anak_id);

        if (!$anak) {
            return response()->json([
                'message' => 'Anak tidak ditemukan'
            ], 404);
        }

        $log = LogPengukuran::with(['anak', 'posyandu'])
            ->where('anak_id', $anak_id)
            ->orderBy('diubah_pada', 'desc')
            ->get();

        return new Resource(true, 'Log pengukuran berdasarkan Anak ID', $log);
    }

    /**
     * DELETE /api/log-pengukuran/nik/{nik}
     * Hapus semua log untuk NIK tertentu
     */
    public function destroyByNik($nik)
    {
        $count = LogPengukuran::where('nik_log', $nik)->delete();

        if ($count === 0) {
            return response()->json([
                'message' => 'Tidak ada log untuk NIK tersebut'
            ], 404);
        }

        return new Resource(true, "Berhasil menghapus {$count} log pengukuran", null);
    }
}