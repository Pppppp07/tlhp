<?php

namespace App\Enums;

/**
 * Warna lencana untuk data master.
 *
 * Delapan pilihan, bukan pemilih warna bebas. Warna bebas menghasilkan dua
 * kategori yang nyaris kembar dan lencana yang tulisannya tidak terbaca di atas
 * latarnya sendiri — dan yang menanggungnya pembaca, bukan yang memilih.
 *
 * Tiap warna punya tiga nilai yang sudah dipasangkan: latar, tulisan, dan
 * garis tepi. Ketiganya dari tangga warna yang sama, jadi rasio kontrasnya
 * terjaga tanpa perlu diperiksa satu per satu.
 */
enum WarnaLabel: string
{
    case BIRU   = 'biru';
    case HIJAU  = 'hijau';
    case MERAH  = 'merah';
    case JINGGA = 'jingga';
    case KUNING = 'kuning';
    case UNGU   = 'ungu';
    case TOSCA  = 'tosca';
    case ABU    = 'abu';

    public function nama(): string
    {
        return ucfirst($this->value);
    }

    /** Warna padatnya — dipakai titik dan contoh warna di data master. */
    public function padat(): string
    {
        return match ($this) {
            self::BIRU   => '#3B82F6',
            self::HIJAU  => '#22C55E',
            self::MERAH  => '#EF4444',
            self::JINGGA => '#F97316',
            self::KUNING => '#EAB308',
            self::UNGU   => '#8B5CF6',
            self::TOSCA  => '#14B8A6',
            self::ABU    => '#9CA3AF',
        };
    }

    /** Latar lencana. */
    public function isi(): string
    {
        return match ($this) {
            self::BIRU   => '#EFF6FF',
            self::HIJAU  => '#F0FDF4',
            self::MERAH  => '#FEF2F2',
            self::JINGGA => '#FFF7ED',
            self::KUNING => '#FFFBEB',
            self::UNGU   => '#F5F3FF',
            self::TOSCA  => '#F0FDFA',
            self::ABU    => '#F3F4F6',
        };
    }

    /** Tulisan lencana. */
    public function teks(): string
    {
        return match ($this) {
            self::BIRU   => '#1D4ED8',
            self::HIJAU  => '#15803D',
            self::MERAH  => '#B91C1C',
            self::JINGGA => '#C2410C',
            self::KUNING => '#A16207',
            self::UNGU   => '#6D28D9',
            self::TOSCA  => '#0F766E',
            self::ABU    => '#4B5563',
        };
    }

    /** Garis tepi lencana. */
    public function garis(): string
    {
        return match ($this) {
            self::BIRU   => '#BFDBFE',
            self::HIJAU  => '#BBF7D0',
            self::MERAH  => '#FECACA',
            self::JINGGA => '#FED7AA',
            self::KUNING => '#FDE68A',
            self::UNGU   => '#DDD6FE',
            self::TOSCA  => '#99F6E4',
            self::ABU    => '#E5E7EB',
        };
    }

    /** Abu jadi cadangan: yang belum dipilih tidak boleh menonjol. */
    public static function dari(?string $k): self
    {
        return self::tryFrom((string) $k) ?? self::ABU;
    }
}
