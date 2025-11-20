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
        Schema::table('users', function (Blueprint $table) {
            // SRS-01: No Handphone PIC
            $table->string('phone')->nullable()->after('email');
            // Role: 'admin' (Platform), 'seller' (Penjual), 'buyer' (Pengunjung Login - opsional)
            $table->enum('role', ['admin', 'seller', 'buyer'])->default('buyer')->after('password');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
        });
    }
};
