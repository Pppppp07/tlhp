<?php

namespace App\Http\Controllers;

use App\Enums\PeranPengguna;
use App\Enums\PosisiBerkas;
use App\Models\Rekomendasi;
use App\Support\RiwayatTindakLanjut;
use App\Support\Terlihat;
use Illuminate\Http\Request;

/**
 * Halaman Rincian rekomendasi — padanan komponen `Rincian`.
 *
 * Satu lajur: laporan dan temuannya, lalu tindak lanjut — kepala rekomendasi,
 * satu tiket per bentuk tindak lanjut berisi tabel satuan kerjanya, urusan
 * SIPTL, riwayat status, dan arsip. Panel kerja duduk di dalam baris satuan
 * kerjanya; siapa boleh mengerjakan apa dihitung di sini dan diperiksa lagi di
 * pengendali aksinya.
 */
class RincianController extends Controller
{
    public function tampil(Request $req, Rekomendasi $rekomendasi)
    {
        $u = auth()->user();
        $terlihat = Terlihat::untuk();

        $rekomendasi->load([
            'temuan.laporan.lampiran', 'temuan.satkers', 'temuan.kategori', 'temuan.kategoriIntern',
            'temuan.rekomendasi.sasaran.satker',
            'tindakan.bentuk',
            'sasaran.satker', 'sasaran.tindakan.bentuk', 'sasaran.riwayatStatus', 'sasaran.draf',
            'tanggapan', 'pemulihan.lampiran', 'tolakanBpk',
            'permintaanDokumen.item.lampiran',
            'pengembalian.sasaran', 'telaah.sasaran', 'telaah.lampiran',
            'keputusan.verifikasi.lampiran', 'keputusan.sasaran',
            'surat.sasaran', 'lampiran', 'riwayat', 'alasanTd', 'sifat',
        ]);

        /* Diperiksa di peladen, bukan disembunyikan di tampilan. */
        abort_unless($terlihat->bolehLihatRekomendasi($rekomendasi), 403,
            'Rekomendasi ini tidak ditujukan ke satuan kerja Anda.');

        $terlihat->pangkasRekomendasi($rekomendasi);

        $tem = $rekomendasi->temuan;
        $saudara = $tem->rekomendasi
            ->reject(fn ($r) => $r->id === $rekomendasi->id)
            ->filter(fn ($r) => $u->peran !== PeranPengguna::SATKER || $r->dituju($u->satker_id))
            ->map(fn ($r) => $terlihat->pangkasRekomendasi($r->setRelation('temuan', $tem)))
            ->values();

        /* Kembali ke tempat asalnya: halaman laporan kalau datang dari sana,
           selain itu layar awal perannya. */
        $kembali = $req->query('dari') === 'laporan'
            ? route('laporan.show', $tem->laporan)
            : ($u->peran === PeranPengguna::PIMPINAN ? route('ringkasan') : route('rekomendasi.index'));

        return view('rekomendasi.show', [
            'r'         => $rekomendasi,
            'tem'       => $tem,
            'lap'       => $tem->laporan,
            'saudara'   => $saudara,
            'kembali'   => $kembali,
            'riwayat'   => RiwayatTindakLanjut::kartu($rekomendasi, $u->peran, $u->satker_id),
            'suratTerakhir' => [
                'uki'         => session('surat_terakhir.'.PosisiBerkas::UKI->value),
                'inspektorat' => session('surat_terakhir.'.PosisiBerkas::INSPEKTORAT->value),
            ],
        ]);
    }

    /**
     * Pekerjaan peran ini pada satu baris — padanan `aksiBaris`. Kosong berarti
     * tidak ada; yang pertama jadi label tombol "Kerjakan".
     *
     * @return array{label:string, ket:string}|null
     */
    public static function aksiBaris(Rekomendasi $r, $x, PeranPengguna $peran, ?int $satkerId): ?array
    {
        $pos = $x->pos();
        $jenis = $r->jenis();
        $bareng = $r->semuaBaris()->filter(fn ($y) => $y->pos() === $pos)->pluck('satker_id')->unique()->count();
        $ikut = $bareng > 1 ? " Berlaku sekaligus untuk {$bareng} satuan kerja yang berkasnya ada di meja ini." : '';

        return match (true) {
            $peran === PeranPengguna::SATKER => $pos === PosisiBerkas::SATKER && $x->satker_id === $satkerId
                ? ['label' => 'Isi tindak lanjut', 'ket' => 'Unggah berkasnya, tulis uraian dan nomor suratnya, lalu kirim ke Setba.'] : null,
            $peran === PeranPengguna::UKI => $pos === PosisiBerkas::UKI
                ? ['label' => 'Telaah berkas', 'ket' => 'Putuskan '.mb_strtolower(\App\Enums\HasilTelaah::M->nama($jenis)).' atau belum, beserta nomor dan tanggal surat hasil validasi.'.$ikut] : null,
            $peran === PeranPengguna::INSPEKTORAT => $pos === PosisiBerkas::INSPEKTORAT
                ? ['label' => 'Catat hasil verifikasi', 'ket' => 'Putusan Inspektorat beserta nomor dan tanggal CHV.'.$ikut] : null,
            $peran !== PeranPengguna::SETBA => null,
            $pos === PosisiBerkas::SETBA_TINJAU => ['label' => 'Teruskan ke UKI',
                'ket' => 'Berkas keluar dari meja Setba. Surat pengantar bernomor wajib diisi, dan menariknya kembali harus lewat UKI.'.$ikut],
            $pos === PosisiBerkas::SETBA_KEMBALI => ['label' => 'Kirim ulang ke satuan kerja',
                'ket' => 'Ditolak '.($x->kembali_dari ?: 'pemeriksa').'. Setba yang mengirimkannya ulang ke satuan kerja untuk pemberkasan ulang, dan boleh menambahkan keterangan.'],
            $pos === PosisiBerkas::SETBA_TERUSKAN => ['label' => 'Teruskan ke Inspektorat',
                'ket' => 'Nomor surat ini yang dirujuk Inspektorat saat menerbitkan CHV.'.$ikut],
            $pos === PosisiBerkas::INSPEKTORAT => ['label' => 'Catat hasil verifikasi Inspektorat',
                'ket' => 'Setba yang mengetik, tapi yang tercatat sebagai penilai tetap Inspektorat -- disalin apa adanya dari surat CHV.'.$ikut],
            default => null,
        };
    }

