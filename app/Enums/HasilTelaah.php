<?php

namespace App\Enums;

/**
 * Sumbu penilaian Itjen — terpisah dari StatusTindakLanjut, yang milik BPK.
 *
 * Lembar pemantauan SETBA memang memisahkan keduanya, dan 312 barisnya memakai
 * "Belum memadai" — bukan "tidak memadai", karena berkasnya masih bisa
 * diperbaiki dan dikembalikan.
 *
 * Maknanya sama untuk kedua jenis laporan — cukup atau belum cukup — tapi
 * sebutannya berbeda, dan Mbak Puspi menegaskan itu: "hasil verifikasinya ini
 * tergantung jenis laporannya."
 *
 *     LHA   Sesuai / Belum sesuai
 *     LHP   Memadai / Belum memadai
 *
 * Kolomnya tetap satu. Yang berbeda hanya cara menyebutnya.
 *
 * NULL punya artinya sendiri dan bukan sekadar kosong: belum ditelaah. Itu
 * tidak sama dengan belum memadai, dan tidak boleh ditampilkan seakan sama.
 */
enum HasilTelaah: string
{
    case M  = 'M';
    case BM = 'BM';

    public function nama(?SumberLaporan $sumber = null): string
    {
        $lha = $sumber === SumberLaporan::LHA;

        return match ($this) {
            self::M  => $lha ? 'Sesuai' : 'Memadai',
            self::BM => $lha ? 'Belum sesuai' : 'Belum memadai',
        };
    }

    public function memadai(): bool
    {
        return $this === self::M;
    }

    /** Kelas lencana; sejalan dengan StatusTindakLanjut supaya sewarna. */
    public function cap(): string
    {
        return $this === self::M ? 'cap-ss' : 'cap-bs';
    }
}
