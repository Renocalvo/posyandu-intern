<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\LoginController;
use App\Http\Controllers\Api\PosyanduController;
use App\Http\Controllers\Api\AnakController;
use App\Http\Controllers\Api\AnakPengukuranController;
use App\Http\Controllers\Api\LogPengukuranController;

Route::post('/login', [LoginController::class, 'login']);

Route::middleware(['auth:sanctum'])->group(function () {

    Route::post('/logout', [LoginController::class, 'logout']);

    // Users
    Route::apiResource('/users', UserController::class);

    // Posyandu & Anak
    Route::apiResource('/posyandu', PosyanduController::class);
    Route::get('/anak/nik/{nik}', [AnakController::class, 'showByNik']);
    Route::apiResource('/anak', AnakController::class);

    // Anak Pengukuran
    Route::get   ('/anak-pengukuran', [AnakPengukuranController::class, 'index']);
    Route::post  ('/anak-pengukuran', [AnakPengukuranController::class, 'store']);
    Route::get   ('/anak-pengukuran/{nik_anak}', [AnakPengukuranController::class, 'showByNik']);
    Route::patch ('/anak-pengukuran/{nik_anak}', [AnakPengukuranController::class, 'updateByNik']);
    Route::delete('/anak-pengukuran/{nik_anak}', [AnakPengukuranController::class, 'destroyByNik']);

    // Log Pengukuran
    Route::get   ('/log-pengukuran', [LogPengukuranController::class, 'index']);
    Route::post  ('/log-pengukuran', [LogPengukuranController::class, 'store']);
    Route::get   ('/log-pengukuran/{id}', [LogPengukuranController::class, 'show']);
    Route::delete('/log-pengukuran/{id}', [LogPengukuranController::class, 'destroy']);
    Route::get   ('/log-pengukuran/nik/{nik}', [LogPengukuranController::class, 'byNik']);
});
