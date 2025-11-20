<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * Otomatis casting angka desimal dan integer.
     */
    protected $casts = [
        'price' => 'decimal:2',
        'stock' => 'integer',
        'average_rating' => 'float',
    ];

    /**
     * Field tambahan (virtual) yang akan dikirim via JSON (misal: URL gambar).
     */
    protected $appends = ['image_url'];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function seller()
    {
        return $this->belongsTo(Seller::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function reviews()
    {
        return $this->hasMany(ProductReview::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    /**
     * Akses URL gambar produk secara langsung.
     * Jika null, return placeholder default.
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->image_path 
                ? Storage::disk('public')->url($this->image_path) 
                : 'https://placehold.co/600x400?text=No+Image', // Fallback image
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes (Untuk Pencarian SRS-05)
    |--------------------------------------------------------------------------
    */

    /**
     * Filter pencarian global (Nama Produk atau Deskripsi).
     */
    public function scopeSearch($query, $keyword)
    {
        return $query->where('name', 'ILIKE', "%{$keyword}%") // ILIKE untuk Case Insensitive (PostgreSQL)
                     ->orWhere('description', 'ILIKE', "%{$keyword}%");
    }

    /**
     * Filter berdasarkan Kategori.
     */
    public function scopeByCategory($query, $categorySlug)
    {
        return $query->whereHas('category', function ($q) use ($categorySlug) {
            $q->where('slug', $categorySlug);
        });
    }

    /**
     * Filter berdasarkan Lokasi Toko (Provinsi) - SRS-05.
     */
    public function scopeByLocation($query, $provinceCode)
    {
        return $query->whereHas('seller', function ($q) use ($provinceCode) {
            $q->where('province_code', $provinceCode);
        });
    }
}