    /**
     * Keadaan urusan SIPTL dalam satu kalimat — dihitung dari barisnya, bukan
     * dipilih dari satu status. Null: kartunya tidak digambar.
     *
     * @return array{nada:string, kalimat:string}|null
     */
    public static function keadaanSiptl(Rekomendasi $r, PeranPengguna $peran, ?int $satkerId): ?array
    {
        $jenis = $r->jenis();
        if (! $jenis->melewatiSiptl()) {
            return null;
        }
        $baris = $r->barisTerlihat($peran, $satkerId);
        if ($baris->isEmpty()) {
            return null;
        }
        $st = $peran === PeranPengguna::SATKER
            ? Rekomendasi::rangkumBpk($baris->map(fn ($x) => $x->status_bpk))->value
            : $r->statusRek()->value;
        if ($st === 'SS') {
            return ['nada' => 'hijau', 'kalimat' => ($peran === PeranPengguna::SATKER
                ? 'Seluruh tindak lanjut Anda sudah dinyatakan Sudah Sesuai oleh BPK. '
                : 'Seluruh satuan kerja sudah dinyatakan Sudah Sesuai oleh BPK. ').'Tidak ada lagi yang perlu dikerjakan di sini.'];
        }
        if ($st === 'TD') {
            return ['nada' => 'abu', 'kalimat' => 'BPK menyatakan Tidak Dapat Ditindaklanjuti, atas alasan yang diakui peraturan. Tidak ada lagi yang perlu dikerjakan di sini.'];
        }

        $naik = $baris->filter(fn ($x) => $x->perluUnggah($jenis))->count();
        $ditolak = $baris->filter(fn ($x) => $x->status_bpk?->value === 'BS' && $x->siptl_tanggal)->count();
        $ditunggu = $baris->filter(fn ($x) => $x->perluCek($jenis))->count() - $ditolak;
        $dikerjakanUlang = $baris->filter(fn ($x) => ! $x->tuntas()
            && $r->tolakanBpk->contains(fn ($t) => ! $t->sasaran_id || $t->sasaran_id === $x->id))->count();

        $bagian = [];
        if ($naik) {
            $bagian[] = "{$naik} tindak lanjut siap diunggah ke SIPTL";
        }
        if ($ditolak) {
            $bagian[] = "{$ditolak} ditolak BPK, perlu dikirim ulang";
        }
        if ($ditunggu > 0) {
            $bagian[] = "{$ditunggu} sudah diunggah, menunggu penilaian BPK";
        }
        if ($dikerjakanUlang) {
            $bagian[] = "{$dikerjakanUlang} sedang ditindaklanjuti ulang";
        }
        $sesuai = $baris->filter(fn ($x) => $x->siptl_tanggal && in_array($x->status_bpk?->value, ['SS', 'TD'], true))->count();
        if ($sesuai) {
            $bagian[] = "{$sesuai} sudah sesuai menurut BPK";
        }
        if (! $bagian) {
            return null;
        }
        $belumSampai = $baris->reject(fn ($x) => $x->tuntas())->count() - $dikerjakanUlang;
        if ($belumSampai > 0) {
            $bagian[] = "{$belumSampai} belum selesai diperiksa";
        }

        $kalimat = count($bagian) === 1
            ? $bagian[0].'.'
            : implode(', ', array_slice($bagian, 0, -1)).', dan '.end($bagian).'.';

        return ['nada' => $ditolak ? 'jingga' : ($naik ? '' : 'kuning'), 'kalimat' => mb_strtoupper(mb_substr($kalimat, 0, 1)).mb_substr($kalimat, 1)];
    }
}
