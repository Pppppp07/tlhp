<?php

namespace App\Http\Controllers;

use App\Enums\JenisPemulihan;
use App\Enums\PeranPengguna;
use App\Enums\PosisiBerkas;
use App\Models\ItemPermintaan;
use App\Models\Pemulihan;
use App\Models\RiwayatBerkas;
use App\Models\Sasaran;
use App\Models\Tanggapan;
use Illuminate\Http\Request;

/**
 * Tindak lanjut satuan kerja atas BAGIANNYA sendiri.
 *
 * Bekerja pada Sasaran, bukan Rekomendasi. Rekomendasi yang dipikul tiga
 * satuan kerja punya tiga berkas terpisah; jawaban Balai Medan tidak boleh
 * memindahkan berkas Politeknik PU, dan setorannya tidak boleh dikurangkan
 * dari tagihan siapa pun selain dirinya.
 */
class TanggapanController extends Controller
{
    /**
     * Satu jalan masuk untuk dua maksud. Menyimpan tidak memindahkan berkas —
     * satuan kerja boleh melengkapi sedikit-sedikit. Mengirim memindahkannya
     * ke Setba.
     */
    public function simpan(Request $req, Sasaran $sasaran)
    {
        abort_unless(auth()->user()->peran === PeranPengguna::SATKER
            && $sasaran->satker_id === auth()->user()->satker_id
            && $sasaran->posisi === PosisiBerkas::SATKER, 403);

        $rek = $sasaran->tindakan->rekomendasi;

        /* Berkas yang rekomendasinya sudah diputus lewat CHV tidak bisa
           disentuh lagi oleh siapa pun — isian itu sudah jadi dasar surat
           resmi bernomor. */
        abort_if($rek->terkunciOlehSurat(), 422,
            'Rekomendasi ini sudah diputus lewat surat verifikasi.');

        $data = $req->validate([
            'uraian'                        => ['required', 'string', 'min:6'],
            'kirim'                         => ['nullable', 'boolean'],
            /* Bukti per butir yang diminta: judul yang ditulis pengirimnya,
               dan tautan ke arsipnya sendiri. Tidak ada centang terpisah —
               butir terpenuhi begitu keduanya terisi. */
            'bukti'                         => ['array'],
            'bukti.*.judul'                 => ['nullable', 'string', 'max:200'],
            'bukti.*.tautan'                => ['nullable', 'url', 'max:500'],
            'lain.judul'                    => ['nullable', 'string', 'max:200'],
            'lain.tautan'                   => ['nullable', 'url', 'max:500'],
            'pemulihan'                     => ['array'],
            'pemulihan.*.jenis'             => ['required_with:pemulihan.*.nilai', 'in:setor,perbaikan'],
            'pemulihan.*.tanggal'           => ['nullable', 'date'],
            'pemulihan.*.nilai'             => ['nullable'],
            'pemulihan.*.no_ssbp'           => ['nullable', 'string'],
            'pemulihan.*.ntpn'              => ['nullable', 'string'],
            'pemulihan.*.no_nota_kppn'      => ['nullable', 'string'],
            'pemulihan.*.no_berita_acara'   => ['nullable', 'string'],
            'pemulihan.*.tautan'            => ['nullable', 'string', 'max:500'],
        ]);

        /* Tanggalnya tidak ditanyakan. Kata Bang Kamal di rapat, "tanggal
           nggak perlu, ya kan?" — dan memang begitu: orang mengisinya pada hari
           ia mengerjakannya, jadi menanyakannya cuma menambah satu isian yang
           jawabannya selalu hari ini. */
        Tanggapan::create([
            'rekomendasi_id' => $rek->id,
            'sasaran_id'     => $sasaran->id,
            'tanggal'        => now()->toDateString(),
            'uraian'         => $data['uraian'],
            'dicatat_oleh'   => auth()->id(),
            'label_pencatat' => auth()->user()->satker?->namaPendek() ?? auth()->user()->name,
        ]);

        /* Nilai dibersihkan dulu — isian berupa teks dan bisa memuat titik. */
        $jml = 0;
        foreach ($data['pemulihan'] ?? [] as $p) {
            $nilai = (int) preg_replace('/\D/', '', (string) ($p['nilai'] ?? ''));
            if ($nilai <= 0 || empty($p['tanggal'])) {
                continue;
            }
            $jenis = JenisPemulihan::from($p['jenis']);
            $sah = $jenis->perluNtpn()
                ? preg_match('/^[0-9A-Za-z]{16}$/', (string) ($p['ntpn'] ?? ''))
                : filled($p['no_berita_acara'] ?? null);
            if (! $sah) {
                continue;
            }
            Pemulihan::create([
                'rekomendasi_id'  => $rek->id,
                'sasaran_id'      => $sasaran->id,
                'jenis'           => $jenis->value,
                'tanggal'         => $p['tanggal'],
                'nilai'           => $nilai,
                'no_ssbp'         => $p['no_ssbp'] ?? null,
                'ntpn'            => $p['ntpn'] ?? null,
                'no_nota_kppn'    => $p['no_nota_kppn'] ?? null,
                'no_berita_acara' => $p['no_berita_acara'] ?? null,
                'dicatat_oleh'    => auth()->id(),
            ]);
            $jml++;
        }

        /* Bukti berupa tautan, satu per butir yang diminta.

           Butirnya disaring lewat sasarannya sendiri — kalau lewat
           rekomendasi, satu satuan kerja bisa menandai lunas permintaan yang
           ditujukan ke satuan kerja lain.

           Butir ditandai terpenuhi di sini, bukan lewat centang tersendiri:
           centang yang berdiri sendiri bisa menyatakan dokumen terkirim
           padahal tidak ada apa-apa yang dilampirkan. */
        $butir = collect();
        foreach ($data['bukti'] ?? [] as $idButir => $b) {
            $judul = trim((string) ($b['judul'] ?? ''));
            $tautan = trim((string) ($b['tautan'] ?? ''));
            if ($judul === '' || $tautan === '') {
                continue;
            }

            $it = ItemPermintaan::where('id', (int) $idButir)
                ->whereHas('permintaan', fn ($q) => $q->where('sasaran_id', $sasaran->id))
                ->first();
            if (! $it) {
                continue;
            }

            $lampiran = $this->tautan($sasaran, $rek, $judul, $tautan);
            $lampiran->butir()->syncWithoutDetaching([$it->id]);
            $it->update(['terpenuhi' => true, 'dipenuhi_pada' => now()->toDateString()]);
            $butir->push($it->id);
        }

        /* Bukti yang tidak menjawab butir tertentu. Satuan kerja kerap punya
           lampiran pendukung yang memang tidak diminta namanya. */
        $judulLain = trim((string) ($data['lain']['judul'] ?? ''));
        $tautanLain = trim((string) ($data['lain']['tautan'] ?? ''));
        if ($judulLain !== '' && $tautanLain !== '') {
            $this->tautan($sasaran, $rek, $judulLain, $tautanLain);
        }

        $sasaran->refresh()->load('pemulihan', 'permintaanDokumen.item');
        $kirim = (bool) ($data['kirim'] ?? false);

        /* Penjagaan terakhir ada di sini, bukan cuma di tombol. Tombol yang
           dimatikan hanya membantu; yang benar-benar menahan adalah ini.

           Yang ditahan hanya kelengkapan dokumen. Kelunasan adalah TANDA,
           bukan penguncian: pemulihan dana bisa memakan bertahun-tahun, dan
           menahan berkasnya sampai lunas berarti tidak ada yang bisa memeriksa
           kemajuannya selama itu. */
        if ($kirim && ! $sasaran->bolehDikirim()) {
            return back()->with('gagal',
                'Belum bisa dikirim — masih ada dokumen yang diminta dan belum diunggah. Pembaruan tetap tersimpan.');
        }

        $rincian = collect([
            $jml ? "{$jml} baris pemulihan" : null,
            $butir->count() ? $butir->count().' dokumen bertautan' : null,
        ])->filter()->join(', ');

        if ($kirim) {
            $dari = $sasaran->posisi;
            $sasaran->update(['posisi' => PosisiBerkas::SETBA_TINJAU->value]);

            $sisa = $sasaran->sisaPemulihan();
            $catatan = 'Berkas dikirim ke Setba'
                .($rincian ? " — {$rincian}" : '')
                .($sisa > 0 ? ' — sisa pemulihan '.\App\Support\Tampil::rupiahSingkat($sisa) : '');

            $this->catat($sasaran, $catatan, $dari, PosisiBerkas::SETBA_TINJAU);

            return redirect()->route('rekomendasi.index', ['tandai' => $rek->id, 'nada' => 'ok'])
                ->with('pesan', 'Berkas dikirim ke Setba.');
        }

        $this->catat($sasaran, 'Menyimpan kemajuan'
            .($rincian ? " — {$rincian}" : '').', berkas tetap di satuan kerja', null, null);

        return back()->with('pesan', 'Pembaruan tersimpan. Berkas tetap di satuan kerja.');
    }

