<?php

namespace App\Http\Controllers;

use App\Enums\JenisReferensi;
use App\Enums\PeranPengguna;
use App\Enums\PosisiBerkas;
use App\Enums\StatusTindakLanjut;
use App\Enums\SumberLaporan;
use App\Models\DrafLaporan;
use App\Models\ItemPermintaan;
use App\Models\KategoriTemuan;
use App\Models\Lampiran;
use App\Models\Laporan;
use App\Models\PermintaanDokumen;
use App\Models\Referensi;
use App\Models\Rekomendasi;
use App\Models\Sasaran;
use App\Models\Satker;
use App\Models\Temuan;
use App\Models\Tindakan;
use App\Support\BentukTindakLanjut;
use App\Support\Jejak;
use App\Support\Kabar;
use App\Support\Tampil;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Catat laporan baru — padanan `FormBaru` dan `simpanBaru` prototipe.
 *
 * Tiga langkah: data surat, temuan & rekomendasinya, lalu tinjau dan kirim.
 * Temuan ditampilkan satu per satu, bukan ditumpuk ke bawah: satu laporan bisa
 * memuat belasan temuan, dan yang ditumpuk memaksa pengisinya menggulir jauh
 * hanya untuk melihat yang sedang dikerjakan.
 *
 * Isian disimpan sebagai draf di basis data, satu per pengguna. Rapat Agustus:
 * "Save Draft wajib (bukan fitur tambahan) karena form bertingkat jadi sangat
 * panjang." Draf hanya terlihat pembuatnya dan belum terkirim ke satuan kerja
 * mana pun.
 *
 * Seluruh perbuatan lewat kiriman formulir biasa, bukan JavaScript: menekan
 * tombol apa pun membawa seluruh isian yang sedang tampak, jadi tidak ada
 * ketikan yang hilang.
 */
class LaporanBaruController extends Controller
{
    /* ================================================================
       BENTUK DRAF
       ================================================================ */

    private function boleh(): void
    {
        abort_unless(in_array(auth()->user()->peran, [PeranPengguna::SETBA, PeranPengguna::ADMIN], true),
            403, 'Hanya Setba yang mencatat laporan pemeriksaan.');
    }

    private function id(string $awalan): string
    {
        return $awalan.Str::lower(Str::random(6));
    }

    private function kosong(): array
    {
        $t = $this->temKosong();

        return [
            'n'      => 1,
            'surat'  => ['sumber' => 'LHP', 'nomor' => '', 'tgl_surat' => '', 'tgl_terima' => ''],
            'berkas' => ['judul' => '', 'tautan' => ''],
            'temuan' => [$t],
            'aktif'  => $t['id'],
            /* `null` = bawaan, rekomendasi terakhir yang terbuka. `''` =
               semuanya ditutup dengan sengaja. */
            'buka'     => null,
            'disimpan' => null,
        ];
    }

    private function temKosong(): array
    {
        return [
            'id' => $this->id('t'), 'nomor' => '', 'judul' => '', 'sebab' => '', 'akibat' => '',
            /* Kosong, bukan satuan kerja pertama daftar: pilihan bawaan yang
               diam-diam menugaskan satuan kerja yang tidak diperiksa kalau
               terlewat. Daftar kosong itulah yang menahan langkah berikutnya. */
            'satker'   => [],
            'kategori' => '',
            'intern'   => (string) (Referensi::where('jenis', JenisReferensi::KATEGORI_INTERN->value)
                ->where('aktif', true)->orderBy('urutan')->orderBy('id')->value('id') ?? ''),
            'rekom'    => [$this->rekKosong()],
        ];
    }

    private function rekKosong(): array
    {
        return [
            'id' => $this->id('r'), 'uraian' => '', 'ref_lhp' => '',
            'sifat'  => (string) (Referensi::where('jenis', JenisReferensi::SIFAT_REKOM->value)
                ->orderBy('urutan')->orderBy('id')->value('id') ?? ''),
            'angsur' => '', 'kunci' => false,
            /* Satu tindak lanjut untuk memulai. Satuan kerjanya dipilih di
               dalamnya, dan tindak lanjut kedua ditambahkan sendiri kalau
               memang ada satuan kerja lain yang dituntut hal berbeda. */
            'tindakan' => [$this->tindakanKosong()],
        ];
    }

    private function tindakanKosong(?string $bentuk = null, string $renaksi = ''): array
    {
        $bentuk ??= BentukTindakLanjut::nama()[0];

        return [
            'id' => $this->id('k'), 'bentuk' => $bentuk, 'satker' => [],
            /* Terisi awal dari bentuk tindak lanjutnya. Setba tetap bisa
               mengubah — daftarnya cuma usulan. */
            'dokumen'     => BentukTindakLanjut::peta()[$bentuk] ?? [''],
            'tgl_renaksi' => $renaksi, 'target' => '', 'catatan' => '',
        ];
    }

