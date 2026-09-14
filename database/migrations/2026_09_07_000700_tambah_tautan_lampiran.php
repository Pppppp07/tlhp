<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Berkas jadi tautan, bukan unggahan.
 *
 * Rapat pemangku kepentingan 30 Agustus: berkas tindak lanjut sudah tersimpan
 * di arsip masing-masing satuan kerja, dan mengunggah ulang salinannya ke
 * sistem ini berarti dua tempat menyimpan satu berkas — lalu keduanya bisa
 * berbeda tanpa ada yang tahu mana yang benar.
 *
 * Kolom unggahan tidak dibuang: berkas yang sudah terlanjur diunggah tetap
 * boleh ada, dan sebagiannya sudah jadi dasar surat resmi. Yang berubah hanya
 * kewajibannya — sekarang boleh kosong asal ada tautannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lampirans', function (Blueprint $table) {
            $table->string('tautan', 500)->nullable()->after('jenis_dokumen_id');

            // Berkas yang dilampirkan satu satuan kerja pada tindak lanjutnya
            // sendiri. Kosong berarti berkas milik rekomendasi atau laporan
            // seutuhnya — mis. surat pemeriksaan aslinya.
            $table->foreignId('sasaran_id')->nullable()->after('rekomendasi_id')
                  ->constrained('sasarans')->nullOnDelete();
            $table->index('sasaran_id');
        });
    }

    public function down(): void
    {
        Schema::table('lampirans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sasaran_id');
            $table->dropColumn('tautan');
        });
    }
};
