<?php

namespace App\Support;

use App\Enums\HasilTelaah;
use App\Enums\PosisiBerkas;
use App\Enums\StatusTindakLanjut;
use App\Enums\SumberLaporan;
use App\Enums\WarnaLabel;
use App\Models\Rekomendasi;
use Illuminate\Support\Collection;

/**
 * Peta data: mengelompokkan rekomendasi menurut sumbu apa pun, menghitung
 * ukuran apa pun — padanan `DIMENSI`, `UKURAN`, `bagianRek`, dan `ringkasPeta`
 * prototipe.
 *
 * Dipakai halaman Ringkasan supaya pembacanya bisa bertanya sendiri, bukan
 * hanya melihat angka yang sudah dipilihkan. Pilihannya dibawa lewat alamat
 * halaman, jadi tampilan yang sedang dilihat bisa disalin dan dikirim.
 */
class PetaData
{
    /* Warna keadaan, bukan warna identitas. Merah dan hijau di sini nyaris
       tidak terbedakan oleh mata yang buta warna merah-hijau, jadi tiap
       potongan status selalu ditempeli kodenya sendiri — yang membedakan
       hurufnya, bukan warnanya. */
    public const WARNA_STATUS = ['SS' => 'var(--ok)', 'BS' => 'var(--bad)',
        'BT' => 'var(--warn)', 'TD' => 'var(--calm)'];

    public const URUT_STATUS = ['BT', 'BS', 'SS', 'TD'];

    /* Warna identitas untuk potongan donat. Bukan pilihan selera: kedelapan
       warna ini sudah dilewatkan pengukur keterbacaan buta warna, dan pasangan
       bersebelahan terburuk masih terpisah cukup jauh. Satu di antaranya —
       kuning tua — kontrasnya di bawah 3:1 terhadap latar putih, jadi tiap
       potongan wajib berlabel, tidak boleh mengandalkan warna saja. Jangan
       menambah warna kesembilan. */
    public const WARNA_KATEG = ['#2563EB', '#EA580C', '#0D9488', '#7C3AED',
        '#CA8A04', '#DB2777', '#0284C7', '#65A30D'];

    public const BENTUK_TAMPIL = [
        'batang' => 'Batang',
        'tumpuk' => 'Batang terbelah status',
        'donat'  => 'Donat',
        'tabel'  => 'Tabel',
    ];

    public const URUT_HARI = ['Belum jatuh tempo', 'Jatuh tempo hari ini', 'Lewat 1–30 hari',
        'Lewat 31–90 hari', 'Lewat 91–180 hari', 'Lewat di atas 180 hari', 'Tanpa tenggat'];

    public const URUT_UMUR = ['0–3 bulan', '3–6 bulan', '6–12 bulan', 'Lebih dari setahun', 'Tidak diketahui'];

    /* ================================================================
       PITA
       ================================================================ */

    public static function pitaHari(?int $n): string
    {
        return match (true) {
            $n === null => 'Tanpa tenggat',
            $n < 0      => 'Belum jatuh tempo',
            $n === 0    => 'Jatuh tempo hari ini',
            $n <= 30    => 'Lewat 1–30 hari',
            $n <= 90    => 'Lewat 31–90 hari',
            $n <= 180   => 'Lewat 91–180 hari',
            default     => 'Lewat di atas 180 hari',
        };
    }

    public static function pitaUmur(?int $n): string
    {
        return match (true) {
            $n === null => 'Tidak diketahui',
            $n <= 90    => '0–3 bulan',
            $n <= 180   => '3–6 bulan',
            $n <= 365   => '6–12 bulan',
            default     => 'Lebih dari setahun',
        };
    }

    /* ================================================================
       BAGIAN REKOMENDASI
       ================================================================ */

