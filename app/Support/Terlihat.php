<?php

namespace App\Support;

use App\Enums\PeranPengguna;
use App\Models\Laporan;
use App\Models\Rekomendasi;
use App\Models\Sasaran;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Penyaring hak akses, ditaruh di satu tempat.
 *
 * Yang keluar dari kelas ini sudah dipangkas, jadi setiap perhitungan di
 * bawahnya — jumlah rekomendasi, nilai temuan, progres — mewarisi saringannya
 * tanpa perlu diingat satu per satu. Saringan yang harus diingat di tiap
 * tempat pemakaian cepat atau lambat terlupa di salah satunya.
 *
 * SATU-SATUNYA pemeriksa hak akses satuan kerja adalah `sasarans.satker_id`.
 * Jangan pernah menambahkan penyaring kedua di tempat lain: begitu ada dua,
 * keduanya akan berbeda, dan yang longgar yang menang.
 *
 * Yang boleh dilihat satuan kerja pada sebuah laporan ada dua macam:
 *
 *   1. rekomendasi yang punya sasaran untuknya — ini pekerjaannya;
 *   2. temuan yang terjadi di tempatnya walau rekomendasinya jatuh ke pihak
 *      lain — ini haknya untuk tahu, tapi bukan pekerjaannya. Ditandai
 *      `hanya_terperiksa` supaya tampilan bisa menyebutnya apa adanya.
 *
 * Yang tidak masuk keduanya tidak pernah ikut terkirim ke peramban.
 *
 * Sejak satu rekomendasi bisa dipikul beberapa satuan kerja, memangkas
 * rekomendasinya saja tidak cukup: barisnya pun harus dipangkas. Kalau tidak,
 * Balai Medan membuka rekomendasi yang memang miliknya lalu membaca nominal,
 * posisi berkas, dan hasil telaah Politeknik PU di dalamnya.
 */
class Terlihat
{
    public function __construct(private User $pengguna) {}

    public static function untuk(?User $pengguna = null): self
    {
        return new self($pengguna ?? auth()->user());
    }

    /** Peran selain satuan kerja mengawasi seluruh berkas — itu memang tugasnya. */
    public function seluruhnya(): bool
    {
        return $this->pengguna->peran !== PeranPengguna::SATKER;
    }

    private function satkerId(): ?int
    {
        return $this->pengguna->satker_id;
    }

    /* ================================================================
       SASARAN — pintu masuk semua penyaringan
       ================================================================ */

    public function sasaran(): Builder
    {
        $q = Sasaran::query();
        if (! $this->seluruhnya()) {
            $q->where('satker_id', $this->satkerId());
        }
        return $q;
    }

    /** Baris sebuah rekomendasi yang boleh dibaca pengguna ini. */
    public function barisRekomendasi(Rekomendasi $r): Collection
    {
        $baris = $r->daftarSasaran();
        if ($this->seluruhnya()) {
            return $baris;
        }
        return $baris->where('satker_id', $this->satkerId())->values();
    }

    /* ================================================================
       REKOMENDASI
       ================================================================ */

    public function rekomendasi(): Builder
    {
        $q = Rekomendasi::query();
        if (! $this->seluruhnya()) {
            $satker = $this->satkerId();
            $q->whereHas('sasaran', fn ($s) => $s->where('sasarans.satker_id', $satker));
        }
        return $q;
    }

    public function bolehLihatRekomendasi(Rekomendasi $r): bool
    {
        return $this->seluruhnya()
            || $r->daftarSasaran()->contains('satker_id', $this->satkerId());
    }

