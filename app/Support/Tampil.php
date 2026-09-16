<?php

namespace App\Support;

use Carbon\Carbon;

/** Pembantu tampilan. Tidak ada aturan bisnis di sini — hanya cara menuliskan. */
class Tampil
{
    /** "Rp 18.600.000", atau "—" untuk nol — `rp` prototipe. */
    public static function rupiah($n): string
    {
        $n = (int) $n;

        return $n ? 'Rp '.number_format($n, 0, ',', '.') : '—';
    }

    /** Sisa nol berarti tuntas, bukan kosong. */
    public static function rupiahSisa($n): string
    {
        return (int) $n === 0 ? 'lunas' : self::rupiah($n);
    }

    /**
     * "Rp 1.5 M", "Rp 24 jt" — `rpk` prototipe, termasuk titik desimalnya
     * (toFixed di sana). Kedua artefak diperagakan berdampingan; angka yang
     * ditulis beda bentuk terbaca seperti angka yang berbeda.
     */
    public static function rupiahSingkat($n): string
    {
        $n = (int) $n;
        if (! $n) {
            return 'Rp 0';
        }
        if ($n >= 1e9) {
            return 'Rp '.str_replace('.0', '', number_format($n / 1e9, 1, '.', '')).' M';
        }
        if ($n >= 1e6) {
            return 'Rp '.round($n / 1e6).' jt';
        }

        return self::rupiah($n);
    }

    /**
     * Tanggal boleh datang sebagai Carbon atau teks "2026-08-05".
     *
     * Nama bulan ditulis sendiri, tidak lewat locale Carbon: singkatan Carbon
     * untuk Agustus "Agt", prototipe "Agu" — bedanya satu huruf, tapi muncul di
     * ratusan tempat.
     */
    public static function tgl($t): string
    {
        if (! $t) {
            return '—';
        }
        if (is_string($t)) {
            $t = Carbon::parse($t);
        }

        return $t->format('d').' '.self::BULAN[(int) $t->format('n') - 1].' '.$t->format('Y');
    }

    private const BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
                           'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    /**
     * Lama keterlambatan dalam satuan yang masih bisa dibayangkan. "Lewat 3665
     * hari" memaksa pembacanya membagi sendiri untuk tahu itu sepuluh tahun.
     */
    public static function lamaTelat(int $n): string
    {
        if ($n < 31) {
            return "lewat {$n} hari";
        }
        if ($n < 365) {
            return 'lewat '.round($n / 30).' bulan';
        }
        $th = $n / 365;

        return 'lewat '.($th < 2 ? 'setahun' : floor($th).' tahun');
    }

    /** Nama pendek sederet satuan kerja. */
    public static function daftarPendek($satker): string
    {
        return collect($satker)->map(fn ($s) => $s->namaPendek())->join(', ');
    }

    /** Persentase bulat untuk bagian dari seluruhnya. */
    public static function bagian($a, $b): string
    {
        return $b > 0 ? round($a / $b * 100).'%' : '0%';
    }
}
