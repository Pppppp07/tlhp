<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lima tabel tindak lanjut pindah gantungan dari rekomendasi ke sasaran.
 *
 * Alasannya sama untuk kelimanya: yang menjawab, menyetor, dimintai dokumen,
 * dikembalikan berkasnya, dan dicatat perpindahannya adalah *satu satuan
 * kerja* — bukan rekomendasinya. Selama gantungannya di rekomendasi, jawaban
 * Balai Medan dan Politeknik PU bercampur dalam satu daftar, dan tidak ada
 * cara memisahkannya.
 *
 * `rekomendasi_id` sengaja dipertahankan di semua tabel ini. Dua alasan: data
 * lama tetap terbaca sesudah migrasi, dan `riwayat_berkas` memang butuh
 * keduanya — gerak tingkat 1 dicatat per satuan kerja, gerak tingkat 2 tidak
 * punya satuan kerja sama sekali.
 */
return new class extends Migration
{
    private const TABEL = [
        'tindak_lanjuts', 'pemulihans', 'permintaan_dokumens',
        'pengembalians', 'riwayat_berkas',
    ];

    public function up(): void
    {
        foreach (self::TABEL as $nama) {
            if (Schema::hasColumn($nama, 'sasaran_id')) {
                continue;
            }
            Schema::table($nama, function (Blueprint $table) {
                $table->foreignId('sasaran_id')->nullable()->after('rekomendasi_id')
                      ->constrained('sasarans')->nullOnDelete();
                $table->index('sasaran_id');
            });
        }

        // Data lama disambungkan ke sasaran pertama rekomendasinya. Sebelum
        // migrasi ini tiap rekomendasi memang hanya punya satu satuan kerja,
        // jadi pemetaannya tidak ambigu.
        $sasaranPertama = DB::table('sasarans')
            ->join('tindakans', 'sasarans.tindakan_id', '=', 'tindakans.id')
            ->select('tindakans.rekomendasi_id', DB::raw('MIN(sasarans.id) as sasaran_id'))
            ->groupBy('tindakans.rekomendasi_id')
            ->pluck('sasaran_id', 'rekomendasi_id');

        foreach (self::TABEL as $nama) {
            foreach ($sasaranPertama as $rekId => $sasaranId) {
                DB::table($nama)
                    ->where('rekomendasi_id', $rekId)
                    ->whereNull('sasaran_id')
                    ->update(['sasaran_id' => $sasaranId]);
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABEL as $nama) {
            if (! Schema::hasColumn($nama, 'sasaran_id')) {
                continue;
            }
            Schema::table($nama, function (Blueprint $table) {
                $table->dropConstrainedForeignId('sasaran_id');
            });
        }
    }
};
