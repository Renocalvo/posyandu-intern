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
     * Optional: ?anak_id=1
     */
    public function index(Request $request)
    {
        $q = AnakPengukuran::with(['anak.posyandu', 'posyandu']);

        if ($request->filled('anak_id')) {
            $q->where('anak_id', $request->anak_id);
        }

        $data = $q->orderBy('tanggal_ukur', 'desc')->get();
        return new Resource(true, 'Data Pengukuran Anak', $data);
    }

    /**
     * GET /api/anak-pengukuran/{id}
     * Detail 1 pengukuran
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
     * POST /api/anak-pengukuran
     * Create pengukuran (1 anak bisa banyak)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'anak_id'          => 'required|exists:anak,id',
            'tanggal_ukur'     => 'required|date',
            'posyandu_id'      => 'nullable|exists:posyandu,id',
            'berat'            => 'nullable|numeric',
            'tinggi'           => 'nullable|numeric',
            'lila'             => 'nullable|numeric',
            'lingkar_kepala'   => 'nullable|numeric',
            'cara_ukur'        => 'nullable|in:terlentang,berdiri,Terlentang,Berdiri',
            'vit_a'            => 'nullable|string',
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
            $created = AnakPengukuran::create($request->all());
            $created->load(['anak.posyandu', 'posyandu']);

            return new Resource(true, 'Pengukuran berhasil ditambahkan', $created);
        });
    }

    /**
     * PUT /api/anak-pengukuran/{id}
     * Update pengukuran + simpan log
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
            'berat'            => 'nullable|numeric',
            'tinggi'           => 'nullable|numeric',
            'lila'             => 'nullable|numeric',
            'lingkar_kepala'   => 'nullable|numeric',
            'cara_ukur'        => 'nullable|in:terlentang,berdiri,Terlentang,Berdiri',
            'vit_a'            => 'nullable|string',
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
            LogPengukuran::create([
                'anak_id'                => $row->anak_id,
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
            ]);

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

    public function destroyByNik($nik)
    {
        $anak = Anak::where('nik', $nik)->first();

        if (!$anak) {
            return response()->json(['message' => 'Anak tidak ditemukan'], 404);
        }

        AnakPengukuran::where('anak_id', $anak->id)->delete();

        return new Resource(true, 'Semua data pengukuran anak berhasil dihapus', null);
    }

    public function updateByNik(Request $request, $nik)
    {
        $anak = Anak::where('nik', $nik)->first();

        if (!$anak) {
            return response()->json([
                'message' => 'Anak tidak ditemukan'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'tanggal' => 'required|date',
            'berat_badan' => 'nullable|numeric',
            'tinggi_badan' => 'nullable|numeric',
            'lingkar_kepala' => 'nullable|numeric',
            'vit_a' => 'nullable|boolean',
            'catatan' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $pengukuran = AnakPengukuran::where('anak_id', $anak->id)
            ->where('tanggal', $request->tanggal)
            ->first();

        if (!$pengukuran) {
            return response()->json([
                'message' => 'Data pengukuran tidak ditemukan'
            ], 404);
        }

        $pengukuran->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Data pengukuran berhasil diperbarui',
            'data' => $pengukuran
        ]);
    }

    public function showByNik($nik)
    {
        $anak = Anak::with('pengukuran')
            ->where('nik', $nik)
            ->first();

        if (!$anak) {
            return response()->json([
                'message' => 'Anak tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $anak
        ]);
    }
}
