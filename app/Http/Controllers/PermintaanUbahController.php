<?php

namespace App\Http\Controllers;

use App\Enums\JenisPermintaanUbah;
use App\Enums\PeranPengguna;
use App\Enums\PosisiBerkas;
use App\Enums\SebabTarikBerkas;
use App\Enums\StatusPermintaanUbah;
use App\Models\PermintaanUbah;
use App\Models\RiwayatBerkas;
use App\Models\Sasaran;
use Illuminate\Http\Request;

/**
 * Permintaan perubahan atas berkas yang sudah dikirim.
 *
 * Inilah satu-satunya jalan mengubah kiriman yang sudah lepas dari meja satuan
 * kerja. Aturannya dari pemilik sistem, dan sengaja tidak dilonggarkan:
 *
 *   - Satuan kerja yang sudah mengirim TIDAK bisa menarik berkasnya sendiri.
 *     Ia hanya bisa meminta, dan yang memegang berkasnya yang memutuskan.
 *   - Unit lain — Setba, UKI, Inspektorat — juga tidak bisa menghapus berkas
 *     satuan kerja. Yang bisa mereka lakukan mengembalikannya ke asalnya, lalu
 *     satuan kerjanya sendiri yang mengganti.
 *   - Begitu surat verifikasi terbit, seluruhnya terkunci. Tidak ada permintaan
 *     yang bisa diajukan maupun disetujui.
 *
 * Satu-satunya jalur yang menembus semuanya adalah penarikan berkas pribadi,
 * dan itu tidak lewat sini — lihat SebabTarikBerkas::tanpaPersetujuan().
 */
class PermintaanUbahController extends Controller
{
    public function ajukan(Request $req, Sasaran $sasaran)
    {
        $u = $req->user();
        $rek = $sasaran->rekomendasi();
        abort_unless($rek, 404);

        /* Yang meminta harus pemilik barisnya. Peran lain tidak "mengajukan
           permintaan" — mereka mengembalikan berkasnya, dan itu tombol lain. */
        abort_unless($u->peran === PeranPengguna::SATKER
            && $sasaran->satker_id === $u->satker_id, 403,
            'Permintaan perubahan hanya bisa diajukan satuan kerja pemilik berkasnya.');

        /* Selama berkasnya masih di mejanya sendiri, tidak ada yang perlu
           diminta — ia tinggal mengubahnya langsung. */
        abort_if($sasaran->posisi === PosisiBerkas::SATKER, 422,
            'Berkas ini masih di meja Anda; ubah langsung, tidak perlu meminta.');

        abort_if($rek->terkunciOlehSurat(), 422,
            'Surat verifikasi sudah terbit. Berkasnya terkunci dan tidak bisa diubah lagi.');

        abort_if($rek->adaPermintaanMenunggu(), 422,
            'Masih ada permintaan yang belum diputus. Tunggu keputusannya dulu.');

        $data = $req->validate([
            'jenis' => ['required', 'string'],
            'alasan' => ['required', 'string', 'min:6'],
            'lampiran_sasaran_id' => ['nullable', 'integer'],
        ]);

        $jenis = JenisPermintaanUbah::from($data['jenis']);

        /* Membatalkan berkas harus menyebut berkas yang mana. Tanpa itu, yang
           memutus menyetujui sesuatu yang tidak disebut namanya. */
        $lampiran = null;
        if ($jenis === JenisPermintaanUbah::BATAL_DOKUMEN) {
            $lampiran = $sasaran->lampiran()
                ->whereNull('ditarik_pada')
                ->find($data['lampiran_sasaran_id'] ?? 0);

            abort_unless($lampiran, 422,
                'Sebutkan berkas mana yang diminta dibatalkan.');
        }

        PermintaanUbah::create([
            'rekomendasi_id' => $rek->id,
            'sasaran_id' => $sasaran->id,
            'jenis' => $jenis->value,
            'alasan' => $data['alasan'],
            'lampiran_sasaran_id' => $lampiran?->id,
            'diajukan_oleh' => $u->id,
            'label_pengaju' => $u->satker?->namaPendek() ?? $u->name,
            'tanggal' => now()->toDateString(),
            'status' => StatusPermintaanUbah::MENUNGGU->value,
        ]);

        RiwayatBerkas::create([
            'rekomendasi_id' => $rek->id,
            'sasaran_id' => $sasaran->id,
            'waktu' => now(),
            'aktor_id' => $u->id,
            'label_aktor' => $u->satker?->namaPendek() ?? $u->name,
            'aksi' => 'Mengajukan permintaan perubahan: ' . $jenis->nama(),
        ]);

        return back()->with('pesan',
            'Permintaan dikirim. Berkasnya belum berubah — ia menunggu keputusan '
            . $rek->pemutusPerubahan()->pendek() . '.');
    }

