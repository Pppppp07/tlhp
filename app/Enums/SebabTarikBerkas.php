<?php

namespace App\Enums;

/* Penarikan berkas pribadi tidak lewat persetujuan dan tidak terhalang surat
   verifikasi. Isi berkas dihapus; catatan bahwa berkas pernah ada tetap. */
enum SebabTarikBerkas: string
{
    case PRIBADI  = 'pribadi';
    case KELIRU   = 'keliru';
    case BERLEBIH = 'berlebih';
    case BATAL    = 'batal';

    public function nama(): string
    {
        return match ($this) {
            self::PRIBADI  => 'Salah unggah — berkas pribadi, bukan bukti',
            self::KELIRU   => 'Salah unggah — berkas keliru, bukan yang diminta',
            self::BERLEBIH => 'Memuat data pribadi berlebih, diganti versi tersamarkan',
            self::BATAL    => 'Dibatalkan lewat permintaan perubahan',
        };
    }

    /** Jalur data pribadi menembus semua tahap, termasuk setelah surat terbit. */
    public function tanpaPersetujuan(): bool
    {
        return $this !== self::BATAL;
    }
}
