<?php

namespace App\Http\Controllers;

use App\Enums\HasilTelaah;
use App\Enums\JenisReferensi;
use App\Enums\StatusTindakLanjut;
use App\Models\Referensi;
use App\Models\Rekomendasi;
use App\Support\Lingkup;
use App\Support\PetaData;
use App\Support\Terlihat;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Ringkasan — padanan `Dasbor` prototipe, dibentuk mengikuti dasbor pemantauan
 * yang mereka pakai sendiri (`(BID KI) Dashboard Pemantauan_LHP BPSDM.xlsx`):
 * dua blok status bertumpuk, tiap keranjang membawa jumlah DAN rupiahnya, lalu
 * dua tabel rekap — per satuan kerja dan per tahun LHP.
 *
 * DUA PENYEBUT, dan ini yang paling gampang tertukar:
 *
 *     rekomendasi   123    Reff IDT unik        -> SS / BS / BT
 *     penugasan     312    Satker x Reff IDT    -> memadai / belum memadai
 *
 * Judul tabel mereka sendiri menyebutnya: "Reff IDT per Satker" untuk yang
 * berpenyebut 312, "Reff IDT" saja untuk yang 123.
 */
class RingkasanController extends Controller
{
    /**
     * Susunan panel bawaan, meniru gambar demi gambar di dasbor mereka.
     * Bedanya cuma satu, dan itu yang penting: di Excel susunan ini dipatok, di
     * sini tiap panel bisa diganti sendiri.
     *
     *     Komposisi Status SIPTL              -> donat, dikelompokkan status
     *     Komposisi Status Unor               -> donat, dikelompokkan verifikasi
     *     Pemantauan Rekomendasi per Satker   -> tabel per satuan kerja
     *     Belum Selesai per Satker            -> batang, ukurannya "belum memadai"
     *     Rekap Status per Tahun LHP          -> tabel per tahun
     *
     * Keterlambatan ikut turun ke sini. Mbak Puspi: "kayaknya enggak perlu
     * dimunculin ... di bagian bawah aja enggak apa-apa." Dasbor mereka sendiri
     * tidak memuat tenggat sama sekali — tidak ada sanksinya, dan temuan 2005
     * yang masih menggantung akan membuat kepalanya merah selamanya.
     */
    private const PANEL_AWAL = [
        ['dim' => 'status',     'ukur' => 'jumlah',       'bentuk' => 'donat',  'urut' => 'alami', 'lebar' => 0],
        ['dim' => 'verifikasi', 'ukur' => 'jumlah',       'bentuk' => 'donat',  'urut' => 'alami', 'lebar' => 0],
        ['dim' => 'satker',     'ukur' => 'jumlah',       'bentuk' => 'tabel',  'urut' => 'nilai', 'lebar' => 1],
        ['dim' => 'satker',     'ukur' => 'belumMemadai', 'bentuk' => 'batang', 'urut' => 'nilai', 'lebar' => 1],
        /* `nama`, bukan `alami`: tahun tidak punya daftar tetap, dan
           mengurutkannya menurut nama berarti kronologis — 2019 di atas, 2026
           di bawah, sama seperti rekap mereka. */
        ['dim' => 'tahun',      'ukur' => 'jumlah',       'bentuk' => 'tabel',  'urut' => 'nama',  'lebar' => 1],
        ['dim' => 'posisi',     'ukur' => 'jumlah',       'bentuk' => 'batang', 'urut' => 'alami', 'lebar' => 0],
        ['dim' => 'intern',     'ukur' => 'nilaiTemuan',  'bentuk' => 'donat',  'urut' => 'nilai', 'lebar' => 0],
        ['dim' => 'telat',      'ukur' => 'jumlah',       'bentuk' => 'batang', 'urut' => 'alami', 'lebar' => 0],
    ];

    /** Muatan yang dibutuhkan seluruh hitungan halaman ini. */
    private const MUAT = [
        'temuan.laporan', 'temuan.kategori', 'temuan.kategoriIntern', 'temuan.rekomendasi',
        'sasaran.satker', 'sasaran.tindakan.bentuk', 'tindakan.bentuk',
        'pemulihan', 'tolakanBpk', 'permintaanDokumen.item',
    ];

