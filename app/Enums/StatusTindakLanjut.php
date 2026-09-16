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

    /**
     * Sebutan pada lencana. "dengan rekomendasi" dibuang: lencananya sudah
     * menempel pada rekomendasi itu sendiri, dan sepasang "Sudah sesuai" /
     * "Belum sesuai" langsung terbaca sebagai lawan satu sama lain.
     */
    public function pendek(): string
    {
        return match ($this) {
            self::BT => 'Belum ditindaklanjuti',
            self::SS => 'Sudah sesuai',
            self::BS => 'Belum sesuai',
            self::TD => 'Tidak dapat ditindaklanjuti',
        };
    }

    public function cap(): string
    {
        return 'cap-'.strtolower($this->value);
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
