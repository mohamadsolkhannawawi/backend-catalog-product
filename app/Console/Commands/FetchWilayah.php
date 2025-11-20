<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class FetchWilayah extends Command
{
    protected $signature = 'wilayah:fetch
        {--all : Fetch provinces, regencies, districts and villages recursively}
        {--province= : Fetch regencies (and nested) for given PROVINCE_CODE}
        {--regency= : Fetch districts (and nested) for given REGENCY_CODE}
        {--district= : Fetch villages for given DISTRICT_CODE}
        {--force : Force download even if file exists}';

    protected $description = 'Download wilayah data with Smart Resume capability (Skip existing files)';

    private $baseUrl = 'https://wilayah.id/api';
    private $verifySSL; 

    private function safeGet(string $url, int $timeout = 30, int $retries = 3)
    {
        $attempt = 0;
        $wait = 500000; // 0.5 detik
        
        while ($attempt < $retries) {
            try {
                $resp = Http::withOptions(['verify' => $this->verifySSL])
                            ->timeout($timeout)
                            ->get($url);
                
                if ($resp->successful()) {
                    return $resp;
                }
                
                if ($resp->clientError()) {
                    $this->warn("   [CLIENT ERROR] ({$resp->status()}): {$url}");
                    return null;
                }
                
            } catch (\Throwable $e) {
                $this->warn("   [RETRY] Attempt " . ($attempt + 1) . " failed: " . $e->getMessage());
            }

            $attempt++;
            if ($attempt < $retries) {
                usleep($wait);
                $wait *= 2;
            }
        }
        return null;
    }

    private function storagePutPublic(string $filename, string $body)
    {
        $path = "wilayah/{$filename}";
        if (!Storage::disk('public')->exists('wilayah')) {
            Storage::disk('public')->makeDirectory('wilayah');
        }
        Storage::disk('public')->put($path, $body);
        return $path;
    }

    private function getOrFetch(string $endpoint, string $filename, string $label)
    {
        $path = "wilayah/{$filename}";
        
        if (!$this->option('force') && Storage::disk('public')->exists($path)) {
            $this->line("   [SKIP] {$label} - File exists locally.");
            return json_decode(Storage::disk('public')->get($path), true);
        }

        $this->info("   [DOWN] Fetching {$label}...");
        $response = $this->safeGet("{$this->baseUrl}/{$endpoint}");

        if ($response && $response->ok()) {
            $this->storagePutPublic($filename, $response->body());
            return $response->json();
        }

        $this->error("   [FAIL] Failed to fetch {$label}");
        return null;
    }

    public function handle()
    {
        $this->setupCertificates();
        $this->info('[START] Starting Wilayah Data Fetch Process');
        
        $provincesResponse = $this->getOrFetch('provinces.json', 'provinces.json', 'All Provinces');
        
        if (!$provincesResponse) {
            $this->error('[CRITICAL] Could not fetch provinces. Aborting process.');
            return 1;
        }

        if ($this->handleSpecificOptions()) {
            return 0;
        }

        if ($this->option('all')) {
            $this->info("[MODE] ALL - Recursive Download Active");
            $provinces = $provincesResponse['data'] ?? [];
            
            foreach ($provinces as $prov) {
                $pCode = $prov['code'];
                $pName = $prov['name'];
                $this->comment("\n[PROCESSING] Province: [{$pCode}] {$pName}");

                $regResponse = $this->getOrFetch("regencies/{$pCode}.json", "regencies_{$pCode}.json", "Regencies of {$pName}");
                $regencies = $regResponse['data'] ?? [];

                foreach ($regencies as $reg) {
                    $rCode = $reg['code'];
                    $rName = $reg['name'];
                    
                    $distResponse = $this->getOrFetch("districts/{$rCode}.json", "districts_{$rCode}.json", "Districts of {$rName}");
                    $districts = $distResponse['data'] ?? [];

                    foreach ($districts as $dist) {
                        $dCode = $dist['code'];
                        $this->getOrFetch("villages/{$dCode}.json", "villages_{$dCode}.json", "Villages of {$dCode}");
                    }
                    
                    if (function_exists('gc_collect_cycles')) {
                        gc_collect_cycles();
                    }
                }
            }
            $this->info("\n[SUCCESS] All data fetched successfully.");
            return 0;
        }

        $this->info('[INFO] Nothing to do. Use --all to fetch everything, or specific flags like --province=XX');
        return 0;
    }

    private function handleSpecificOptions()
    {
        if ($district = $this->option('district')) {
            $this->getOrFetch("villages/{$district}.json", "villages_{$district}.json", "Villages for District {$district}");
            return true;
        }

        if ($regency = $this->option('regency')) {
            $dData = $this->getOrFetch("districts/{$regency}.json", "districts_{$regency}.json", "Districts for Regency {$regency}");
            $districts = $dData['data'] ?? [];
            foreach ($districts as $dist) {
                $this->getOrFetch("villages/{$dist['code']}.json", "villages_{$dist['code']}.json", "Villages for {$dist['code']}");
            }
            return true;
        }

        if ($province = $this->option('province')) {
            $rData = $this->getOrFetch("regencies/{$province}.json", "regencies_{$province}.json", "Regencies for Province {$province}");
            $regencies = $rData['data'] ?? [];
            foreach ($regencies as $reg) {
                $dData = $this->getOrFetch("districts/{$reg['code']}.json", "districts_{$reg['code']}.json", "Districts for {$reg['code']}");
                $districts = $dData['data'] ?? [];
                foreach ($districts as $dist) {
                    $this->getOrFetch("villages/{$dist['code']}.json", "villages_{$dist['code']}.json", "Villages for {$dist['code']}");
                }
            }
            return true;
        }

        return false;
    }

    private function setupCertificates()
    {
        $certDir = storage_path('app/certs');
        if (!file_exists($certDir)) {
            mkdir($certDir, 0755, true);
        }
        
        $cacertPath = $certDir . DIRECTORY_SEPARATOR . 'cacert.pem';
        
        if (!file_exists($cacertPath)) {
            $this->warn('[WARNING] Downloading cacert.pem for SSL verification...');
            try {
                $resp = Http::timeout(30)->get('https://curl.se/ca/cacert.pem');
                if ($resp->ok()) {
                    file_put_contents($cacertPath, $resp->body());
                    $this->info('[SUCCESS] cacert.pem downloaded.');
                }
            } catch (\Throwable $e) {
                $this->warn('[WARNING] Failed to download cacert.pem. Trying fallback...');
                try {
                    $arrContextOptions=array("ssl"=>array("verify_peer"=>false,"verify_peer_name"=>false));  
                    $content = file_get_contents("https://curl.se/ca/cacert.pem", false, stream_context_create($arrContextOptions));
                    if ($content) {
                        file_put_contents($cacertPath, $content);
                        $this->info('[SUCCESS] cacert.pem downloaded (insecure fallback).');
                    }
                } catch (\Throwable $ex) {
                     $this->error('[ERROR] Could not download certs. Will proceed insecurely.');
                }
            }
        }

        $this->verifySSL = file_exists($cacertPath) ? $cacertPath : false;
    }
}