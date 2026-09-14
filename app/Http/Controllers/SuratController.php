<?php

namespace App\Http\Controllers;

use App\Enums\PeranPengguna;
use App\Enums\PosisiBerkas;
use App\Enums\StatusTindakLanjut;
use App\Models\Rekomendasi;
use App\Models\RiwayatBerkas;
use App\Support\Kabar;
use Illuminate\Http\Request;

/**
 * Gerak tingkat 2 — rekomendasinya sendiri.
 *
 * Dua pintu, berurutan, dan keduanya milik Setba:
 *
 *   1. SIPTL       mengunggah ke sistem BPK, lalu mencatat tanda terimanya
 *   2. STATUS BPK  menyalin hasil yang keluar dari SIPTL apa adanya
 *
 * Gerbang sebelum keduanya — surat CHV — BUKAN urusan Setba. Ia terbit dari
 * panel verifikasi Inspektorat (SasaranController::telaah), karena yang
 * memutus memang Inspektorat.
 *
 * Dua sumbu penilaian yang tidak boleh dicampur bertemu di sini:
 *
 *   CHV  memakai M / BM     — sumbu Itjen, penilaian atas kecukupan berkas
 *   BPK  memakai SS/BS/TD   — sumbu BPK, penilaian atas rekomendasinya
 *
 * Jalur LHA berhenti di langkah 1: tidak ada SIPTL, tidak ada BPK.
 */
class SuratController extends Controller
{
    /* ================================================================
       2. SIPTL — jalur LHP saja
       ================================================================ */

    public function siptl(Request $req, Rekomendasi $rekomendasi)
    {
        $this->hanyaSetba();
        abort_unless($rekomendasi->posisi === PosisiBerkas::SIPTL, 422,
            'Berkas tidak sedang menunggu unggahan SIPTL.');

        $data = $req->validate([
            'siptl_tanggal'      => ['required', 'date', 'before_or_equal:today'],
            'siptl_tanda_terima' => ['required', 'string', 'max:120'],
        ]);

        $rekomendasi->update($data + ['posisi' => PosisiBerkas::BPK->value]);

        RiwayatBerkas::create([
            'rekomendasi_id' => $rekomendasi->id,
            'sasaran_id'     => null,
            'waktu'          => now(),
            'aktor_id'       => auth()->id(),
            'label_aktor'    => 'Setba',
            'aksi'           => 'Diunggah ke SIPTL, tanda terima '.$data['siptl_tanda_terima']
                                .' — status tidak berubah',
            'posisi_dari'    => PosisiBerkas::SIPTL->value,
            'posisi_ke'      => PosisiBerkas::BPK->value,
        ]);

        return back()->with('pesan', 'Unggahan SIPTL dicatat. Sekarang menunggu penilaian BPK.');
    }

    /* ================================================================
       3. STATUS BPK — disalin dari SIPTL, bukan disimpulkan
       ================================================================ */

    /**
     * Hasil yang keluar dari SIPTL, disalin Setba apa adanya.
     *
     * Sistem ini tidak pernah menyimpulkan status BPK sendiri. Yang menilai
     * BPK, yang membacanya Setba, dan yang dilakukan sistem hanyalah menyimpan
     * salinannya berikut kapan ia dibaca — dua tanggal yang memang berbeda.
     */
    public function bpk(Request $req, Rekomendasi $rekomendasi)
    {
        $this->hanyaSetba();
        abort_unless($rekomendasi->posisi === PosisiBerkas::BPK, 422, 'Berkas tidak sedang di BPK.');

        $data = $req->validate([
            'hasil'   => ['required', 'in:SS,BS,TD'],
            'tanggal' => ['nullable', 'date', 'before_or_equal:today'],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ]);

        $hasil = StatusTindakLanjut::from($data['hasil']);

        /* SS dan TD menutup berkasnya. BS memulangkannya ke satuan kerja untuk
           ditindaklanjuti ulang — dan kalau begitu, seluruh barisnya ikut
           pulang, karena yang dinilai BPK adalah rekomendasinya. */
        $posisi = $hasil === StatusTindakLanjut::BS ? null : PosisiBerkas::SELESAI;

        $rekomendasi->update([
            'status'             => $hasil->value,
            'posisi'             => $posisi?->value,
            'siptl_status'       => $hasil->value,
            'siptl_catatan'      => $data['catatan'] ?? null,
            'siptl_dicatat_pada' => now()->toDateString(),
        ] + (filled($data['tanggal'] ?? null) ? ['siptl_tanggal' => $data['tanggal']] : []));

        if ($hasil === StatusTindakLanjut::BS) {
            foreach ($rekomendasi->daftarSasaran() as $x) {
                $x->update(['posisi' => PosisiBerkas::SATKER->value, 'hasil' => null]);
            }
        }

        RiwayatBerkas::create([
            'rekomendasi_id' => $rekomendasi->id,
            'sasaran_id'     => null,
            'waktu'          => now(),
            'aktor_id'       => auth()->id(),
            'label_aktor'    => 'Setba',
            'aksi'           => 'Status SIPTL dicatat — '.$hasil->nama()
                                .($data['catatan'] ? '. '.$data['catatan'] : ''),
            'posisi_dari'    => PosisiBerkas::BPK->value,
            'posisi_ke'      => $posisi?->value,
        ]);

        Kabar::tulis($rekomendasi, 'Status SIPTL dicatat — '.$hasil->nama(),
            [PeranPengguna::SETBA, PeranPengguna::SATKER], 'r-verifikasi');

        return back()->with('pesan', 'Status dari SIPTL dicatat.');
    }

    private function hanyaSetba(): void
    {
        abort_unless(auth()->user()->peran === PeranPengguna::SETBA, 403,
            'Hanya Sekretariat Badan yang mencatat surat verifikasi.');
    }
}
