<?php

namespace App\Support;

use App\Enums\PeranPengguna;
use App\Models\DrafLaporan;
use App\Models\User;

/**
 * Isi bingkai halaman: angka di menu, kabar yang belum dibaca, dan kabar yang
 * disembulkan. Dihitung sekali per permintaan, dari daftar yang sudah disaring
 * hak aksesnya — padanan `perluKerja`, `belumDibaca`, dan `sembul` di App
 * prototipe.
 */
class Rangka
{
    private const KUNCI_SEMBUL = 'kabar_disembulkan';

    public static function untuk(User $u): array
    {
        static $isi = [];

        return $isi[$u->id] ??= self::hitung($u);
    }

    private static function hitung(User $u): array
    {
        $lingkup = Lingkup::dari();
        $terlihat = Terlihat::untuk($u);

        /* Angka menu Rekomendasi = keranjang "Perlu saya kerjakan" — rumus yang
           sama, supaya angka yang menuntun orang masuk tidak berbeda dari isi
           keranjangnya. */
        $perluKerja = $terlihat->rekomendasi()
            ->with(['sasaran', 'temuan.laporan'])
            ->get()
            ->filter(fn ($r) => Lingkup::berlaku($lingkup, $r->jenis()))
            ->map(fn ($r) => $terlihat->pangkasRekomendasi($r))
            ->filter(fn ($r) => $r->diMeja($u->peran, $u->satker_id))
            ->count();

        $belum = Kabar::belumDibaca($u);

        return [
            'perluKerja' => $perluKerja,
            'belumDibaca' => $belum,
            'sembul' => self::sembul($u, $belum),
            'drafLaporan' => $u->peran === PeranPengguna::SETBA
                && DrafLaporan::where('user_id', $u->id)->exists(),
        ];
    }

    /**
     * Kabar baru yang belum pernah disembulkan dalam sesi ini. Satu kabar
     * ditampilkan isinya; lebih dari satu cukup jumlahnya.
     */
    private static function sembul(User $u, int $belum): ?array
    {
        if (! $belum || request()->routeIs('kabar')) {
            return null;
        }

        $sudah = session(self::KUNCI_SEMBUL, []);
        $baru = Kabar::untuk($u)
            ->reject(fn ($k) => Kabar::sudahDibaca($k, $u))
            ->reject(fn ($k) => in_array($k->id, $sudah, true))
            ->values();
        if ($baru->isEmpty()) {
            return null;
        }

        session([self::KUNCI_SEMBUL => array_merge($sudah, $baru->pluck('id')->all())]);

        return ['kabar' => $baru->first(), 'jumlah' => $baru->count()];
    }

    /** Sebutan akun di kanan batang atas dan di kaki menu, seperti prototipe. */
    public static function pengguna(User $u): array
    {
        return match ($u->peran) {
            PeranPengguna::SETBA       => ['rupa' => 'SE', 'nama' => 'Setba', 'ket' => 'Sekretariat Badan', 'peran' => 'Setba — Sekretariat Badan'],
            PeranPengguna::SATKER      => ['rupa' => 'SA', 'nama' => $u->satker?->namaPendek() ?? 'Satuan kerja', 'ket' => 'Satuan kerja', 'peran' => 'Satuan kerja'],
            PeranPengguna::UKI         => ['rupa' => 'UK', 'nama' => 'UKI', 'ket' => 'Unit Kepatuhan Internal', 'peran' => 'UKI — Unit Kepatuhan Internal'],
            PeranPengguna::INSPEKTORAT => ['rupa' => 'IN', 'nama' => 'Inspektorat', 'ket' => 'Pengguna', 'peran' => 'Inspektorat'],
            PeranPengguna::PIMPINAN    => ['rupa' => 'PI', 'nama' => 'Pimpinan', 'ket' => 'Pengguna', 'peran' => 'Pimpinan'],
            PeranPengguna::ADMIN       => ['rupa' => 'AD', 'nama' => 'Admin', 'ket' => 'Administrator', 'peran' => 'Administrator'],
        };
    }
}
