<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * Store a newly created product in storage (SRS-03).
     */
    public function store(Request $request)
    {
        // 1. Validasi Input (Sesuai Elemen Data Tokopedia/SRS)
        $validator = Validator::make($request->all(), [
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric|min:100',
            'stock' => 'required|integer|min:0',
            'image' => 'required|image|mimes:jpeg,png,jpg|max:2048', // Max 2MB
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // 2. Ambil User & Seller
        $user = Auth::user();
        
        // Pastikan User adalah Seller yang Aktif
        if (!$user->isSeller() || !$user->seller || $user->seller->status !== 'active') {
            return response()->json(['message' => 'Unauthorized or Store not active.'], 403);
        }

        // 3. Upload Gambar
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('images/products', 'public');
        }

        // 4. Simpan Produk
        // Menggunakan relasi user->seller->products()
        $product = $user->seller->products()->create([
            'category_id' => $request->category_id,
            'name' => $request->name,
            'slug' => Str::slug($request->name) . '-' . Str::random(5),
            'description' => $request->description,
            'price' => $request->price,
            'stock' => $request->stock,
            'image_path' => $imagePath,
        ]);

        return response()->json([
            'message' => 'Produk berhasil ditambahkan',
            'data' => $product
        ], 201);
    }

    /**
     * Menampilkan daftar produk milik Penjual yang sedang login (Untuk Dashboard).
     */
    public function indexSeller()
    {
        $user = Auth::user();
        
        // Eager load category dan sort terbaru
        $products = $user->seller->products()
                         ->with('category')
                         ->latest()
                         ->get();

        return response()->json(['data' => $products]);
    }
}