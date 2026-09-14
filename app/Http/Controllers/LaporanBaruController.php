<?php

namespace App\Http\Controllers;

use App\Enums\JenisReferensi;
use App\Enums\PeranPengguna;
use App\Enums\PosisiBerkas;
use App\Enums\StatusTindakLanjut;
use App\Enums\SumberLaporan;
use App\Models\KategoriTemuan;
use App\Models\Lampiran;
use App\Models\Laporan;
use App\Models\Referensi;
use App\Models\Rekomendasi;
use App\Models\RiwayatBerkas;
use App\Models\Satker;
use App\Models\Sasaran;
use App\Models\Temuan;
use App\Models\Tindakan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Mencatat laporan pemeriksaan baru, tiga langkah.
 *
 * Isian disimpan di sesi antarlangkah, bukan di satu halaman panjang. Satu
 * laporan bisa memuat belasan temuan dan tiap temuan beberapa rekomendasi —
 * digelar sekaligus, pengisinya harus menggulir jauh hanya untuk melihat yang
 * sedang dikerjakan, dan satu salah klik menghapus seluruh isian.
 *
 * Menambah temuan atau rekomendasi juga lewat kiriman form biasa, bukan
 * JavaScript: halaman ini bagian dari tugas, dan tidak ada satu pun tugas di
 * sistem ini yang boleh bergantung pada skrip.
 */
class LaporanBaruController extends Controller
{
    private const KUNCI = 'laporan_baru';

    private function bolehMencatat(): void
    {
        abort_unless(auth()->user()->peran === PeranPengguna::SETBA, 403,
            'Hanya Setba yang mencatat laporan pemeriksaan.');
    }

    /** Isian yang sedang dikerjakan, berikut bentuk kosongnya. */
    private function draf(): array
    {
        return $this->naikkanDraf(session(self::KUNCI, [
            'langkah' => 1,
            'surat' => ['sumber' => 'LHP', 'nomor' => '', 'tgl_surat' => '', 'tgl_terima' => '', 'berkas' => false],
            'temuan' => [$this->temuanKosong()],
        ]));
    }

    private function temuanKosong(): array
    {
        return [
            /* Jamak: satu temuan lazim mengenai beberapa satuan kerja
               sekaligus. Selama ia tunggal, satu temuan harus dipecah jadi
               beberapa baris supaya bisa menyebut semuanya — dan sesudah
               dipecah, nilai temuannya ikut terhitung berkali-kali. */
            'nomor_pada_surat' => '', 'satker' => [], 'judul' => '',
            'sebab' => '', 'akibat' => '',
            'kategori' => '', 'kategori_intern' => '', 'nilai' => '',
            'rekom' => [$this->rekomKosong()],
        ];
    }

    private function rekomKosong(): array
    {
        /* Nilai tidak diketik di sini. Ia jumlah bagian tiap satuan kerja —
           mengetiknya terpisah berarti ada dua angka untuk satu hal, dan cepat
           atau lambat keduanya berbeda. */
        return [
            'uraian' => '', 'sifat' => '', 'catatan' => '',
            /* Bentuk tindak lanjut dan satuan kerjanya turun satu tingkat.
               Satu rekomendasi bisa menuntut dua bentuk sekaligus — menyetor
               ke kas negara DAN membenahi prosedurnya — dan tiap bentuk bisa
               dibebankan ke satuan kerja yang berbeda. */
            'tindakan' => [$this->tindakanKosong()],
        ];
    }

    private function tindakanKosong(): array
    {
        /* Tanggalnya sengaja kosong, bukan diisi tanggal hitungan. Kosong
           berarti "ikut tanggal rekomendasinya" — dan itu keadaan yang benar
           untuk hampir semua bentuk. Yang mengisi hanya yang memang berbeda. */
        return ['bentuk' => '', 'tgl_renaksi' => '', 'sasaran' => [$this->sasaranKosong()]];
    }

