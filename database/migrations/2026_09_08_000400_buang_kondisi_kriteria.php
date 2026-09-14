<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kondisi dan kriteria berhenti disimpan.
 *
 * Prototipe sengaja tidak mengumpulkan keduanya, dan alasannya bukan soal
 * tempat: kondisi berbeda-beda tiap satuan kerja sehingga menumpuknya jadi satu
 * kolom justru mengaburkan siapa kena apa, dan kriteria berisi terlalu banyak
 * peraturan untuk diketik ulang. Keduanya tetap ada di dokumen LHP-nya, dan
 * berkas LHP-nya tersimpan sebagai lampiran laporan.
 *
 * Formulir sudah berhenti memintanya lebih dulu (migrasi tampilan 000200), dan
 * tidak ada satu pun tampilan yang membacanya lagi. Yang tersisa cuma kolomnya.
 *
 * ISINYA HILANG. Tidak ada salinannya di tempat lain di dalam sistem ini —
 * yang ada cuma di berkas LHP aslinya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('temuans', function (Blueprint $t) {
            $t->dropColumn(['kondisi', 'kriteria']);
        });
    }

    /**
     * Kolomnya bisa kembali; isinya tidak. Dikembalikan kosong supaya migrasi
     * ini tetap bisa dibalik tanpa berpura-pura datanya masih ada.
     */
    public function down(): void
    {
        Schema::table('temuans', function (Blueprint $t) {
            $t->text('kondisi')->nullable()->after('judul');
            $t->text('kriteria')->nullable()->after('kondisi');
        });
    }
};
