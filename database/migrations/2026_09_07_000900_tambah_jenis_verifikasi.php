<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dua surat berbeda memakai tabel yang sama, jadi harus bisa dibedakan.
 *
 *   LHV — Laporan Hasil Validasi, terbit dari UKI atas berkas satu satuan kerja
 *   CHV — Catatan Hasil Verifikasi, terbit dari Inspektorat, memutus seluruh
 *         rekomendasi sekaligus
 *
 * Perbedaan cakupan itu yang penting: tanda memadai per baris adalah penilaian
 * atas satu satuan kerja, sedangkan surat CHV memutus rekomendasinya utuh.
 * Menyamakan keduanya membuat satu satuan kerja yang belum beres bisa
 * "menuntaskan" rekomendasi yang dipikul lima satuan kerja.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verifikasis', function (Blueprint $table) {
            $table->string('jenis', 3)->default('CHV')->after('id');
            $table->index('jenis');
        });

        // Yang sudah ada semuanya surat Inspektorat — layar UKI belum pernah
        // menerbitkan surat sebelum ini.
        DB::table('verifikasis')->update(['jenis' => 'CHV']);
    }

    public function down(): void
    {
        Schema::table('verifikasis', function (Blueprint $table) {
            $table->dropIndex(['jenis']);
            $table->dropColumn('jenis');
        });
    }
};
