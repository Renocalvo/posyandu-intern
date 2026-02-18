<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Anak;
use App\Models\AnakPengukuran;
use App\Models\LogPengukuran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\Resource;

class AnakPengukuranController extends Controller
{
    /**
     * GET /api/anak-pengukuran
     * Optional: ?anak_id=1 atau ?nik=1234567890123456
     */
    public function index(Request $request)
    {
        $q = AnakPengukuran::with(['anak.posyandu', 'posyandu']);

        if ($request->filled('anak_id')) {
            $q->where('anak_id', $request->anak_id);
        }

        if ($request->filled('nik')) {
            $anak = Anak::where('nik', $request->nik)->first();
            if ($anak) {
                $q->where('anak_id', $anak->id);
            }
        }

        $data = $q->orderBy('tanggal_ukur', 'desc')->get();
        return new Resource(true, 'Data Pengukuran Anak', $data);
    }

    /**
     * GET /api/anak-pengukuran/{id}
     * Detail 1 pengukuran berdasarkan ID
     */
    public function show($id)
    {
        $row = AnakPengukuran::with(['anak.posyandu', 'posyandu'])->find($id);
        if (!$row) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        return new Resource(true, 'Detail Pengukuran Anak', $row);
    }

    /**
     * GET /api/anak-pengukuran/by-nik/{nik}
     * Ambil semua pengukuran berdasarkan NIK
     */
    public function showByNik($nik)
    {
        $anak = Anak::with('pengukuran.posyandu')
            ->where('nik', $nik)
            ->first();

        if (!$anak) {
            return response()->json([
                'message' => 'Anak tidak ditemukan'
            ], 404);
        }

        return new Resource(true, 'Data Pengukuran Anak', $anak);
    }

