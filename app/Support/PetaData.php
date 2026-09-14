<?php

namespace App\Support;

use App\Enums\HasilTelaah;
use App\Enums\PosisiBerkas;
use App\Enums\StatusTindakLanjut;
use App\Enums\SumberLaporan;
use Illuminate\Support\Collection;

/**
 * Peta data: mengelompokkan rekomendasi menurut sumbu apa pun, menghitung
 * ukuran apa pun. Dipakai halaman Ringkasan supaya pembacanya bisa bertanya
 * sendiri — bukan hanya melihat empat angka yang sudah dipilihkan.
 *
 * Pilihannya dibawa lewat alamat halaman, bukan disimpan di peramban. Dengan
 * begitu tampilan yang sedang dilihat bisa disalin dan dikirim ke orang lain
 * apa adanya, dan halaman ini tetap bekerja tanpa JavaScript sama sekali.
 */
class PetaData
{
    /** Sumbu pengelompokan: menjawab "dikelompokkan menurut apa". */
    public static function dimensi(): array
    {
        return [
            'posisi' => [
                'nama' => 'Posisi berkas',
                'ket' => 'Di meja siapa berkasnya menunggu sekarang.',
                /* posisiTampil(), bukan kolom posisi mentah: kolom itu
                   bernilai NULL selama tingkat 1 berjalan, dan membacanya
                   mentah menjatuhkan seluruh peta ke satu kelompok kosong. */
                'kunci' => fn ($r) => $r->posisiTampil()?->value ?? '—',
                'label' => fn ($k) => $k === '—' ? 'Belum ditugaskan' : PosisiBerkas::from($k)->label(),
                'alami' => array_map(fn ($p) => $p->value, PosisiBerkas::cases()),
            ],
            'status' => [
                'nama' => 'Status',
                'ket' => 'Keadaan resmi rekomendasi menurut Peraturan BPK 2/2017.',
                'kunci' => fn ($r) => $r->status->value,
                'label' => fn ($k) => $k . ' · ' . StatusTindakLanjut::from($k)->nama(),
                'alami' => ['BT', 'BS', 'SS', 'TD'],
            ],
            'satker' => [
                'nama' => 'Satuan kerja penanggung jawab',
                'ket' => 'Satuan kerja yang harus mengerjakan rekomendasinya.',
                /* Satu rekomendasi bisa dipikul beberapa satuan kerja, jadi
                   ia memang masuk beberapa kelompok sekaligus. Panelnya sudah
                   memperingatkan kalau jumlah kelompok melebihi angka
                   sebenarnya — peringatan itu justru untuk keadaan ini. */
                'kunci' => fn ($r) => $r->daftarSasaran()->first()?->satker?->kode ?? '—',
                'label' => fn ($k, $c) => $c->first()?->daftarSasaran()->first()?->satker?->namaPendek() ?? $k,
            ],
            'terperiksa' => [
                'nama' => 'Satuan kerja diperiksa',
                'ket' => 'Satuan kerja tempat temuannya terjadi — belum tentu yang menanggung perbaikannya.',
                'kunci' => fn ($r) => $r->temuan->satkerPertama()?->kode ?? '—',
                'label' => fn ($k, $c) => $c->first()?->temuan?->satkerPertama()?->namaPendek() ?? $k,
            ],
            'bentuk' => [
                'nama' => 'Bentuk tindak lanjut',
                'ket' => 'Jenis tindakan yang diminta rekomendasi, mengikuti SOP.',
                'kunci' => fn ($r) => $r->bentuk?->nama ?? 'Tidak diisi',
            ],
            'kategori' => [
                'nama' => 'Kategori pemeriksa',
                'ket' => 'Kategori yang ditulis pemeriksa sendiri di suratnya.',
                'kunci' => fn ($r) => $r->temuan->kategori?->nama ?? 'Tidak diisi',
            ],
            'intern' => [
                'nama' => 'Kategori internal',
                'ket' => 'Pengelompokan BPSDM sendiri, dipakai untuk rekap ke dalam.',
                'kunci' => fn ($r) => $r->temuan->kategoriIntern?->nama ?? 'Lainnya',
            ],
            'sumber' => [
                'nama' => 'Sumber laporan',
                'ket' => 'Dari BPK lewat LHP, atau dari Inspektorat lewat LHA.',
                'kunci' => fn ($r) => $r->temuan->laporan->sumber->value,
                'label' => fn ($k) => $k . ' · ' . SumberLaporan::from($k)->nama(),
                'alami' => ['LHP', 'LHA'],
            ],
            /* Dua sudut pandang atas hal yang sama, dan angkanya memang
               berbeda. Mbak Puspi: "yang status SS di SIPTL 88, versi Unor kita
               tuh udah selesai 117." Karena satu rekomendasi BPK bisa
               menyangkut beberapa Unor, status SIPTL bisa Belum Sesuai gara-gara
               Unor lain — sedangkan bagian BPSDM sudah beres. */
            'verifikasi' => [
                'nama' => 'Status verifikasi',
                'ket' => 'Keadaan menurut BPSDM: memadai kalau seluruh satuan kerja pada rekomendasi itu sudah memadai. Kode BS, BT, dan SS milik BPK.',
                'kunci' => fn ($r) => $r->keadaanUnor()->value,
                'label' => fn ($k, $c) => HasilTelaah::from($k)
                    ->nama($c->first()?->temuan?->laporan?->sumber),
                'alami' => ['M', 'BM'],
            ],
            /* Baris paling bawah rekap mereka, dan yang paling banyak
               bercerita: pada lembar 2005–2025 hampir seluruh tahun lama sudah
               lunas, sementara 18 dari 20 rekomendasi yang belum
               ditindaklanjuti menumpuk di tahun terakhir. Pita bulan tidak bisa
               menunjukkan itu — yang ditanya orang "temuan tahun berapa". */
            'tahun' => [
                'nama' => 'Tahun LHP',
                'ket' => 'Tahun surat pemeriksaannya, sama dengan tahun pada Ref IDT.',
                'kunci' => fn ($r) => optional($r->temuan->laporan->tgl_surat)->format('Y') ?? '—',
            ],
            'telat' => [
                'nama' => 'Keterlambatan',
                'ket' => 'Berapa lama tenggat menjawab sudah terlewat.',
                'kunci' => fn ($r) => self::pitaTelat($r),
                'alami' => ['Belum jatuh tempo', 'Lewat 1–30 hari', 'Lewat 31–90 hari',
                    'Lewat 91–180 hari', 'Lewat di atas 180 hari', 'Tanpa tenggat'],
            ],
        ];
    }

