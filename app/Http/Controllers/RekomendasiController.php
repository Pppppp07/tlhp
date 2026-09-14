<?php

namespace App\Http\Controllers;

use App\Enums\PeranPengguna;
use App\Enums\PosisiBerkas;
use App\Enums\StatusTindakLanjut;
use App\Enums\SumberLaporan;
use App\Models\Rekomendasi;
use App\Models\Satker;
use App\Support\Terlihat;
use Illuminate\Http\Request;

/**
 * Sisi baca rekomendasi.
 *
 * Tindakannya sendiri ada di dua tempat lain, dan pembagian itu mengikuti dua
 * tingkat gerak yang memang berbeda:
 *
 *   SasaranController  gerak tingkat 1 — berkas tiap satuan kerja
 *   SuratController    gerak tingkat 2 — rekomendasinya sendiri
 *
 * Kepala tabelnya mengikuti prototipe: dua deret keping (jenis laporan dan
 * keranjang) lalu baris penyaring. Angkanya dihitung dari daftar penuh, bukan
 * dari hasil saringan — kalau ikut tersaring, angkanya berubah tiap kali orang
 * mengetik dan tidak ada lagi yang bisa dijadikan pegangan.
 */
class RekomendasiController extends Controller
{
    public function index(Request $req)
    {
        $peran = auth()->user()->peran;
        $satkerAktif = auth()->user()->satker_id;
        $terlihat = Terlihat::untuk();

        /* Lewat penyaring hak akses, bukan Rekomendasi::query() lalu disaring
           di sini. Yang tidak boleh dilihat juga tidak boleh ketemu lewat
           pencarian. */
        $semua = $terlihat->rekomendasi()
            ->with([
                'temuan.laporan', 'temuan.satkers',
                'sasaran.satker', 'tindakan.bentuk',
                'pemulihan', 'permintaanDokumen.item',
            ])
            ->get()
            ->map(fn ($r) => $terlihat->pangkasRekomendasi($r));

        /* ---------- keranjang ---------- */

        $diMejaku = function ($r) use ($peran, $satkerAktif) {
            $baris = $r->daftarSasaran();
            if ($peran === PeranPengguna::SATKER) {
                $baris = $baris->where('satker_id', $satkerAktif);
            }
            return $baris->contains(fn ($x) => $x->posisi?->pemegang() === $peran)
                || $r->posisi?->pemegang() === $peran
                || ($peran === PeranPengguna::SETBA && $r->bolehMasukTingkat2());
        };

        $selesai = fn ($r) => $r->posisiTampil() === PosisiBerkas::SELESAI;

        $KERANJANG = [
            'kerjakan' => ['nama' => 'Perlu saya kerjakan', 'uji' => $diMejaku],
            'menunggu' => ['nama' => 'Sedang menunggu',
                           'uji' => fn ($r) => ! $diMejaku($r) && ! $selesai($r)],
            'selesai'  => ['nama' => 'Sudah selesai', 'uji' => $selesai],
            /* Bukan keadaan berkas, melainkan urusan tersendiri — karena itu ia
               berdiri di seberang pemisah, bukan jadi keranjang keempat. */
            'siptl'    => ['nama' => 'Urusan SIPTL', 'pemisah' => true,
                           'uji' => fn ($r) => in_array($r->posisi,
                               [PosisiBerkas::SIPTL, PosisiBerkas::BPK], true)],
        ];

        $jenis = $req->query('jenis', '');
        $keadaan = $req->query('keadaan', '');
        $cari = trim((string) $req->query('cari'));
        $status = $req->query('status', '');
        $satker = $req->query('satker', '');

        /* Angka keping jenis laporan dihitung sebelum saringan jenis dipakai;
           angka keranjang dihitung sesudahnya. Begitulah prototipe: keranjang
           menghitung apa yang sedang dilihat, jenis menghitung seluruhnya. */
        $jumlahJenis = [
            '' => $semua->count(),
            SumberLaporan::LHP->value => $semua->filter(fn ($r) => $r->temuan->laporan->sumber === SumberLaporan::LHP)->count(),
            SumberLaporan::LHA->value => $semua->filter(fn ($r) => $r->temuan->laporan->sumber === SumberLaporan::LHA)->count(),
        ];

        $lingkup = $jenis === ''
            ? $semua
            : $semua->filter(fn ($r) => $r->temuan->laporan->sumber->value === $jenis);

        $jumlahKeranjang = collect($KERANJANG)
            ->map(fn ($k) => $lingkup->filter($k['uji'])->count())->all();

        /* ---------- saringan ---------- */

        $hasil = $lingkup
            ->when($keadaan !== '' && isset($KERANJANG[$keadaan]),
                fn ($c) => $c->filter($KERANJANG[$keadaan]['uji']))
            ->when($status !== '', fn ($c) => $c->filter(fn ($r) => $r->status->value === $status))
            ->when($satker !== '', fn ($c) => $c->filter(
                fn ($r) => $r->daftarSasaran()->contains('satker_id', (int) $satker)))
            ->when($cari !== '', fn ($c) => $c->filter(function ($r) use ($cari) {
                $teks = implode(' ', [
                    $r->kode, $r->ref_lhp, $r->uraian,
                    $r->temuan->kode, $r->temuan->judul, $r->temuan->laporan->nomor,
                    $r->daftarSasaran()->map(fn ($x) => $x->satker?->nama)->join(' '),
                ]);
                return str_contains(mb_strtolower($teks), mb_strtolower($cari));
            }))
            ->values();

        /* Keping SIPTL hanya muncul kalau jalurnya memang lewat SIPTL — pada
           lingkup LHA ia keranjang yang tidak akan pernah terisi. */
        if ($jenis === SumberLaporan::LHA->value) {
            unset($KERANJANG['siptl']);
        }

        return view('rekomendasi/index', [
            'daftar'          => $hasil,
            'semuaJumlah'     => $lingkup->count(),
            'perlu'           => $hasil->filter(fn ($r) => $r->lewatTenggat() > 0)->count(),
            'KERANJANG'       => $KERANJANG,
            'jumlahJenis'     => $jumlahJenis,
            'jumlahKeranjang' => $jumlahKeranjang,
            'jenis'           => $jenis,
            'keadaan'         => $keadaan,
            'cari'            => $cari,
            'status'          => $status,
            'satker'          => $satker,
            'daftarSatker'    => $peran === PeranPengguna::SATKER
                ? collect()
                : Satker::orderBy('nama')->get(),
            'daftarStatus'    => StatusTindakLanjut::cases(),
            /* Berkas mana yang baru saja disentuh. Dihitung peladen, bukan
               peramban: barisnya sudah bertanda saat HTML-nya sampai, jadi
               penandanya tetap terlihat walau JavaScript mati. */
            'tandai'          => (int) $req->query('tandai', 0),
            'nada'            => $req->query('nada', 'aksen'),
        ]);
    }

