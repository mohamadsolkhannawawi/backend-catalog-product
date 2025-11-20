<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;

class Seller extends Model
{
    use HasFactory;

    /**
     * Semua field bisa diisi (karena validasi ketat dilakukan di Controller/Request).
     * Ini menampung SRS-01 (14 Elemen Data).
     */
    protected $guarded = ['id'];

    /**
     * Casting tipe data untuk mempermudah manipulasi.
     */
    protected $casts = [
        'verified_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors & Mutators
    |--------------------------------------------------------------------------
    */

    /**
     * Mendapatkan Full URL untuk Foto PIC.
     * Frontend tidak perlu tahu logic 'storage/'.
     */
    protected function picUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->pic_file_path 
                ? Storage::disk('public')->url($this->pic_file_path) 
                : null,
        );
    }

    /**
     * Mendapatkan Full Address (Helper untuk UI).
     */
    protected function fullAddress(): Attribute
    {
        return Attribute::make(
            get: fn () => "{$this->address}, RT {$this->rt}/RW {$this->rw}",
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes (Query Filters)
    |--------------------------------------------------------------------------
    */

    /**
     * Filter toko yang sudah diverifikasi (Aktif).
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Filter berdasarkan lokasi provinsi (SRS-05 & SRS-07).
     */
    public function scopeByProvince($query, $provinceCode)
    {
        return $query->where('province_code', $provinceCode);
    }
}