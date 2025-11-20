<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Handle Login Request
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // Coba Login
        if (!Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['Kredensial yang Anda masukkan salah.'],
            ]);
        }

        // Regenerate Session (Security Best Practice untuk Session Fixation)
        $request->session()->regenerate();

        $user = Auth::user();

        // Cek Status Seller (Jika User adalah Seller)
        $sellerStatus = null;
        if ($user->role === 'seller' && $user->seller) {
            $sellerStatus = $user->seller->status;
            
            // Jika ingin memblokir login jika belum verify
            // if ($sellerStatus !== 'active') {
            //     Auth::guard('web')->logout();
            //     throw ValidationException::withMessages(['email' => ['Akun Anda belum diverifikasi admin.']]);
            // }
        }

        return response()->json([
            'message' => 'Login Berhasil',
            'user' => $user,
            'seller_status' => $sellerStatus, // Penting untuk redirect frontend (Pending vs Active)
        ]);
    }

    /**
     * Handle Logout Request
     */
    public function logout(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logout Berhasil']);
    }
}