<?php

namespace App\Enums;

/* Banyak pihak memakai sistem, tapi hanya Setba yang boleh mengubah status —
   itu pun hanya dengan menyalin isi surat verifikasi bernomor. */
enum PeranPengguna: string
{
    case SETBA       = 'setba';
    case SATKER      = 'satker';
    case UKI         = 'uki';
    case INSPEKTORAT = 'inspektorat';
    case PIMPINAN    = 'pimpinan';
    case ADMIN       = 'admin';

    public function nama(): string
    {
        return match ($this) {
            self::SETBA       => 'Sekretariat Badan',
            self::SATKER      => 'Satuan kerja',
            self::UKI         => 'Unit Kepatuhan Internal',
            self::INSPEKTORAT => 'Inspektorat',
            self::PIMPINAN    => 'Pimpinan',
            self::ADMIN       => 'Administrator',
        };
    }

    /** Sebutan pendek untuk kolom sempit dan keping - "Setba", bukan
        "Sekretariat Badan". Nama panjangnya dipakai di kepala halaman. */
    public function pendek(): string
    {
        return match ($this) {
            self::SETBA       => 'Setba',
            self::SATKER      => 'Satuan kerja',
            self::UKI         => 'UKI',
            self::INSPEKTORAT => 'Inspektorat',
            self::PIMPINAN    => 'Pimpinan',
            self::ADMIN       => 'Admin',
        };
    }

    public function bolehUbahStatus(): bool
    {
        return $this === self::SETBA;
    }
}