    /**
     * Draf lama dinaikkan ke bentuk baru saat dibaca.
     *
     * Setba yang sedang mengisi formulir tidak boleh kehilangan ketikannya cuma
     * karena bentuk datanya berubah di tengah jalan. Yang lama punya `bentuk`
     * dan `sasaran` langsung di rekomendasinya; keduanya dibungkus jadi satu
     * tindakan, apa adanya.
     */
    private function naikkanDraf(array $draf): array
    {
        foreach ($draf['temuan'] ?? [] as $i => $t) {
            foreach ($t['rekom'] ?? [] as $j => $r) {
                if (isset($r['tindakan'])) {
                    continue;
                }
                $draf['temuan'][$i]['rekom'][$j]['tindakan'] = [[
                    'bentuk' => $r['bentuk'] ?? '',
                    'tgl_renaksi' => '',
                    'sasaran' => $r['sasaran'] ?? [$this->sasaranKosong()],
                ]];
                unset($draf['temuan'][$i]['rekom'][$j]['bentuk'],
                      $draf['temuan'][$i]['rekom'][$j]['sasaran']);
            }
        }

        return $draf;
    }

    private function sasaranKosong(): array
    {
        return ['satker' => '', 'nilai' => ''];
    }

    /** Jumlah tagihan sebuah rekomendasi: dijumlah dari bagian tiap satuan
        kerja, tidak pernah disimpan terpisah. */
    private function tagihanRekom(array $r): int
    {
        return collect($r['tindakan'] ?? [])
            ->flatMap(fn ($tk) => $tk['sasaran'] ?? [])
            ->sum(fn ($x) => (int) preg_replace('/\D/', '', (string) ($x['nilai'] ?? '')));
    }

    private function simpan(array $draf): void
    {
        session([self::KUNCI => $draf]);
    }

    public function form()
    {
        $this->bolehMencatat();
        $draf = $this->draf();

        return view('laporan/baru', [
            'd' => $draf,
            'satker' => Satker::orderBy('nama')->get(),
            'kategori' => KategoriTemuan::where('sumber', $draf['surat']['sumber'])->orderBy('nama')->get(),
            'intern' => Referensi::jenis(JenisReferensi::KATEGORI_INTERN)->get(),
            'bentuk' => Referensi::jenis(JenisReferensi::BENTUK_TL)->get(),
            'sifat' => Referensi::jenis(JenisReferensi::SIFAT_REKOM)->get(),
            'lengkap1' => $this->lengkap1($draf),
            'kurang2' => $this->kurang2($draf),
        ]);
    }

    /* ---------- langkah 1: surat ---------- */

    public function surat(Request $req)
    {
        $this->bolehMencatat();

        $data = $req->validate([
            'sumber' => ['required', 'in:LHP,LHA'],
            'nomor' => ['required', 'string', 'max:120'],
            'tgl_surat' => ['required', 'date', 'before_or_equal:today'],
            /* Surat tidak mungkin diterima sebelum suratnya dibuat, dan tidak
               mungkin diterima besok. */
            'tgl_terima' => ['required', 'date', 'after_or_equal:tgl_surat', 'before_or_equal:today'],
            'berkas' => ['nullable'],
        ], [], [
            'nomor' => 'nomor surat',
            'tgl_surat' => 'tanggal surat',
            'tgl_terima' => 'tanggal surat diterima',
        ]);

        $draf = $this->draf();
        $ganti = $draf['surat']['sumber'] !== $data['sumber'];
        $draf['surat'] = [
            'sumber' => $data['sumber'], 'nomor' => trim($data['nomor']),
            'tgl_surat' => $data['tgl_surat'], 'tgl_terima' => $data['tgl_terima'],
            'berkas' => (bool) ($data['berkas'] ?? $draf['surat']['berkas']),
        ];

        /* Daftar kategori temuan ditentukan sumber laporannya. Kalau sumbernya
           diganti, kategori yang sudah dipilih tidak lagi ada di daftarnya —
           dibiarkan terisi, ia akan tersimpan sebagai nilai yang tidak sah. */
        if ($ganti) {
            $draf['temuan'] = array_map(function ($t) {
                $t['kategori'] = '';
                return $t;
            }, $draf['temuan']);
        }

        $draf['langkah'] = (int) $req->input('ke', 2);
        $this->simpan($draf);

        return redirect()->route('laporan.baru');
    }

    /* ---------- langkah 2: temuan & rekomendasi ---------- */