    public function __invoke(Request $req)
    {
        $lingkup = Lingkup::dari($req);

        /* Susunan panel dibawa alamat halaman, jadi tampilan yang sedang
           dilihat bisa disalin dan dikirim apa adanya. Tombol tambah, hapus,
           dan lebar dijawab dengan alamat baru — tanpa itu, menyegarkan
           halaman akan mengulangi perbuatannya. */
        $panel = $this->panel($req);
        if ($req->query('aksi')) {
            return redirect()->route('ringkasan', ['p' => $this->terapkan($panel, (string) $req->query('aksi'))]);
        }

        $terlihat = Terlihat::untuk();

        /* Dimuat lewat penyaring hak akses, bukan Rekomendasi::all(). Tanpa ini
           satuan kerja melihat angka yang menghitung berkas satuan kerja lain —
           dan angka yang bocor jauh lebih sulit disadari daripada halaman yang
           bocor. */
        $utuh = $terlihat->rekomendasi()->with(self::MUAT)->orderBy('rekomendasis.id')->get()
            ->map(fn ($r) => $terlihat->pangkasRekomendasi($r));

        /* Angka pada tombol pemilih dihitung SEBELUM lingkup disaring: kalau
           tidak, membuka LHP membuat tombol LHA menulis nol — dan tombol yang
           menulis nol tidak bisa lagi dipakai pindah ke sana. */
        $jumlahJenis = [
            'semua' => $utuh->count(),
            'LHP'   => $utuh->filter(fn ($x) => $x->jenis()->value === 'LHP')->count(),
            'LHA'   => $utuh->filter(fn ($x) => $x->jenis()->value === 'LHA')->count(),
        ];

        $daftar = $utuh->filter(fn ($x) => Lingkup::berlaku($lingkup, $x->jenis()))->values();
        $laporan = $terlihat->laporan()->orderBy('id')->get()
            ->filter(fn ($l) => Lingkup::berlaku($lingkup, $l->sumber))->values();
        $baris = $daftar->flatMap(fn ($x) => $x->daftarSasaran());

        $master = Referensi::where('jenis', JenisReferensi::KATEGORI_INTERN)
            ->orderBy('urutan')->orderBy('id')->get();

        return view('ringkasan.index', array_merge(
            $this->kepala($daftar, $laporan, $baris),
            $this->blokBpk($daftar),
            $this->blokUnor($daftar, $baris),
            [
                'lingkup'     => $lingkup,
                'jumlahJenis' => $jumlahJenis,
                'jenisUtama'  => $this->jenisUtama($lingkup, $laporan),
                'perSatker'   => $this->perSatker($daftar),
                'perTahun'    => $this->perTahun($daftar),
                'panel'       => array_map(fn ($p) => $p + [
                    'data' => PetaData::ringkas($daftar, $p['dim'], $p['ukur'], $p['urut'], $master),
                ], $panel),
            ]
        ));
    }

    /* ================================================================
       KEPALA
       ================================================================ */

