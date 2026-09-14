<?php

namespace App\Support;

/**
 * Bukti baku menurut ketentuan, mengikuti bentuk tindak lanjutnya.
 *
 * Isinya dibaca dari BentukTindakLanjut supaya nama bentuk dan usulan
 * dokumennya tidak pernah berselisih — dulu keduanya ditulis dua kali, dan
 * sesudah nama bentuknya disamakan dengan SOP, peta di sini masih memakai nama
 * lama sehingga usulannya diam-diam berhenti muncul.
 */
class UsulDokumen
{
    /** @return list<string> */
    public static function untuk(?string $bentuk): array
    {
        return BentukTindakLanjut::peta()[$bentuk] ?? ['Dokumen pendukung'];
    }
}
