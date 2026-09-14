<?php

use App\Enums\JenisReferensi;
use App\Models\Referensi;
use App\Support\BentukTindakLanjut;
use Illuminate\Database\Migrations\Migration;

/**
 * Daftar bentuk tindak lanjut disamakan dengan SOP — dan dengan prototipe.
 *
 * simtlhp memakai tujuh sebutan sendiri ditambah "Lainnya"; SOP punya delapan,
 * dan keterangan Info di formulirnya sendiri sudah menyebut "delapan jenis
 * resmi di SOP". Dua bentuk tidak pernah ada di simtlhp: pelimpahan kepada
 * aparat penegak hukum, dan pengenaan sanksi daftar hitam — keduanya jalur yang
 * memang dipakai, dan tidak bisa dicatat sama sekali selama tidak ada
 * pilihannya.
 *
 * Namanya diganti DI TEMPAT, bukan dibuat baris baru: 114 tindakan sudah
 * menunjuk baris-baris itu, dan mengganti barisnya berarti seluruhnya
 * kehilangan bentuk tindak lanjutnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        $urutan = array_flip(BentukTindakLanjut::nama());

        foreach (BentukTindakLanjut::petaLama() as $lama => $baru) {
            $baris = Referensi::where('jenis', JenisReferensi::BENTUK_TL->value)
                ->where('nama', $lama)->first();

            if (! $baris) {
                continue;
            }

            if ($baru === null) {
                /* Tidak dihapus — sepuluh tindakan memakainya. Dinonaktifkan
                   saja: berkas lama tetap terbaca, berkas baru tidak bisa
                   memilihnya lagi. */
                $baris->update(['aktif' => false]);
                continue;
            }

            $baris->update(['nama' => $baru, 'urutan' => $urutan[$baru] + 1, 'aktif' => true]);
        }

        /* Dua bentuk yang belum pernah ada. firstOrCreate supaya migrasi ini
           aman dijalankan ulang. */
        foreach (BentukTindakLanjut::nama() as $i => $nama) {
            Referensi::firstOrCreate(
                ['jenis' => JenisReferensi::BENTUK_TL->value, 'nama' => $nama],
                ['urutan' => $i + 1, 'aktif' => true],
            );
        }
    }

    /**
     * Tidak dibalik. Nama lama tidak menyimpan keterangan apa pun yang hilang,
     * dan mengembalikannya justru membuat dua bentuk yang baru ditambahkan
     * menggantung tanpa nama pasangannya.
     */
    public function down(): void {}
};
