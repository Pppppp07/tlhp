<?php

namespace App\Http\Controllers;

use App\Models\Laporan;
use App\Support\Terlihat;

class LaporanController extends Controller
{
    public function index(\Illuminate\Http\Request $req)
    {
        /* Disaring di hulu, bukan di tampilan. Yang keluar dari sini sudah
           dipangkas, jadi seluruh angka turunannya ikut tersaring sendiri. */
        $semua = Terlihat::untuk()->daftarLaporan();

        $KEADAAN = [
            'jalan'  => fn ($l) => ! $l->tuntas(),
            'tuntas' => fn ($l) => $l->tuntas(),
        ];

        /* Angka keping dihitung dari daftar penuh, bukan dari hasil pencarian.
           Kalau ikut tersaring, angkanya berubah tiap kali orang mengetik dan
           tidak ada lagi yang bisa dijadikan pegangan. */
        $jumlah = [
            'jalan'  => $semua->filter($KEADAAN['jalan'])->count(),
            'tuntas' => $semua->filter($KEADAAN['tuntas'])->count(),
            'semua'  => $semua->count(),
        ];

        $keadaan = $req->query('keadaan', 'jalan');
        $cari = trim((string) $req->query('cari'));

        $hasil = $semua
            ->when(isset($KEADAAN[$keadaan]), fn ($c) => $c->filter($KEADAAN[$keadaan]))
            ->when($cari !== '', fn ($c) => $c->filter(function ($l) use ($cari) {
                $teks = implode(' ', [
                    $l->nomor, $l->sumber->nama(),
                    $l->satkerDiperiksa()->map->nama->join(' '),
                    $l->temuan->map->judul->join(' '),
                ]);
                return str_contains(mb_strtolower($teks), mb_strtolower($cari));
            }))
            ->values();

        return view('laporan/index', [
            'daftar'  => $hasil,
            'jumlah'  => $jumlah,
            'keadaan' => $keadaan,
            'cari'    => $cari,
            'perlu'   => $hasil->sum(fn ($l) => $l->angka()['telat']),
        ]);
    }

    public function show(Laporan $laporan)
    {
        $laporan->load([
            'temuan.rekomendasi.sasaran.satker', 'temuan.rekomendasi.tindakan.bentuk',
            'temuan.rekomendasi.pemulihan',
            'temuan.rekomendasi.permintaanDokumen.item', 'temuan.kategori',
            'temuan.kategoriIntern', 'temuan.satkers', 'lampiran',
        ]);

        $terlihat = Terlihat::untuk();

        /* Laporan yang sama sekali tidak menyangkut pengguna ini tidak dibuka —
           bukan dibuka lalu dikosongkan. Halaman kosong tetap membocorkan bahwa
           laporannya ada, nomornya berapa, dan kapan diterimanya. */
        abort_unless($terlihat->bolehLihatLaporan($laporan), 403,
            'Laporan ini tidak menyangkut satuan kerja Anda.');

        return view('laporan/show', [
            'l' => $terlihat->pangkas($laporan),
            'dipangkas' => ! $terlihat->seluruhnya(),
        ]);
    }
}
