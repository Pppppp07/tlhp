<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laporans', function (Blueprint $table) {
            $table->id();
            $table->string('sumber', 3);                 // LHA atau LHP
            $table->string('nomor')->unique();
            $table->date('tgl_surat');
            $table->date('tgl_terima');                  // dasar penghitungan tenggat

            // Kapan Setba memasukkannya ke sistem. Bukan tgl_terima: surat bisa
            // menganggur berminggu-minggu sebelum dicatat, dan selama itu tenggat
            // jawabannya sudah berjalan tanpa ada yang bisa mengerjakannya.
            $table->date('dicatat_pada')->nullable();
            $table->foreignId('dicatat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('sumber');
        });
        // Status laporan sengaja tidak disimpan — selalu disimpulkan dari
        // rekomendasi di bawahnya. Kalau disimpan, cepat atau lambat berbeda
        // dengan kenyataan.
    }

    public function down(): void
    {
        Schema::dropIfExists('laporans');
    }
};