    public function temuan(Request $req)
    {
        $this->bolehMencatat();

        $draf = $this->draf();
        $draf['temuan'] = collect($req->input('temuan', []))->map(function ($t) {
            $bersih = array_merge($this->temuanKosong(), array_intersect_key(
                $t, $this->temuanKosong()));
            // Kotak centang yang tidak dicentang tidak dikirim sama sekali.
            $bersih['satker'] = array_values(array_filter(
                array_map('intval', (array) ($t['satker'] ?? []))));

            $bersih['rekom'] = collect($t['rekom'] ?? [])->map(function ($r) {
                $rb = array_merge($this->rekomKosong(), array_intersect_key($r, $this->rekomKosong()));
                $rb['sasaran'] = collect($r['sasaran'] ?? [])->map(fn ($x) => array_merge(
                    $this->sasaranKosong(), array_intersect_key($x, $this->sasaranKosong())))->all();
                if (! $rb['sasaran']) {
                    $rb['sasaran'] = [$this->sasaranKosong()];
                }
                return $rb;
            })->all();
            if (! $bersih['rekom']) {
                $bersih['rekom'] = [$this->rekomKosong()];
            }
            return $bersih;
        })->all();

        if (! $draf['temuan']) {
            $draf['temuan'] = [$this->temuanKosong()];
        }

        /* Tombol tambah/hapus mengirim form yang sama — isiannya tersimpan
           dulu, baru barisnya ditambah atau dibuang. Tanpa itu, menambah satu
           rekomendasi menghapus semua yang belum sempat disimpan. */
        $aksi = $req->input('aksi');
        if ($aksi === 'tambah-temuan') {
            $draf['temuan'][] = $this->temuanKosong();
        } elseif (str_starts_with((string) $aksi, 'hapus-temuan:')) {
            $i = (int) explode(':', $aksi)[1];
            unset($draf['temuan'][$i]);
            $draf['temuan'] = array_values($draf['temuan']) ?: [$this->temuanKosong()];
        } elseif (str_starts_with((string) $aksi, 'tambah-rekom:')) {
            $i = (int) explode(':', $aksi)[1];
            $draf['temuan'][$i]['rekom'][] = $this->rekomKosong();
        } elseif (str_starts_with((string) $aksi, 'hapus-rekom:')) {
            [, $i, $j] = explode(':', $aksi);
            unset($draf['temuan'][(int) $i]['rekom'][(int) $j]);
            $draf['temuan'][(int) $i]['rekom'] =
                array_values($draf['temuan'][(int) $i]['rekom']) ?: [$this->rekomKosong()];
        } elseif (str_starts_with((string) $aksi, 'tambah-tindakan:')) {
            [, $i, $j] = explode(':', $aksi);
            $draf['temuan'][(int) $i]['rekom'][(int) $j]['tindakan'][] = $this->tindakanKosong();
        } elseif (str_starts_with((string) $aksi, 'hapus-tindakan:')) {
            [, $i, $j, $t] = explode(':', $aksi);
            unset($draf['temuan'][(int) $i]['rekom'][(int) $j]['tindakan'][(int) $t]);
            $draf['temuan'][(int) $i]['rekom'][(int) $j]['tindakan'] =
                array_values($draf['temuan'][(int) $i]['rekom'][(int) $j]['tindakan'])
                ?: [$this->tindakanKosong()];
        } elseif (str_starts_with((string) $aksi, 'tambah-sasaran:')) {
            [, $i, $j, $t] = explode(':', $aksi);
            $draf['temuan'][(int) $i]['rekom'][(int) $j]['tindakan'][(int) $t]['sasaran'][]
                = $this->sasaranKosong();
        } elseif (str_starts_with((string) $aksi, 'hapus-sasaran:')) {
            [, $i, $j, $t, $k] = explode(':', $aksi);
            $jalur = &$draf['temuan'][(int) $i]['rekom'][(int) $j]['tindakan'][(int) $t]['sasaran'];
            unset($jalur[(int) $k]);
            $jalur = array_values($jalur) ?: [$this->sasaranKosong()];
            unset($jalur);
        }

        /* Ditahan di langkah yang sama kalau masih ada yang kurang — bukan
           dilarang mengirim. Isian yang tidak pernah sampai ke peladen tidak
           pernah bisa diperiksa, dan halamannya buntu. */
        $kurang = $this->kurang2($draf);
        $draf['langkah'] = ($aksi === 'lanjut' && ! $kurang) ? 3 : (int) $req->input('ke', 2);
        $this->simpan($draf);

        if ($aksi === 'lanjut' && $kurang) {
            return redirect()->route('laporan.baru')->with('gagal', $kurang);
        }

        return redirect()->route('laporan.baru');
    }

