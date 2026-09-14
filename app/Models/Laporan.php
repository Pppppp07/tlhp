<?php

namespace App\Models;

use App\Enums\PosisiBerkas;
use App\Enums\StatusTindakLanjut;
use App\Enums\SumberLaporan;
use Illuminate\Support\Collection;

use Illuminate\Database\Eloquent\Model;

class Laporan extends Model
{
    protected $fillable = [
        /* `jenis_periksa` (LK / PDTT) adalah sumbu TERSENDIRI, bukan pengganti
           LHP/LHA. Dari 312 baris lembar pemantauan: 297 Laporan Keuangan,
           15 Pemeriksaan Dengan Tujuan Tertentu — keduanya sama-sama LHP. */
        'sumber', 'jenis_periksa', 'nomor', 'tgl_surat', 'tgl_terima',
        'dicatat_pada', 'dicatat_oleh',
    ];
    protected $casts = [
        'sumber' => SumberLaporan::class,
        'tgl_surat' => 'date',
        'tgl_terima' => 'date',
        'dicatat_pada' => 'date',
    ];

    public function temuan()      { return $this->hasMany(Temuan::class); }
    public function lampiran()    { return $this->hasMany(Lampiran::class); }
    public function dicatatOleh() { return $this->belongsTo(User::class, 'dicatat_oleh'); }

    /** Seluruh rekomendasi di bawah laporan ini, lewat temuannya. */
    public function rekomendasi()
    {
        return $this->hasManyThrough(Rekomendasi::class, Temuan::class);
    }

    /* ================================================================
       SATUAN KERJA YANG DIPERIKSA
       ================================================================ */

    /*
     * Diturunkan dari temuannya, tidak disimpan di kepala laporan. Satu laporan
     * lazim memeriksa beberapa satuan kerja sekaligus, dan kepala laporan yang
     * punya isian sendiri akan cepat berbeda dari isinya. Menurunkannya membuat
     * keduanya mustahil berselisih.
     *
     * Sekarang lewat pivot temuan_satker: satu temuan pun bisa mengenai
     * beberapa satuan kerja.
     */
    public function satkerDiperiksa(): Collection
    {
        return $this->temuan
            ->flatMap(fn ($t) => $t->satkers)
            ->filter()
            ->unique('id')
            ->sortBy('nama')
            ->values();
    }

    /** Berapa temuan yang mengenai satuan kerja ini. */
    public function jumlahTemuanSatker(int $satkerId): int
    {
        return $this->temuan->filter(fn ($t) => $t->satkers->contains('id', $satkerId))->count();
    }

    /*
     * Nama-nama satuan kerja yang diperiksa, siap ditampilkan. Jumlah temuan
     * disebut hanya kalau ada satuan kerja yang punya lebih dari satu — kalau
     * satu temuan satu satker, angka "(1)" cuma menambah bacaan.
     */
    public function daftarSatkerDiperiksa(): string
    {
        $satker = $this->satkerDiperiksa();
        if ($satker->isEmpty()) {
            return '—';
        }
        $adaGanda = $satker->contains(fn ($s) => $this->jumlahTemuanSatker($s->id) > 1);

        return $satker->map(function ($s) use ($adaGanda) {
            $n = $this->jumlahTemuanSatker($s->id);
            return $s->namaPendek().($adaGanda && $n > 1 ? " ({$n} temuan)" : '');
        })->join(', ');
    }

    /** Bentuk ringkas untuk kartu sempit: dua nama pertama, sisanya dihitung. */
    public function ringkasSatkerDiperiksa(int $maks = 2): string
    {
        $satker = $this->satkerDiperiksa();
        if ($satker->isEmpty()) {
            return '—';
        }
        if ($satker->count() <= $maks) {
            return $satker->map->namaPendek()->join(', ');
        }
        return $satker->take($maks)->map->namaPendek()->join(', ')
            .' +'.($satker->count() - $maks).' lagi';
    }

    /* ================================================================
       ANGKA
       ================================================================ */

