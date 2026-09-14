<?php

namespace App\Enums;

enum JenisPermintaanUbah: string
{
    case KOREKSI       = 'koreksi';
    case PENARIKAN     = 'penarikan';
    case BATAL_DOKUMEN = 'batal_dokumen';

    public function nama(): string
    {
        return match ($this) {
            self::KOREKSI       => 'Koreksi isian',
            self::PENARIKAN     => 'Tarik pengiriman',
            self::BATAL_DOKUMEN => 'Batalkan berkas',
        };
    }
}