    public function keLangkah(Request $req, int $langkah)
    {
        $this->bolehMencatat();
        $draf = $this->draf();
        $draf['langkah'] = max(1, min(3, $langkah));
        $this->simpan($draf);

        return redirect()->route('laporan.baru');
    }

    public function batal()
    {
        $this->bolehMencatat();
        session()->forget(self::KUNCI);

        return redirect()->route('rekomendasi.index')->with('pesan', 'Pencatatan laporan dibatalkan.');
    }

    /* ---------- langkah 3: ajukan ---------- */

    public function ajukan()
    {
        $this->bolehMencatat();
        $draf = $this->draf();

        if (! $this->lengkap1($draf) || $this->kurang2($draf)) {
            return redirect()->route('laporan.baru')
                ->with('gagal', 'Masih ada isian yang belum lengkap.');
        }

        $sumber = SumberLaporan::from($draf['surat']['sumber']);

        $laporan = DB::transaction(function () use ($draf, $sumber) {
            $lap = Laporan::create([
                'sumber' => $sumber->value,
                'nomor' => $draf['surat']['nomor'],
                'tgl_surat' => $draf['surat']['tgl_surat'],
                'tgl_terima' => $draf['surat']['tgl_terima'],
                /* Diisi sistem, bukan diketik: tanggal pencatatan yang bisa
                   diatur sendiri tidak ada gunanya sebagai catatan. */
                'dicatat_pada' => now()->toDateString(),
                'dicatat_oleh' => auth()->id(),
            ]);

            if ($draf['surat']['berkas']) {
                Lampiran::create([
                    'laporan_id' => $lap->id,
                    'jenis_dokumen_id' => Referensi::jenis(JenisReferensi::JENIS_DOKUMEN)
                        ->where('nama', 'Surat laporan pemeriksaan')->value('id'),
                    'nama_asli' => 'surat-laporan.pdf',
                    'nama_simpan' => bin2hex(random_bytes(8)) . '.pdf',
                    'mime' => 'application/pdf',
                    'diunggah_oleh' => auth()->id(),
                    'diunggah_pada' => now(),
                ]);
            }

            $tenggat = Rekomendasi::hitungTenggat($lap->tgl_terima, $sumber);
            $urut = Temuan::whereYear('created_at', now()->year)->count();

            foreach ($draf['temuan'] as $t) {
                $urut++;
                $tem = Temuan::create([
                    'laporan_id' => $lap->id,
                    'kode' => 'TMN-' . now()->year . '-' . str_pad((string) $urut, 3, '0', STR_PAD_LEFT),
                    'nomor_pada_surat' => $t['nomor_pada_surat'] ?: null,
                    'judul' => $t['judul'],

                    'sebab' => $t['sebab'] ?: null, 'akibat' => $t['akibat'] ?: null,
                    'kategori_temuan_id' => $t['kategori'] ?: null,
                    'kategori_intern_id' => $t['kategori_intern'] ?: null,
                    'nilai' => (int) preg_replace('/\D/', '', (string) $t['nilai']),
                ]);

                // Satuan kerja terperiksa lewat pivot, dan boleh lebih dari satu.
                if ($t['satker']) {
                    $tem->satkers()->syncWithoutDetaching(array_map('intval', (array) $t['satker']));
                }

                foreach (array_values($t['rekom']) as $j => $r) {
                    /* Baris sasaran yang benar-benar diisi, dikelompokkan per
                       bentuk tindak lanjut. Baris kosong yang tertinggal di
                       formulir tidak boleh jadi penugasan hantu — ia akan
                       berdiri di daftar tanpa satuan kerja.

                       Satuan kerja yang sama boleh muncul di dua bentuk: itu
                       memang dua kewajiban terpisah, dan keduanya harus tuntas
                       sendiri-sendiri. Yang tidak boleh cuma dua kali di dalam
                       SATU bentuk. */
                    $perBentuk = collect($r['tindakan'] ?? [])
                        ->map(fn ($tk) => [
                            'bentuk' => $tk['bentuk'] ?? '',
                            'tgl_renaksi' => $tk['tgl_renaksi'] ?? '',
                            'baris' => collect($tk['sasaran'] ?? [])
                                ->filter(fn ($x) => filled($x['satker'] ?? null))
                                ->map(fn ($x) => [
                                    'satker_id' => (int) $x['satker'],
                                    'nilai' => (int) preg_replace('/\D/', '', (string) ($x['nilai'] ?? '')),
                                ])
                                ->unique('satker_id')
                                ->values(),
                        ])
                        ->filter(fn ($tk) => $tk['baris']->isNotEmpty())
                        ->values();

                    // Tagihan rekomendasi = jumlah bagian seluruh bentuknya.
                    $nilai = (int) $perBentuk->sum(fn ($tk) => $tk['baris']->sum('nilai'));

                    $rek = Rekomendasi::create([
                        'temuan_id' => $tem->id,
                        'kode' => str_replace('TMN', 'REK', $tem->kode) . '.' . ($j + 1),
                        'nomor_urut' => $j + 1,
                        'uraian' => $r['uraian'],
                        'nilai_pulih' => $nilai,
                        'tenggat_jawab' => $tenggat->toDateString(),
                        'sifat_id' => $r['sifat'] ?: null,
                        'catatan' => $r['catatan'] ?: null,
                        'status' => StatusTindakLanjut::BT->value,
                        /* Tingkat 2 belum berjalan. NULL, bukan 'satker' —
                           gerak satuan kerja hidup di sasarannya. */
                        'posisi' => null,
                    ]);

                    /* Satu tindakan per bentuk tindak lanjut, masing-masing
                       dengan satuan kerjanya sendiri.

                       Tanggalnya ikut tersimpan di tindakannya, bukan cuma di
                       kepala rekomendasi. Tetap dihitung sistem, bukan diketik:
                       dasar hukumnya melekat pada tanggal laporan diterima. */
                    foreach ($perBentuk as $u => $tk) {
                        $tindakan = Tindakan::create([
                            'rekomendasi_id' => $rek->id,
                            'bentuk_id' => $tk['bentuk'] ?: null,
                            'urutan' => $u + 1,
                            /* Yang diketik Setba menang atas yang dihitung
                               sistem; yang dibiarkan kosong ikut tanggal
                               rekomendasinya. */
                            'tgl_renaksi' => $tk['tgl_renaksi'] ?: $tenggat->toDateString(),
                            'target_selesai' => $rek->target_selesai,
                        ]);

                        foreach ($tk['baris'] as $x) {
                            Sasaran::create([
                                'tindakan_id' => $tindakan->id,
                                'satker_id' => $x['satker_id'],
                                'nilai' => $x['nilai'],
                                'posisi' => PosisiBerkas::SATKER->value,
                            ]);
                        }
                    }

                    RiwayatBerkas::create([
                        'rekomendasi_id' => $rek->id, 'waktu' => now(),
                        'aktor_id' => auth()->id(), 'label_aktor' => 'Setba',
                        'aksi' => 'Rekomendasi dikirim ke satuan kerja',
                        'posisi_ke' => PosisiBerkas::SATKER->value,
                    ]);
                }
            }

            return $lap;
        });

        session()->forget(self::KUNCI);

        return redirect()->route('laporan.show', $laporan)
            ->with('pesan', 'Laporan dicatat dan rekomendasinya dikirim ke satuan kerja.');
    }