    /**
     * Bukti berupa tautan ke arsip satuan kerja, bukan salinan di sini.
     *
     * Judulnya ditulis pengirimnya, bukan dikarang dari nama butir
     * permintaannya: yang membacanya perlu tahu isi tautannya apa tanpa harus
     * membukanya satu per satu.
     */
    private function tautan(Sasaran $sasaran, $rek, string $judul, string $tautan): \App\Models\Lampiran
    {
        return \App\Models\Lampiran::create([
            'rekomendasi_id' => $rek->id,
            'sasaran_id'     => $sasaran->id,
            'nama_asli'      => $judul,
            'tautan'         => $tautan,
            'diunggah_oleh'  => auth()->id(),
            'diunggah_pada'  => now(),
        ]);
    }

    private function catat(Sasaran $s, string $aksi, ?PosisiBerkas $dari, ?PosisiBerkas $ke): void
    {
        RiwayatBerkas::create([
            'rekomendasi_id' => $s->tindakan->rekomendasi_id,
            'sasaran_id'     => $s->id,
            'waktu'          => now(),
            'aktor_id'       => auth()->id(),
            'label_aktor'    => auth()->user()->satker?->namaPendek() ?? auth()->user()->name,
            'aksi'           => $aksi,
            'posisi_dari'    => $dari?->value,
            'posisi_ke'      => $ke?->value,
        ]);
    }
}
