<?php

namespace App\Support;

use App\Enums\PeranPengguna;
use App\Models\Notifikasi;
use App\Models\Rekomendasi;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Menulis dan membaca pemberitahuan.
 *
 * `blok` menunjuk bagian mana di halaman rincian yang berubah karena tindakan
 * ini. Tanpa itu kabar hanya bisa mengantar ke halamannya, dan pembacanya
 * menyusuri sendiri halaman panjang untuk menemukan apa yang dikabarkan.
 */
class Kabar
{
    /* Nama bagian pada halaman rincian, dipakai sebagai keterangan tujuan pada
       baris kabar. Namanya sama persis dengan judul di halaman itu — kalau
       berbeda, orang mengira sampai di tempat yang salah. */
    public const BAGIAN = [
        'r-kepala'       => 'Kepala rekomendasi',
        'r-tindaklanjut' => 'Rekam jejak tindak lanjut',
        'r-kembali'      => 'Pernah dikembalikan',
        'r-dokumen'      => 'Kelengkapan dokumen yang diminta',
        'r-pemulihan'    => 'Pemulihan nilai',
        'r-telaah'       => 'Hasil telaah',
        'r-verifikasi'   => 'Riwayat verifikasi',
        'r-jejak'        => 'Jejak perpindahan berkas',
    ];

    /**
     * @param  list<PeranPengguna>  $untuk  peran yang berwenang atau berkepentingan
     */
    public static function tulis(Rekomendasi $r, string $aksi, array $untuk, ?string $blok = null): Notifikasi
    {
        $pelaku = auth()->user();

        $kabar = Notifikasi::create([
            'rekomendasi_id' => $r->id,
            'waktu' => now(),
            'label_pelaku' => $pelaku?->peran === PeranPengguna::SATKER
                ? ($pelaku->satker?->namaPendek() ?? 'Satuan kerja')
                : ($pelaku?->peran->nama() ?? 'Sistem'),
            'aksi' => $aksi,
            'untuk_peran' => array_map(fn ($p) => $p->value, $untuk),
            /* Kabar ditujukan ke satuan kerja tertentu hanya kalau
               rekomendasinya memang cuma menyangkut satu. Rekomendasi yang
               dipikul beramai-ramai dikabarkan ke semuanya — menunjuk salah
               satu berarti yang lain tidak pernah tahu. */
            'satker_id' => $r->daftarSasaran()->pluck('satker_id')->unique()->count() === 1
                ? $r->daftarSasaran()->first()?->satker_id
                : null,
            'blok' => $blok,
        ]);

        /* Yang mengerjakannya sudah tahu — kabar itu untuk pihak lain. Tanpa
           ini tiap tindakan memantulkan lonceng balik ke wajah orang yang baru
           saja menekan tombolnya. */
        if ($pelaku) {
            $kabar->dibaca()->attach($pelaku->id, ['dibaca_pada' => now()]);
        }

        return $kabar;
    }

    /** Kabar yang menyangkut pengguna ini, terbaru dulu. */
    public static function untuk(User $u, int $batas = 60): Collection
    {
        return Notifikasi::with('rekomendasi.temuan.laporan', 'satker')
            ->whereJsonContains('untuk_peran', $u->peran->value)
            ->when($u->peran === PeranPengguna::SATKER,
                fn ($q) => $q->where('satker_id', $u->satker_id))
            ->orderByDesc('waktu')
            ->limit($batas)
            ->get();
    }

    public static function belumDibaca(User $u): int
    {
        return Notifikasi::whereJsonContains('untuk_peran', $u->peran->value)
            ->when($u->peran === PeranPengguna::SATKER,
                fn ($q) => $q->where('satker_id', $u->satker_id))
            ->whereDoesntHave('dibaca', fn ($q) => $q->where('users.id', $u->id))
            ->count();
    }
}