    /**
     * Judulnya jumlah yang BELUM MEMADAI, bukan yang terlambat.
     *
     * Mbak Puspi menolak keterlambatan ditonjolkan; Mas Naufal menyebut
     * sebabnya — "Pusat 4 tuh udah berapa tahun tuh, udah 4 tahun." Temuan lama
     * tidak akan pernah berhenti terlambat, jadi kepalanya akan merah
     * selamanya, dan merah yang tidak pernah berubah berhenti dibaca orang.
     *
     * Gantinya angka yang memang mereka pakai memutuskan. Catatan lembar
     * mereka: "Fokus utama dapat diarahkan pada Satker dengan backlog 'Belum
     * Selesai' tertinggi." Angka ini juga turun kalau dikerjakan; keterlambatan
     * cuma bisa naik.
     */
    private function kepala(Collection $daftar, Collection $laporan, Collection $baris): array
    {
        /* Satuan kerja yang tumpukan belum memadainya paling tinggi. Itu yang
           dipakai memutuskan apa yang dikejar lebih dulu. */
        $tumpukan = $baris->reject(fn ($b) => $b->hasil === HasilTelaah::M)
            ->groupBy(fn ($b) => $b->satker?->namaPendek() ?? 'Tidak diisi')
            ->map->count()
            ->sortDesc();

        return [
            'total'         => $daftar->count(),
            'jumlahLaporan' => $laporan->count(),
            'jumlahLhp'     => $laporan->filter(fn ($l) => $l->sumber->value === 'LHP')->count(),
            'jumlahTemuan'  => $daftar->pluck('temuan_id')->unique()->count(),
            'nilaiTemuan'   => PetaData::nilaiTemuanUnik($daftar),
            'tumpukan'      => $tumpukan->isEmpty() ? null
                : ['satker' => $tumpukan->keys()->first(), 'belum' => $tumpukan->first()],
            /* Rekomendasi yang dokumennya sudah diminta tapi belum terpenuhi
               semua. Inilah satu-satunya tempat Setba bisa melihat permintaan
               mana yang belum dijawab tanpa membuka satu per satu. */
            'menungguDok' => $daftar->filter(function ($x) {
                $d = $x->progresDok();

                return $d && $d['ada'] < $d['dari'];
            })->values(),
        ];
    }

    /* ================================================================
       DUA BLOK STATUS
       ================================================================ */

    /**
     * Sumbu BPK. Hanya LHP — LHA tidak pernah masuk SIPTL, jadi menghitungnya
     * di sini berarti menaruh berkas yang tidak pernah dilihat BPK ke dalam
     * keranjang "belum ditindaklanjuti" milik BPK.
     *
     * Uangnya MEMBAGI HABIS totalnya: yang sudah diakui masuk keranjang SS,
     * sisanya dipecah menurut status rekomendasinya. Begitu lembar mereka
     * menyusunnya, dan angkanya cocok — 3.062.188.341 + 628.480.000 +
     * 247.791.563 = 3.938.459.904.
     */
    private function blokBpk(Collection $daftar): array
    {
        $bpk = $daftar->filter(fn ($x) => $x->jenis()->melewatiSiptl());

        $jml = ['BT' => 0, 'SS' => 0, 'BS' => 0, 'TD' => 0];
        $rp = ['BT' => 0, 'SS' => 0, 'BS' => 0, 'TD' => 0];
        foreach ($bpk as $x) {
            $kode = $x->status?->value ?? 'BT';
            $jml[$kode]++;
            $rp['SS'] += $x->nilaiDiakuiBpk();
            if ($kode !== 'SS') {
                $rp[$kode] += $x->sisaNilaiBpk();
            }
        }

        return [
            'jumlahBpk' => $bpk->count(),
            'nilaiBpk'  => (int) $bpk->sum(fn ($x) => $x->nilaiRek()),
            'statusJml' => $jml,
            'statusRp'  => $rp,
            'sisaBpk'   => $rp['BS'] + $rp['BT'] + $rp['TD'],
            /* Inilah selisih yang jadi pokok persoalan Mbak Puspi: sudah
               memadai menurut Inspektorat, tapi BPK belum menyatakannya
               selesai. */
            'beda' => $daftar->filter(fn ($x) => $x->keadaanUnor()->memadai()
                && $x->status !== StatusTindakLanjut::SS
                && $x->status !== StatusTindakLanjut::TD)->count(),
        ];
    }

