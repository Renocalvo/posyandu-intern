<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Anak;
use App\Http\Resources\Resource;

class AnakController extends Controller
{
    /**
     * GET /api/anak
     * List semua anak, dengan optional search
     */
    public function index(Request $request)
    {
        $query = Anak::with('posyandu');

        // Support search parameter untuk typeahead
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nik', 'like', '%' . $search . '%')
                  ->orWhere('nama_anak', 'like', '%' . $search . '%');
            });
        }

        // Support pagination
        if ($request->has('page')) {
            $anak = $query->paginate($request->get('per_page', 15));
        } else {
            $anak = $query->get();
        }

        return new Resource(true, 'Data Semua Anak', $anak);
    }

    /**
     * POST /api/anak
     * Create anak baru
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nik' => 'required|string|unique:anak,nik|min:13|max:16',
            'anak_ke' => 'nullable|integer',
            'tgl_lahir' => 'required|date',
            'jenis_kelamin' => 'required|in:L,P',
            'nomor_KK' => 'nullable|string',
            'nama_anak' => 'required|string',
            'usia_hamil' => 'nullable|integer',
            'berat_lahir' => 'nullable|numeric',
            'panjang_lahir' => 'nullable|numeric',
            'lingkar_kepala_lahir' => 'nullable|numeric',
            'kia' => 'nullable|boolean',
            'kia_bayi_kecil' => 'nullable|boolean',
            'imd' => 'nullable|boolean',
            'nama_ortu' => 'nullable|string',
            'nik_ortu' => 'nullable|string',
            'hp_ortu' => 'nullable|string',
            'posyandu_id' => 'nullable|exists:posyandu,id',
            'rt' => 'nullable|string',
            'rw' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $anak = Anak::create($request->all());

        return new Resource(true, 'Data Anak berhasil ditambahkan', $anak);
    }

    /**
     * GET /api/anak/{id}
     * Detail anak berdasarkan ID
     */
    public function show($id)
    {
        $anak = Anak::with('posyandu')->find($id);

        if (!$anak) {
            return response()->json(['message' => 'Data anak tidak ditemukan'], 404);
        }

        return new Resource(true, 'Detail Anak', $anak);
    }

    /**
     * PUT/PATCH /api/anak/{id}
     * Update anak berdasarkan ID
     */
    public function update(Request $request, $id)
    {
        $anak = Anak::find($id);

        if (!$anak) {
            return response()->json(['message' => 'Data anak tidak ditemukan'], 404);
        }

        $validator = Validator::make($request->all(), [
            'nik' => 'required|string|unique:anak,nik,' . $anak->id . '|min:13|max:16',
            'anak_ke' => 'nullable|integer',
            'tgl_lahir' => 'required|date',
            'jenis_kelamin' => 'required|in:L,P',
            'nomor_KK' => 'nullable|string',
            'nama_anak' => 'required|string',
            'usia_hamil' => 'nullable|integer',
            'berat_lahir' => 'nullable|numeric',
            'panjang_lahir' => 'nullable|numeric',
            'lingkar_kepala_lahir' => 'nullable|numeric',
            'kia' => 'nullable|boolean',
            'kia_bayi_kecil' => 'nullable|boolean',
            'imd' => 'nullable|boolean',
            'nama_ortu' => 'nullable|string',
            'nik_ortu' => 'nullable|string',
            'hp_ortu' => 'nullable|string',
            'posyandu_id' => 'nullable|exists:posyandu,id',
            'rt' => 'nullable|string',
            'rw' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $anak->update($request->all());

        return new Resource(true, 'Data Anak berhasil diperbarui', $anak);
    }

    /**
     * DELETE /api/anak/{id}
     * Hapus anak berdasarkan ID
     */
    public function destroy($id)
    {
        $anak = Anak::find($id);

        if (!$anak) {
            return response()->json(['message' => 'Data anak tidak ditemukan'], 404);
        }

        $anak->delete();

        return new Resource(true, 'Data Anak berhasil dihapus', null);
    }

    /**
     * GET /api/anak/nik/{nik}
     * Cari anak berdasarkan NIK
     */
    public function showByNik($nik)
    {
        $anak = Anak::with('posyandu')
            ->where('nik', $nik)
            ->first();

        if (!$anak) {
            return response()->json(['message' => 'Data anak tidak ditemukan'], 404);
        }

        return new Resource(true, 'Detail Anak', $anak);
    }
}