    private function draf(): array
    {
        $baris = DrafLaporan::where('user_id', auth()->id())->first();

        return $baris ? array_replace($this->kosong(), $baris->isian) : $this->kosong();
    }

    private function simpanDraf(array $d): void
    {
        $d['disimpan'] = now()->toDateString();

        DrafLaporan::updateOrCreate(['user_id' => auth()->id()], ['isian' => $d]);
    }

    private function hapusDraf(): void
    {
        DrafLaporan::where('user_id', auth()->id())->delete();
    }

    /* ================================================================
       LAYAR
       ================================================================ */

    public function form()
    {
        $this->boleh();
        $d = $this->draf();

        /* Keterangan "melanjutkan draf" hanya untuk yang kembali ke formulir
           yang pernah ditinggalkan, bukan untuk yang sedang mengisinya. */
        $ada = (bool) ($d['ditinggal'] ?? false);
        $i = array_search($d['aktif'], array_column($d['temuan'], 'id'), true);

        return view('laporan.baru', [
            'd'        => $d,
            'iAktif'   => $i === false ? 0 : $i,
            'adaDraf'  => $ada,
            'satker'   => Satker::orderBy('id')->get(),
            'kategori' => KategoriTemuan::where('aktif', true)->orderBy('urutan')->orderBy('id')->get(),
            'intern'   => Referensi::where('jenis', JenisReferensi::KATEGORI_INTERN->value)
                ->orderBy('urutan')->orderBy('id')->get(),
            'sifat'    => Referensi::where('jenis', JenisReferensi::SIFAT_REKOM->value)
                ->orderBy('urutan')->orderBy('id')->get(),
            'bentuk'   => BentukTindakLanjut::nama(),
            'ok1'      => $this->ok1($d),
            'kurang'   => $this->kurangnya($d),
            'ringkas'  => $this->ringkas($d),
            'adaIsi'   => $this->adaIsi($d),
            'form'     => $this,
        ]);
    }

    /**
     * Satu pintu untuk seluruh perbuatan di formulir ini: isian yang sedang
     * tampak diserap dulu, baru tombolnya dikerjakan. Tanpa itu, menambah satu
     * rekomendasi menghapus semua yang belum sempat tersimpan.
     */
    public function simpan(Request $req)
    {
        $this->boleh();

        $d = $this->serap($req, $this->draf());
        $aksi = (string) $req->input('aksi', '');

        if ($aksi === 'ajukan') {
            return $this->ajukan($d);
        }
        if ($aksi === 'kosongkan') {
            $this->hapusDraf();

            return redirect()->route('laporan.baru');
        }

        /* Ditahan di langkah yang sama kalau masih ada yang kurang — bukan
           dilarang mengirim. Tombol yang dimatikan sampai isiannya lengkap
           membuat halamannya buntu: isian yang tidak pernah sampai ke peladen
           tidak pernah bisa diperiksa. */
        if ($aksi === 'maju' && ! ($d['n'] === 1 ? $this->ok1($d) : $this->ok2($d))) {
            $this->simpanDraf($d);

            return redirect()->route('laporan.baru')->with('gagal', $this->kurangnya($d));
        }

        $d = $this->terapkan($d, $aksi);
        /* Keterangan "melanjutkan draf" cuma untuk yang kembali ke formulir
           yang pernah ditinggalkan — bukan untuk yang sedang mengisinya. */
        $d['ditinggal'] = in_array($aksi, ['simpan-draf', 'tinggalkan'], true);
        $this->simpanDraf($d);

        /* "Simpan draft" dan "Kembali ke beranda" meninggalkan formulir —
           isiannya sudah tersimpan, jadi tidak ada yang hilang. */
        if ($aksi === 'simpan-draf' || $aksi === 'tinggalkan') {
            return redirect()->route('rekomendasi.index');
        }

        return redirect()->route('laporan.baru');
    }

    /** Membuang draf tanpa membuka formulirnya. */
    public function buang()
    {
        $this->boleh();
        $this->hapusDraf();

        return redirect()->route('rekomendasi.index');
    }

    /* ================================================================
       MENYERAP ISIAN
       ================================================================ */

