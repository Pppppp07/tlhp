<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bentuk tindak lanjut yang dituntut sebuah rekomendasi.
 *
 * Dulu bentuknya satu kolom di rekomendasi (`bentuk_id`). Rapat pemangku
 * kepentingan 26 Agustus menunjukkan itu tidak cukup: satu rekomendasi bisa
 * menuntut lebih dari satu bentuk sekaligus — menyetor ke kas negara *dan*
 * memperbaiki dokumen administrasinya — dan yang mengerjakan tiap bentuk bisa
 * satuan kerja yang berbeda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tindakans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rekomendasi_id')->constrained('rekomendasis')->cascadeOnDelete();
            $table->foreignId('bentuk_id')->nullable()->constrained('referensis')->nullOnDelete();
            $table->unsignedSmallInteger('urutan')->default(1);
            $table->timestamps();
            $table->index('rekomendasi_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tindakans');
    }
};