    /**
     * Memangkas baris sebuah rekomendasi yang sudah dimuat, lalu mengecilkan
     * nilai tagihannya jadi bagian pengguna ini saja.
     *
     * Angka utuhnya menyebut berapa besar beban satuan kerja sebelah — dan itu
     * bukan urusannya, walau rekomendasinya satu.
     */
    public function pangkasRekomendasi(Rekomendasi $r): Rekomendasi
    {
        if ($this->seluruhnya()) {
            return $r;
        }

        $satker = $this->satkerId();
        $baris = $r->daftarSasaran()->where('satker_id', $satker)->values();

        $r->setRelation('sasaran', $baris);
        $r->nilai_pulih = (int) $baris->sum('nilai');

        /* Baris di dalam rekomendasi yang dipikul bersama ikut dipangkas.
           Tanpa ini satuan kerja bisa membaca bukti setor, berkas, surat, dan
           rekam jejak satuan kerja sebelah — dan halaman laporan
           menjumlahkannya jadi angka yang bukan miliknya. */
        $id = $baris->pluck('id')->all();

        foreach (['tanggapan', 'permintaanDokumen', 'pemulihan', 'pengembalian', 'telaah'] as $rel) {
            if ($r->relationLoaded($rel)) {
                $r->setRelation($rel, $r->getRelation($rel)
                    ->filter(fn ($x) => in_array($x->sasaran_id, $id, true))->values());
            }
        }

        /* Riwayat tingkat 2 tidak punya sasaran dan memang boleh dibaca semua
           pihak — ia gerak rekomendasinya sendiri, bukan pekerjaan satu satker. */
        if ($r->relationLoaded('riwayat')) {
            $r->setRelation('riwayat', $r->getRelation('riwayat')
                ->filter(fn ($x) => $x->sasaran_id === null || in_array($x->sasaran_id, $id, true))
                ->values());
        }

        /* Lampiran tanpa sasaran menempel pada rekomendasi seutuhnya. Surat
           pemeriksaan aslinya TIDAK termasuk di sini — ia menempel pada
           laporan, dan halaman laporan yang menahannya. */
        if ($r->relationLoaded('lampiran')) {
            $r->setRelation('lampiran', $r->getRelation('lampiran')
                ->filter(fn ($x) => $x->sasaran_id === null || in_array($x->sasaran_id, $id, true))
                ->values());
        }

        return $r;
    }

    /* ================================================================
       LAPORAN
       ================================================================ */

    /**
     * Laporan yang menyangkut pengguna ini. Bagi satuan kerja: yang punya
     * sasaran untuknya, atau punya temuan yang terjadi di tempatnya.
     */
    public function laporan(): Builder
    {
        $q = Laporan::query();
        if ($this->seluruhnya()) {
            return $q;
        }

        $satker = $this->satkerId();

        return $q->where(function ($x) use ($satker) {
            $x->whereHas('temuan.satkers', fn ($s) => $s->where('satkers.id', $satker))
              ->orWhereHas('temuan.rekomendasi.sasaran',
                  fn ($s) => $s->where('sasarans.satker_id', $satker));
        });
    }

    public function bolehLihatLaporan(Laporan $l): bool
    {
        if ($this->seluruhnya()) {
            return true;
        }
        $satker = $this->satkerId();

        return $l->temuan->contains(fn ($t) => $t->mengenai($satker))
            || $l->temuan->contains(fn ($t) => $t->rekomendasi->contains(
                fn ($r) => $r->daftarSasaran()->contains('satker_id', $satker)));
    }

    /**
     * Memangkas isi sebuah laporan yang sudah dimuat. Temuan yang tidak
     * menyangkut pengguna dibuang seluruhnya; temuan yang menyangkutnya hanya
     * sebagai tempat kejadian dipertahankan tapi rekomendasinya dikosongkan —
     * ia berhak tahu ada temuan di tempatnya, bukan berhak membaca pekerjaan
     * satuan kerja lain.
     */
    public function pangkas(Laporan $l): Laporan
    {
        if ($this->seluruhnya()) {
            return $l;
        }

        $satker = $this->satkerId();

        $temuan = $l->temuan->map(function ($t) use ($satker) {
            $punyaku = $t->rekomendasi
                ->filter(fn ($r) => $r->daftarSasaran()->contains('satker_id', $satker))
                ->map(fn ($r) => $this->pangkasRekomendasi($r))
                ->values();

            if ($punyaku->isEmpty() && ! $t->mengenai($satker)) {
                return null;
            }

            $t->setRelation('rekomendasi', $punyaku);
            $t->hanya_terperiksa = $punyaku->isEmpty();

            return $t;
        })->filter()->values();

        $l->setRelation('temuan', $temuan);

        return $l;
    }

    /** Daftar laporan yang sudah dipangkas isinya, siap ditampilkan. */
    public function daftarLaporan(array $muat = []): Collection
    {
        $bawaan = ['temuan.satkers', 'temuan.rekomendasi.sasaran.satker'];

        return $this->laporan()
            ->with(array_unique(array_merge($bawaan, $muat)))
            ->orderByDesc('tgl_terima')
            ->get()
            ->map(fn ($l) => $this->pangkas($l))
            ->filter(fn ($l) => $l->temuan->isNotEmpty())
            ->values();
    }
}
