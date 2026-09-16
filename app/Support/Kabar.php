<?php

namespace App\Support;

use App\Enums\PeranPengguna;
use App\Models\Notifikasi;
use App\Models\Rekomendasi;
use App\Models\Tindakan;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Menulis dan membaca pemberitahuan — padanan `kabari` dan `notifSaya`
 * prototipe.
 *
 * `blok` menunjuk bagian mana di halaman rincian yang berubah karena tindakan
 * ini. Tanpa itu kabar hanya bisa mengantar ke halamannya, dan pembacanya
 * menyusuri sendiri halaman panjang untuk menemukan apa yang dikabarkan.
 */
class Kabar
{
    /* Nama bagian pada halaman rincian, dipakai sebagai keterangan tujuan pada
       baris kabar. Namanya sama persis dengan judul di halaman itu — sama
       dengan `NAMA_BAGIAN` prototipe. Kabar SIPTL (`r-siptl`) sengaja tanpa
       keterangan tujuan, seperti prototipe: menekannya tetap membuka berkasnya
       dan menunjuk bagiannya. */
    public const BAGIAN = [
        'r-kepala'       => 'Kepala rekomendasi',
        'r-perkembangan' => 'Perkembangan',
        'r-tindaklanjut' => 'Laporan satuan kerja',
        'r-dokumen'      => 'Perkembangan tiap satuan kerja',
        'r-pemulihan'    => 'Pemulihan nilai',
        'r-riwayat'      => 'Riwayat status tindak lanjut',
        'r-ulang'        => 'Tindak lanjut ulang atas penolakan BPK',
        'r-tindakan'     => 'Tindakan',
        'r-arsip'        => 'Arsip rekomendasi',
        'r-jejak'        => 'Riwayat aktivitas',
    ];

    /**
     * @param  list<PeranPengguna>  $untuk      peran yang berwenang atau berkepentingan
     * @param  list<int>|null       $satkerIds  satuan kerja yang dikabari; kosong = seluruh satuan kerja rekomendasinya
     * @param  bool                 $sistem     dikirim perintah terjadwal — belum dibaca siapa pun
     */
    public static function tulis(Rekomendasi $r, string $aksi, array $untuk, ?string $blok = null,
        ?array $satkerIds = null, ?Tindakan $tindakan = null, bool $sistem = false,
        ?string $pelaku = null): Notifikasi
    {
        $pengguna = $sistem ? null : auth()->user();

        $kabar = Notifikasi::create([
            'rekomendasi_id' => $r->id,
            /* Bentuk tindak lanjutnya disebut hanya kalau rekomendasinya punya
               lebih dari satu — kalau cuma satu, menyebutnya menambah bacaan. */
            'tindakan_id'    => $tindakan && $r->tindakan()->count() > 1 ? $tindakan->id : null,
            'waktu'          => now(),
            'label_pelaku'   => $pelaku ?? self::labelPelaku($pengguna),
            'aksi'           => $aksi,
            'untuk_peran'    => array_values(array_unique(array_map(fn ($p) => $p->value, $untuk))),
            'blok'           => $blok,
        ]);

        $satker = $satkerIds ?? $r->semuaBaris()->pluck('satker_id')->unique()->values()->all();
        $kabar->satker()->attach(array_values(array_unique($satker)));

        /* Yang mengerjakannya sudah tahu — kabar itu untuk pihak lain. Kiriman
           otomatis tidak punya pelaku, jadi belum terbaca oleh siapa pun
           (W16 prototipe). */
        if ($pengguna) {
            $kabar->dibaca()->attach($pengguna->id, ['dibaca_pada' => now()]);
        }

        return $kabar;
    }

    public static function labelPelaku(?User $u): string
    {
        if (! $u) {
            return 'Sistem';
        }

        return $u->peran === PeranPengguna::SATKER
            ? ($u->satker?->namaPendek() ?? 'Satuan kerja')
            : $u->peran->pendek();
    }

    /** Kabar yang menyangkut pengguna ini, terbaru dulu. */
    public static function untuk(User $u): Collection
    {
        return self::kueri($u)
            ->with('rekomendasi.temuan.laporan', 'satker', 'tindakan.bentuk', 'dibaca')
            ->orderByDesc('waktu')->orderByDesc('id')
            ->get();
    }

    public static function belumDibaca(User $u): int
    {
        return self::kueri($u)
            ->whereDoesntHave('dibaca', fn ($q) => $q->where('users.id', $u->id))
            ->count();
    }

    public static function sudahDibaca(Notifikasi $n, User $u): bool
    {
        return $n->dibaca->contains('id', $u->id);
    }

    /** Kabar ini memang ditujukan kepada pengguna ini. */
    public static function boleh(Notifikasi $n, User $u): bool
    {
        return self::kueri($u)->whereKey($n->id)->exists();
    }

    /**
     * Satuan kerja yang disebut pada baris kabar. Satuan kerja hanya melihat
     * namanya sendiri — kabar bersama tidak boleh membocorkan siapa lagi yang
     * kebagian.
     */
    public static function sebutSatker(Notifikasi $n, User $u): string
    {
        $satker = $u->peran === PeranPengguna::SATKER
            ? $n->satker->where('id', $u->satker_id)
            : $n->satker;

        return $satker->map(fn ($s) => $s->namaPendek())->join(', ') ?: '—';
    }

    /**
     * Satuan kerja hanya menerima kabar yang menyebut satuan kerjanya. Admin
     * membaca apa yang dibaca Setba.
     */
    private static function kueri(User $u)
    {
        $peran = $u->peran === PeranPengguna::ADMIN ? PeranPengguna::SETBA : $u->peran;

        return Notifikasi::query()
            ->whereJsonContains('untuk_peran', $peran->value)
            ->when($u->peran === PeranPengguna::SATKER,
                fn ($q) => $q->whereHas('satker', fn ($s) => $s->where('satkers.id', $u->satker_id)));
    }
}
