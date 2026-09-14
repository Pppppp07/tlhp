<?php

namespace App\Http\Controllers;

use App\Enums\JenisReferensi;
use App\Enums\PeranPengguna;
use App\Enums\WarnaLabel;
use App\Models\Referensi;
use App\Models\Temuan;
use Illuminate\Http\Request;

/**
 * Daftar pilihan yang boleh berubah mengikuti peraturan.
 *
 * Bukan lagi daftar mati di dalam kode: Setba boleh menambah, mengganti nama,
 * mengganti warna, dan menonaktifkan. Tahun depan daftarnya bertambah tanpa
 * perlu mengubah kode maupun menjalankan migrasi.
 *
 * Yang TIDAK bisa diubah dari sini: bentuk tindak lanjut dan alasan sah tidak
 * dapat ditindaklanjuti. Keduanya menyalin SOP dan peraturan; mengubahnya
 * berarti menyimpang dari dasar hukumnya, dan itu bukan keputusan yang pantas
 * diambil lewat satu kotak isian.
 */
class DataMasterController extends Controller
{
    /** Yang boleh disunting dari layar ini. */
    private const BISA_DISUNTING = [
        JenisReferensi::KATEGORI_INTERN->value,
    ];

    public function index(Request $req)
    {
        $this->pastikanSetba($req);

        $kategori = Referensi::where('jenis', JenisReferensi::KATEGORI_INTERN->value)
            ->orderBy('urutan')->orderBy('id')->get();

        /* Berapa temuan memakainya. Yang dipakai tidak boleh hilang diam-diam —
           angkanya yang membuat orang berpikir dua kali sebelum menonaktifkan. */
        $terpakai = Temuan::selectRaw('kategori_intern_id, count(*) n')
            ->whereNotNull('kategori_intern_id')
            ->groupBy('kategori_intern_id')->pluck('n', 'kategori_intern_id');

        return view('data-master', [
            'kategori' => $kategori,
            'terpakai' => $terpakai,
            'warna' => WarnaLabel::cases(),
        ]);
    }

    public function simpan(Request $req, Referensi $referensi)
    {
        $this->pastikanSetba($req);
        $this->pastikanBisaDisunting($referensi);

        $data = $req->validate([
            'nama' => ['required', 'string', 'max:120'],
            'warna' => ['nullable', 'string'],
        ]);

        $referensi->update([
            'nama' => trim($data['nama']),
            'warna' => WarnaLabel::dari($data['warna'] ?? null)->value,
        ]);

        return back()->with('pesan', 'Kategori diperbarui.');
    }

    public function tambah(Request $req)
    {
        $this->pastikanSetba($req);

        $data = $req->validate([
            'nama' => ['required', 'string', 'max:120'],
        ]);

        $urutan = (int) Referensi::where('jenis', JenisReferensi::KATEGORI_INTERN->value)
            ->max('urutan');

        Referensi::create([
            'jenis' => JenisReferensi::KATEGORI_INTERN->value,
            'nama' => trim($data['nama']),
            'warna' => WarnaLabel::ABU->value,
            'urutan' => $urutan + 1,
            'aktif' => true,
        ]);

        return back()->with('pesan', 'Kategori baru ditambahkan.');
    }

    /**
     * Menyalakan atau memadamkan, bukan menghapus.
     *
     * Kategori yang sudah dipakai temuan tidak boleh hilang: keterangannya
     * tetap harus terbaca di berkas lama. Yang berubah cuma munculnya di
     * pilihan saat mencatat laporan baru.
     */
    public function saklar(Request $req, Referensi $referensi)
    {
        $this->pastikanSetba($req);
        $this->pastikanBisaDisunting($referensi);

        $referensi->update(['aktif' => ! $referensi->aktif]);

        $dipakai = Temuan::where('kategori_intern_id', $referensi->id)->count();

        return back()->with('pesan', $referensi->aktif
            ? 'Kategori diaktifkan kembali.'
            : ($dipakai > 0
                ? "Kategori dinonaktifkan. {$dipakai} temuan yang memakainya tetap terbaca."
                : 'Kategori dinonaktifkan.'));
    }

    private function pastikanSetba(Request $req): void
    {
        abort_unless(in_array($req->user()->peran,
            [PeranPengguna::SETBA, PeranPengguna::ADMIN], true), 403,
            'Data master hanya bisa diubah Setba.');
    }

    private function pastikanBisaDisunting(Referensi $r): void
    {
        abort_unless(in_array($r->jenis->value, self::BISA_DISUNTING, true), 403,
            'Daftar ini menyalin SOP dan tidak bisa diubah dari layar ini.');
    }
}