    public function show(Rekomendasi $rekomendasi)
    {
        $terlihat = Terlihat::untuk();

        /* Pemeriksaan dilakukan di sisi peladen, bukan disembunyikan di
           tampilan. Satuan kerja hanya boleh membuka rekomendasi yang punya
           baris untuknya. */
        abort_unless($terlihat->bolehLihatRekomendasi($rekomendasi), 403,
            'Rekomendasi ini tidak ditujukan ke satuan kerja Anda.');

        $rekomendasi->load([
            'temuan.laporan', 'temuan.rekomendasi', 'temuan.kategori', 'temuan.satkers',
            'tindakan.bentuk', 'tindakan.sasaran.satker',
            'sasaran.satker',
            'sifat', 'alasanTd',
            'tanggapan.sasaran.satker',
            'permintaanDokumen.item', 'permintaanDokumen.sasaran.satker',
            'pemulihan.lampiran', 'pemulihan.sasaran.satker',
            'keputusan.verifikasi',
            'lampiran.jenisDokumen',
            'riwayat.sasaran.satker',
            'permintaanUbah',
            'pengembalian.sasaran.satker',
            'telaah.sasaran.satker',
            'surat',
        ]);

        /* Baris satuan kerja lain dibuang di sini, bukan di Blade. Menyaring di
           tampilan berarti datanya sudah sampai di peramban dan tinggal dibaca
           lewat "lihat sumber halaman". */
        $terlihat->pangkasRekomendasi($rekomendasi);

        return view('rekomendasi/show', [
            'r'     => $rekomendasi,
            'baris' => $terlihat->barisRekomendasi($rekomendasi),
        ]);
    }
}