    /**
     * Sumbu Inspektorat: dua keadaan saja. Kodenya sengaja tidak dipinjam dari
     * BPK — sebutannya bahkan berbeda menurut jenis laporannya.
     *
     * Begitu suratnya memutus memadai, seluruh nilainya masuk tanpa melihat
     * tanda tiap barisnya: surat verifikasi memutus rekomendasinya, bukan baris
     * per baris. Yang belum diputus barulah dipecah menurut barisnya, dan di
     * situlah pengakuan sebagian berlaku.
     */
    private function blokUnor(Collection $daftar, Collection $baris): array
    {
        $jml = ['M' => 0, 'BM' => 0];
        $rp = ['M' => 0, 'BM' => 0];
        foreach ($daftar as $x) {
            if ($x->keadaanUnor()->memadai()) {
                $jml['M']++;
                $rp['M'] += $x->nilaiRek();
            } else {
                $jml['BM']++;
                $rp['M'] += $x->nilaiDiakuiItjen();
                $rp['BM'] += $x->sisaNilaiItjen();
            }
        }

        return [
            'nilaiRek' => (int) $daftar->sum(fn ($x) => $x->nilaiRek()),
            'unorJml'  => $jml,
            'unorRp'   => ['M' => (int) $rp['M'], 'BM' => (int) $rp['BM']],
            'sisaItjen' => (int) $rp['BM'],
            /* Penyebut kedua: penugasan, satu baris per pasangan tindak lanjut
               dan satuan kerja. Inilah 312 baris lembar pemantauan mereka, dan
               angka "MEMADAI 303 / BELUM MEMADAI 9" berdiri di atasnya — bukan
               di atas jumlah rekomendasi. */
            'tugas' => [
                'total'   => $baris->count(),
                'memadai' => $baris->filter(fn ($b) => $b->hasil === HasilTelaah::M)->count(),
            ],
        ];
    }

    /* ================================================================
       DUA TABEL REKAP
       ================================================================ */

    /**
     * Penyebutnya PENUGASAN — satu baris untuk tiap pasangan tindak lanjut dan
     * satuan kerja. Itu penyebut yang dipakai lembar mereka juga: tabelnya
     * berjudul "Reff IDT per Satker" dan totalnya 312, bukan 123.
     */
    private function perSatker(Collection $daftar): array
    {
        $peta = [];
        foreach ($daftar as $x) {
            foreach ($x->daftarSasaran() as $b) {
                $k = $b->satker?->namaPendek() ?? 'Tidak diisi';
                $peta[$k] ??= ['kunci' => $k, 'satker' => $k, 'tugas' => 0, 'memadai' => 0,
                    'belum' => 0, 'sisaItjen' => 0, 'sisaBpk' => 0];
                $peta[$k]['tugas']++;
                if ($b->hasil === HasilTelaah::M) {
                    $peta[$k]['memadai']++;
                } else {
                    $peta[$k]['belum']++;
                }
                $peta[$k]['sisaItjen'] += max(0, (int) $b->nilai - $b->nilaiDiakuiItjen());
                $peta[$k]['sisaBpk'] += max(0, (int) $b->nilai - $b->nilaiDiakuiBpk($x));
            }
        }

        $baris = array_values($peta);
        usort($baris, fn ($a, $b) => [$b['belum'], $b['tugas']] <=> [$a['belum'], $a['tugas']]);
        $baris = array_map(function ($o) {
            $o['persen'] = $o['tugas'] ? $o['memadai'] / $o['tugas'] * 100 : 0;

            return $o;
        }, $baris);

        return ['baris' => $baris, 'total' => $this->jumlahkan($baris, 'satker', 'TOTAL')];
    }

    /**
     * Penyebutnya REKOMENDASI — satu Ref IDT sekali, berapa pun satuan
     * kerjanya. Dari tabel inilah terbaca yang paling penting: tahun-tahun lama
     * hampir semua lunas sementara yang belum ditindaklanjuti menumpuk di tahun
     * terakhir.
     */
    private function perTahun(Collection $daftar): array
    {
        $peta = [];
        foreach ($daftar as $x) {
            $lap = $x->temuan->laporan;
            $k = $lap->tahun();
            $peta[$k] ??= ['kunci' => $k, 'tahun' => $k, 'jml' => 0, 'keSiptl' => 0,
                'SS' => 0, 'BS' => 0, 'BT' => 0, 'TD' => 0,
                'memadai' => 0, 'belum' => 0, 'nilai' => 0, 'sisaBpk' => 0, 'sisaItjen' => 0];
            $peta[$k]['jml']++;
            $peta[$k]['nilai'] += $x->nilaiRek();
            /* Status BPK cuma dihitung untuk laporan yang memang masuk SIPTL —
               itu sebabnya ada kolom "dari LHP" sebagai penyebutnya. Tanpa itu
               baris yang bercampur terbaca seperti salah hitung: SS + BS + BT
               tidak sama dengan jumlah rekomendasinya. */
            if ($lap->sumber->melewatiSiptl()) {
                $peta[$k]['keSiptl']++;
                $peta[$k][$x->status?->value ?? 'BT']++;
                $peta[$k]['sisaBpk'] += $x->sisaNilaiBpk();
            }
            if ($x->keadaanUnor()->memadai()) {
                $peta[$k]['memadai']++;
            } else {
                $peta[$k]['belum']++;
            }
            $peta[$k]['sisaItjen'] += $x->sisaNilaiItjen();
        }
        ksort($peta, SORT_STRING);
        $baris = array_values($peta);

        return ['baris' => $baris, 'total' => $this->jumlahkan($baris, 'tahun', 'TOTAL')];
    }

