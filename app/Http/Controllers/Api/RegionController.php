<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class RegionController extends Controller
{
    private $baseUrl = 'https://wilayah.id/api';

    public function getRegionData($type, $code = null)
    {
        $filename = $code ? "{$type}_{$code}.json" : "{$type}.json";
        $endpoint = $code ? "{$type}/{$code}.json" : "{$type}.json";
        $storagePath = "wilayah/{$filename}";
        $cacheKey = $code ? "wilayah_{$type}_{$code}" : "wilayah_{$type}";

        return Cache::remember($cacheKey, 3600, function () use ($storagePath, $endpoint) {
            
            // 1. LOCAL HIT
            if (Storage::disk('public')->exists($storagePath)) {
                $content = Storage::disk('public')->get($storagePath);
                return json_decode($content, true);
            }

            // 2. CDN / GITHUB PAGES
            $cdnUrl = env('WILAYAH_BASE_URL');
            if ($cdnUrl && !str_contains($cdnUrl, 'localhost') && !str_contains($cdnUrl, '127.0.0.1')) {
                Log::info("[CDN FETCH] Mencoba download dari CDN: $endpoint");
                $publicData = $this->tryFetchUrl($cdnUrl, $endpoint);
                
                if ($publicData) {
                    $this->saveToLocal($storagePath, $publicData);
                    return $publicData;
                }
            }

            // 3. LIVE API
            Log::warning("[LIVE FETCH] CDN Gagal/Kosong. Mencoba Live API...");
            $liveData = $this->fetchLive("{$this->baseUrl}/{$endpoint}");
            
            if ($liveData) {
                $this->saveToLocal($storagePath, $liveData);
                return $liveData;
            }

            Log::error("[FAILURE] Semua metode gagal untuk: $endpoint");
            return response()->json(['error' => 'Service unavailable'], 503);
        });
    }

    private function saveToLocal($path, $data)
    {
        if (!Storage::disk('public')->exists('wilayah')) {
            Storage::disk('public')->makeDirectory('wilayah');
        }
        Storage::disk('public')->put($path, json_encode($data, JSON_PRETTY_PRINT));
        Log::info("[SAVED] File berhasil disimpan ke: $path");
    }

    private function tryFetchUrl(string $baseUrl, string $endpoint, int $timeout = 5)
    {
        try {
            $url = rtrim($baseUrl, '/') . '/' . ltrim($endpoint, '/');
            $resp = Http::timeout($timeout)->get($url);
            
            if ($resp->successful()) {
                return $resp->json();
            }
        } catch (\Throwable $e) {
            Log::warning("[FETCH ERROR] Gagal ambil dari $baseUrl: " . $e->getMessage());
        }
        return null;
    }

    private function fetchLive(string $fullUrl, int $timeout = 10)
    {
        try {
            $verify = file_exists(storage_path('app/certs/cacert.pem')) 
                ? storage_path('app/certs/cacert.pem') 
                : false;

            $resp = Http::withOptions(['verify' => $verify])
                        ->timeout($timeout)
                        ->get($fullUrl);
            
            if ($resp->successful()) return $resp->json();
        } catch (\Throwable $e) {
            Log::error("[LIVE ERROR] " . $e->getMessage());
        }
        return null;
    }

    public function provinces() { return $this->getRegionData('provinces'); }
    public function regencies($code) { return $this->getRegionData('regencies', $code); }
    public function districts($code) { return $this->getRegionData('districts', $code); }
    public function villages($code) { return $this->getRegionData('villages', $code); }
}