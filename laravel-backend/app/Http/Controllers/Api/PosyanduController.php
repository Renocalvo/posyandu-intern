<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Posyandu;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\Resource;

class PosyanduController extends Controller
{
    // GET /api/posyandu
    public function index()
    {
        $data = Posyandu::all();
        return new Resource(true, 'Daftar Seluruh Posyandu', $data);
    }

    // POST /api/posyandu
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'desa'  => 'required|string',
            'nama'  => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $posyandu = Posyandu::create($request->all());

        return new Resource(true, 'Posyandu berhasil ditambahkan', $posyandu);
    }

    // GET /api/posyandu/{id}
    public function show($id)
    {
        $posyandu = Posyandu::find($id);
        if (!$posyandu) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        return new Resource(true, 'Detail Posyandu', $posyandu);
    }

    // PUT /api/posyandu/{id}
    public function update(Request $request, $id)
    {
        $posyandu = Posyandu::find($id);
        if (!$posyandu) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        $validator = Validator::make($request->all(), [
            'desa'  => 'required|string',
            'nama'  => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $posyandu->update($request->all());

        return new Resource(true, 'Posyandu berhasil diperbarui', $posyandu);
    }

    // DELETE /api/posyandu/{id}
    public function destroy($id)
    {
        $posyandu = Posyandu::find($id);
        if (!$posyandu) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        $posyandu->delete();

        return new Resource(true, 'Posyandu berhasil dihapus', null);
    }
}
