<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sellers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Relasi ke PIC
            
            // Info Toko
            $table->string('store_name');
            $table->text('store_description');
            
            // SRS-01: Alamat Lengkap & Wilayah
            $table->string('address'); // Nama Jalan
            $table->string('rt', 5);
            $table->string('rw', 5);
            
            // Kita simpan Kode Wilayah dari API/Json yang kita fetch (string/char)
            $table->char('province_code', 2);
            $table->char('regency_code', 4);
            $table->char('district_code', 7);
            $table->char('village_code', 10);

            // SRS-01: Dokumen Identitas
            $table->string('ktp_number', 16);
            $table->string('ktp_file_path'); // Private Storage
            $table->string('pic_file_path');  // Private/Public Storage
            
            // SRS-02: Status Verifikasi
            $table->enum('status', ['pending', 'active', 'rejected'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('verified_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sellers');
    }
};
