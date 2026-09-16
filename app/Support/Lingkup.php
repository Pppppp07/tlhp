<?php

namespace App\Support;

use App\Enums\SumberLaporan;
use Illuminate\Http\Request;

/**
 * Lingkup laporan yang sedang dilihat: Semua, LHP, atau LHA.
 *
 * Bukan mode aplikasi, melainkan keadaan kerja — dan ia ikut ke mana pun:
 * dipilih di layar Rekomendasi atau Ringkasan, berlaku juga di Daftar laporan,
 * pencarian, dan angka menu. Karena itu disimpan di sesi, bukan diulang di
 * alamat tiap halaman.
 */
class Lingkup
{
    private const KUNCI = 'lingkup';

    /** Baca dari alamat kalau disebut (dan simpan), selain itu dari sesi. */
    public static function dari(?Request $req = null): string
    {
        $req ??= request();
        $diminta = $req->query('lingkup');
        if (in_array($diminta, ['semua', 'LHP', 'LHA'], true)) {
            session([self::KUNCI => $diminta]);

            return $diminta;
        }

        return session(self::KUNCI, 'semua');
    }

    public static function berlaku(string $lingkup, SumberLaporan $jenis): bool
    {
        return $lingkup === 'semua' || $lingkup === $jenis->value;
    }
}
