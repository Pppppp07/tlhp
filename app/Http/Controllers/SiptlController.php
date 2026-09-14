<?php

namespace App\Http\Controllers;

use App\Enums\PeranPengguna;
use App\Enums\PosisiBerkas;
use App\Enums\StatusTindakLanjut;
use App\Models\Rekomendasi;
use Illuminate\Http\Request;

/**
 * Satu layar untuk seluruh urusan SIPTL.
 *
 * Kata Hizkia: "mengapa tidak dibuat semacam Page atau filter, atau section,
 * atau bagian terpisah terkait update status Laporan berdasarkan hasil Laporan
 * SIPTL, jadi semuanya akan muncul disitu mau itu Input Tanggal Upload laporan
 * ke SIPTL atau Update status Laporan berdasarkan SIPTL."
 *
 * Dua pekerjaan yang selalu dikerjakan berurutan dan hanya oleh Setba:
 *
 *   1. mengunggah berkas ke SIPTL, lalu mencatat tanda terimanya
 *   2. membuka SIPTL beberapa waktu kemudian, membaca statusnya, menyalinnya
 *
 * Sebelum ini keduanya tersebar di halaman rincian tiap rekomendasi — Setba
 * harus membuka satu per satu untuk tahu mana yang perlu diunggah, padahal
 * pekerjaannya justru borongan: satu sesi membuka SIPTL, banyak berkas.
 *
 * Jalur LHA tidak pernah sampai di sini.
 */
class SiptlController extends Controller
{
    public function __invoke(Request $req)
    {
        abort_unless(in_array(auth()->user()->peran,
            [PeranPengguna::SETBA, PeranPengguna::PIMPINAN, PeranPengguna::ADMIN], true), 403,
            'Urusan SIPTL dikerjakan Sekretariat Badan.');

        $muat = ['temuan.laporan', 'sasaran.satker', 'keputusan.verifikasi'];

        /* Tiga keranjang, berurutan seperti pekerjaannya sendiri. Yang sudah
           ditetapkan ikut ditampilkan supaya Setba bisa memeriksa kembali apa
           yang pernah dicatatnya — bukan untuk dikerjakan. */
        $siapUnggah = Rekomendasi::with($muat)
            ->where('posisi', PosisiBerkas::SIPTL->value)->orderBy('kode')->get();

        $menunggu = Rekomendasi::with($muat)
            ->where('posisi', PosisiBerkas::BPK->value)->orderBy('siptl_tanggal')->get();

        $sudah = Rekomendasi::with($muat)
            ->whereNotNull('siptl_status')
            ->orderByDesc('siptl_dicatat_pada')->take(25)->get();

        /* Yang seluruh satuan kerjanya sudah tuntas tapi belum disurati. Belum
           masuk urusan SIPTL — tapi disebut di sini karena itulah yang
           menghalangi berkas berikutnya sampai ke sini. */
        $belumDisurati = Rekomendasi::with($muat)
            ->whereNull('posisi')->get()
            ->filter(fn ($r) => $r->bolehMasukTingkat2()
                && $r->temuan->laporan->sumber->melewatiSiptl())
            ->sortBy('kode')->values();

        return view('siptl', [
            'siapUnggah'    => $siapUnggah,
            'menunggu'      => $menunggu,
            'sudah'         => $sudah,
            'belumDisurati' => $belumDisurati,
            'status'        => StatusTindakLanjut::cases(),
            'sorot'         => $req->query('sorot'),
        ]);
    }
}