    private function serap(Request $req, array $d): array
    {
        $s = (array) $req->input('surat', []);
        $lama = $d['surat'];
        $d['surat'] = [
            'sumber'     => in_array($s['sumber'] ?? '', ['LHP', 'LHA'], true) ? $s['sumber'] : $lama['sumber'],
            'nomor'      => trim((string) ($s['nomor'] ?? $lama['nomor'])),
            'tgl_surat'  => (string) ($s['tgl_surat'] ?? $lama['tgl_surat']),
            'tgl_terima' => (string) ($s['tgl_terima'] ?? $lama['tgl_terima']),
        ];
        $gantiSumber = $lama['sumber'] !== $d['surat']['sumber'];

        $b = (array) $req->input('berkas', []);
        $d['berkas'] = [
            'judul'  => trim((string) ($b['judul'] ?? $d['berkas']['judul'])),
            'tautan' => trim((string) ($b['tautan'] ?? $d['berkas']['tautan'])),
        ];

        /* Hanya temuan yang sedang tampak yang dikirim; sisanya tetap seperti
           tersimpan. */
        $isi = (array) $req->input('t', []);
        if ($isi) {
            $d['temuan'] = array_map(fn ($t) => $t['id'] === ($isi['id'] ?? null)
                ? $this->serapTemuan($t, $isi) : $t, $d['temuan']);
        }

        /* Sumber laporan berganti: daftar kategori temuannya ikut berganti,
           jadi yang sudah dipilih tidak lagi ada di daftarnya. */
        if ($gantiSumber) {
            $d['temuan'] = array_map(function ($t) {
                $t['kategori'] = '';

                return $t;
            }, $d['temuan']);
        }

        return $this->isiRenaksi($d, $gantiSumber);
    }

    private function serapTemuan(array $t, array $isi): array
    {
        $t['nomor']    = trim((string) ($isi['nomor'] ?? $t['nomor']));
        $t['judul']    = trim((string) ($isi['judul'] ?? $t['judul']));
        $t['sebab']    = trim((string) ($isi['sebab'] ?? $t['sebab']));
        $t['akibat']   = trim((string) ($isi['akibat'] ?? $t['akibat']));
        $t['kategori'] = (string) ($isi['kategori'] ?? $t['kategori']);
        $t['intern']   = (string) ($isi['intern'] ?? $t['intern']);
        /* Kotak centang yang tidak dicentang tidak dikirim sama sekali, jadi
           daftar kosong memang berarti kosong. */
        $t['satker'] = array_values(array_unique(array_map('intval', (array) ($isi['satker'] ?? []))));

        $t['rekom'] = array_map(function ($r) use ($isi, $t) {
            $ri = $isi['rekom'][$r['id']] ?? null;
            if (! is_array($ri)) {
                return $r;
            }
            $r['uraian']  = trim((string) ($ri['uraian'] ?? $r['uraian']));
            $r['ref_lhp'] = trim((string) ($ri['ref_lhp'] ?? $r['ref_lhp']));
            $r['sifat']   = (string) ($ri['sifat'] ?? $r['sifat']);
            $r['angsur']  = preg_replace('/\D/', '', (string) ($ri['angsur'] ?? $r['angsur']));
            $r['kunci']   = (bool) ($ri['kunci'] ?? false);
            $r['tindakan'] = array_map(fn ($tk) => is_array($ri['tindakan'][$tk['id']] ?? null)
                ? $this->serapTindakan($tk, $ri['tindakan'][$tk['id']], $t['satker'])
                : $tk, $r['tindakan']);

            return $r;
        }, $t['rekom']);

        return $t;
    }

    private function serapTindakan(array $tk, array $ki, array $satkerTemuan): array
    {
        $bentukLama = $tk['bentuk'];
        $tk['bentuk'] = trim((string) ($ki['bentuk'] ?? $tk['bentuk']));

        /* Usul dokumen ikut berganti saat bentuknya berganti — tapi hanya kalau
           daftarnya belum pernah disunting tangan. Kalau sudah, suntingan Setba
           yang menang, termasuk suntingan yang datang bersamaan dengan
           pergantian bentuknya. */
        $utuh = $tk['dokumen'] === (BentukTindakLanjut::peta()[$bentukLama] ?? []);
        $kiriman = array_values(array_map(fn ($x) => trim((string) $x),
            (array) ($ki['dokumen'] ?? $tk['dokumen'])));
        $disunting = $kiriman !== $tk['dokumen'];
        $tk['dokumen'] = $kiriman;
        if ($tk['bentuk'] !== $bentukLama && $utuh && ! $disunting) {
            $tk['dokumen'] = BentukTindakLanjut::peta()[$tk['bentuk']] ?? [''];
        }

        $tk['tgl_renaksi'] = (string) ($ki['tgl_renaksi'] ?? $tk['tgl_renaksi']);
        $tk['target']      = (string) ($ki['target'] ?? $tk['target']);
        $tk['catatan']     = trim((string) ($ki['catatan'] ?? $tk['catatan']));

        /* Nilai yang sudah diketik dipertahankan untuk satuan kerja yang masih
           terpilih — mengetiknya ulang tiap kali daftarnya disentuh cuma
           mengundang salah ketik. Yang bukan satuan kerja terperiksa dibuang:
           ia tidak akan pernah bisa menindaklanjutinya. */
        $lama = collect($tk['satker'])->keyBy('satker');
        $dipilih = array_values(array_intersect(
            array_map('intval', (array) ($ki['satker'] ?? [])), $satkerTemuan));
        $tk['satker'] = array_map(fn ($id) => [
            'satker' => $id,
            'nilai'  => preg_replace('/\D/', '',
                (string) ($ki['nilai'][$id] ?? $lama[$id]['nilai'] ?? '')),
        ], $dipilih);

        return $tk;
    }