    /**
     * Bagian satu rekomendasi yang barisnya memenuhi `$uji`. Dipakai sumbu yang
     * MEMECAH rekomendasi — menurut satuan kerja atau bentuk tindak lanjut:
     * nilai, setoran, tolakan BPK, dan status SIPTL-nya ikut dikerucutkan, jadi
     * ukuran apa pun yang dipilih menghitung bagian itu saja.
     *
     * Salinannya hanya untuk dihitung dan ditampilkan — TIDAK BOLEH disimpan.
     */
    public static function bagianRek(Rekomendasi $r, callable $uji): Rekomendasi
    {
        $baris = $r->daftarSasaran()->filter($uji)->values();
        $id = $baris->pluck('id')->all();
        $milik = fn ($x) => in_array($x->sasaran_id, $id, true);

        $b = clone $r;
        $b->barisSemua = null;
        $b->setRelation('sasaran', $baris);
        foreach (['pemulihan', 'tolakanBpk'] as $rel) {
            if ($r->relationLoaded($rel)) {
                $b->setRelation($rel, $r->getRelation($rel)->filter($milik)->values());
            }
        }
        $b->nilai_pulih = (int) $baris->sum('nilai');

        /* Status SIPTL bagian ini sendiri, kalau statusnya memang dicatat per
           baris. Baris yang belum diunggah terhitung BT, sama dengan
           `statusRek`. */
        $b->status = $r->daftarSasaran()->contains(fn ($x) => $x->status_bpk !== null)
            ? Rekomendasi::rangkumBpk($baris->map(fn ($x) => $x->status_bpk))
            : $r->status;

        return $b;
    }

    /* ================================================================
       SUMBU PENGELOMPOKAN
       ================================================================ */