    private static function pitaTelat($r): string
    {
        if ($r->posisi === PosisiBerkas::SELESAI || ! $r->tenggat_jawab) {
            return 'Tanpa tenggat';
        }
        $n = $r->lewatTenggat();
        if ($n === 0)   return 'Belum jatuh tempo';
        if ($n <= 30)   return 'Lewat 1–30 hari';
        if ($n <= 90)   return 'Lewat 31–90 hari';
        if ($n <= 180)  return 'Lewat 91–180 hari';
        return 'Lewat di atas 180 hari';
    }

    /**
     * Yang dihitung. Nilai temuan sengaja dihitung per temuan, bukan per
     * rekomendasi: satu temuan bisa melahirkan empat rekomendasi, dan
     * menjumlahkannya empat kali melipatgandakan angka yang dilaporkan ke atas.
     */
    public static function ukuran(): array
    {
        return [
            'jumlah' => [
                'nama' => 'Jumlah rekomendasi',
                'ket' => 'Berapa banyak rekomendasi yang masuk kelompok itu.',
                'hitung' => fn (Collection $g) => $g->count(),
                'uang' => false, 'satuan' => 'rekomendasi',
            ],
            'temuan' => [
                'nama' => 'Jumlah temuan',
                'ket' => 'Temuan dihitung sekali, walau melahirkan beberapa rekomendasi.',
                'hitung' => fn (Collection $g) => $g->pluck('temuan_id')->unique()->count(),
                'uang' => false, 'satuan' => 'temuan',
            ],
            'telat' => [
                'nama' => 'Jumlah lewat tenggat',
                'ket' => 'Rekomendasi yang tenggat menjawabnya sudah terlewat.',
                'hitung' => fn (Collection $g) => $g->filter(fn ($r) => $r->lewatTenggat() > 0)->count(),
                'uang' => false, 'satuan' => 'rekomendasi',
            ],
            'nilaiTemuan' => [
                'nama' => 'Nilai temuan',
                'ket' => 'Jumlah nilai temuan, dihitung sekali per temuan.',
                'hitung' => fn (Collection $g) => self::nilaiTemuanUnik($g),
                'uang' => true, 'satuan' => 'rupiah',
            ],
            'tagihan' => [
                'nama' => 'Tagihan pemulihan',
                'ket' => 'Bagian nilai temuan yang memang harus disetor ke kas negara.',
                'hitung' => fn (Collection $g) => (int) $g->sum('nilai_pulih'),
                'uang' => true, 'satuan' => 'rupiah',
            ],
            'masuk' => [
                'nama' => 'Sudah dipulihkan',
                'ket' => 'Yang sudah benar-benar masuk kas negara.',
                'hitung' => fn (Collection $g) => (int) $g->sum(fn ($r) => $r->nilaiTerpulihkan()),
                'uang' => true, 'satuan' => 'rupiah',
            ],
            /* Tiga "sisa" yang berbeda, dan bedanya penting.

               Yang pertama menghitung uang yang belum masuk kas. Dua yang lain
               menghitung nilai yang belum DIAKUI — dan itu tidak sama:
               rekomendasi yang uangnya sudah lunas tapi buktinya ditolak
               sebagian tetap punya sisa nilai.

               Kolom "Sisa Nilai" di lembar pemantauan mereka yang dimaksud dua
               yang terakhir. */
            'belumSetor' => [
                'nama' => 'Belum disetor',
                'ket' => 'Tagihan dikurangi uang yang sudah masuk kas negara.',
                'hitung' => fn (Collection $g) => (int) $g->sum(fn ($r) => $r->sisaPemulihan()),
                'uang' => true, 'satuan' => 'rupiah',
            ],
            'sisaItjen' => [
                'nama' => 'Sisa nilai · Inspektorat',
                'ket' => 'Nilai rekomendasi dikurangi yang sudah dinilai memadai. Bisa tersisa walau uangnya sudah lunas — buktinya yang belum diterima seluruhnya.',
                'hitung' => fn (Collection $g) => (int) $g->sum(fn ($r) => $r->sisaNilaiItjen()),
                'uang' => true, 'satuan' => 'rupiah',
            ],
            'sisaSiptl' => [
                'nama' => 'Sisa nilai · SIPTL',
                'ket' => 'Nilai rekomendasi dikurangi yang sudah diakui BPK. Hanya berlaku untuk LHP.',
                'hitung' => fn (Collection $g) => (int) $g->sum(fn ($r) => $r->sisaNilaiBpk()),
                'uang' => true, 'satuan' => 'rupiah',
            ],
            /* Dipakai grafik "Belum Selesai per Satker" pada lembar mereka, dan
               butir catatannya menyebut gunanya: "Fokus utama dapat diarahkan
               pada Satker dengan backlog 'Belum Selesai' tertinggi." */
            'belumMemadai' => [
                'nama' => 'Belum memadai',
                'ket' => 'Rekomendasi yang belum seluruh satuan kerjanya memadai.',
                'hitung' => fn (Collection $g) => $g->reject(fn ($r) => $r->keadaanUnor()->memadai())->count(),
                'uang' => false, 'satuan' => 'rekomendasi',
            ],
            'penugasan' => [
                'nama' => 'Jumlah penugasan',
                'ket' => 'Satu satuan kerja pada satu bentuk tindak lanjut. Satu rekomendasi yang dipikul empat satuan kerja berisi empat penugasan.',
                'hitung' => fn (Collection $g) => (int) $g->sum(fn ($r) => $r->daftarSasaran()->count()),
                'uang' => false, 'satuan' => 'penugasan',
            ],
        ];
    }

