<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\RegionController;

// Wilayah Public Routes
Route::prefix('wilayah')->group(function () {
    Route::get('/provinces', [RegionController::class, 'provinces']);
    Route::get('/regencies/{code}', [RegionController::class, 'regencies']);
    Route::get('/districts/{code}', [RegionController::class, 'districts']);
    Route::get('/villages/{code}', [RegionController::class, 'villages']);
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');