    /**
     * Mengisi tanggal rencana aksi seluruh tindak lanjut. Yang sudah disetel
     * tangan dibiarkan — kecuali sumber laporannya berganti: tenggatnya memang
     * berubah dasar hukumnya, jadi yang lama tidak lagi berlaku.
     */
    private function isiRenaksi(array $d, bool $paksa): array
    {
        if (! $d['surat']['tgl_terima']) {
            return $d;
        }
        $tenggat = Rekomendasi::hitungTenggat(
            Carbon::parse($d['surat']['tgl_terima']),
            SumberLaporan::from($d['surat']['sumber'])
        )->toDateString();

        $d['temuan'] = array_map(fn ($t) => array_merge($t, [
            'rekom' => array_map(fn ($r) => array_merge($r, [
                'tindakan' => array_map(fn ($tk) => array_merge($tk, [
                    'tgl_renaksi' => ($paksa || ! $tk['tgl_renaksi']) ? $tenggat : $tk['tgl_renaksi'],
                ]), $r['tindakan']),
            ]), $t['rekom']),
        ]), $d['temuan']);

        return $d;
    }

    /* ================================================================
       TOMBOL
       ================================================================ */

    private function terapkan(array $d, string $aksi): array
    {
        $bagian = explode(':', $aksi);
        [$apa, $a, $b, $c, $e] = array_pad($bagian, 5, null);
        $iTem = fn ($id) => array_search($id, array_column($d['temuan'], 'id'), true);

        switch ($apa) {
            case 'maju':
                $d['n'] = min(3, $d['n'] + 1);
                break;
            case 'mundur':
                $d['n'] = max(1, $d['n'] - 1);
                break;
            case 'langkah':
                $d['n'] = max(1, min(3, (int) $a));
                break;

            case 'temuan':
                $d['aktif'] = (string) $a;
                /* Berpindah temuan melepas pilihan rekomendasi yang terbuka:
                   pilihan lama masih menunjuk rekomendasi milik temuan lain. */
                $d['buka'] = null;
                break;
            case 'tambah-temuan':
                $t = $this->temKosong();
                $d['temuan'][] = $t;
                $d['aktif'] = $t['id'];
                $d['buka'] = null;
                $d = $this->isiRenaksi($d, false);
                break;
            case 'hapus-temuan':
                $i = $iTem($a);
                if (count($d['temuan']) > 1 && $i !== false) {
                    $ganti = $d['temuan'][$i - 1] ?? $d['temuan'][$i + 1] ?? null;
                    unset($d['temuan'][$i]);
                    $d['temuan'] = array_values($d['temuan']);
                    if ($d['aktif'] === $a) {
                        $d['aktif'] = $ganti['id'] ?? $d['temuan'][0]['id'];
                    }
                }
                break;

            case 'buka-rek':
                $d['buka'] = $d['buka'] === $a ? '' : (string) $a;
                break;
            case 'tambah-rek':
                $i = $iTem($a);
                if ($i !== false) {
                    $r = $this->rekKosong();
                    $d['temuan'][$i]['rekom'][] = $r;
                    $d['buka'] = $r['id'];
                    $d = $this->isiRenaksi($d, false);
                }
                break;
            case 'hapus-rek':
                $i = $iTem($a);
                if ($i !== false && count($d['temuan'][$i]['rekom']) > 1) {
                    $d['temuan'][$i]['rekom'] = array_values(array_filter(
                        $d['temuan'][$i]['rekom'], fn ($r) => $r['id'] !== $b));
                }
                break;

            case 'tambah-tindakan':
                $d = $this->padaRek($d, $a, $b, fn ($r) => array_merge($r, [
                    /* Tanggalnya ikut tindakan pertama sebagai titik mula —
                       lebih sering benar daripada kosong, dan tetap bisa
                       diubah. */
                    'tindakan' => array_merge($r['tindakan'],
                        [$this->tindakanKosong(null, (string) ($r['tindakan'][0]['tgl_renaksi'] ?? ''))]),
                ]));
                break;
            case 'hapus-tindakan':
                $d = $this->padaRek($d, $a, $b, function ($r) use ($c) {
                    if (count($r['tindakan']) > 1) {
                        unset($r['tindakan'][(int) $c]);
                        $r['tindakan'] = array_values($r['tindakan']);
                    }

                    return $r;
                });
                break;
            case 'tambah-dok':
                $d = $this->padaRek($d, $a, $b, function ($r) use ($c) {
                    $r['tindakan'][(int) $c]['dokumen'][] = '';

                    return $r;
                });
                break;
            case 'hapus-dok':
                $d = $this->padaRek($d, $a, $b, function ($r) use ($c, $e) {
                    unset($r['tindakan'][(int) $c]['dokumen'][(int) $e]);
                    $r['tindakan'][(int) $c]['dokumen'] = array_values($r['tindakan'][(int) $c]['dokumen']);

                    return $r;
                });
                break;

            /* Naik-turun jumlah angsuran. Batas bawahnya dua kali, karena "satu
               kali" bukan angsuran melainkan pembayaran sekaligus — dan itu
               diwakili isian kosong. Batas atasnya 24, mengikuti tenggang paling
               lama surat pernyataan kesanggupan menurut PP 38/2016. */
            case 'angsur':
                $d = $this->padaRek($d, $b, $c, function ($r) use ($a) {
                    $kini = (int) $r['angsur'];
                    $baru = $kini === 0 ? ($a === 'naik' ? 2 : 0) : $kini + ($a === 'naik' ? 1 : -1);
                    $r['angsur'] = $baru < 2 ? '' : (string) min($baru, 24);
                    $r['kunci'] = $r['angsur'] ? $r['kunci'] : false;

                    return $r;
                });
                break;
        }

        return $d;
    }