    public static function nilaiTemuanUnik(Collection $g): int
    {
        return (int) $g->unique('temuan_id')->sum(fn ($r) => $r->temuan->nilai);
    }

    public static function bentukTampil(): array
    {
        /* Donat tidak ditawarkan: ia butuh delapan warna identitas yang saling
           terbedakan, sedangkan palet sistem ini sengaja hanya punya empat warna
           keadaan. Menambah delapan warna karangan akan mengubah wajah seluruh
           aplikasi demi satu bentuk grafik. */
        return [
            'batang' => 'Batang',
            'tumpuk' => 'Batang terbelah status',
            'tabel'  => 'Tabel',
        ];
    }

    public static function urutTampil(): array
    {
        return ['nilai' => 'Terbesar dulu', 'alami' => 'Urutan bakunya', 'nama' => 'Menurut abjad'];
    }

    /**
     * Mengelompokkan sekali, dipakai semua bentuk tampilan. Pecahan statusnya
     * ikut dihitung walau bentuknya belum tentu memakainya — harganya murah dan
     * mengganti bentuk jadi tidak perlu menghitung ulang.
     */
    public static function ringkas(Collection $daftar, string $dim, string $ukur, string $urut): array
    {
        $D = self::dimensi()[$dim] ?? self::dimensi()['posisi'];
        $U = self::ukuran()[$ukur] ?? self::ukuran()['jumlah'];

        $baris = $daftar->groupBy($D['kunci'])->map(function (Collection $g, $kunci) use ($D, $U) {
            $label = isset($D['label'])
                ? (new \ReflectionFunction($D['label']))->getNumberOfParameters() > 1
                    ? ($D['label'])($kunci, $g)
                    : ($D['label'])($kunci)
                : (string) $kunci;

            return [
                'kunci' => (string) $kunci,
                'nama' => $label,
                'nilai' => ($U['hitung'])($g),
                'pecah' => collect(['BT', 'BS', 'SS', 'TD'])
                    ->map(fn ($s) => ['s' => $s, 'n' => ($U['hitung'])($g->filter(fn ($r) => $r->status->value === $s))])
                    ->filter(fn ($x) => $x['n'] > 0)->values()->all(),
            ];
        })->values();

        $baris = match ($urut) {
            'nama'  => $baris->sortBy('nama', SORT_NATURAL | SORT_FLAG_CASE)->values(),
            'alami' => isset($D['alami'])
                ? $baris->sortBy(fn ($b) => array_search($b['kunci'], $D['alami'], true))->values()
                : $baris->sortByDesc('nilai')->values(),
            default => $baris->sortByDesc('nilai')->values(),
        };

        /* `total` = jumlah kelompoknya, dipakai menghitung bagian supaya
           bagiannya genap seratus persen. `sebenarnya` = ukuran yang sama
           dihitung ulang atas seluruh daftar. Keduanya berbeda kalau satu temuan
           masuk dua kelompok sekaligus — misalnya temuannya satu, tapi
           rekomendasinya jatuh ke dua satuan kerja. */
        return [
            'baris' => $baris,
            'total' => (int) $baris->sum('nilai'),
            'sebenarnya' => ($U['hitung'])($daftar),
            'maks' => max(1, (int) $baris->max('nilai')),
            'D' => $D, 'U' => $U,
        ];
    }

    public static function bagian(int|float $a, int|float $b): string
    {
        return $b > 0 ? round($a / $b * 100) . '%' : '0%';
    }
}