    /* ---------- kelengkapan ---------- */

    private function lengkap1(array $draf): bool
    {
        $s = $draf['surat'];

        /* Pindaian suratnya tidak ikut menentukan. Surat kerap sampai lebih
           dulu daripada pindaiannya, dan menahan pencatatan sampai pindaiannya
           ada justru merugikan: tenggat jawabannya sudah berjalan sementara
           belum ada satu pun satuan kerja yang bisa mengerjakannya.
           Ketiadaannya ditandai di halaman laporannya, bukan ditahan di sini. */
        return trim($s['nomor']) !== ''
            && $s['tgl_surat'] && $s['tgl_terima']
            && $this->salahTanggal($s) === '';
    }

    /**
     * Urutan tanggal surat, sama persis dengan aturan `validate()` di
     * `surat()`. Diperiksa lagi di sini karena draf bisa memuat isian yang
     * tersimpan sebelum aturannya ada — dan yang membacanya `ajukan()`, satu
     * langkah sebelum datanya masuk ke basis data untuk selamanya.
     */
    private function salahTanggal(array $s): string
    {
        if (! $s['tgl_surat'] || ! $s['tgl_terima']) {
            return '';
        }
        $ini = now()->toDateString();
        if ($s['tgl_surat'] > $ini) {
            return 'tanggal surat masih di depan hari ini';
        }
        if ($s['tgl_terima'] > $ini) {
            return 'tanggal diterima masih di depan hari ini';
        }
        if ($s['tgl_terima'] < $s['tgl_surat']) {
            return 'tanggal diterima mendahului tanggal suratnya';
        }

        return '';
    }

