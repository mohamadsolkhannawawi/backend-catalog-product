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
        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            
            // SRS-06: Data Pengunjung
            $table->string('visitor_name');
            $table->string('visitor_email');
            $table->string('visitor_phone');
            
            // PENTING: SRS-06 & SRS-08 (Analitik per Provinsi)
            $table->char('visitor_province_code', 2); 
            
            $table->integer('rating'); // 1-5
            $table->text('comment');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_reviews');
    }
};
