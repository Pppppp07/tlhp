<?php

namespace App\Http\Controllers;

use App\Enums\PeranPengguna;
use App\Enums\PosisiBerkas;
use App\Enums\StatusTindakLanjut;
use App\Support\Terlihat;
use Illuminate\Http\Request;

/**
 * Dua layar riwayat yang di prototipe berdiri sendiri.
 *
 * `saya` — berkas yang menyangkut pengguna ini, dibagi tiga: masih di mejanya,
 * sudah berlanjut ke pihak lain, dan sudah ditetapkan. Satu layar untuk tiga
 * peran, karena isinya konsep yang sama: berkas saya, sudah sampai mana.
 *
 * `selesai` — arsip yang statusnya tidak berubah lagi. Dipisah dari daftar
 * berjalan supaya yang masih perlu dikerjakan tidak tenggelam di antaranya.
 */
class RiwayatController extends Controller
{
    public function saya(Request $req)
    {
        $u = auth()->user();
        $terlihat = Terlihat::untuk($u);

        $semua = $terlihat->rekomendasi()
            ->with(['temuan.laporan', 'sasaran.satker', 'riwayat', 'pemulihan', 'permintaanDokumen.item'])
            ->get()
            ->map(fn ($r) => $terlihat->pangkasRekomendasi($r));

        /* Di meja siapa berkasnya, dibaca dari barisnya. Kolom posisi
           rekomendasi bernilai NULL selama tingkat 1 berjalan, jadi
           membacanya mentah membuat seluruh antrean tampak kosong. */
        $diMejaPeran = fn ($r) => $r->daftarSasaran()
                ->contains(fn ($x) => $x->posisi?->pemegang() === $u->peran)
            || $r->posisi?->pemegang() === $u->peran;

        /* Cakupannya berbeda menurut peran. Satuan kerja melihat seluruh
           rekomendasi yang ditujukan kepadanya. UKI dan Inspektorat melihat
           yang pernah lewat mejanya — ditelusuri dari rekam jejak, ditambah
           yang sedang dipegang, karena berkas yang baru diteruskan belum
           sempat meninggalkan jejak dari peran itu. */
        if (in_array($u->peran, [PeranPengguna::UKI, PeranPengguna::INSPEKTORAT], true)) {
            $label = $u->peran === PeranPengguna::UKI ? 'UKI' : 'Inspektorat';
            $semua = $semua->filter(fn ($r) => $diMejaPeran($r)
                || $r->riwayat->contains(fn ($j) => str_contains($j->label_aktor, $label)));
        }

        $diMeja = $semua->filter($diMejaPeran);
        $tuntas = $semua->filter(fn ($r) => $r->posisiTampil() === PosisiBerkas::SELESAI);
        $lanjut = $semua->reject(fn ($r) => $diMeja->contains($r) || $tuntas->contains($r));

        return view('riwayat/saya', [
            'blok' => [
                ['Masih di meja saya', 'Perlu Anda kerjakan sekarang.', $diMeja->values()],
                ['Sudah berlanjut', 'Sudah dikirim, sedang ditangani pihak lain.', $lanjut->values()],
                ['Sudah ditetapkan', 'Statusnya tidak berubah lagi.', $tuntas->values()],
            ],
            'sorot' => $req->query('sorot'),
            'nada' => $req->query('nada', 'aksen'),
            'tandai' => $this->tandai($semua, $req),
        ]);
    }

    public function selesai(Request $req)
    {
        $terlihat = Terlihat::untuk();
        $tuntas = $terlihat->rekomendasi()
            ->where('posisi', PosisiBerkas::SELESAI->value)
            ->with(['temuan.laporan', 'sasaran.satker', 'pemulihan', 'permintaanDokumen.item'])
            ->get()
            ->map(fn ($r) => $terlihat->pangkasRekomendasi($r));

        return view('riwayat/selesai', [
            'tuntas' => $tuntas,
            'sesuai' => $tuntas->where('status', StatusTindakLanjut::SS)->count(),
            'tidakDapat' => $tuntas->where('status', StatusTindakLanjut::TD)->count(),
            'pulih' => (int) $tuntas->sum(fn ($r) => $r->nilaiTerpulihkan()),
            'sorot' => $req->query('sorot'),
            'nada' => $req->query('nada', 'ok'),
            'tandai' => $this->tandai($tuntas, $req),
        ]);
    }

    /** Berkas mana yang ditandai, dihitung peladen supaya tandanya tetap
        terlihat walau JavaScript mati. */
    private function tandai($daftar, Request $req): array
    {
        return match ($req->query('saring')) {
            'ss' => $daftar->where('status', StatusTindakLanjut::SS)->pluck('id')->all(),
            'td' => $daftar->where('status', StatusTindakLanjut::TD)->pluck('id')->all(),
            'pulih' => $daftar->filter(fn ($r) => $r->nilaiTerpulihkan() > 0)->pluck('id')->all(),
            'telat' => $daftar->filter(fn ($r) => $r->lewatTenggat() > 0)->pluck('id')->all(),
            default => $req->query('tandai') ? [(int) $req->query('tandai')] : [],
        };
    }
}