    /** Baris jumlah untuk kaki tabel. */
    private function jumlahkan(array $baris, string $kunciNama, string $label): array
    {
        $t = [$kunciNama => $label, 'kunci' => 'T'];
        foreach ($baris as $o) {
            foreach ($o as $k => $v) {
                if ($k === $kunciNama || $k === 'kunci' || $k === 'persen') {
                    continue;
                }
                $t[$k] = ($t[$k] ?? 0) + $v;
            }
        }
        if (isset($t['tugas'])) {
            $t['persen'] = $t['tugas'] ? $t['memadai'] / $t['tugas'] * 100 : 0;
        }

        return $t;
    }

    /* ================================================================
       PANEL
       ================================================================ */

    /** Susunan panel dari alamat halaman; tanpa itu, susunan bawaan. */
    private function panel(Request $req): array
    {
        $p = $req->query('p');
        if (! is_array($p)) {
            return self::PANEL_AWAL;
        }

        $dim = array_keys(PetaData::dimensi());
        $ukur = array_keys(PetaData::ukuran());
        $bentuk = array_keys(PetaData::BENTUK_TAMPIL);

        $bersih = [];
        foreach ($p as $x) {
            if (! is_array($x)) {
                continue;
            }
            $bersih[] = [
                'dim'    => in_array($x['dim'] ?? '', $dim, true) ? $x['dim'] : 'kategori',
                'ukur'   => in_array($x['ukur'] ?? '', $ukur, true) ? $x['ukur'] : 'jumlah',
                'bentuk' => in_array($x['bentuk'] ?? '', $bentuk, true) ? $x['bentuk'] : 'batang',
                'urut'   => in_array($x['urut'] ?? '', ['nilai', 'alami', 'nama'], true) ? $x['urut'] : 'nilai',
                'lebar'  => ($x['lebar'] ?? '0') === '1' || ($x['lebar'] ?? 0) === 1 ? 1 : 0,
            ];
        }

        return array_slice($bersih, 0, 24);
    }

    /** Tombol tambah, hapus, dan lebar — dijawab dengan susunan baru. */
    private function terapkan(array $panel, string $aksi): array
    {
        [$apa, $ke] = array_pad(explode(':', $aksi), 2, null);
        $i = (int) $ke;

        if ($apa === 'tambah') {
            $panel[] = ['dim' => 'kategori', 'ukur' => 'jumlah', 'bentuk' => 'batang',
                'urut' => 'nilai', 'lebar' => 0];
        } elseif ($apa === 'hapus' && isset($panel[$i])) {
            unset($panel[$i]);
            $panel = array_values($panel);
        } elseif ($apa === 'lebar' && isset($panel[$i])) {
            $panel[$i]['lebar'] = $panel[$i]['lebar'] ? 0 : 1;
        }

        return $panel;
    }

    /**
     * Sebutan hasil mengikuti lingkup yang SEDANG DIPILIH lebih dulu; mayoritas
     * data cuma dipakai kalau lingkupnya "Semua". Kalau ditebak dari isi saja,
     * lingkup LHA yang kebetulan kosong akan berbunyi "Memadai" — sebutan LHP.
     */
    private function jenisUtama(string $lingkup, Collection $laporan): string
    {
        if ($lingkup !== 'semua') {
            return $lingkup;
        }
        $lhp = $laporan->filter(fn ($l) => $l->sumber->value === 'LHP')->count();

        return $lhp >= $laporan->count() - $lhp ? 'LHP' : 'LHA';
    }
}