    /**
     * Menjawab "dikelompokkan menurut apa". Tiap butir membawa keterangannya
     * sendiri supaya pembaca tidak perlu menebak arti pilihan yang baru saja
     * ia tekan.
     */
    public static function dimensi(): array
    {
        return [
            'posisi' => [
                'nama'  => 'Posisi berkas',
                'ket'   => 'Di meja siapa berkasnya menunggu sekarang.',
                'kunci' => fn (Rekomendasi $x) => $x->posisiRek()->value,
                'label' => fn (string $k) => PosisiBerkas::tryFrom($k)?->label() ?? $k,
                'alami' => array_map(fn ($p) => $p->value, PosisiBerkas::cases()),
            ],
            'status' => [
                'nama'  => 'Status SIPTL',
                'ket'   => 'Putusan BPK menurut Peraturan BPK 2/2017, disalin Setba dari SIPTL. Putusan terakhir, sesudah Inspektorat. Hanya berlaku untuk LHP.',
                'kunci' => fn (Rekomendasi $x) => $x->status?->value,
                'label' => fn (string $k) => $k.' · '.(StatusTindakLanjut::tryFrom($k)?->pendek() ?? $k),
                'alami' => self::URUT_STATUS,
            ],
            /* Dua sumbu berikut MEMECAH rekomendasi: yang dipikul bersama masuk
               ke tiap kelompoknya, dengan bagiannya sendiri. Kalau dibaca dari
               satuan kerja PERTAMA saja, rekomendasi yang dipikul tiga satuan
               kerja terhitung milik yang pertama seluruhnya — dan yang lain
               tidak muncul sama sekali. */
            'satker' => [
                'nama'  => 'Satuan kerja',
                'ket'   => 'Satuan kerja yang memikul rekomendasi. Rekomendasi yang dipikul bersama masuk ke tiap satuan kerjanya, dengan bagiannya sendiri.',
                'pecah' => fn (Rekomendasi $x) => $x->daftarSasaran()->map->satker
                    ->filter()->unique('id')->values()
                    ->map(fn ($s) => [$s->namaPendek(),
                        self::bagianRek($x, fn ($b) => $b->satker_id === $s->id)])
                    ->all(),
            ],
            'bentuk' => [
                'nama'  => 'Bentuk tindak lanjut',
                'ket'   => 'Jenis tindakan yang diminta rekomendasi. Rekomendasi dengan beberapa bentuk tindak lanjut masuk ke tiap bentuknya.',
                'pecah' => fn (Rekomendasi $x) => $x->tindakan
                    ->map(fn ($tk) => $tk->bentuk?->nama)->filter()->unique()->values()
                    ->map(fn ($bt) => [$bt, self::bagianRek($x,
                        fn ($b) => $b->tindakan?->bentuk?->nama === $bt)])
                    ->all(),
                'alami' => BentukTindakLanjut::nama(),
            ],
            'kategori' => [
                'nama'  => 'Kategori temuan',
                'ket'   => 'Penggolongan yang ditulis pemeriksa sendiri di suratnya.',
                'kunci' => fn (Rekomendasi $x) => $x->temuan->kategori?->nama,
            ],
            /* Dua sudut pandang atas hal yang sama, dan angkanya memang
               berbeda. Mbak Puspi: "yang status SS di SIPTL 88, versi Unor kita
               tuh udah selesai 117." Karena satu rekomendasi BPK bisa
               menyangkut beberapa Unor, status SIPTL bisa Belum Sesuai gara-gara
               Unor lain — sedangkan bagian BPSDM sudah beres.

               Kosakatanya berbeda karena yang mengeluarkannya berbeda: sampai
               Itjen cuma memadai dan belum memadai, sedangkan BS / BT / SS
               milik BPK. */
            'verifikasi' => [
                'nama'  => 'Status verifikasi',
                'ket'   => 'Keadaan menurut BPSDM: memadai kalau seluruh satuan kerja pada rekomendasi itu sudah memadai. Dua dari tiga selesai tetap belum memadai. Hanya memadai dan belum memadai — kode BS, BT, dan SS milik BPK.',
                'kunci' => fn (Rekomendasi $x) => $x->keadaanUnor()->value,
                'label' => fn (string $k) => HasilTelaah::tryFrom($k)?->nama() ?? $k,
                'alami' => ['BM', 'M'],
            ],
            'intern' => [
                'nama'  => 'Kategori internal',
                'ket'   => 'Pengelompokan BPSDM sendiri, dipakai untuk rekap ke dalam.',
                /* Warna dan urutannya diambil dari data master, tidak dipatok di
                   sini. Itu memang gunanya master: grafiknya langsung terbaca
                   tanpa perlu menghafal warna apa untuk kategori apa. */
                'kunci'      => fn (Rekomendasi $x) => $x->temuan->kategoriIntern?->nama,
                'dariMaster' => true,
            ],
            'sumber' => [
                'nama'  => 'Sumber laporan',
                'ket'   => 'Dari BPK lewat LHP, atau dari Inspektorat lewat LHA.',
                'kunci' => fn (Rekomendasi $x) => $x->jenis()->value,
                'label' => fn (string $k) => $k.' · '.(SumberLaporan::tryFrom($k)?->nama() ?? $k),
                'alami' => ['LHP', 'LHA'],
            ],
            'telat' => [
                'nama'  => 'Keterlambatan',
                'ket'   => 'Berapa lama tenggat menjawab sudah terlewat.',
                'kunci' => fn (Rekomendasi $x) => $x->tanpaTenggat()
                    ? 'Tanpa tenggat'
                    : self::pitaHari(Rekomendasi::selisih($x->tenggat_jawab)),
                'alami' => self::URUT_HARI,
            ],
            'umur' => [
                'nama'  => 'Umur berkas',
                'ket'   => 'Terhitung sejak laporan pemeriksaannya diterima.',
                'kunci' => fn (Rekomendasi $x) => self::pitaUmur(
                    Rekomendasi::selisih($x->temuan->laporan->tgl_terima)),
                'alami' => self::URUT_UMUR,
            ],
            /* Baris paling bawah rekap mereka, dan yang paling banyak
               bercerita: pada lembar 2005–2025 hampir seluruh tahun lama sudah
               lunas, sementara 18 dari 20 rekomendasi yang belum
               ditindaklanjuti menumpuk di tahun terakhir. Pita bulan tidak bisa
               menunjukkan itu — yang ditanya orang "temuan tahun berapa". */
            'tahun' => [
                'nama'  => 'Tahun LHP',
                'ket'   => 'Tahun surat pemeriksaannya, sama dengan tahun pada Ref IDT.',
                'kunci' => fn (Rekomendasi $x) => $x->temuan->laporan->tahun(),
            ],
        ];
    }

