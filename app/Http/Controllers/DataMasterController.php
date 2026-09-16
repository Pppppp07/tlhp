<?php

namespace App\Http\Controllers;

use App\Enums\JenisReferensi;
use App\Enums\PeranPengguna;
use App\Enums\WarnaLabel;
use App\Models\KategoriTemuan;
use App\Models\Referensi;
use App\Models\Temuan;
use Illuminate\Http\Request;

/**
 * Data master — padanan `LayarMaster` prototipe.
 *
 * Dua daftar yang boleh berubah mengikuti kebiasaan mereka sendiri: kategori
 * internal (BPSDM) dan kategori temuan (per sumber laporan). Bukan lagi daftar
 * mati di dalam kode: Setba boleh menambah, mengganti nama, mengganti warna,
 * dan menonaktifkan. Tahun depan daftarnya bertambah tanpa perlu mengubah kode
 * maupun menjalankan migrasi.
 *
 * Tidak ada yang dihapus, hanya dinonaktifkan. Menghapusnya membuat temuan lama
 * menunjuk ke kategori yang sudah tidak ada.
 *
 * Yang TIDAK bisa diubah dari sini: bentuk tindak lanjut dan alasan sah tidak
 * dapat ditindaklanjuti. Keduanya menyalin SOP dan peraturan; mengubahnya
 * berarti menyimpang dari dasar hukumnya, dan itu bukan keputusan yang pantas
 * diambil lewat satu kotak isian.
 */
class DataMasterController extends Controller
{
    public function index(Request $req)
    {
        $this->pastikanSetba($req);

        return view('data-master', [
            'kategori' => $this->kategoriIntern(),
            /* Berapa temuan memakainya. Yang dipakai tidak boleh hilang
               diam-diam — angkanya yang membuat orang berpikir dua kali sebelum
               menonaktifkan. */
            'terpakai' => Temuan::selectRaw('kategori_intern_id, count(*) n')
                ->whereNotNull('kategori_intern_id')
                ->groupBy('kategori_intern_id')->pluck('n', 'kategori_intern_id'),
            'kategoriTemuan' => KategoriTemuan::orderBy('urutan')->orderBy('id')->get()
                ->groupBy(fn ($k) => $k->sumber->value),
            'terpakaiTemuan' => Temuan::selectRaw('kategori_temuan_id, count(*) n')
                ->whereNotNull('kategori_temuan_id')
                ->groupBy('kategori_temuan_id')->pluck('n', 'kategori_temuan_id'),
            'warna' => WarnaLabel::cases(),
        ]);
    }

    /* ================================================================
       KATEGORI INTERNAL
       ================================================================ */

    public function simpan(Request $req, Referensi $referensi)
    {
        $this->pastikanSetba($req);
        $this->pastikanIntern($referensi);

        $data = $req->validate([
            'nama'  => ['required', 'string', 'max:120'],
            'warna' => ['nullable', 'string'],
        ]);

        $referensi->update([
            'nama'  => trim($data['nama']),
            'warna' => WarnaLabel::dari($data['warna'] ?? null)->value,
        ]);

        return back();
    }

    public function tambah(Request $req)
    {
        $this->pastikanSetba($req);

        $data = $req->validate(['nama' => ['required', 'string', 'max:120']]);

        Referensi::create([
            'jenis'  => JenisReferensi::KATEGORI_INTERN->value,
            'nama'   => trim($data['nama']),
            'warna'  => WarnaLabel::ABU->value,
            'urutan' => (int) Referensi::where('jenis', JenisReferensi::KATEGORI_INTERN->value)->max('urutan') + 1,
            'aktif'  => true,
        ]);

        return back();
    }

    /**
     * Menyalakan atau memadamkan, bukan menghapus. Kategori yang sudah dipakai
     * temuan tidak boleh hilang: keterangannya tetap harus terbaca di berkas
     * lama. Yang berubah cuma munculnya di pilihan saat mencatat laporan baru.
     */
    public function saklar(Request $req, Referensi $referensi)
    {
        $this->pastikanSetba($req);
        $this->pastikanIntern($referensi);

        $referensi->update(['aktif' => ! $referensi->aktif]);

        return back();
    }

    /* ================================================================
       KATEGORI TEMUAN
       ================================================================ */

    public function tambahTemuan(Request $req)
    {
        $this->pastikanSetba($req);

        $data = $req->validate([
            'nama'   => ['required', 'string', 'max:160'],
            'sumber' => ['required', 'in:LHP,LHA'],
        ]);
        $nama = trim($data['nama']);

        /* Nama yang sama pada sumber yang sama cuma membingungkan yang memilih:
           dua baris bertulisan sama, dan tidak ada cara membedakannya. */
        $ada = KategoriTemuan::where('sumber', $data['sumber'])
            ->whereRaw('LOWER(nama) = ?', [mb_strtolower($nama)])->exists();
        if ($ada) {
            return back()->with('gagal', 'Kategori dengan nama itu sudah ada.');
        }

        KategoriTemuan::create([
            'sumber' => $data['sumber'],
            'nama'   => $nama,
            'urutan' => (int) KategoriTemuan::where('sumber', $data['sumber'])->max('urutan') + 1,
            'aktif'  => true,
        ]);

        return back();
    }

    /**
     * Nama yang sudah dipakai temuan tidak diubah dari sini: yang dibaca orang
     * pada berkas lama adalah nama itu, dan menggantinya berarti mengubah
     * keterangan berkas yang sudah selesai.
     */
    public function simpanTemuan(Request $req, KategoriTemuan $kategori)
    {
        $this->pastikanSetba($req);

        $data = $req->validate(['nama' => ['required', 'string', 'max:160']]);

        if (Temuan::where('kategori_temuan_id', $kategori->id)->exists()) {
            return back()->with('gagal', 'Kategori ini sudah dipakai temuan, jadi namanya tidak bisa diganti.');
        }

        $kategori->update(['nama' => trim($data['nama'])]);

        return back();
    }

    public function saklarTemuan(Request $req, KategoriTemuan $kategori)
    {
        $this->pastikanSetba($req);

        $kategori->update(['aktif' => ! $kategori->aktif]);

        return back();
    }

    /* ================================================================
       PENJAGA
       ================================================================ */

    private function kategoriIntern()
    {
        return Referensi::where('jenis', JenisReferensi::KATEGORI_INTERN->value)
            ->orderBy('urutan')->orderBy('id')->get();
    }

    private function pastikanSetba(Request $req): void
    {
        abort_unless(in_array($req->user()->peran,
            [PeranPengguna::SETBA, PeranPengguna::ADMIN], true), 403,
            'Data master hanya bisa diubah Setba.');
    }

    private function pastikanIntern(Referensi $r): void
    {
        abort_unless($r->jenis === JenisReferensi::KATEGORI_INTERN, 403,
            'Daftar ini menyalin SOP dan tidak bisa diubah dari layar ini.');
    }
}
