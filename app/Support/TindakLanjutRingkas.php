<?php

namespace App\Support;

use App\Enums\HasilTelaah;
use App\Enums\PeranPengguna;
use App\Models\Rekomendasi;

/**
 * Kemajuan tindak lanjut sebuah rekomendasi, untuk kolom "Kemajuan" di tabel
 * Rekomendasi dan tabel rekomendasi halaman laporan, dan kartu "Posisi tindak
 * lanjut" halaman laporan.
 *
 * Kedua tabel dulu menulis satu "Posisi berkas" dan satu "Status" untuk
 * seluruh rekomendasi. Kata Hizkia (15 Sep): posisi berkas bisa berbeda pada
 * tiap satuan kerja dan tiap tindak lanjut, dan yang diproses hanya tindak
 * lanjutnya — rekomendasi tidak ditetapkan statusnya. Penggantinya menyebut
 * berapa tindak lanjut yang sudah selesai, dan rangkuman yang DIHITUNG.
 *
 * Posisi tiap satuan kerja tidak ditempel di kolomnya: kata Hizkia, kolomnya
 * jadi terlalu padat. Itu dibaca di halaman rincian.
 */
class TindakLanjutRingkas
{
    /** Sebutan meja yang cukup pendek untuk keping. */
    public const MEJA_SINGKAT = ['Satuan kerja' => 'Satker', 'BPK / SIPTL' => 'BPK'];

    public const URUT_MEJA = ['Satker', 'Setba', 'UKI', 'Inspektorat', 'BPK', 'Selesai'];

    public const KETERANGAN = [
        'Berapa tindak lanjut yang sudah selesai, dihitung per satuan kerja: satuan kerja yang memikul dua tindak lanjut terhitung dua. LHP selesai kalau BPK menyatakan Sudah sesuai. LHA selesai kalau Inspektorat menyatakan Sesuai.',
        'Rangkuman di bawahnya tidak ditetapkan siapa pun, melainkan dihitung dari tindak lanjutnya. LHP: status BPK yang paling belakang yang menentukan, dan tindak lanjut yang belum diunggah terhitung BT, sama dengan kolom Max Rank Status SiPTL di lembar pemantauan. LHA: Sesuai hanya kalau seluruh satuan kerjanya sesuai.',
    ];

    /**
     * Satu butir per satuan kerja dan tindak lanjut yang terlihat: di meja
     * mana, dan sudah selesai atau belum.
     *
     * @return list<array{key:string, meja:string, selesai:bool}>
     */
    public static function daftar(Rekomendasi $r, PeranPengguna $peran, ?int $satkerId): array
    {
        $jenis = $r->jenis();

        return $r->barisTerlihat($peran, $satkerId)->map(function ($x) use ($jenis) {
            $info = $x->presentasi($jenis);

            return [
                'key'     => $x->satker_id.'|'.$x->tindakan_id,
                'meja'    => self::MEJA_SINGKAT[$info['pemegang']] ?? $info['pemegang'],
                'selesai' => $info['selesai'],
            ];
        })->values()->all();
    }

    /**
     * Berapa yang selesai, dan rangkumannya. LHP memakai status BPK yang paling
     * belakang, dengan baris belum diunggah terhitung BT; LHA "Sesuai" hanya
     * kalau seluruh barisnya sesuai. Satuan kerja membaca rangkuman bagiannya
     * sendiri.
     *
     * @return array{selesai:int, dari:int, rangkuman:string}
     */
    public static function kemajuan(Rekomendasi $r, PeranPengguna $peran, ?int $satkerId): array
    {
        $daftar = self::daftar($r, $peran, $satkerId);
        $baris = $r->barisTerlihat($peran, $satkerId);

        if ($r->jenis()->melewatiSiptl()) {
            $rangkuman = $r->semuaBaris()->contains(fn ($b) => $b->status_bpk !== null)
                ? Rekomendasi::rangkumBpk($baris->map(fn ($b) => $b->status_bpk))->value
                : ($r->status?->value ?? 'BT');
        } else {
            $rangkuman = $baris->isNotEmpty() && $baris->every(fn ($b) => $b->hasil === HasilTelaah::M) ? 'M' : 'BM';
        }

        return [
            'selesai'   => count(array_filter($daftar, fn ($x) => $x['selesai'])),
            'dari'      => count($daftar),
            'rangkuman' => $rangkuman,
        ];
    }

    /** Bagian yang sudah selesai, untuk urutan kolom Kemajuan. */
    public static function bagian(Rekomendasi $r, PeranPengguna $peran, ?int $satkerId): float
    {
        $k = self::kemajuan($r, $peran, $satkerId);

        return $k['dari'] ? $k['selesai'] / $k['dari'] : 0;
    }
}
