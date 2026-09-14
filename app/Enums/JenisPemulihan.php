<?php

namespace App\Enums;

/* Nilai bisa dipulihkan dengan uang masuk maupun tanpa uang masuk.
   SOP menyebut sisa nilai temuan setelah perbaikan fisik tetap wajib disetor. */
enum JenisPemulihan: string
{
    case SETOR     = 'setor';
    case PERBAIKAN = 'perbaikan';

    public function nama(): string
    {
        return match ($this) {
            self::SETOR     => 'Setoran ke kas negara',
            self::PERBAIKAN => 'Perbaikan fisik atau pengembalian barang',
        };
    }

    /** Setoran tunai wajib punya NTPN; perbaikan wajib punya berita acara. */
    public function perluNtpn(): bool
    {
        return $this === self::SETOR;
    }
}
