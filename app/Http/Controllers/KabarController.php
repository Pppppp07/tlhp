<?php

namespace App\Http\Controllers;

use App\Models\Notifikasi;
use App\Support\Kabar;

class KabarController extends Controller
{
    public function index()
    {
        $u = auth()->user();
        $daftar = Kabar::untuk($u);
        $sudah = $daftar->filter(fn ($k) => $k->dibaca->contains('id', $u->id));

        return view('kabar', [
            'belum' => $daftar->reject(fn ($k) => $sudah->contains('id', $k->id))->values(),
            'sudah' => $sudah->values(),
        ]);
    }

    /** Membuka satu kabar: ditandai terbaca, lalu diantar ke bagian yang
        berubah — bukan sekadar ke halaman rincian dari atas. */
    public function buka(Notifikasi $notifikasi)
    {
        $u = auth()->user();

        abort_unless(in_array($u->peran->value, $notifikasi->untuk_peran ?? [], true), 403);

        $notifikasi->dibaca()->syncWithoutDetaching([$u->id => ['dibaca_pada' => now()]]);

        $tujuan = route('rekomendasi.show', $notifikasi->rekomendasi_id);
        if ($notifikasi->blok) {
            $tujuan .= '#' . $notifikasi->blok;
        }

        return redirect($tujuan);
    }

    public function tandaiSemua()
    {
        $u = auth()->user();

        foreach (Kabar::untuk($u) as $k) {
            $k->dibaca()->syncWithoutDetaching([$u->id => ['dibaca_pada' => now()]]);
        }

        return back()->with('pesan', 'Semua kabar ditandai sudah dibaca.');
    }
}
