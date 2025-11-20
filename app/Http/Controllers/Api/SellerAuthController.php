<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class SellerAuthController extends Controller
{
    /**
     * Handle Seller Registration (SRS-01 & SRS-02).
     */
    public function register(Request $request)
    {
        // 1. Validasi Input
        // Kita gunakan Validator facade manual agar lebih kontrol terhadap response error
        $validator = Validator::make($request->all(), [
            // Akun Logic
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8|confirmed',
            
            // Data Toko
            'store_name' => 'required|string|max:255',
            'description' => 'required|string',
            
            // Data PIC
            'pic_name' => 'required|string|max:255',
            'pic_phone' => 'required|string|max:20',
            'ktp_number' => 'required|string|size:16', // Wajib 16 digit
            
            // Alamat & Wilayah
            'address' => 'required|string',
            'rt' => 'required|string|max:5',
            'rw' => 'required|string|max:5',
            'province_code' => 'required',
            'regency_code' => 'required',
            'district_code' => 'required',
            'village_code' => 'required',
            
            // File Upload (Max 2MB, Image Only)
            'ktp_file' => 'required|image|mimes:jpeg,png,jpg|max:2048',
            'pic_file' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi Gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        // 2. Mulai Database Transaction
        DB::beginTransaction();

        try {
            // A. Upload File
            
            // KTP disimpan di 'local' (storage/app) -> PRIVATE, tidak bisa diakses via URL publik
            $ktpPath = $request->file('ktp_file')->store('documents/ktp', 'local');
            
            // Foto PIC disimpan di 'public' (storage/app/public) -> PUBLIC, bisa diakses via URL
            $picPath = $request->file('pic_file')->store('images/sellers', 'public');

            // B. Create User (Akun Login)
            $user = User::create([
                'name' => $request->pic_name, // Nama user diambil dari nama PIC
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'phone' => $request->pic_phone,
                'role' => 'seller', // Set role spesifik
            ]);

            // C. Create Seller Profile (Data Toko)
            $seller = Seller::create([
                'user_id' => $user->id,
                'store_name' => $request->store_name,
                'store_description' => $request->description,
                
                // Alamat
                'address' => $request->address,
                'rt' => $request->rt,
                'rw' => $request->rw,
                'province_code' => $request->province_code,
                'regency_code' => $request->regency_code,
                'district_code' => $request->district_code,
                'village_code' => $request->village_code,
                
                // Dokumen
                'ktp_number' => $request->ktp_number,
                'ktp_file_path' => $ktpPath,
                'pic_file_path' => $picPath,
                
                // Status Awal (SRS-02)
                'status' => 'pending', 
            ]);

            // Commit Transaksi jika semua sukses
            DB::commit();

            // TODO: Trigger Event untuk Kirim Email Notifikasi (Queue)

            return response()->json([
                'message' => 'Registrasi Berhasil. Mohon tunggu verifikasi admin.',
                'data' => [
                    'user_id' => $user->id,
                    'store_name' => $seller->store_name,
                    'status' => $seller->status
                ]
            ], 201);

        } catch (\Exception $e) {
            // Rollback jika ada error (File terlanjur upload tidak otomatis terhapus di transaction DB, 
            // tapi untuk MVP ini acceptable, atau bisa ditambahkan logic delete file di catch)
            DB::rollBack();
            Log::error("Seller Registration Failed: " . $e->getMessage());

            return response()->json([
                'message' => 'Terjadi kesalahan sistem saat registrasi.',
                'error' => $e->getMessage() // Debug only, hide in production
            ], 500);
        }
    }
}