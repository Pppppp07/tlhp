<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tanggal dan catatan turun dari rekomendasi ke tiap bentuk tindak lanjut.
 *
 * Satu rekomendasi bisa menuntut dua bentuk sekaligus — menyetor ke kas negara
 * DAN membenahi prosedurnya — dan keduanya bisa dibebankan ke satuan kerja yang
 * berbeda dengan tenggat yang berbeda pula. Selama tanggalnya cuma satu di
 * kepala rekomendasi, satuan kerja yang cuma kena satu bentuk tetap melihat
 * tanggal yang mengikat bentuk yang lain.
 *
 * Bentuk datanya menyusul prototipe, yang menyimpannya di tiap tindakan:
 *
 *     tindakan: [{ bentuk, sasaran, dokumen, tglRenaksi, targetSelesai, catatan }]
 *
 * Kolom di rekomendasi TIDAK dibuang. Ia tetap jadi tanggal rekomendasi
 * seutuhnya — dasar hukumnya melekat di sana, dihitung dari tanggal laporan
 * diterima — dan jadi cadangan untuk tindakan yang tanggalnya belum diisi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tindakans', function (Blueprint $t) {
            /* Rencana aksi yang mengikat bentuk tindak lanjut ini. Kosong
               berarti ikut tanggal rekomendasinya. */
            $t->date('tgl_renaksi')->nullable()->after('urutan');
            $t->date('target_selesai')->nullable()->after('tgl_renaksi');
            $t->text('catatan')->nullable()->after('target_selesai');
        });

        /* Data lama diisi dari rekomendasinya: sampai sekarang formulirnya
           memang cuma membuat satu tindakan per rekomendasi, jadi tanggal
           rekomendasi memang tanggal tindakan itu. Diisi apa adanya supaya
           tidak ada baris yang mendadak kehilangan tanggalnya.

           Ditulis dengan pembangun kueri, bukan `UPDATE ... JOIN` mentah:
           sintaks itu milik MySQL, dan rangkaian ujinya berjalan di SQLite.
           Migrasi yang cuma jalan di satu mesin basis data membuat seluruh uji
           mati sebelum satu pun berkas tampilan dibuka. */
        foreach (DB::table('rekomendasis')->select('id', 'tenggat_jawab', 'target_selesai')
            ->orderBy('id')->cursor() as $r) {
            DB::table('tindakans')
                ->where('rekomendasi_id', $r->id)
                ->update([
                    'tgl_renaksi'    => $r->tenggat_jawab,
                    'target_selesai' => $r->target_selesai,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('tindakans', function (Blueprint $t) {
            $t->dropColumn(['tgl_renaksi', 'target_selesai', 'catatan']);
        });
    }
};