    /* ================================================================
       UKURAN
       ================================================================ */

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
                'satuan' => 'rekomendasi',
            ],
            'temuanN' => [
                'nama' => 'Jumlah temuan',
                'ket' => 'Temuan dihitung sekali, walau melahirkan beberapa rekomendasi.',
                'hitung' => fn (Collection $g) => $g->pluck('temuan_id')->unique()->count(),
                'satuan' => 'temuan',
            ],
            'lewat' => [
                'nama' => 'Jumlah lewat tenggat',
                'ket' => 'Rekomendasi yang tenggat menjawabnya, atau batas perbaikan dari Inspektorat, sudah terlewat.',
                'hitung' => fn (Collection $g) => $g->filter(fn ($x) => $x->telat())->count(),
                'satuan' => 'rekomendasi',
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
                'hitung' => fn (Collection $g) => (int) $g->sum(fn ($x) => (int) $x->nilai_pulih),
                'uang' => true, 'satuan' => 'rupiah',
            ],
            'masuk' => [
                'nama' => 'Sudah dipulihkan',
                'ket' => 'Yang sudah benar-benar masuk kas negara.',
                'hitung' => fn (Collection $g) => (int) $g->sum(fn ($x) => $x->totalSetor()),
                'uang' => true, 'satuan' => 'rupiah',
            ],
            /* Tiga "sisa" yang berbeda, dan bedanya penting. Yang pertama
               menghitung uang yang belum masuk kas; dua yang lain menghitung
               nilai yang belum DIAKUI — dan itu tidak sama: rekomendasi yang
               uangnya sudah lunas tapi buktinya ditolak sebagian tetap punya
               sisa nilai. */
            'belumSetor' => [
                'nama' => 'Belum disetor',
                'ket' => 'Tagihan dikurangi uang yang sudah masuk kas negara.',
                'hitung' => fn (Collection $g) => (int) $g->sum(fn ($x) => max(0, $x->nilaiRek() - $x->totalSetor())),
                'uang' => true, 'satuan' => 'rupiah',
            ],
            'sisaItjen' => [
                'nama' => 'Sisa nilai · Inspektorat',
                'ket' => 'Nilai rekomendasi dikurangi yang sudah dinilai memadai. Bisa tersisa walau uangnya sudah lunas — buktinya yang belum diterima seluruhnya.',
                'hitung' => fn (Collection $g) => (int) $g->sum(fn ($x) => $x->sisaNilaiItjen()),
                'uang' => true, 'satuan' => 'rupiah',
            ],
            /* Dipakai grafik "Belum Selesai per Satker" pada lembar mereka, dan
               butir catatannya menyebut gunanya: "Fokus utama dapat diarahkan
               pada Satker dengan backlog 'Belum Selesai' tertinggi." */
            'belumMemadai' => [
                'nama' => 'Belum memadai',
                'ket' => 'Rekomendasi yang belum dinyatakan memadai oleh Inspektorat.',
                'hitung' => fn (Collection $g) => $g->filter(fn ($x) => ! $x->keadaanUnor()->memadai())->count(),
                'satuan' => 'rekomendasi',
            ],
            'penugasan' => [
                'nama' => 'Jumlah penugasan',
                'ket' => 'Satu satuan kerja pada satu bentuk tindak lanjut. Satu rekomendasi yang dipikul empat satuan kerja berisi empat penugasan.',
                'hitung' => fn (Collection $g) => (int) $g->sum(fn ($x) => $x->daftarSasaran()->count()),
                'satuan' => 'penugasan',
            ],
            'sisaSiptl' => [
                'nama' => 'Sisa nilai · SIPTL',
                'ket' => 'Nilai rekomendasi dikurangi yang sudah diakui BPK. Hanya berlaku untuk LHP.',
                'hitung' => fn (Collection $g) => (int) $g->sum(fn ($x) => $x->sisaNilaiBpk()),
                'uang' => true, 'satuan' => 'rupiah',
            ],
        ];
    }

    /** Nilai temuan dihitung sekali per temuan. */
    public static function nilaiTemuanUnik(Collection $g): int
    {
        return (int) $g->unique('temuan_id')->sum(fn ($x) => $x->temuan->nilaiTemuan());
    }

    /* ================================================================
       PENGELOMPOKAN
       ================================================================ */

    /**
     * Mengelompokkan sekali, dipakai semua bentuk tampilan. Pecahan statusnya
     * ikut dihitung walau bentuknya belum tentu memakainya — harganya murah dan
     * mengganti bentuk jadi tidak perlu menghitung ulang.
     *
     * @param  Collection|null  $master  kategori internal dari data master
     */
    public static function ringkas(Collection $daftar, string $dim, string $ukur,
        string $urut, ?Collection $master = null): array
    {
        $D = self::dimensi()[$dim] ?? self::dimensi()['posisi'];
        $U = self::ukuran()[$ukur] ?? self::ukuran()['jumlah'];
        /* Sumbu yang bersandar pada data master mengambil urutan dan warnanya
           dari sana, jadi menambah kategori baru tidak perlu menyentuh kode. */
        $master = ! empty($D['dariMaster']) ? ($master ?? collect()) : null;
        $alami = $master ? $master->pluck('nama')->all() : ($D['alami'] ?? null);

        /* Kelompok disimpan sebagai daftar berurutan, bukan larik berkunci:
           kunci berupa angka — "2019" — berubah jadi bilangan di PHP, dan
           urutan kemunculannya ikut berubah. */
        $kunciKe = [];
        $kelompok = [];
        foreach ($daftar as $x) {
            /* Sumbu yang memecah rekomendasi memasukkannya ke tiap kelompok
               dengan bagiannya sendiri; sisanya satu rekomendasi satu kelompok. */
            $pecahan = isset($D['pecah']) ? ($D['pecah'])($x) : [[($D['kunci'])($x), $x]];
            foreach ($pecahan ?: [[null, $x]] as [$kunci, $y]) {
                $k = ($kunci === null || $kunci === '') ? 'Tidak diisi' : (string) $kunci;
                if (! isset($kunciKe[$k])) {
                    $kunciKe[$k] = count($kelompok);
                    $kelompok[] = ['kunci' => $k, 'isi' => []];
                }
                $kelompok[$kunciKe[$k]]['isi'][] = $y;
            }
        }

        $baris = array_map(function ($g) use ($D, $U, $master) {
            $isi = collect($g['isi']);

            return [
                'kunci'  => $g['kunci'],
                'nama'   => isset($D['label']) ? ($D['label'])($g['kunci']) : $g['kunci'],
                'warna'  => $master ? self::warnaMaster($master, $g['kunci']) : null,
                'nilai'  => ($U['hitung'])($isi),
                'jumlah' => $isi->count(),
                'pecah'  => array_values(array_filter(array_map(fn ($s) => [
                    's' => $s,
                    'n' => ($U['hitung'])($isi->filter(fn ($x) => $x->status?->value === $s)),
                ], self::URUT_STATUS), fn ($p) => $p['n'] > 0)),
            ];
        }, $kelompok);

        if ($urut === 'nama') {
            $abjad = new \Collator('id');
            usort($baris, fn ($a, $b) => $abjad->compare((string) $a['nama'], (string) $b['nama']));
        } elseif ($urut === 'alami' && $alami) {
            usort($baris, fn ($a, $b) => self::indeks($alami, $a['kunci']) <=> self::indeks($alami, $b['kunci']));
        } else {
            usort($baris, fn ($a, $b) => $b['nilai'] <=> $a['nilai']);
        }

        /* `total` = jumlah kelompoknya, dipakai menghitung bagian supaya
           bagiannya genap seratus persen. `sebenarnya` = ukuran yang sama
           dihitung ulang atas seluruh daftar. Keduanya berbeda kalau satu
           temuan atau rekomendasi masuk dua kelompok sekaligus. */
        $nilai = array_column($baris, 'nilai');

        return [
            'baris'      => $baris,
            'total'      => array_sum($nilai),
            'sebenarnya' => ($U['hitung'])($daftar),
            'maks'       => max(1, $nilai ? max($nilai) : 1),
            'D'          => $D,
            'U'          => $U,
            'alami'      => $alami,
        ];
    }

    private static function indeks(array $alami, string $kunci): int
    {
        $i = array_search($kunci, $alami, true);

        return $i === false ? -1 : $i;
    }

    private static function warnaMaster(Collection $master, string $nama): string
    {
        $w = $master->firstWhere('nama', $nama)?->warna;

        return ($w instanceof WarnaLabel ? $w : WarnaLabel::dari($w))->padat();
    }

    /* ================================================================
       PENULISAN
       ================================================================ */

    /** Ukuran berupa uang ditulis ringkas; sisanya apa adanya. */
    public static function tulis(array $U, $v): string
    {
        return ! empty($U['uang']) ? Tampil::rupiahSingkat($v) : (string) $v;
    }

    public static function bagian($a, $b): string
    {
        return $b > 0 ? round($a / $b * 100).'%' : '0%';
    }
}
