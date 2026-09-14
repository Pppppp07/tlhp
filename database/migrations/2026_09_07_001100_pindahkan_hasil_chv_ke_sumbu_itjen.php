<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Hasil surat CHV pindah dari sumbu BPK ke sumbu Itjen.
 *
 * Data lama menyimpan kode BPK (SS / BS / TD) pada surat Inspektorat. Itu
 * kekeliruan yang berakibat nyata: status BPK ikut berubah tiap kali
 * Inspektorat menerbitkan surat, padahal BPK belum menilai apa pun.
 *
 * Lembar pemantauan SETBA memang memisahkan keduanya:
 *
 *   Itjen  memadai / belum memadai   atas kecukupan berkas
 *   BPK    SS / BS / TD              atas rekomendasinya, lewat SIPTL
 *
 * Pemetaannya:
 *
 *   SS → M    suratnya menyatakan tindak lanjutnya cukup
 *   BS → BM   belum cukup, dikembalikan dengan tenggat baru
 *   TD → BM   "tidak dapat ditindaklanjuti" adalah putusan BPK, bukan
 *             penilaian Itjen atas berkas. Alasannya tetap tersimpan di
 *             alasan_td_id dan rekomendasinya tetap berstatus TD; yang
 *             diubah hanya bunyi suratnya.
 */
return new class extends Migration
{
    private const KE_ITJEN = ['SS' => 'M', 'BS' => 'BM', 'TD' => 'BM'];

    public function up(): void
    {
        foreach (self::KE_ITJEN as $lama => $baru) {
            DB::table('keputusan_verifikasis')->where('hasil', $lama)->update(['hasil' => $baru]);
        }
    }

    public function down(): void
    {
        // Turun tangga tidak bisa utuh: BM dulunya bisa BS maupun TD, dan
        // bedanya tidak tersimpan di mana pun. Yang dikembalikan BS.
        DB::table('keputusan_verifikasis')->where('hasil', 'M')->update(['hasil' => 'SS']);
        DB::table('keputusan_verifikasis')->where('hasil', 'BM')->update(['hasil' => 'BS']);
    }
};
