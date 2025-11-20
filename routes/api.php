<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\RegionController;
use App\Http\Controllers\Api\SellerAuthController;

// Wilayah Public Routes
Route::prefix('wilayah')->group(function () {
    Route::get('/provinces', [RegionController::class, 'provinces']);
    Route::get('/regencies/{code}', [RegionController::class, 'regencies']);
    Route::get('/districts/{code}', [RegionController::class, 'districts']);
    Route::get('/villages/{code}', [RegionController::class, 'villages']);
});

// Authentication Routes
Route::post('/register-seller', [SellerAuthController::class, 'register']);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');