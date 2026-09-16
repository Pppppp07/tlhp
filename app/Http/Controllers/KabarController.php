<?php

namespace App\Http\Controllers;

use App\Models\Notifikasi;
use App\Support\Kabar;

/**
 * Layar Pemberitahuan — padanan `Pemberitahuan`, `bukaKabar`, dan
 * `tandaiTerbaca` prototipe.
 */
class KabarController extends Controller
{
    /** Yang belum dibaca berdiri sendiri di atas; yang sudah dibaca turun ke
        blok tertutup. Kalau bercampur, kabar baru tenggelam begitu daftarnya
        panjang. */
    public function index()
    {
        $u = auth()->user();
        [$sudah, $belum] = Kabar::untuk($u)->partition(fn ($k) => Kabar::sudahDibaca($k, $u));

        return view('kabar', ['belum' => $belum->values(), 'sudah' => $sudah->values()]);
    }

    /**
     * Dari kabar langsung ke bagian yang berubah: membuka rincian rekomendasinya
     * lalu menunjuk bagiannya. Membukanya TIDAK menandai terbaca — sama dengan
     * prototipe, tanda terbaca hanya lewat "Tandai semua terbaca".
     */
    public function buka(Notifikasi $notifikasi)
    {
        abort_unless(Kabar::boleh($notifikasi, auth()->user()), 403);

        return redirect()->route('rekomendasi.show', array_filter([
            'rekomendasi' => $notifikasi->rekomendasi_id,
            'sorot'       => $notifikasi->blok,
        ]));
    }

    public function tandaiSemua()
    {
        $u = auth()->user();

        foreach (Kabar::untuk($u) as $k) {
            if (! Kabar::sudahDibaca($k, $u)) {
                $k->dibaca()->attach($u->id, ['dibaca_pada' => now()]);
            }
        }

        return back();
    }
}