    /**
     * POST /api/anak-pengukuran
     * Create atau Update pengukuran
     * Jika sudah ada data untuk anak_id tersebut, akan update + simpan log
     * Jika belum ada, akan create baru
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'anak_id'          => 'required|exists:anak,id',
            'tanggal_ukur'     => 'required|date',
            'posyandu_id'      => 'nullable|exists:posyandu,id',
            'berat'            => 'required|numeric',
            'tinggi'           => 'required|numeric',
            'lila'             => 'nullable|numeric',
            'lingkar_kepala'   => 'nullable|numeric',
            'cara_ukur'        => 'required|in:Terlentang,Berdiri',
            'vit_a'            => 'nullable|in:Biru,Merah',
            'asi_bulan_0'      => 'nullable|boolean',
            'asi_bulan_1'      => 'nullable|boolean',
            'asi_bulan_2'      => 'nullable|boolean',
            'asi_bulan_3'      => 'nullable|boolean',
            'asi_bulan_4'      => 'nullable|boolean',
            'asi_bulan_5'      => 'nullable|boolean',
            'asi_bulan_6'      => 'nullable|boolean',
            'kelas_ibu_balita' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        return DB::transaction(function () use ($request) {
            $anakId = $request->anak_id;
            
            // Cek apakah sudah ada data pengukuran untuk anak ini
            $existing = AnakPengukuran::where('anak_id', $anakId)->first();

            if ($existing) {
                // Ada data lama - simpan ke log dulu
                $anak = Anak::find($anakId);
                
                LogPengukuran::create([
                    'anak_id'                => $existing->anak_id,
                    'nik_log'                => $anak->nik,
                    'posyandu_id_lama'       => $existing->posyandu_id,
                    'tanggal_ukur_lama'      => $existing->tanggal_ukur,
                    'berat_lama'             => $existing->berat,
                    'tinggi_lama'            => $existing->tinggi,
                    'lila_lama'              => $existing->lila,
                    'lingkar_kepala_lama'    => $existing->lingkar_kepala,
                    'cara_ukur_lama'         => $existing->cara_ukur,
                    'vit_a_lama'             => $existing->vit_a,
                    'asi_bulan_0_lama'       => $existing->asi_bulan_0,
                    'asi_bulan_1_lama'       => $existing->asi_bulan_1,
                    'asi_bulan_2_lama'       => $existing->asi_bulan_2,
                    'asi_bulan_3_lama'       => $existing->asi_bulan_3,
                    'asi_bulan_4_lama'       => $existing->asi_bulan_4,
                    'asi_bulan_5_lama'       => $existing->asi_bulan_5,
                    'asi_bulan_6_lama'       => $existing->asi_bulan_6,
                    'kelas_ibu_balita_lama'  => $existing->kelas_ibu_balita,
                    'diubah_pada'            => now(),
                ]);

                // Update dengan data baru
                $existing->update($request->all());
                $existing->load(['anak.posyandu', 'posyandu']);

                return new Resource(true, 'Pengukuran berhasil diperbarui (data lama tersimpan di log)', $existing);
            }

            // Tidak ada data lama - create baru
            $created = AnakPengukuran::create($request->all());
            $created->load(['anak.posyandu', 'posyandu']);

            return new Resource(true, 'Pengukuran berhasil ditambahkan', $created);
        });
    }

    /**
     * PATCH /api/anak-pengukuran/{id}
     * Update pengukuran berdasarkan ID + simpan log
     */
    public function update(Request $request, $id)
    {
        $row = AnakPengukuran::find($id);
        if (!$row) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        $validator = Validator::make($request->all(), [
            'tanggal_ukur'     => 'required|date',
            'posyandu_id'      => 'nullable|exists:posyandu,id',
            'berat'            => 'required|numeric',
            'tinggi'           => 'required|numeric',
            'lila'             => 'nullable|numeric',
            'lingkar_kepala'   => 'nullable|numeric',
            'cara_ukur'        => 'required|in:Terlentang,Berdiri',
            'vit_a'            => 'nullable|in:Biru,Merah',
            'asi_bulan_0'      => 'nullable|boolean',
            'asi_bulan_1'      => 'nullable|boolean',
            'asi_bulan_2'      => 'nullable|boolean',
            'asi_bulan_3'      => 'nullable|boolean',
            'asi_bulan_4'      => 'nullable|boolean',
            'asi_bulan_5'      => 'nullable|boolean',
            'asi_bulan_6'      => 'nullable|boolean',
            'kelas_ibu_balita' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        return DB::transaction(function () use ($row, $request) {
            $anak = Anak::find($row->anak_id);
            
            // Simpan data lama ke log
            LogPengukuran::create([
                'anak_id'                => $row->anak_id,
                'nik_log'                => $anak->nik,
                'posyandu_id_lama'       => $row->posyandu_id,
                'tanggal_ukur_lama'      => $row->tanggal_ukur,
                'berat_lama'             => $row->berat,
                'tinggi_lama'            => $row->tinggi,
                'lila_lama'              => $row->lila,
                'lingkar_kepala_lama'    => $row->lingkar_kepala,
                'cara_ukur_lama'         => $row->cara_ukur,
                'vit_a_lama'             => $row->vit_a,
                'asi_bulan_0_lama'       => $row->asi_bulan_0,
                'asi_bulan_1_lama'       => $row->asi_bulan_1,
                'asi_bulan_2_lama'       => $row->asi_bulan_2,
                'asi_bulan_3_lama'       => $row->asi_bulan_3,
                'asi_bulan_4_lama'       => $row->asi_bulan_4,
                'asi_bulan_5_lama'       => $row->asi_bulan_5,
                'asi_bulan_6_lama'       => $row->asi_bulan_6,
                'kelas_ibu_balita_lama'  => $row->kelas_ibu_balita,
                'diubah_pada'            => now(),
            ]);

            // Update dengan data baru
            $row->update($request->all());
            $row->load(['anak.posyandu', 'posyandu']);

            return new Resource(true, 'Pengukuran berhasil diperbarui (log tersimpan)', $row);
        });
    }

    /**
     * DELETE /api/anak-pengukuran/{id}
     */
    public function destroy($id)
    {
        $row = AnakPengukuran::find($id);
        if (!$row) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        $row->delete();
        return new Resource(true, 'Pengukuran berhasil dihapus', null);
    }

    /**
     * DELETE /api/anak-pengukuran/by-nik/{nik}
     * Hapus semua pengukuran berdasarkan NIK
     */
    public function destroyByNik($nik)
    {
        $anak = Anak::where('nik', $nik)->first();

        if (!$anak) {
            return response()->json(['message' => 'Anak tidak ditemukan'], 404);
        }

        AnakPengukuran::where('anak_id', $anak->id)->delete();

        return new Resource(true, 'Semua data pengukuran anak berhasil dihapus', null);
    }
}