    public function putus(Request $req, PermintaanUbah $permintaan)
    {
        $u = $req->user();
        $rek = $permintaan->rekomendasi;

        abort_unless($permintaan->status === StatusPermintaanUbah::MENUNGGU, 422,
            'Permintaan ini sudah diputus.');

        /* Yang memutus adalah yang sedang memegang berkasnya. Kalau berkasnya
           sedang tidak di tangan siapa pun — menunggu surat, menunggu BPK —
           Setba yang memutuskan. */
        abort_unless($u->peran === $rek->pemutusPerubahan(), 403,
            'Yang memutuskan adalah unit yang sedang memegang berkasnya.');

        abort_if($rek->terkunciOlehSurat(), 422,
            'Surat verifikasi sudah terbit. Permintaan ini tidak bisa disetujui lagi.');

        $data = $req->validate([
            'putusan' => ['required', 'in:setuju,tolak'],
            'catatan' => ['nullable', 'string'],
        ]);

        $setuju = $data['putusan'] === 'setuju';

        $permintaan->update([
            'status' => ($setuju ? StatusPermintaanUbah::DISETUJUI
                : StatusPermintaanUbah::DITOLAK)->value,
            'diputus_oleh' => $u->id,
            'label_pemutus' => $u->peran->pendek(),
            'tgl_putus' => now()->toDateString(),
            'catatan_putus' => $data['catatan'] ?? null,
        ]);

        if ($setuju) {
            $this->jalankan($permintaan, $u);
        }

        RiwayatBerkas::create([
            'rekomendasi_id' => $rek->id,
            'sasaran_id' => $permintaan->sasaran_id,
            'waktu' => now(),
            'aktor_id' => $u->id,
            'label_aktor' => $u->peran->pendek(),
            'aksi' => ($setuju ? 'Menyetujui' : 'Menolak')
                . ' permintaan perubahan: ' . $permintaan->jenis->nama(),
        ]);

        return back()->with('pesan', $setuju
            ? 'Permintaan disetujui.'
            : 'Permintaan ditolak. Berkasnya tidak berubah.');
    }

    /**
     * Akibat dari persetujuan.
     *
     * Koreksi dan penarikan sama-sama mengembalikan berkasnya ke satuan kerja —
     * bukan mengubah isinya. Yang mengubah tetap satuan kerjanya sendiri, dan
     * itu memang inti aturannya: unit lain tidak pernah menyunting berkas orang.
     */
    private function jalankan(PermintaanUbah $permintaan, $oleh): void
    {
        $sasaran = $permintaan->sasaran;

        if ($permintaan->jenis === JenisPermintaanUbah::BATAL_DOKUMEN) {
            /* Satu-satunya penarikan berkas yang lewat persetujuan. Isinya
               dihapus, catatan bahwa berkas pernah ada tetap — justru itu yang
               membuat penarikannya bisa dipertanggungjawabkan. */
            $permintaan->lampiranSasaran?->tarik(
                SebabTarikBerkas::BATAL, $oleh, $permintaan->alasan);

            return;
        }

        $sasaran?->update(['posisi' => PosisiBerkas::SATKER->value]);
    }
}
