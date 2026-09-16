<?php

namespace App\Http\Controllers;

use App\Support\Lingkup;
use App\Support\Terlihat;
use App\Support\Tampil;
use Illuminate\Http\Request;

/**
 * Pencarian di batang atas — padanan `CariGlobal`.
 *
 * Yang dicari diambil dari daftar yang hak aksesnya sudah disaring, jadi
 * satuan kerja tetap tidak bisa menemukan berkas satuan kerja lain lewat
 * pintu ini. Rekomendasi lebih dulu (paling banyak enam), laporan menyusul
 * (paling banyak tiga).
 */
class CariController extends Controller
{
    public function __invoke(Request $req)
    {
        $kata = mb_strtolower(trim((string) $req->query('q', '')));
        if (mb_strlen($kata) < 2) {
            return response()->json(['rek' => [], 'lap' => [], 'jumlah' => 0]);
        }

        $terlihat = Terlihat::untuk();
        $lingkup = Lingkup::dari();
        $cocok = fn (?string $t) => str_contains(mb_strtolower((string) $t), $kata);

        $rek = $terlihat->rekomendasi()
            ->with(['temuan.laporan', 'sasaran.satker'])
            ->get()
            ->map(fn ($r) => $terlihat->pangkasRekomendasi($r))
            ->filter(fn ($r) => Lingkup::berlaku($lingkup, $r->jenis()))
            ->filter(fn ($r) => $cocok($r->kode) || $cocok($r->uraian)
                || $cocok($r->daftarSasaran()->first()?->satker?->nama)
                || $cocok($r->temuan->kode) || $cocok($r->temuan->judul) || $cocok($r->temuan->nomor_pada_surat))
            ->values();

        $lap = $terlihat->daftarLaporan()
            ->filter(fn ($l) => Lingkup::berlaku($lingkup, $l->sumber))
            ->filter(fn ($l) => $cocok($l->nomor) || $cocok($l->sumber->nama())
                || $cocok($l->satkerDiperiksa()->map->nama->join(' '))
                || $l->temuan->contains(fn ($t) => $cocok($t->judul) || $cocok($t->kode)))
            ->values();

        return response()->json([
            'rek' => $rek->take(6)->map(fn ($r) => [
                'tautan'      => route('rekomendasi.show', $r),
                'kode'        => $r->kode,
                'cap'         => view('components.cap', ['s' => $r->status, 'jenis' => $r->jenis(), 'rek' => $r])->render(),
                'uraian'      => $r->uraian,
                'satker'      => $r->daftarSasaran()->first()?->satker?->namaPendek() ?? '—',
                'nomorTemuan' => $r->temuan->nomor_pada_surat,
            ])->values(),
            'lap' => $lap->take(3)->map(fn ($l) => [
                'tautan'   => route('laporan.show', $l),
                'nomor'    => $l->nomor,
                'sumber'   => view('components.sumber', ['j' => $l->sumber])->render(),
                'satker'   => view('components.daftar-satker', ['lap' => $l])->render(),
                'temuan'   => $l->temuan->count(),
                'diterima' => Tampil::tgl($l->tgl_terima),
            ])->values(),
            'jumlah' => $rek->count() + $lap->count(),
        ]);
    }
}
