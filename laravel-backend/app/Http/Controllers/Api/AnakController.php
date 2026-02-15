<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Anak;
use App\Http\Resources\Resource;

class AnakController extends Controller
{
    public function index()
    {
        $anak = Anak::with('posyandu')->get();
        return new Resource(true, 'Data Semua Anak', $anak);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nik' => 'required|string|unique:anak,nik',
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

    public function show($id)
    {
        $anak = Anak::with('posyandu')->find($id);

        if (!$anak) {
            return response()->json(['message' => 'Data anak tidak ditemukan'], 404);
        }

        return new Resource(true, 'Detail Anak', $anak);
    }

    public function update(Request $request, $id)
    {
        $anak = Anak::find($id);

        if (!$anak) {
            return response()->json(['message' => 'Data anak tidak ditemukan'], 404);
        }

        $validator = Validator::make($request->all(), [
            'nik' => 'required|string|unique:anak,nik,' . $anak->id,
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

    public function destroy($id)
    {
        $anak = Anak::find($id);

        if (!$anak) {
            return response()->json(['message' => 'Data anak tidak ditemukan'], 404);
        }

        $anak->delete();

        return new Resource(true, 'Data Anak berhasil dihapus', null);
    }

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
