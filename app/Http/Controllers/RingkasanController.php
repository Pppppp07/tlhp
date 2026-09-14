<?php

namespace App\Http\Controllers;

use App\Enums\StatusTindakLanjut;
use App\Support\PetaData;
use App\Support\Terlihat;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Ringkasan — dibentuk mengikuti dasbor pemantauan yang mereka pakai sendiri.
 *
 * Acuannya `(BID KI) Dashboard Pemantauan_LHP BPSDM.xlsx`: dua blok status
 * bertumpuk, tiap keranjang membawa jumlah DAN rupiahnya, lalu dua tabel rekap
 * — per satuan kerja dan per tahun LHP.
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
     *
     * Bedanya cuma satu, dan itu yang penting: di Excel susunan ini dipatok,
     * di sini tiap panel bisa diganti sendiri.
     *
     * Keterlambatan ikut turun ke sini. Mbak Puspi: "kayaknya enggak perlu
     * dimunculin ... di bagian bawah aja enggak apa-apa." Dasbor mereka
     * sendiri tidak memuat tenggat sama sekali — tidak ada sanksinya, dan
     * temuan 2005 yang masih menggantung akan membuat kepalanya merah
     * selamanya.
     */
    private const PANEL_BAWAAN = [
        ['dim' => 'status',     'ukur' => 'jumlah', 'bentuk' => 'batang', 'urut' => 'alami'],
        ['dim' => 'verifikasi', 'ukur' => 'jumlah', 'bentuk' => 'batang', 'urut' => 'alami'],
        ['dim' => 'intern',     'ukur' => 'jumlah', 'bentuk' => 'batang', 'urut' => 'nilai'],
        ['dim' => 'telat',      'ukur' => 'jumlah', 'bentuk' => 'batang', 'urut' => 'alami'],
    ];

    public function __invoke(Request $req)
    {
        $terlihat = Terlihat::untuk();

        /* Dimuat lewat penyaring hak akses, bukan Rekomendasi::all(). Tanpa ini
           satuan kerja melihat angka yang menghitung berkas satuan kerja lain —
           dan angka yang bocor jauh lebih sulit disadari daripada halaman yang
           bocor. */
        $semua = $terlihat->rekomendasi()
            ->with(['temuan.laporan', 'temuan.kategori', 'temuan.kategoriIntern', 'temuan.satkers',
                'sasaran.satker', 'tindakan.bentuk', 'pemulihan', 'permintaanDokumen.item',
                'keputusan.verifikasi'])
            ->get();

        /* Lingkup laporan. Mbak Puspi: "di atasnya mungkin, jadi sebelum milih
           ini dia mau LHP laporan pemeriksaan apa." Gabungannya sengaja
           dipertahankan — "jangan dihilangin juga, jadi ini buat kayak
           monev-nya."

           Angkanya dihitung SEBELUM disaring, supaya tombol yang tidak sedang
           dipilih tidak pernah menulis nol — tombol bernilai nol tidak bisa
           lagi dipakai berpindah ke sana. */
        $jenis = fn ($x) => $x->temuan->laporan->sumber->value;
        $jumlahJenis = [
            'semua' => $semua->count(),
            'LHP'   => $semua->filter(fn ($x) => $jenis($x) === 'LHP')->count(),
            'LHA'   => $semua->filter(fn ($x) => $jenis($x) === 'LHA')->count(),
        ];
        $lingkup = in_array($req->query('lingkup'), ['LHP', 'LHA'], true)
            ? $req->query('lingkup') : 'semua';
        $r = $lingkup === 'semua' ? $semua
            : $semua->filter(fn ($x) => $jenis($x) === $lingkup)->values();

        $laporan = $r->map(fn ($x) => $x->temuan->laporan)->unique('id')->values();
        $baris = $r->flatMap(fn ($x) => $x->daftarSasaran());

        return view('ringkasan', array_merge(
            $this->kepala($r, $laporan, $baris),
            $this->blokBpk($r),
            $this->blokUnor($r, $baris),
            [
                'lingkup'     => $lingkup,
                'jumlahJenis' => $jumlahJenis,
                'jenisUtama'  => $this->jenisUtama($lingkup, $laporan),
                'perSatker'   => $this->perSatker($r),
                'perTahun'    => $this->perTahun($r),
                'panel'       => $this->panel($req, $r),
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
     * sebabnya — "Pusat 4 tuh udah berapa tahun tuh, udah 4 tahun." Temuan
     * lama tidak akan pernah berhenti terlambat, jadi kepalanya akan merah
     * selamanya, dan merah yang tidak pernah berubah berhenti dibaca orang.
     *
     * Gantinya angka yang memang mereka pakai memutuskan. Catatan lembar
     * mereka: "Fokus utama dapat diarahkan pada Satker dengan backlog 'Belum
     * Selesai' tertinggi." Angka ini juga turun kalau dikerjakan; keterlambatan
     * cuma bisa naik.
     */
    private function kepala(Collection $r, Collection $laporan, Collection $baris): array
    {
        $tumpukan = $baris->reject(fn ($x) => $x->hasil?->memadai())
            ->groupBy(fn ($x) => $x->satker?->namaPendek() ?? 'Tidak diisi')
            ->map->count()->sortDesc();

        return [
            'jumlahLaporan' => $laporan->count(),
            'jumlahLhp'     => $laporan->filter(fn ($l) => $l->sumber->value === 'LHP')->count(),
            'jumlahTemuan'  => $r->pluck('temuan_id')->unique()->count(),
            'nilaiTemuan'   => PetaData::nilaiTemuanUnik($r),
            'total'         => $r->count(),
            'tumpukan'      => $tumpukan->isEmpty() ? null
                : ['satker' => $tumpukan->keys()->first(), 'belum' => $tumpukan->first()],
            /* Daftar berkas yang dokumennya belum lengkap. Bukan bagian dasbor
               mereka, tapi inilah satu-satunya tempat Setba bisa melihat
               permintaan mana yang belum dijawab tanpa membuka satu per satu. */
            'menungguDok' => $r->filter(function ($x) {
                $p = $x->progresDokumen();
                return $p && $p[0] < $p[1];
            }),
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
    private function blokBpk(Collection $r): array
    {
        $bpk = $r->filter(fn ($x) => $x->temuan->laporan->sumber->melewatiSiptl());

        $jml = [];
        $rp = [];
        foreach (StatusTindakLanjut::cases() as $st) {
            $jml[$st->value] = 0;
            $rp[$st->value] = 0;
        }
        foreach ($bpk as $x) {
            $jml[$x->status->value]++;
            $rp['SS'] += $x->nilaiDiakuiBpk();
            if ($x->status !== StatusTindakLanjut::SS) {
                $rp[$x->status->value] += $x->sisaNilaiBpk();
            }
        }

        return [
            'adaLhp'    => $bpk->isNotEmpty(),
            'jumlahBpk' => $bpk->count(),
            'nilaiBpk'  => (int) $bpk->sum(fn ($x) => $x->nilaiSasaran()),
            'statusJml' => $jml,
            'statusRp'  => $rp,
            'sisaBpk'   => (int) $bpk->sum(fn ($x) => $x->sisaNilaiBpk()),
            /* Inilah selisih yang jadi pokok persoalan Mbak Puspi: sudah
               memadai menurut Inspektorat, tapi BPK belum menyatakannya
               selesai. */
            'beda' => $bpk->filter(fn ($x) => $x->keadaanUnor()->memadai()
                && $x->status !== StatusTindakLanjut::SS
                && $x->status !== StatusTindakLanjut::TD)->count(),
        ];
    }

    /**
     * Sumbu BPSDM. Dua keranjang saja — "belum ditindaklanjuti" bukan putusan
     * Inspektorat, dan di lembar mereka kolom BT (Unor) memang selalu nol.
     *
     * Begitu barisnya seluruhnya memadai, seluruh nilainya masuk keranjang
     * memadai. Yang belum barulah dipecah per baris, dan di situlah pengakuan
     * sebagian berlaku.
     */
    private function blokUnor(Collection $r, Collection $baris): array
    {
        $jml = ['M' => 0, 'BM' => 0];
        $rp = ['M' => 0, 'BM' => 0];
        foreach ($r as $x) {
            if ($x->keadaanUnor()->memadai()) {
                $jml['M']++;
                $rp['M'] += $x->nilaiSasaran();
            } else {
                $jml['BM']++;
                $rp['M'] += $x->nilaiDiakuiItjen();
                $rp['BM'] += $x->sisaNilaiItjen();
            }
        }

        return [
            'nilaiRek'     => (int) $r->sum(fn ($x) => $x->nilaiSasaran()),
            'belumMemadai' => $jml['BM'],
            'unorJml'      => $jml,
            'unorRp'       => ['M' => (int) $rp['M'], 'BM' => (int) $rp['BM']],
            'sisaItjen'    => (int) $rp['BM'],
            /* Penyebut kedua: penugasan. Inilah 312 baris lembar pemantauan,
               dan angka "MEMADAI 303 / BELUM MEMADAI 9" berdiri di atasnya —
               bukan di atas jumlah rekomendasi. */
            'tugas' => [
                'total'   => $baris->count(),
                'memadai' => $baris->filter(fn ($x) => $x->hasil?->memadai())->count(),
            ],
        ];
    }

    /* ================================================================
       DUA TABEL REKAP
       ================================================================ */

    /** Penyebutnya PENUGASAN. Diurutkan dari tumpukan belum selesai terbanyak. */
    private function perSatker(Collection $r): array
    {
        $peta = [];
        foreach ($r as $x) {
            foreach ($x->daftarSasaran() as $b) {
                $k = $b->satker?->namaPendek() ?? 'Tidak diisi';
                $peta[$k] ??= ['satker' => $k, 'tugas' => 0, 'memadai' => 0,
                    'belum' => 0, 'sisaItjen' => 0, 'sisaBpk' => 0];
                $peta[$k]['tugas']++;
                if ($b->hasil?->memadai()) {
                    $peta[$k]['memadai']++;
                } else {
                    $peta[$k]['belum']++;
                }
                $peta[$k]['sisaItjen'] += $b->sisaNilaiItjen();
                $peta[$k]['sisaBpk'] += $b->sisaNilaiBpk();
            }
        }
        $baris = collect($peta)->values()
            ->sortByDesc(fn ($o) => $o['belum'] * 10000 + $o['tugas'])->values()
            ->map(function ($o) {
                $o['persen'] = $o['tugas'] ? $o['memadai'] / $o['tugas'] * 100 : 0;
                return $o;
            });

        return ['baris' => $baris, 'total' => $this->jumlahkan($baris, 'satker', 'TOTAL')];
    }

    /**
     * Penyebutnya REKOMENDASI. Dari tabel inilah terbaca umur tumpukannya:
     * tahun lama biasanya sudah lunas, sementara yang belum ditindaklanjuti
     * menumpuk di tahun terakhir.
     */
    private function perTahun(Collection $r): array
    {
        $peta = [];
        foreach ($r as $x) {
            $lap = $x->temuan->laporan;
            $k = optional($lap->tgl_surat)->format('Y') ?? '—';
            $peta[$k] ??= ['tahun' => $k, 'jml' => 0, 'keSiptl' => 0,
                'SS' => 0, 'BS' => 0, 'BT' => 0, 'TD' => 0,
                'memadai' => 0, 'belum' => 0, 'nilai' => 0, 'sisaBpk' => 0, 'sisaItjen' => 0];
            $peta[$k]['jml']++;
            $peta[$k]['nilai'] += $x->nilaiSasaran();
            /* Status BPK cuma dihitung untuk laporan yang memang masuk SIPTL —
               itu sebabnya ada kolom "dari LHP" sebagai penyebutnya. Tanpa itu
               baris yang bercampur terbaca seperti salah hitung: SS + BS + BT
               tidak sama dengan jumlah rekomendasinya. */
            if ($lap->sumber->melewatiSiptl()) {
                $peta[$k]['keSiptl']++;
                $peta[$k][$x->status->value]++;
                $peta[$k]['sisaBpk'] += $x->sisaNilaiBpk();
            }
            if ($x->keadaanUnor()->memadai()) {
                $peta[$k]['memadai']++;
            } else {
                $peta[$k]['belum']++;
            }
            $peta[$k]['sisaItjen'] += $x->sisaNilaiItjen();
        }
        ksort($peta);
        $baris = collect($peta)->values();

        return ['baris' => $baris, 'total' => $this->jumlahkan($baris, 'tahun', 'TOTAL')];
    }

    /** Baris jumlah untuk kaki tabel. */
    private function jumlahkan(Collection $baris, string $kunciNama, string $label): array
    {
        $t = [$kunciNama => $label];
        foreach ($baris as $o) {
            foreach ($o as $k => $v) {
                if ($k === $kunciNama || $k === 'persen') {
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

    private function panel(Request $req, Collection $r): array
    {
        $panel = [];
        foreach (self::PANEL_BAWAAN as $i => $bawaan) {
            $p = [
                'i'      => $i,
                'dim'    => $req->query("p{$i}dim", $bawaan['dim']),
                'ukur'   => $req->query("p{$i}ukur", $bawaan['ukur']),
                'bentuk' => $req->query("p{$i}bentuk", $bawaan['bentuk']),
                'urut'   => $req->query("p{$i}urut", $bawaan['urut']),
            ];
            $p['data'] = PetaData::ringkas($r, $p['dim'], $p['ukur'], $p['urut']);
            $panel[] = $p;
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
