<?php

namespace App\Http\Controllers;

use App\Enums\HasilTelaah;
use App\Enums\PeranPengguna;
use App\Enums\PosisiBerkas;
use App\Enums\StatusTindakLanjut;
use App\Models\ItemPermintaan;
use App\Models\KeputusanVerifikasi;
use App\Models\Pengembalian;
use App\Models\PermintaanDokumen;
use App\Models\RiwayatBerkas;
use App\Models\Sasaran;
use App\Models\Surat;
use App\Models\Rekomendasi;
use App\Models\Telaah;
use App\Models\Verifikasi;
use App\Support\Kabar;
use Illuminate\Http\Request;

/**
 * Gerak tingkat 1 — berkas satu satuan kerja.
 *
 * Semuanya bekerja pada Sasaran, bukan Rekomendasi. Itu bukan soal kerapian
 * kode: rekomendasi yang dipikul tiga satuan kerja berada di tiga tahap
 * sekaligus, dan meneruskan "rekomendasinya" akan memindahkan berkas dua
 * satuan kerja yang belum selesai bekerja.
 *
 * Gerak tingkat 2 — siptl, bpk, selesai — ada di SuratController. Ia baru bisa
 * dimulai sesudah seluruh sasaran tuntas dan Inspektorat menerbitkan CHV.
 */
class SasaranController extends Controller
{
    /* ================================================================
       SETBA — meneruskan
       ================================================================ */

    /**
     * Meneruskan berkas ke tahap berikutnya. Hanya memindahkan posisi —
     * status tidak tersentuh sama sekali.
     *
     * Surat pengantarnya ikut dicatat kalau nomornya diisi. Nomor itu yang
     * dipakai menelusuri berkas di luar sistem, jadi kehilangan nomornya
     * berarti kehilangan jejak kertasnya.
     */
    public function teruskan(Request $req, Sasaran $sasaran)
    {
        abort_unless(auth()->user()->peran === PeranPengguna::SETBA, 403);

        $ke = match ($sasaran->posisi) {
            PosisiBerkas::SETBA_TINJAU   => PosisiBerkas::UKI,
            PosisiBerkas::SETBA_TERUSKAN => PosisiBerkas::INSPEKTORAT,
            default => abort(422, 'Berkas tidak sedang di meja Setba.'),
        };

        $data = $req->validate([
            'nomor'   => ['nullable', 'string', 'max:190'],
            'tanggal' => ['nullable', 'date'],
            'perihal' => ['nullable', 'string', 'max:500'],
        ]);

        $nama = $ke === PosisiBerkas::UKI ? 'UKI' : 'Inspektorat';
        $satker = $sasaran->satker?->namaPendek() ?? 'satuan kerja';

        $this->pindah($sasaran, $ke, "Berkas {$satker} diteruskan ke {$nama}");

        if (filled($data['nomor'] ?? null)) {
            Surat::create([
                'rekomendasi_id' => $sasaran->tindakan->rekomendasi_id,
                'sasaran_id'     => $sasaran->id,
                'dari'           => 'Setba',
                'ke'             => $nama,
                'nomor'          => $data['nomor'],
                'tanggal'        => $data['tanggal'] ?? now()->toDateString(),
                'tanggal_catat'  => now()->toDateString(),
                'perihal'        => $data['perihal'] ?? null,
                'dicatat_oleh'   => auth()->id(),
            ]);
        }

        $rek = $sasaran->tindakan->rekomendasi;

        Kabar::tulis($rek, "Berkas {$satker} diteruskan ke {$nama}",
            [$ke === PosisiBerkas::UKI ? PeranPengguna::UKI : PeranPengguna::INSPEKTORAT,
             PeranPengguna::SATKER, PeranPengguna::SETBA], 'r-jejak');

        return redirect()
            ->route('rekomendasi.index', ['tandai' => $rek->id, 'nada' => 'ok'])
            ->with('pesan', "Berkas {$satker} diteruskan ke {$nama}.");
    }

    /* ================================================================
       MEMULANGKAN
       ================================================================ */