    /**
     * Angka ringkas untuk kepala halaman laporan. Dikumpulkan di satu tempat
     * supaya kartu ringkasan dan rel kanan tidak pernah menghitung hal yang
     * sama dengan cara yang berbeda.
     *
     * Posisi dibaca lewat posisiTampil(), bukan kolom posisi mentah — kolom itu
     * sekarang hanya menyimpan tingkat 2 dan bernilai NULL selama satuan
     * kerjanya masih bekerja. Membacanya mentah membuat seluruh laporan yang
     * sedang berjalan tampak tidak punya posisi sama sekali.
     */
    public function angka(): array
    {
        $rek = $this->temuan->flatMap->rekomendasi;

        $posisi = $rek->map(fn ($r) => $r->posisiTampil())->filter();
        $selesai = $posisi->filter(fn ($p) => $p === PosisiBerkas::SELESAI)->count();
        $diSatker = $posisi->filter(fn ($p) => $p === PosisiBerkas::SATKER)->count();

        return [
            'rek'     => $rek,
            'temuan'  => $this->temuan->count(),
            'jml'     => $rek->count(),
            'satker'  => $this->satkerDiperiksa()->count(),
            'selesai' => $selesai,
            /* Yang sudah lepas dari satuan kerja tapi belum final — sedang
               antre di salah satu meja pemeriksa. */
            'antre'   => $rek->count() - $selesai - $diSatker,
            'belumTL' => $diSatker,
            'telat'   => $rek->filter(fn ($r) => $r->lewatTenggat() > 0)->count(),
            /* Sebaran pemegang berkas, dihitung per baris satuan kerja —
               bukan per rekomendasi. Satu rekomendasi yang dipikul tiga satker
               memang berada di tiga meja sekaligus. */
            'pos'     => $this->sebaranPosisi(),
            'nilaiTemuan' => (int) $this->temuan->sum('nilai'),
            'tagihan' => (int) $rek->sum('nilai_pulih'),
            'masuk'   => (int) $rek->sum(fn ($r) => $r->nilaiTerpulihkan()),
        ];
    }

    /** Berapa berkas di tiap meja, dihitung per baris satuan kerja. */
    public function sebaranPosisi(): array
    {
        $hitung = [];
        foreach ($this->temuan->flatMap->rekomendasi as $r) {
            foreach ($r->sebaranPosisi() as $nama => $n) {
                $hitung[$nama] = ($hitung[$nama] ?? 0) + $n;
            }
        }
        arsort($hitung);
        return $hitung;
    }

    /**
     * Jarak wajar antara surat sampai dan surat dicatat. Lewat dari ini,
     * jaraknya disebut di daftar laporan — bukan sebagai kesalahan, tapi
     * supaya terbaca.
     *
     * Seminggu, karena mencatat surat yang sudah di tangan bukan pekerjaan
     * yang perlu ditunda. Dan selama belum dicatat, tenggat jawabannya sudah
     * berjalan tanpa ada satu pun satuan kerja yang bisa mengerjakannya —
     * tujuh hari sudah lebih dari sepersepuluh tenggat LHP.
     */
    public const BATAS_JEDA_CATAT = 7;

    /** Jarak antara surat sampai dan surat dicatat. Selama itu tenggat
        jawabannya sudah berjalan tapi belum ada yang bisa mengerjakannya. */
    public function jedaPencatatan(): ?int
    {
        if (! $this->dicatat_pada) {
            return null;
        }
        return (int) $this->tgl_terima->startOfDay()->diffInDays($this->dicatat_pada->startOfDay(), false);
    }

    /* Status laporan tidak pernah disimpan. Kalau disimpan, cepat atau lambat
       berbeda dengan kenyataan — dan yang paling berbahaya, bedanya ke arah
       yang kelihatan lebih baik. */
    public function statusSimpulan(): StatusTindakLanjut
    {
        return Rekomendasi::simpulkan($this->rekomendasi);
    }

    /**
     * Tuntas bila tidak ada lagi yang perlu dikerjakan siapa pun. Patokannya
     * posisi, bukan status: yang berstatus SS tapi belum diunggah ke SIPTL
     * masih punya pekerjaan.
     *
     * Laporan tanpa rekomendasi yang terlihat bukan berarti sudah beres —
     * tanpa penjaga isNotEmpty, every() pada larik kosong membuatnya dianggap
     * selesai dan berkasnya menghilang dari daftar yang masih berjalan.
     */
    public function tuntas(): bool
    {
        $r = $this->rekomendasi;
        return $r->isNotEmpty()
            && $r->every(fn ($x) => $x->posisiTampil() === PosisiBerkas::SELESAI);
    }

    public function tenggatJawab(): \Carbon\CarbonInterface
    {
        return Rekomendasi::hitungTenggat($this->tgl_terima, $this->sumber);
    }
}
