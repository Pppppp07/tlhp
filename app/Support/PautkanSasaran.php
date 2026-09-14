<?php

namespace App\Support;

use App\Models\Lampiran;
use App\Models\Pemulihan;
use App\Models\PermintaanDokumen;
use App\Models\Rekomendasi;
use App\Models\Sasaran;
use App\Models\Tanggapan;
use App\Models\Telaah;

/**
 * Menyambungkan catatan tindak lanjut ke BARIS satuan kerja yang memilikinya.
 *
 * Kolom `sasaran_id` ditambahkan waktu penugasan dipecah per satuan kerja,
 * tapi isinya tidak pernah diisi — seluruh catatan tetap menggantung pada
 * rekomendasinya saja. Selama halaman menampilkannya sebagai satu daftar
 * panjang, itu tidak kelihatan. Begitu rinciannya dipindah ke dalam baris tiap
 * satuan kerja, catatan tanpa pemilik jadi tidak punya tempat berdiri.
 *
 * Aturannya sengaja pengecut: menebak salah lebih buruk daripada tidak
 * menebak. Yang tidak bisa dipastikan dibiarkan kosong, dan halaman tetap
 * menampilkannya di bawah tabel sebagai "belum bertanda satuan kerja".
 */
class PautkanSasaran
{
    /** @return array<string,int> berapa baris tersambung per tabel */
    public static function jalankan(): array
    {
        $hasil = [];

        foreach (Rekomendasi::with('tindakan.sasaran.satker')->get() as $r) {
            $sasaran = $r->tindakan->flatMap->sasaran;
            if ($sasaran->isEmpty()) {
                continue;
            }

            $tunggal = $sasaran->count() === 1 ? $sasaran->first() : null;

            /* Laporan satuan kerja menyebut penulisnya. Itu petunjuk paling
               langsung: yang melapor adalah yang mengerjakan. */
            $hasil['tindak_lanjuts'] = ($hasil['tindak_lanjuts'] ?? 0) + self::pautkan(
                Tanggapan::where('rekomendasi_id', $r->id)->whereNull('sasaran_id')->get(),
                fn ($x) => self::cocokNama($sasaran, $x->label_pencatat) ?? $tunggal,
            );

            /* Uang menempel pada baris yang memang dibebani nilai. Kalau cuma
               satu baris yang bernilai, tidak ada yang perlu ditebak. */
            $bernilai = $sasaran->filter(fn ($s) => (int) $s->nilai > 0);
            $hasil['pemulihans'] = ($hasil['pemulihans'] ?? 0) + self::pautkan(
                Pemulihan::where('rekomendasi_id', $r->id)->whereNull('sasaran_id')->get(),
                fn () => $bernilai->count() === 1 ? $bernilai->first() : $tunggal,
            );

            $hasil['permintaan_dokumens'] = ($hasil['permintaan_dokumens'] ?? 0) + self::pautkan(
                PermintaanDokumen::where('rekomendasi_id', $r->id)->whereNull('sasaran_id')->get(),
                fn () => $tunggal,
            );

            /* Telaah menilai berkas SATU satuan kerja. Penulisnya UKI atau
               Inspektorat, jadi namanya tidak menolong — yang menolong cuma
               kalau barisnya memang cuma satu. */
            $hasil['telaahs'] = ($hasil['telaahs'] ?? 0) + self::pautkan(
                Telaah::where('rekomendasi_id', $r->id)->whereNull('sasaran_id')->get(),
                fn () => $tunggal,
            );

            $hasil['lampirans'] = ($hasil['lampirans'] ?? 0) + self::pautkan(
                Lampiran::where('rekomendasi_id', $r->id)->whereNull('sasaran_id')->get(),
                fn () => $tunggal,
            );
        }

        return $hasil;
    }

    /** @param \Illuminate\Support\Collection<int,Sasaran> $sasaran */
    private static function cocokNama($sasaran, ?string $nama): ?Sasaran
    {
        if (! $nama) {
            return null;
        }
        $cocok = $sasaran->filter(fn ($s) => $s->satker?->namaPendek() === $nama
            || $s->satker?->nama === $nama);

        return $cocok->count() === 1 ? $cocok->first() : null;
    }

    private static function pautkan($baris, callable $pilih): int
    {
        $n = 0;
        foreach ($baris as $x) {
            $s = $pilih($x);
            if ($s) {
                $x->sasaran_id = $s->id;
                $x->save();
                $n++;
            }
        }

        return $n;
    }
}
