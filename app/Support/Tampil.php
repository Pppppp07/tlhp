<?php

namespace App\Support;

use App\Enums\PosisiBerkas;
use App\Enums\SumberLaporan;
use App\Models\Rekomendasi;

/** Pembantu tampilan. Tidak ada aturan bisnis di sini — hanya cara menuliskan. */
class Tampil
{
    public static function rupiah(?int $n): string
    {
        return $n ? 'Rp ' . number_format($n, 0, ',', '.') : '—';
    }

    public static function rupiahSisa(?int $n): string
    {
        return (int) $n === 0 ? 'lunas' : self::rupiah($n);
    }

    public static function rupiahSingkat(?int $n): string
    {
        if (! $n) return 'Rp 0';
        if ($n >= 1_000_000_000) return 'Rp ' . rtrim(rtrim(number_format($n / 1e9, 1, ',', '.'), '0'), ',') . ' M';
        if ($n >= 1_000_000) return 'Rp ' . number_format(round($n / 1e6), 0, ',', '.') . ' jt';
        return self::rupiah($n);
    }

    /**
     * Tanggal boleh datang sebagai Carbon dari model, atau sebagai teks
     * "2026-08-05" dari isian yang masih tersimpan di sesi. Keduanya
     * diterima — pemanggilnya tidak perlu ingat yang mana.
     */
    public static function tgl($t): string
    {
        if (! $t) {
            return '—';
        }
        if (is_string($t)) {
            $t = \Carbon\Carbon::parse($t);
        }

        return $t->format('d') . ' ' . self::BULAN[(int) $t->format('n') - 1] . ' ' . $t->format('Y');
    }

    /* Nama bulan ditulis sendiri, tidak lewat locale Carbon. Dua alasan:
       locale-nya bergantung setelan aplikasi dan diam-diam berubah jadi
       "Aug" kalau setelannya belum dipasang, dan singkatan Carbon untuk
       Agustus adalah "Agt" sementara prototipe memakai "Agu". Bedanya satu
       huruf, tapi ia muncul di ratusan tempat. */
    private const BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
                           'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    /** Rel posisi. Jalur LHA lima tahap, LHP enam. */
    /** Nama tiap tahap menurut jalurnya. LHP lewat SIPTL dan penilaian BPK,
        jadi punya satu tahap lebih panjang daripada LHA. */
    public static function tahap(SumberLaporan $sumber): array
    {
        $dasar = ['Registrasi', 'Tanggapan satuan kerja', 'Telaah UKI',
            'Verifikasi Inspektorat', 'Surat & penetapan'];

        return $sumber->melewatiSiptl()
            ? [...$dasar, 'Penyampaian ke BPK']
            : $dasar;
    }

    public static function rel(Rekomendasi $r, SumberLaporan $sumber): array
    {
        $total = $sumber->melewatiSiptl() ? 6 : 5;
        /* Dibaca dari posisi yang tampil. Kolom posisi rekomendasi bernilai
           NULL selama tingkat 1 berjalan, dan untuk rekomendasi yang dipikul
           beberapa satuan kerja yang disebut adalah yang paling tertinggal —
           itu yang menentukan seberapa jauh relnya berjalan. */
        $posisi = $r->posisiTampil();
        $kini = $posisi?->tahap() ?? 1;
        $out = [];
        for ($i = 1; $i <= $total; $i++) {
            if ($posisi === PosisiBerkas::SELESAI) {
                $out[] = match ($r->status->value) {
                    'SS' => 'ok', 'TD' => 'off', default => 'bad',
                };
            } elseif ($i < $kini) {
                $out[] = 'lewat';
            } elseif ($i === $kini) {
                $out[] = $r->status->value === 'BS' ? 'bad' : 'kini';
            } else {
                $out[] = '';
            }
        }
        return $out;
    }
}