    /**
     * Memulangkan berkas ke satuan kerja. Alasan wajib, dan status tetap —
     * ini kerja rapi-rapi, bukan keputusan resmi.
     *
     * Berkasnya sendiri TIDAK dihapus. Yang bisa dilakukan pihak lain hanyalah
     * mengembalikannya ke asal; satuan kerja yang mengganti atau menghapus.
     */
    public function kembalikan(Request $req, Sasaran $sasaran)
    {
        /* Setba TIDAK termasuk. Kata Mas Naufal di rapat, "Kita nggak punya
           hak untuk menolak"; kata Mbak Puspi, "untuk ngecek kebenaran
           dokumennya bukan di kita. Kita cuma ada apa nggak."

           Yang boleh Setba cuma meminta dokumen yang memang belum ada — itu
           juga memundurkan berkasnya, tapi karena kurang, bukan karena dinilai
           salah. Menilai benar-tidaknya isi dokumen wewenang UKI dan
           Inspektorat, dan merekalah yang boleh mengembalikan. */
        abort_unless(in_array(auth()->user()->peran,
            [PeranPengguna::UKI, PeranPengguna::INSPEKTORAT], true), 403,
            'Yang boleh mengembalikan berkas hanya UKI dan Inspektorat.');

        $rek = $sasaran->tindakan->rekomendasi;
        abort_if($rek->terkunciOlehSurat(), 422,
            'Rekomendasi ini sudah diputus lewat surat verifikasi.');

        $data = $req->validate(['alasan' => ['required', 'string', 'min:6']]);
        $satker = $sasaran->satker?->namaPendek() ?? 'satuan kerja';

        $this->pindah($sasaran, PosisiBerkas::SATKER,
            "Berkas {$satker} dikembalikan ke satuan kerja — ".$data['alasan']);

        /* Dicatat tersendiri, bukan hanya sebagai kalimat rekam jejak.
           Alasannya perlu terbaca di tempat orang mencari "kenapa berkas ini
           balik lagi" — bukan setelah menyusuri seluruh riwayat. */
        Pengembalian::create([
            'rekomendasi_id' => $rek->id,
            'sasaran_id'     => $sasaran->id,
            'tanggal'        => now()->toDateString(),
            'oleh_id'        => auth()->id(),
            'label_oleh'     => auth()->user()->peran->pendek(),
            'alasan'         => $data['alasan'],
        ]);

        Kabar::tulis($rek, "Berkas {$satker} dikembalikan untuk diperbaiki — ".$data['alasan'],
            [PeranPengguna::SATKER, PeranPengguna::SETBA], 'r-kembali');

        return redirect()
            ->route('rekomendasi.index', ['tandai' => $rek->id, 'nada' => 'jingga'])
            ->with('pesan', "Berkas {$satker} dikembalikan. Status tidak berubah.");
    }

    /* ================================================================
       TELAAH — UKI dan Inspektorat
       ================================================================ */

    /**
     * UKI menyatakan bukti cukup, atau Inspektorat menyelesaikan verifikasi.
     *
     * Kesimpulannya wajib ditulis: telaah tanpa kesimpulan bukan telaah, dan
     * yang membaca berikutnya perlu tahu apa yang dinilai.
     *
     * Hasilnya (M/BM) yang mengisi sasarans.hasil. Ini sumbu Itjen — status
     * BPK tidak tersentuh sama sekali, dan memang tidak boleh.
     */
    public function telaah(Request $req, Sasaran $sasaran)
    {
        $peran = auth()->user()->peran;
        $rek = $sasaran->tindakan->rekomendasi;

        abort_if($rek->terkunciOlehSurat(), 422,
            'Rekomendasi ini sudah diputus lewat surat verifikasi.');

        $inspektorat = $peran === PeranPengguna::INSPEKTORAT;

        /* Putusan memadai dari Inspektorat SELALU membawa nomor surat. Tanpa
           nomornya tidak ada surat CHV, dan tanpa surat tidak ada dasar hukum
           untuk menyebut rekomendasinya memadai - yang tersisa cuma centang di
           layar. */
        $data = $req->validate([
            'hasil'       => ['required', 'in:M,BM'],
            'catatan'     => ['required', 'string', 'min:6'],
            'nomor_surat' => [$inspektorat && $req->input('hasil') === 'M'
                                ? 'required' : 'nullable', 'string', 'max:190'],
            'tgl_surat'   => [$inspektorat && $req->input('hasil') === 'M'
                                ? 'required' : 'nullable', 'date', 'before_or_equal:today'],
            'periode'     => ['nullable', 'string', 'max:40'],
            'pejabat'     => ['nullable', 'string', 'max:160'],
            'diakui'      => ['nullable', 'boolean'],
        ], [
            'nomor_surat.required' => 'Nomor surat CHV wajib diisi — tanpa suratnya tidak ada dasar hukum untuk menyebutnya memadai.',
            'tgl_surat.required'   => 'Tanggal surat CHV wajib diisi.',
        ]);

        $hasil = HasilTelaah::from($data['hasil']);
        $satker = $sasaran->satker?->namaPendek() ?? 'satuan kerja';

        [$ke, $teks] = match (true) {
            $peran === PeranPengguna::UKI && $sasaran->posisi === PosisiBerkas::UKI =>
                $hasil->memadai()
                    ? [PosisiBerkas::SETBA_TERUSKAN, 'Telaah UKI selesai, bukti dinilai cukup']
                    : [PosisiBerkas::SATKER, 'Telaah UKI: bukti belum cukup'],

            $peran === PeranPengguna::INSPEKTORAT && $sasaran->posisi === PosisiBerkas::INSPEKTORAT =>
                $hasil->memadai()
                    ? [PosisiBerkas::TUNTAS, 'Verifikasi Inspektorat: tindak lanjut memadai']
                    : [PosisiBerkas::SATKER, 'Verifikasi Inspektorat: tindak lanjut belum memadai'],

            default => abort(403, 'Berkas tidak sedang di meja Anda.'),
        };

        Telaah::create([
            'rekomendasi_id' => $rek->id,
            'sasaran_id'     => $sasaran->id,
            'tanggal'        => now()->toDateString(),
            'oleh_id'        => auth()->id(),
            'label_oleh'     => $peran->pendek(),
            'hasil'          => $hasil->value,
            'nomor_surat'    => $data['nomor_surat'] ?? null,
            'tgl_surat'      => $data['tgl_surat'] ?? null,
            'catatan'        => $data['catatan'],
        ]);

        /* Tanda memadai ditulis di barisnya sendiri. Ia penilaian atas SATU
           satuan kerja — bukan putusan atas rekomendasinya, yang hanya bisa
           lahir dari surat CHV. */
        $sasaran->update(['hasil' => $hasil->value]);

        $this->pindah($sasaran, $ke, "{$teks} — {$satker}: ".$data['catatan']);

        /* Surat CHV terbit di sini, bukan di layar tersendiri milik Setba.
           Cakupannya seluruh rekomendasi, jadi bunyinya ditentukan keadaan
           SELURUH barisnya — bukan cuma baris yang barusan ditandai. */
        if ($inspektorat && filled($data['nomor_surat'] ?? null)) {
            $this->terbitkanChv($rek->fresh(), $data);
        }

        /* Kesimpulannya ikut dikabarkan. Kabar yang hanya berbunyi "telaah
           selesai" memaksa pembacanya membuka halaman untuk tahu hasilnya —
           padahal justru hasil itu yang menentukan apakah ia perlu bertindak. */
        Kabar::tulis($rek, "{$teks} — {$satker}: ".$data['catatan'],
            [PeranPengguna::SETBA, PeranPengguna::SATKER], 'r-telaah');

        return redirect()
            ->route('rekomendasi.index', ['tandai' => $rek->id,
                                'nada' => $hasil->memadai() ? 'ok' : 'jingga'])
            ->with('pesan', $teks.'.');
    }

