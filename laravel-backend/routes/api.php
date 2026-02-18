<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\LoginController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\PosyanduController;
use App\Http\Controllers\Api\AnakController;
use App\Http\Controllers\Api\AnakPengukuranController;
use App\Http\Controllers\Api\LogPengukuranController;

Route::post('/login', [LoginController::class, 'login']);

Route::middleware(['auth:sanctum'])->group(function () {

    Route::post('/logout', [LoginController::class, 'logout']);

    // ==================== USERS ====================
    Route::apiResource('/users', UserController::class);

    // ==================== POSYANDU ====================
    Route::apiResource('/posyandu', PosyanduController::class);

    // ==================== ANAK ====================
    // Route spesifik harus di atas route generic
    Route::get('/anak/nik/{nik}', [AnakController::class, 'showByNik']);
    Route::apiResource('/anak', AnakController::class);

    // ==================== ANAK PENGUKURAN ====================
    // Route spesifik harus di atas route generic untuk menghindari konflik
    Route::get('/anak-pengukuran/by-nik/{nik}', [AnakPengukuranController::class, 'showByNik']);
    Route::delete('/anak-pengukuran/by-nik/{nik}', [AnakPengukuranController::class, 'destroyByNik']);
    
    // Route standard CRUD
    Route::get('/anak-pengukuran', [AnakPengukuranController::class, 'index']);
    Route::post('/anak-pengukuran', [AnakPengukuranController::class, 'store']);
    Route::get('/anak-pengukuran/{id}', [AnakPengukuranController::class, 'show']);
    Route::patch('/anak-pengukuran/{id}', [AnakPengukuranController::class, 'update']);
    Route::put('/anak-pengukuran/{id}', [AnakPengukuranController::class, 'update']);
    Route::delete('/anak-pengukuran/{id}', [AnakPengukuranController::class, 'destroy']);

    // ==================== LOG PENGUKURAN ====================
    // Route spesifik harus di atas route generic
    Route::get('/log-pengukuran/nik/{nik}', [LogPengukuranController::class, 'byNik']);
    Route::get('/log-pengukuran/anak/{anak_id}', [LogPengukuranController::class, 'byAnakId']);
    Route::delete('/log-pengukuran/nik/{nik}', [LogPengukuranController::class, 'destroyByNik']);
    
    // Route standard CRUD
    Route::get('/log-pengukuran', [LogPengukuranController::class, 'index']);
    Route::post('/log-pengukuran', [LogPengukuranController::class, 'store']);
    Route::get('/log-pengukuran/{id}', [LogPengukuranController::class, 'show']);
    Route::patch('/log-pengukuran/{id}', [LogPengukuranController::class, 'update']);
    Route::put('/log-pengukuran/{id}', [LogPengukuranController::class, 'update']);
    Route::delete('/log-pengukuran/{id}', [LogPengukuranController::class, 'destroy']);
});

/*
 * =====================================================
 * RINGKASAN ENDPOINT API
 * =====================================================
 * 
 * ANAK PENGUKURAN:
 * - GET    /api/anak-pengukuran                    → List semua (filter: ?anak_id=1 atau ?nik=xxx)
 * - POST   /api/anak-pengukuran                    → Create/Update otomatis + log
 * - GET    /api/anak-pengukuran/{id}               → Detail by ID
 * - GET    /api/anak-pengukuran/by-nik/{nik}       → Detail by NIK
 * - PATCH  /api/anak-pengukuran/{id}               → Update manual + log
 * - DELETE /api/anak-pengukuran/{id}               → Hapus by ID
 * - DELETE /api/anak-pengukuran/by-nik/{nik}       → Hapus semua by NIK
 * 
 * LOG PENGUKURAN:
 * - GET    /api/log-pengukuran                     → List semua log (filter: ?nik=xxx atau ?anak_id=1)
 * - POST   /api/log-pengukuran                     → Create log manual
 * - GET    /api/log-pengukuran/{id}                → Detail log by ID
 * - GET    /api/log-pengukuran/nik/{nik}           → List log by NIK
 * - GET    /api/log-pengukuran/anak/{anak_id}      → List log by anak_id
 * - PATCH  /api/log-pengukuran/{id}                → Update log
 * - DELETE /api/log-pengukuran/{id}                → Hapus log by ID
 * - DELETE /api/log-pengukuran/nik/{nik}           → Hapus semua log by NIK
 * 
 * =====================================================
 */