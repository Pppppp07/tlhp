<?php

namespace App\Enums;

/* Empat klasifikasi menurut Peraturan BPK Nomor 2 Tahun 2017.
   Hanya berubah lewat pencatatan surat verifikasi bernomor. */
enum StatusTindakLanjut: string
{
    case BT = 'BT';
    case SS = 'SS';
    case BS = 'BS';
    case TD = 'TD';

    public function nama(): string
    {
        return match ($this) {
            self::BT => 'Belum ditindaklanjuti',
            self::SS => 'Sesuai dengan rekomendasi',
            self::BS => 'Belum sesuai dengan rekomendasi',
            self::TD => 'Tidak dapat ditindaklanjuti',
        };
    }

    /** TD wajib disertai alasan sah dari daftar baku. */
    public function perluAlasanSah(): bool
    {
        return $this === self::TD;
    }

    /** BS memulangkan berkas ke satuan kerja dengan tenggat baru. */
    public function perluTenggatBaru(): bool
    {
        return $this === self::BS;
    }
}
