<?php

namespace App\Support;

use App\Enums\PeranPengguna;
use App\Enums\PosisiBerkas;
use App\Models\Rekomendasi;
use App\Models\Sasaran;

/**
 * Kartu berkas ditulis sebagai pekerjaan, bukan sebagai catatan.
 *
 * Yang tampil paling besar bukan bunyi rekomendasinya, melainkan **tindakan
 * yang harus dikerjakan pembacanya**: "Tinjau berkas lalu teruskan ke UKI",
 * bukan "Menagih kelebihan pembayaran…". Bunyi rekomendasinya turun jadi
 * keterangan.
 *
 * Bedanya bukan soal tata letak. Daftar yang menampilkan uraian rekomendasi
 * memaksa pembacanya menerjemahkan sendiri tiap baris jadi "apa yang harus
 * saya lakukan"; daftar yang menampilkan tindakan sudah menerjemahkannya.
 */
class Tugas
{
    /** Yang bisa dikerjakan tiap peran pada tiap posisi berkas. */
    private const AKSI = [
        'satker' => ['satker' => 'Isi tindak lanjut dan lengkapi berkas'],
        'setba' => [
            'setba_tinjau'   => 'Tinjau berkas lalu teruskan ke UKI',
            'setba_teruskan' => 'Teruskan ke Inspektorat',
            'siptl'          => 'Unggah ke SIPTL',
        ],
        'uki' => ['uki' => 'Telaah kecukupan bukti'],
        'inspektorat' => ['inspektorat' => 'Verifikasi lalu terbitkan surat'],
    ];

    /** Kalau bukan giliran kita, yang ditampilkan siapa yang sedang dinanti. */
    private const MENUNGGU = [
        'satker'         => 'Menunggu satuan kerja',
        'setba_tinjau'   => 'Menunggu ditinjau Setba',
        'uki'            => 'Menunggu telaah UKI',
        'setba_teruskan' => 'Menunggu diteruskan Setba',
        'inspektorat'    => 'Menunggu verifikasi Inspektorat',
        'tuntas'         => 'Menunggu satuan kerja lain menyusul',
        'siptl'          => 'Menunggu diunggah ke SIPTL',
        'bpk'            => 'Menunggu penilaian BPK',
        'selesai'        => 'Sudah selesai',
    ];

    /**
     * Apa yang harus dikerjakan pembacanya.
     *
     * Satuan kerja membaca dari BARISNYA SENDIRI, bukan dari rekomendasinya.
     * Rekomendasi yang dipikul tiga satker berada di tiga tahap sekaligus;
     * menyebut satu tahap untuk semuanya pasti salah bagi dua di antaranya.
     *
     * @return array{teks:string, giliranSaya:bool}
     */
    public static function label(Rekomendasi $r, PeranPengguna $peran, ?Sasaran $sasaran = null): array
    {
        $posisi = $sasaran?->posisi ?? $r->posisiTampil();
        if (! $posisi) {
            return ['teks' => 'Belum ditugaskan ke satuan kerja', 'giliranSaya' => false];
        }

        $punya = self::AKSI[$peran->value][$posisi->value] ?? null;
        if ($punya) {
            return ['teks' => $punya, 'giliranSaya' => true];
        }

        /* Gerbang tingkat 2: seluruh satuan kerja sudah tuntas, dan yang
           membukanya adalah Setba. Menyebutnya "menunggu" saja menyembunyikan
           bahwa merekalah yang harus bertindak sekarang. */
        if ($peran === PeranPengguna::SETBA
            && $posisi === PosisiBerkas::TUNTAS
            && $r->bolehMasukTingkat2()) {
            return ['teks' => 'Semua satuan kerja tuntas — siapkan unggahan SIPTL', 'giliranSaya' => true];
        }

        return ['teks' => self::MENUNGGU[$posisi->value] ?? $posisi->label(), 'giliranSaya' => false];
    }

    /**
     * Kenapa berkas ini mendesak. Null berarti tidak ada yang mendesak — dan
     * kalau tidak ada, tidak usah ditulis apa-apa.
     *
     * @return array{teks:string, nada:string}|null
     */
    public static function urgensi(Rekomendasi $r): ?array
    {
        if ($r->posisiTampil() === PosisiBerkas::SELESAI) {
            return null;
        }

        $lewat = $r->lewatTenggat();
        if ($lewat > 0) {
            return ['teks' => "lewat {$lewat} hari", 'nada' => 'genting'];
        }

        if ($r->tenggat_jawab) {
            $sisa = (int) now()->startOfDay()->diffInDays($r->tenggat_jawab->startOfDay(), false);
            if ($sisa >= 0 && $sisa <= 7) {
                return [
                    'teks' => $sisa === 0 ? 'tenggat hari ini' : "tenggat {$sisa} hari lagi",
                    'nada' => 'genting',
                ];
            }
        }

        /* Berkas yang diam berminggu-minggu belum tentu lewat tenggat, tapi
           tetap perlu ditengok — kerap ia tersangkut, bukan sedang dikerjakan. */
        $diam = self::diamnya($r);
        if ($diam > 21) {
            return ['teks' => "diam {$diam} hari", 'nada' => 'awas'];
        }

        return null;
    }

    /** Berapa hari sejak berkas ini terakhir bergerak. */
    public static function diamnya(Rekomendasi $r): int
    {
        $akhir = $r->riwayat->last()?->waktu;
        return $akhir ? (int) $akhir->startOfDay()->diffInDays(now()->startOfDay()) : 0;
    }

    /**
     * Apa yang masih kurang, disebut ringkas. Kosong berarti tidak ada.
     *
     * Menerima sasaran supaya satuan kerja membaca kekurangan BAGIANNYA — sisa
     * tagihan seluruh rekomendasi bukan urusannya, dan menampilkannya membuat
     * ia mengira harus menyetor uang satuan kerja lain.
     */
    public static function kurang(Rekomendasi $r, ?Sasaran $sasaran = null): string
    {
        $bagian = [];
        $sumber = $sasaran ?? $r;

        $d = $sumber->progresDokumen();
        if ($d && $d[0] < $d[1]) {
            $bagian[] = ($d[1] - $d[0]) . ' dokumen belum ada';
        }

        $sisa = $sumber->sisaPemulihan();
        if ($sisa > 0) {
            $bagian[] = 'sisa ' . Tampil::rupiahSingkat($sisa);
        }

        /* Satuan kerja yang belum ditandai memadai, disebut hanya kepada
           pengawas — bagi satuan kerja sendiri, jumlah itu bukan urusannya. */
        if ($sasaran === null) {
            [$ok, $total] = $r->hitungMemadai();
            if ($total > 1 && $ok < $total) {
                $bagian[] = ($total - $ok) . ' dari ' . $total . ' satker belum memadai';
            }
        }

        return implode(' · ', $bagian);
    }
}