    /* ================================================================
       SURAT CHV
       ================================================================ */

    /**
     * Menerbitkan atau menyusulkan putusan ke sebuah surat CHV.
     *
     * Satu surat bernomor bisa memuat putusan untuk beberapa rekomendasi
     * sekaligus — itu kenyataan kertasnya — jadi suratnya dicari dulu menurut
     * nomornya, baru putusannya ditempelkan.
     *
     * Bunyi putusannya bukan sekadar salinan tanda baris terakhir. Kata Pak
     * Iwan: "Itjen tuh lihat dari satu rekomendasi kan. Jadi kalau di saat 3
     * satker itu belum beres, dia dianggap belum memadai semua." Jadi memadai
     * hanya kalau SELURUH satuan kerjanya sudah ditandai memadai.
     */
    private function terbitkanChv(Rekomendasi $rek, array $data): void
    {
        $semuaMemadai = $rek->daftarSasaran()->isNotEmpty()
            && $rek->daftarSasaran()->every(fn ($x) => $x->hasil?->memadai());

        $hasilSurat = $semuaMemadai ? HasilTelaah::M : HasilTelaah::BM;
        $sumber = $rek->temuan->laporan->sumber;

        $surat = Verifikasi::firstOrCreate(
            ['nomor_surat' => $data['nomor_surat']],
            [
                'jenis'        => Verifikasi::CHV,
                'periode'      => $data['periode'] ?? 'Tahun '.now()->year,
                'tgl_surat'    => $data['tgl_surat'],
                'pejabat'      => $data['pejabat'] ?? 'Inspektorat',
                'dicatat_oleh' => auth()->id(),
            ]
        );

        KeputusanVerifikasi::updateOrCreate(
            ['verifikasi_id' => $surat->id, 'rekomendasi_id' => $rek->id],
            [
                'hasil'                => $hasilSurat->value,
                'catatan'              => $data['catatan'],
                'diakui_masih_terbuka' => (bool) ($data['diakui'] ?? false),
            ]
        );

        /* Memadai membuka gerbang tingkat 2. Belum memadai tidak memindahkan
           apa pun: barisnya sudah pulang sendiri ke satuan kerjanya lewat
           pindah(), dan rekomendasinya memang belum bergerak. */
        if (! $hasilSurat->memadai()) {
            return;
        }

        $ke = $sumber->melewatiSiptl() ? PosisiBerkas::SIPTL : PosisiBerkas::SELESAI;

        $rek->update([
            'posisi' => $ke->value,
            /* Jalur LHA berhenti di sini, jadi statusnya memang ditetapkan
               sekarang. Jalur LHP menunggu BPK — menyetel statusnya di sini
               berarti mengarang penilaian yang belum ada. */
            'status' => $sumber->melewatiSiptl()
                ? $rek->status->value : StatusTindakLanjut::SS->value,
        ]);

        RiwayatBerkas::create([
            'rekomendasi_id' => $rek->id,
            'sasaran_id'     => null,
            'waktu'          => now(),
            'aktor_id'       => auth()->id(),
            'label_aktor'    => 'Inspektorat',
            'aksi'           => "Surat CHV {$data['nomor_surat']} terbit — "
                                .$hasilSurat->nama($sumber).'. '
                                .($ke === PosisiBerkas::SIPTL
                                    ? 'Perlu diunggah Setba ke SIPTL.'
                                    : 'Jalur LHA berhenti di sini.'),
            'posisi_dari'    => null,
            'posisi_ke'      => $ke->value,
        ]);

        Kabar::tulis($rek, "Surat CHV {$data['nomor_surat']} — ".$hasilSurat->nama($sumber),
            [PeranPengguna::SETBA, PeranPengguna::SATKER, PeranPengguna::UKI], 'r-verifikasi');
    }

