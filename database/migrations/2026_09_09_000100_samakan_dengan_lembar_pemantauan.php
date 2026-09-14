<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tiga medan dari lembar pemantauan yang belum punya tempat di sini.
 *
 * Dibaca dari `PTL_BPSDM_Clean.csv` — 55 kolom, 312 baris — dan dicocokkan
 * satu per satu dengan tabel yang sudah ada. Dari sembilan kolom yang belum
 * ada, tiga ini yang memang perlu; sisanya kolom kotor, kolom mati, atau
 * catatan bebas yang tidak dipakai menghitung apa pun.
 *
 * 1. NILAI YANG DIAKUI, dua sumbu, keduanya PER BARIS satuan kerja:
 *
 *        Nilai SS (IDR)       -> sasarans.nilai_ss
 *        Nilai Memadai (IDR)  -> sasarans.nilai_memadai
 *
 *    Bukan per rekomendasi. Di lembar mereka kedua kolom itu ada di tiap
 *    baris, dan `Sisa Nilai (IDR)` dihitung per baris juga. Itulah yang
 *    membuat kolom "Sisa Nilai SIPTL" pada tabel per satker bisa ada sama
 *    sekali — kalau nilainya hanya ada di tingkat rekomendasi, tidak ada cara
 *    membagikannya ke satuan kerja.
 *
 *    Dan itu masuk akal: BPK menutup REKOMENDASI, tapi uangnya ditagihkan per
 *    satuan kerja. Waktu buktinya diterima sebagian, yang perlu diketahui
 *    justru bagian siapa yang ditolak. Reff IDT 2022.5b.I.2.9.d: rekomendasi
 *    Rp 792 juta, diakui BPK Rp 192 juta, diakui Itjen Rp 247 juta.
 *
 *    NULL berarti "belum ada angkanya sendiri" — bukan nol. Yang membacanya
 *    jatuh ke statusnya: utuh bila sudah beres, nol bila belum. Kalau dibuat
 *    0 dan bukan NULL, rekomendasi yang sudah SS akan terbaca belum diakui
 *    sepeser pun.
 *
 * 2. JENIS PEMERIKSAAN yang melahirkan LHP-nya: LK 297, PDTT 15 dari 312
 *    baris. Sumbu tersendiri, bukan pengganti LHP/LHA — prototipe sudah
 *    punya daftarnya (`JENIS_PERIKSA`), sisi ini belum punya kolomnya.
 *
 * 3. PROVINSI satuan kerja. Dipakai mengelompokkan di dasbor. Di lembar
 *    mereka kolomnya kotor — "JAWA BARAT" dan "Jawa Barat" terhitung dua dari
 *    15 nilai unik — jadi di sini ia jadi medan tabel `satkers`, bukan teks
 *    yang diketik ulang tiap baris.
 *
 * TIDAK DIAMBIL, dan alasannya:
 *
 * - `Klasifikasi Renaksi` (Pendek/Panjang/TPTD) — 8 dari 312 baris terisi,
 *   tidak pernah disebut di satu pun rapat, dan TPTD sudah punya tempatnya
 *   sendiri lewat `rekomendasis.alasan_td_id`.
 * - `Nilai TPTD (IDR)` — seluruhnya nol.
 * - `Satker Lama`, `Balai`, `Pejabat Terkait`, `Keterangan Paket` — catatan
 *   bebas; `Balai` bahkan memuat nama orang.
 * - `Tahun IHPS` — sama persis dengan `Tahun LHP` di 312 baris.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sasarans', function (Blueprint $t) {
            /* Sengaja nullable, bukan default 0. Lihat keterangan di atas. */
            $t->unsignedBigInteger('nilai_ss')->nullable()->after('nilai');
            $t->unsignedBigInteger('nilai_memadai')->nullable()->after('nilai_ss');
        });

        Schema::table('laporans', function (Blueprint $t) {
            $t->string('jenis_periksa', 16)->nullable()->after('sumber');
        });

        Schema::table('satkers', function (Blueprint $t) {
            $t->string('provinsi', 64)->nullable()->after('nama');
        });
    }

    public function down(): void
    {
        Schema::table('sasarans', fn (Blueprint $t) => $t->dropColumn(['nilai_ss', 'nilai_memadai']));
        Schema::table('laporans', fn (Blueprint $t) => $t->dropColumn('jenis_periksa'));
        Schema::table('satkers', fn (Blueprint $t) => $t->dropColumn('provinsi'));
    }
};