    private function padaRek(array $d, $tid, $rid, callable $fn): array
    {
        $d['temuan'] = array_map(function ($t) use ($tid, $rid, $fn) {
            if ($t['id'] !== $tid) {
                return $t;
            }
            $t['rekom'] = array_map(fn ($r) => $r['id'] === $rid ? $fn($r) : $r, $t['rekom']);

            return $t;
        }, $d['temuan']);

        return $d;
    }

    /* ================================================================
       KELENGKAPAN
       ================================================================ */

    public function adaIsi(array $d): bool
    {
        $s = $d['surat'];
        if ($s['nomor'] || $s['tgl_surat'] || $s['tgl_terima']
            || $d['berkas']['judul'] || $d['berkas']['tautan']) {
            return true;
        }

        foreach ($d['temuan'] as $t) {
            if ($t['judul'] || $t['sebab'] || $t['akibat'] || $t['nomor']) {
                return true;
            }
            foreach ($t['rekom'] as $r) {
                if (trim($r['uraian']) || $this->barisRek($r)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Jumlah penugasan sebuah rekomendasi. */
    public function barisRek(array $r): int
    {
        return (int) collect($r['tindakan'])->sum(fn ($tk) => count($tk['satker']));
    }

    /** Nilai rekomendasi: jumlah bagian tiap satuan kerja, tidak pernah diketik. */
    public function nilaiRek(array $r): int
    {
        return (int) collect($r['tindakan'])->flatMap(fn ($tk) => $tk['satker'])
            ->sum(fn ($x) => (int) $x['nilai']);
    }

    /** Nilai temuan: jumlah tagihan seluruh rekomendasinya. */
    public function nilaiTem(array $t): int
    {
        return (int) collect($t['rekom'])->sum(fn ($r) => $this->nilaiRek($r));
    }

    public function salahTanggal(array $s): string
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

    private function ok1(array $d): bool
    {
        $s = $d['surat'];

        return trim($s['nomor']) !== '' && $s['tgl_surat'] && $s['tgl_terima']
            && $this->salahTanggal($s) === '';
    }

    /** Tindak lanjut tanpa satuan kerja tidak menuntut siapa pun. */
    public function tindakanOk(array $tk): bool
    {
        return count($tk['satker']) > 0 && trim((string) $tk['tgl_renaksi']) !== '';
    }

    public function rekOk(array $r): bool
    {
        return trim($r['uraian']) !== '' && $this->barisRek($r) > 0
            && collect($r['tindakan'])->every(fn ($tk) => $this->tindakanOk($tk));
    }

    public function temOk(array $t): bool
    {
        return trim($t['judul']) !== '' && trim($t['sebab']) !== '' && trim($t['akibat']) !== ''
            && count($t['satker']) > 0 && $t['kategori'] !== ''
            && collect($t['rekom'])->every(fn ($r) => $this->rekOk($r));
    }

    public function ok2(array $d): bool
    {
        return collect($d['temuan'])->every(fn ($t) => $this->temOk($t));
    }

    /**
     * Kalau tombol berikutnya mati, sebutkan isian mana yang kurang. "Lengkapi
     * isian pada langkah ini" memaksa pengisi menebak-nebak sendiri.
     */
    public function kurangnya(array $d): string
    {
        if ($d['n'] === 1) {
            $k = [];
            if (! trim($d['surat']['nomor'])) {
                $k[] = 'nomor surat';
            }
            if (! $d['surat']['tgl_surat']) {
                $k[] = 'tanggal surat';
            }
            if (! $d['surat']['tgl_terima']) {
                $k[] = 'tanggal diterima';
            }
            /* Tanggal yang terisi tapi keliru urutannya disebut apa salahnya,
               bukan disebut "belum diisi" — isinya memang ada. */
            $salah = $this->salahTanggal($d['surat']);
            if ($salah) {
                return 'Perlu dibetulkan: '.$salah;
            }

            return $k ? 'Belum diisi: '.implode(', ', $k) : '';
        }

        if ($d['n'] === 2) {
            foreach (array_values($d['temuan']) as $i => $t) {
                if ($this->temOk($t)) {
                    continue;
                }
                $k = [];
                if (! trim($t['judul'])) {
                    $k[] = 'judul';
                }
                if (! trim($t['sebab'])) {
                    $k[] = 'sebab';
                }
                if (! trim($t['akibat'])) {
                    $k[] = 'akibat';
                }
                if (! count($t['satker'])) {
                    $k[] = 'satuan kerja terperiksa';
                }
                if (! $t['kategori']) {
                    $k[] = 'kategori temuan';
                }

                foreach (array_values($t['rekom']) as $j => $r) {
                    if ($this->rekOk($r)) {
                        continue;
                    }
                    $k[] = 'rekomendasi '.($t['nomor'] ?: $i + 1).'.'.$this->huruf($j).' '
                        .(collect($r['tindakan'])->every(fn ($tk) => $this->tindakanOk($tk))
                            ? 'belum lengkap'
                            : 'ada tindak lanjut yang belum lengkap satuan kerja atau tanggal rencana aksinya');
                    break;
                }

                return 'Temuan '.($i + 1).' belum lengkap: '.implode(', ', $k);
            }
        }

        return '';
    }

    public function huruf(int $j): string
    {
        return $j < 26 ? chr(97 + $j) : (string) ($j + 1);
    }

    /**
     * Angka yang dipakai kotak "Ajukan laporan" dan kartu di kaki langkah dua.
     * Dihitung dari tindak lanjutnya, bukan dari satuan kerja pertama tiap
     * rekomendasi.
     */
    public function ringkas(array $d): array
    {
        $sat = [];
        $ten = [];
        $pen = 0;
        $rek = 0;
        $nilai = 0;
        foreach ($d['temuan'] as $t) {
            foreach ($t['rekom'] as $r) {
                $rek++;
                $nilai += $this->nilaiRek($r);
                foreach ($r['tindakan'] as $tk) {
                    foreach ($tk['satker'] as $x) {
                        $sat[$x['satker']] = true;
                        $pen++;
                    }
                    if ($tk['tgl_renaksi']) {
                        $ten[$tk['tgl_renaksi']] = true;
                    }
                }
            }
        }

        return ['tem' => count($d['temuan']), 'rek' => $rek, 'sat' => count($sat),
            'pen' => $pen, 'ten' => count($ten), 'nilai' => $nilai];
    }

    /* ================================================================
       MENGAJUKAN
       ================================================================ */

    /**
     * Laporan pecah jadi penugasan terpisah ke banyak satuan kerja sekaligus —
     * tidak bisa ditarik lewat aplikasi. Padanan `simpanBaru` prototipe.
     */
    private function ajukan(array $d)
    {
        if (! $this->ok1($d) || ! $this->ok2($d)) {
            $this->simpanDraf($d);

            return redirect()->route('laporan.baru')->with('gagal', 'Masih ada isian yang belum lengkap.');
        }
        if (Laporan::where('nomor', $d['surat']['nomor'])->exists()) {
            $this->simpanDraf($d);

            return redirect()->route('laporan.baru')
                ->with('gagal', 'Nomor surat ini sudah pernah dicatat. Periksa lagi nomornya.');
        }

        $sumber = SumberLaporan::from($d['surat']['sumber']);
        $tahun = substr($d['surat']['tgl_surat'] ?: now()->toDateString(), 0, 4);
        $noSurat = explode('/', $d['surat']['nomor'])[0] ?? '';

        $laporan = DB::transaction(function () use ($d, $sumber, $tahun, $noSurat) {
            $lap = Laporan::create([
                'sumber'     => $sumber->value,
                'nomor'      => $d['surat']['nomor'],
                'tgl_surat'  => $d['surat']['tgl_surat'],
                'tgl_terima' => $d['surat']['tgl_terima'],
                /* Diisi sistem, bukan diketik: tanggal pencatatan yang bisa
                   diatur sendiri tidak ada gunanya sebagai catatan. */
                'dicatat_pada' => now()->toDateString(),
                'dicatat_oleh' => auth()->id(),
            ]);

            /* Surat aslinya tebal — LHP bisa ratusan halaman. Yang disimpan
               tautannya, bukan berkasnya. */
            if ($d['berkas']['judul']) {
                Lampiran::create([
                    'laporan_id'    => $lap->id,
                    'label_jenis'   => 'Surat laporan pemeriksaan',
                    'nama_asli'     => $d['berkas']['judul'],
                    'tautan'        => $d['berkas']['tautan'] ?: null,
                    'diunggah_oleh' => auth()->id(),
                    'label_oleh'    => 'Setba',
                    'surat_asli'    => true,
                    'diunggah_pada' => now(),
                ]);
            }

            $nomorTemuan = Temuan::count();
            $dipakai = [];

            foreach (array_values($d['temuan']) as $i => $t) {
                $nomorTemuan++;
                $tem = Temuan::create([
                    'laporan_id'       => $lap->id,
                    'kode'             => $this->kodeTemuan($tahun, $nomorTemuan),
                    'nomor_pada_surat' => $t['nomor'] ?: null,
                    'judul'            => $t['judul'],
                    'sebab'            => $t['sebab'] ?: null,
                    'akibat'           => $t['akibat'] ?: null,
                    'kategori_temuan_id' => $t['kategori'] ?: null,
                    'kategori_intern_id' => $t['intern'] ?: null,
                    /* Nol berarti "ikut jumlah rekomendasinya", bukan
                       "temuannya tidak bernilai" — yang membacanya
                       `nilaiTemuan()`, bukan medan ini langsung. */
                    'nilai' => 0,
                ]);
                $tem->satkers()->syncWithoutDetaching($t['satker']);

                foreach (array_values($t['rekom']) as $j => $r) {
                    /* Kodenya Ref IDT, dirakit dari surat yang sama persis
                       seperti yang dijanjikan formulirnya: tahun LHP . nomor
                       pendek LHP . Ref LHP. */
                    $refLhp = trim($r['ref_lhp'])
                        ?: collect([$t['nomor'] ?: $i + 1, $this->huruf($j)])->filter()->join('.');
                    $kode = $this->kodeUnik(collect([$tahun, $noSurat, $refLhp])->filter()->join('.'), $dipakai);

                    $tgl = collect($r['tindakan'])->pluck('tgl_renaksi')->filter()->sort()->values();
                    $tgt = collect($r['tindakan'])->pluck('target')->filter()->sort()->values();

                    $rek = Rekomendasi::create([
                        'temuan_id'   => $tem->id,
                        'kode'        => $kode,
                        'ref_lhp'     => trim($r['ref_lhp']) ?: null,
                        'nomor_urut'  => $j + 1,
                        'uraian'      => $r['uraian'],
                        'sifat_id'    => $r['sifat'] ?: null,
                        'nilai_pulih' => $this->nilaiRek($r),
                        /* Tenggat rekomendasi = tanggal paling awal di antara
                           tindak lanjutnya. Kalau satu tindakan sudah lewat,
                           rekomendasinya memang sudah lewat. Target = paling
                           akhir, karena baru tuntas kalau semuanya tuntas. */
                        'tenggat_jawab'  => $tgl->first(),
                        'target_selesai' => $tgt->last(),
                        'catatan'        => collect($r['tindakan'])->pluck('catatan')->filter()->first(),
                        'status'         => StatusTindakLanjut::BT->value,
                        'rencana_angsur' => (int) $r['angsur'],
                        'kunci_angsur'   => (bool) $r['kunci'] && (int) $r['angsur'] > 0,
                    ]);

                    foreach (array_values($r['tindakan']) as $u => $tk) {
                        $dok = collect($tk['dokumen'])->map(fn ($x) => trim((string) $x))
                            ->filter()->unique()->values()->all();

                        $tindakan = Tindakan::create([
                            'rekomendasi_id' => $rek->id,
                            'bentuk_id'      => $this->bentukId($tk['bentuk']),
                            'urutan'         => $u + 1,
                            'tgl_renaksi'    => $tk['tgl_renaksi'] ?: null,
                            'target_selesai' => $tk['target'] ?: null,
                            'catatan'        => trim((string) $tk['catatan']) ?: null,
                            'dokumen'        => $dok,
                        ]);

                        foreach ($tk['satker'] as $x) {
                            $baris = Sasaran::create([
                                'tindakan_id' => $tindakan->id,
                                'satker_id'   => $x['satker'],
                                'nilai'       => (int) $x['nilai'],
                                /* Posisi ditulis sejak awal. Tanpa itu baris
                                   yang belum bergerak harus menebak posisinya
                                   dari rekomendasi. */
                                'posisi' => PosisiBerkas::SATKER->value,
                            ]);

                            /* Satu daftar dokumen yang ditulis Setba melahirkan
                               kewajiban terpisah untuk tiap satuan kerja yang
                               dituju: tiga satuan kerja yang diminta surat
                               teguran berarti tiga surat teguran, dan yang satu
                               mengunggah tidak melunaskan yang dua lagi. */
                            if ($dok) {
                                $minta = PermintaanDokumen::create([
                                    'rekomendasi_id' => $rek->id,
                                    'sasaran_id'     => $baris->id,
                                    'peran_peminta'  => PeranPengguna::SETBA->value,
                                    'dari'           => 'Setba',
                                    'diminta_oleh'   => auth()->id(),
                                    'tanggal'        => now()->toDateString(),
                                ]);
                                foreach ($dok as $nama) {
                                    ItemPermintaan::create([
                                        'permintaan_dokumen_id' => $minta->id,
                                        'nama' => $nama, 'terpenuhi' => false,
                                    ]);
                                }
                            }
                        }
                    }

                    /* Surat pemeriksaannya ikut melekat pada tiap rekomendasi —
                       dan tidak pernah sampai ke satuan kerja: satu surat memuat
                       temuan seluruh satuan kerja. */
                    if ($d['berkas']['judul']) {
                        Lampiran::create([
                            'rekomendasi_id' => $rek->id,
                            'label_jenis'    => 'Surat temuan',
                            'nama_asli'      => $d['berkas']['judul'],
                            'tautan'         => $d['berkas']['tautan'] ?: null,
                            'diunggah_oleh'  => auth()->id(),
                            'label_oleh'     => 'Setba',
                            'surat_asli'     => true,
                            'diunggah_pada'  => now(),
                        ]);
                    }

                    $rek->load('tindakan.bentuk', 'tindakan.sasaran', 'sasaran.satker');

                    $nama = $rek->daftarSasaran()->map(fn ($x) => $x->satker?->namaPendek())
                        ->filter()->unique()->join(', ');
                    Jejak::riwayat($rek, 'Setba', 'Rekomendasi dikirim ke '.($nama ?: '—'));

                    /* Tiap satuan kerja yang ditugasi dikabari: rekomendasi mana,
                       tindak lanjut apa yang dipikulnya, dan rencana aksinya.
                       Satu kabar per satuan kerja, supaya tiap satuan kerja cuma
                       membaca bebannya sendiri. */
                    foreach ($rek->daftarSasaran()->pluck('satker_id')->unique() as $satkerId) {
                        $milik = $rek->tindakan->filter(
                            fn ($tk) => $tk->sasaran->contains('satker_id', $satkerId));
                        $renaksi = $milik->pluck('tgl_renaksi')->filter()->sort()->first();
                        $bentuk = $milik->map(fn ($tk) => $tk->bentuk?->nama)->filter()->join(', ');

                        Kabar::tulis($rek, 'Rekomendasi baru untuk Anda — '
                            .($bentuk ?: 'tindak lanjut')
                            .($renaksi ? ' — rencana aksi '.Tampil::tgl($renaksi) : '')
                            .' — berkasnya di meja Anda',
                            [PeranPengguna::SATKER], 'r-kepala', [$satkerId]);
                    }
                }
            }

            return $lap;
        });

        $this->hapusDraf();

        /* Diantar ke Daftar laporan lalu kartunya ditandai. Mengembalikan
           pengisi ke beranda tanpa keterangan membuat kiriman yang sudah
           berhasil terasa seperti tidak terjadi apa-apa — dan tombolnya ditekan
           lagi. */
        return redirect()->route('laporan.index', ['sorot' => 'lap-'.$laporan->id, 'nada' => 'hijau']);
    }

    /** Tahunnya dari suratnya, nomornya urut sepanjang seluruh laporan. */
    private function kodeTemuan(string $tahun, int $nomor): string
    {
        do {
            $kode = 'TMN-'.$tahun.'-'.str_pad((string) $nomor, 3, '0', STR_PAD_LEFT);
            $nomor++;
        } while (Temuan::where('kode', $kode)->exists());

        return $kode;
    }

    /**
     * Ref IDT yang sudah dipakai laporan lain. Surat yang sama dicatat dua kali
     * memang salah — tapi dua rekomendasi berkode kembar lebih buruk: pencarian,
     * pemberitahuan, dan riwayat menunjuk yang keliru tanpa ada yang sadar. Yang
     * bertabrakan diberi akhiran, dan akhiran itu sendiri yang menandai ada yang
     * perlu diperiksa.
     */
    private function kodeUnik(string $dasar, array &$dipakai): string
    {
        $kode = $dasar;
        $ke = 2;
        while (in_array($kode, $dipakai, true) || Rekomendasi::where('kode', $kode)->exists()) {
            $kode = $dasar.'-'.$ke++;
        }
        $dipakai[] = $kode;

        return $kode;
    }

    /**
     * Bentuk tindak lanjut boleh diketik sendiri: yang tercatat harus sama
     * dengan yang tertulis di suratnya, dan bunyi surat tidak terbatas pada
     * daftar bakunya. Yang belum ada ditambahkan ke data master.
     */
    private function bentukId(string $nama): ?int
    {
        $nama = trim($nama);
        if ($nama === '') {
            return null;
        }
        $ada = Referensi::where('jenis', JenisReferensi::BENTUK_TL->value)
            ->whereRaw('LOWER(nama) = ?', [mb_strtolower($nama)])->first();

        return $ada?->id ?? Referensi::create([
            'jenis'  => JenisReferensi::BENTUK_TL->value,
            'nama'   => $nama,
            'urutan' => (int) Referensi::where('jenis', JenisReferensi::BENTUK_TL->value)->max('urutan') + 1,
            'aktif'  => true,
        ])->id;
    }
}