    /* ================================================================
       MINTA DOKUMEN
       ================================================================ */

    /**
     * Meminta dokumen tambahan dari satu satuan kerja. Tidak memindahkan
     * berkas dan tidak mengubah status — ini permintaan kelengkapan, bukan
     * keputusan.
     */
    public function mintaDokumen(Request $req, Sasaran $sasaran)
    {
        abort_unless(in_array(auth()->user()->peran,
            [PeranPengguna::SETBA, PeranPengguna::UKI, PeranPengguna::INSPEKTORAT], true), 403);

        $data = $req->validate([
            'alasan' => ['required', 'string', 'min:6'],
            'item'   => ['required', 'array', 'min:1'],
            'item.*' => ['nullable', 'string', 'max:190'],
        ]);

        $butir = collect($data['item'])->map(fn ($x) => trim((string) $x))->filter()->values();
        if ($butir->isEmpty()) {
            return back()->withErrors(['item' => 'Sebutkan sedikitnya satu dokumen.'])->withInput();
        }

        $rek = $sasaran->tindakan->rekomendasi;

        $permintaan = PermintaanDokumen::create([
            'rekomendasi_id' => $rek->id,
            'sasaran_id'     => $sasaran->id,
            // Medannya dicast ke enum, jadi yang disimpan nilainya — bukan
            // nama tampilannya. Nama tampilan diambil dari enum saat dibaca.
            'peran_peminta'  => auth()->user()->peran->value,
            'diminta_oleh'   => auth()->id(),
            'tanggal'        => now()->toDateString(),
            'catatan'        => $data['alasan'],
        ]);

        foreach ($butir as $nama) {
            ItemPermintaan::create(['permintaan_dokumen_id' => $permintaan->id, 'nama' => $nama]);
        }

        $satker = $sasaran->satker?->namaPendek() ?? 'satuan kerja';

        RiwayatBerkas::create([
            'rekomendasi_id' => $rek->id,
            'sasaran_id'     => $sasaran->id,
            'waktu'          => now(),
            'aktor_id'       => auth()->id(),
            'label_aktor'    => auth()->user()->peran->pendek(),
            'aksi'           => "Meminta {$butir->count()} dokumen tambahan dari {$satker} — status tidak berubah",
            'posisi_dari'    => $sasaran->posisi?->value,
            'posisi_ke'      => $sasaran->posisi?->value,
        ]);

        Kabar::tulis($rek, "Meminta {$butir->count()} dokumen tambahan dari {$satker} — ".$data['alasan'],
            [PeranPengguna::SATKER, PeranPengguna::SETBA], 'r-dokumen');

        return back()->with('pesan',
            $butir->count().' dokumen diminta dari '.$satker.'. Status dan posisi berkas tidak berubah.');
    }

    /* ================================================================
       PEMBANTU
       ================================================================ */

    private function pindah(Sasaran $s, PosisiBerkas $ke, string $aksi): void
    {
        $dari = $s->posisi;
        $s->update(['posisi' => $ke->value]);

        RiwayatBerkas::create([
            'rekomendasi_id' => $s->tindakan->rekomendasi_id,
            'sasaran_id'     => $s->id,
            'waktu'          => now(),
            'aktor_id'       => auth()->id(),
            'label_aktor'    => auth()->user()->peran->pendek(),
            'aksi'           => $aksi,
            'posisi_dari'    => $dari?->value,
            'posisi_ke'      => $ke->value,
        ]);
    }

}