    /**
     * Kalau tombol berikutnya mati, sebutkan isian mana yang kurang.
     * "Lengkapi isian pada langkah ini" memaksa pengisi menebak-nebak sendiri.
     */
    private function kurang2(array $draf): string
    {
        foreach (array_values($draf['temuan']) as $i => $t) {
            $k = [];
            if (! trim($t['judul'])) $k[] = 'judul';

            if (! $t['kategori']) $k[] = 'kategori temuan';
            if (! $t['satker']) $k[] = 'satuan kerja terperiksa';

            $nilai = (int) preg_replace('/\D/', '', (string) $t['nilai']);
            $tagihan = collect($t['rekom'])->sum(fn ($r) => $this->tagihanRekom($r));

            /* Tagihan tidak boleh melebihi nilai temuannya sendiri — kalau
               melebihi, salah satunya salah ketik, dan mana pun yang salah
               angkanya akan salah selamanya. */
            if ($tagihan > $nilai) {
                $k[] = 'tagihan rekomendasi melebihi nilai temuan';
            }

            foreach (array_values($t['rekom']) as $j => $r) {
                $nomor = ($i + 1) . '.' . ($j + 1);

                if (! trim($r['uraian'])) {
                    $k[] = 'rekomendasi ' . $nomor . ' belum lengkap';
                }

                $tindakan = array_values($r['tindakan'] ?? []);
                foreach ($tindakan as $t2 => $tk) {
                    /* Nomor bentuknya disebut cuma kalau bentuknya memang lebih
                       dari satu. "Bentuk 1" pada rekomendasi yang cuma punya
                       satu bentuk menyuruh pengisi mencari sesuatu yang tidak
                       terlihat di layarnya. */
                    $sebut = count($tindakan) > 1
                        ? $nomor . ' bentuk ' . ($t2 + 1) : $nomor;

                    if (! $tk['bentuk']) {
                        $k[] = 'rekomendasi ' . $sebut . ' belum dipilih bentuk tindak lanjutnya';
                    }

                    /* Tenggat yang mendahului tanggal laporan diterima tidak
                       mungkin benar — ia menuntut satuan kerja menjawab
                       sebelum suratnya sampai. */
                    $terima = $draf['surat']['tgl_terima'] ?? '';
                    if (($tk['tgl_renaksi'] ?? '') && $terima
                        && $tk['tgl_renaksi'] < $terima) {
                        $k[] = 'rencana aksi rekomendasi ' . $sebut
                            . ' mendahului tanggal laporan diterima';
                    }

                    /* Bentuk tindak lanjut tanpa satuan kerja tidak punya siapa
                       pun yang mengerjakannya — ia akan berdiri di daftar tanpa
                       pernah bergerak, dan tidak ada yang tahu itu salah. */
                    $dituju = collect($tk['sasaran'] ?? [])->pluck('satker')->filter();
                    if ($dituju->isEmpty()) {
                        $k[] = 'rekomendasi ' . $sebut . ' belum ditujukan ke satuan kerja';
                    } elseif ($dituju->count() !== $dituju->unique()->count()) {
                        $k[] = 'rekomendasi ' . $sebut . ' menyebut satuan kerja yang sama dua kali';
                    }
                }
            }

            if ($k) {
                return 'Temuan ' . ($i + 1) . ' belum lengkap: ' . implode(', ', $k);
            }
        }

        return '';
    }
}
