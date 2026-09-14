<?php

namespace App\Models;

use App\Enums\HasilTelaah;
use App\Enums\PeranPengguna;
use App\Enums\PosisiBerkas;
use App\Enums\StatusPermintaanUbah;
use App\Enums\StatusTindakLanjut;
use App\Enums\SumberLaporan;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Unit yang dipantau. SOP mewajibkan Daftar Kompilasi Monitoring memuat uraian
 * rekomendasi, nilai keuangan dalam rekomendasi, dan hasil pemantauan status —
 * ketiganya melekat di sini, bukan di temuan.
 *
 * Penugasannya TIDAK di sini. Satu rekomendasi bisa ditujukan ke beberapa
 * satuan kerja sekaligus, nominalnya dipecah, dan berkas tiap satuan kerja
 * berjalan sendiri-sendiri — itu semua hidup di tindakans → sasarans.
 *
 * Yang tersisa di sini hanyalah yang memang berlaku untuk seluruhnya: total
 * tagihan, gerak tingkat 2, dan status dari BPK.
 */
class Rekomendasi extends Model
{
    protected $fillable = [
        'temuan_id', 'kode', 'ref_lhp', 'nomor_urut', 'uraian', 'sifat_id',
        'nilai_pulih', 'rencana_angsur', 'kunci_angsur',
        'tenggat_jawab', 'target_selesai', 'catatan',
        'status', 'posisi', 'alasan_td_id', 'catatan_td',
        'siptl_tanggal', 'siptl_tanda_terima',
        'siptl_status', 'siptl_catatan', 'siptl_dicatat_pada',
    ];

    protected $casts = [
        'status'             => StatusTindakLanjut::class,
        // Hanya tingkat 2. NULL berarti gerbangnya belum terbuka.
        'posisi'             => PosisiBerkas::class,
        'siptl_status'       => StatusTindakLanjut::class,
        'tenggat_jawab'      => 'date',
        'target_selesai'     => 'date',
        'siptl_tanggal'      => 'date',
        'siptl_dicatat_pada' => 'date',
        'kunci_angsur'       => 'boolean',
    ];

    public function temuan()   { return $this->belongsTo(Temuan::class); }
    public function sifat()    { return $this->belongsTo(Referensi::class, 'sifat_id'); }
    public function alasanTd() { return $this->belongsTo(Referensi::class, 'alasan_td_id'); }

    public function tindakan() { return $this->hasMany(Tindakan::class)->orderBy('urutan')->orderBy('id'); }

    /** Seluruh baris penugasan, lintas bentuk tindak lanjut. */
    public function sasaran()
    {
        return $this->hasManyThrough(Sasaran::class, Tindakan::class,
            'rekomendasi_id', 'tindakan_id', 'id', 'id')->orderBy('sasarans.id');
    }

    public function keputusan()      { return $this->hasMany(KeputusanVerifikasi::class); }
    public function permintaanUbah() { return $this->hasMany(PermintaanUbah::class); }
    public function lampiran()       { return $this->hasMany(Lampiran::class); }
    public function riwayat()        { return $this->hasMany(RiwayatBerkas::class)->orderBy('waktu'); }
    public function surat()          { return $this->hasMany(Surat::class)->orderBy('tanggal'); }

    /* Yang di bawah ini menggantung pada sasaran, bukan pada rekomendasi.
       Tetap disediakan di sini sebagai gabungan seluruh satuan kerja — dipakai
       layar pengawas yang memang melihat semuanya sekaligus. Layar satuan
       kerja TIDAK BOLEH memakainya: ia membaca dari sasarannya sendiri. */
    public function tanggapan()        { return $this->hasMany(Tanggapan::class)->orderBy('tanggal'); }
    public function tindakLanjut()     { return $this->tanggapan(); }
    public function permintaanDokumen(){ return $this->hasMany(PermintaanDokumen::class)->orderBy('tanggal'); }
    public function pemulihan()        { return $this->hasMany(Pemulihan::class)->orderBy('tanggal'); }
    public function pengembalian()     { return $this->hasMany(Pengembalian::class)->orderBy('tanggal'); }
    public function telaah()           { return $this->hasMany(Telaah::class)->orderBy('tanggal'); }

    /* ================================================================
       PENOMORAN RESMI
       ================================================================ */

    /**
     * Ref LHP — disalin apa adanya dari suratnya, tidak dirakit.
     *
     * Bentuknya berbeda tiap tahun: "1.7", "10.a", "22.B.12", "I.1.1.a",
     * "II.4.4.f". Merakitnya dari nomor temuan + huruf rekomendasi cuma
     * menghasilkan kode yang tidak cocok dengan suratnya. Kalau belum diisi,
     * rakitannya dipakai sebagai isian awal supaya berkas lama tetap punya
     * kode.
     */
    public function refLhp(): string
    {
        $tersimpan = trim((string) $this->ref_lhp);
        if ($tersimpan !== '') {
            return $tersimpan;
        }

        return collect([$this->temuan?->nomor_pada_surat, $this->nomor_urut])
            ->filter()->join('.');
    }

    /**
     * Ref IDT — justru dirakit, dan rumusnya tetap:
     *
     *     TahunLHP . NomorPendekLHP . RefLHP
     *
     * Contoh dari lembar pemantauan: 2025 + "12.b" + "II.4.4.f"
     * menghasilkan 2025.12.b.II.4.4.f
     *
     * Dihitung, bukan disimpan — kalau disimpan, ia bisa berselisih dengan
     * nomor temuan atau urutan rekomendasi yang jadi asalnya. Dipakai saat
     * berkoordinasi dengan BPK dan Biro.
     */
    public function refIdt(): string
    {
        $lap = $this->temuan?->laporan;
        if (! $lap) {
            return $this->refLhp();
        }

        $tahun = optional($lap->tgl_surat ?? $lap->tgl_terima)->format('Y');
        // Ruas pertama nomor suratnya saja: "12.b/LHP/XVII/05/2025" -> "12.b".
        $noSurat = explode('/', (string) $lap->nomor)[0] ?? '';

        return collect([$tahun, $noSurat, $this->refLhp()])->filter()->join('.');
    }

    /* ================================================================
       POSISI — dua tingkat
       ================================================================ */

    /** Seluruh sasaran, dimuat sekali lalu dipakai berkali-kali. */
    public function daftarSasaran(): Collection
    {
        return $this->relationLoaded('sasaran')
            ? $this->getRelation('sasaran')
            : $this->sasaran()->get();
    }

    public function semuaTuntas(): bool
    {
        $s = $this->daftarSasaran();
        return $s->isNotEmpty() && $s->every(fn ($x) => $x->tuntas());
    }

    /**
     * Posisi yang ditampilkan untuk rekomendasi seutuhnya.
     *
     * Selama tingkat 1 masih berjalan, yang disebut adalah sasaran yang PALING
     * TERTINGGAL — itu yang menentukan rekomendasinya masih jauh atau tinggal
     * sedikit. Menyebut yang paling maju membuat rekomendasi yang empat dari
     * lima satkernya belum mulai tampak hampir selesai.
     */
    public function posisiTampil(): ?PosisiBerkas
    {
        if ($this->posisi !== null) {
            return $this->posisi;
        }

        $s = $this->daftarSasaran();
        if ($s->isEmpty()) {
            return null;
        }
        if ($this->semuaTuntas()) {
            // Sasarannya sudah tuntas semua tapi tingkat 2 belum disetel —
            // data lama. Yang benar berikutnya adalah SIPTL.
            return PosisiBerkas::SIPTL;
        }

        return $s->reject(fn ($x) => $x->tuntas())
                 ->sortBy(fn ($x) => $x->posisi?->tahap() ?? 0)
                 ->first()?->posisi;
    }

    /** Unit yang sedang memegang berkasnya, disebut apa adanya. */
    public function pemegangTampil(): string
    {
        return $this->posisiTampil()?->sebutanPemegang() ?? 'Belum ditugaskan';
    }

    /** Sebaran posisi tiap satuan kerja, untuk kolom "Posisi berkas". */
    public function sebaranPosisi(): array
    {
        $hitung = [];
        foreach ($this->daftarSasaran() as $x) {
            $nama = $x->posisi?->sebutanPemegang() ?? 'Belum ditugaskan';
            $hitung[$nama] = ($hitung[$nama] ?? 0) + 1;
        }
        return $hitung;
    }

    /**
     * Gerbang tingkat 2. Rekomendasi tidak boleh masuk SIPTL sebelum seluruh
     * satuan kerjanya tuntas — dan itu satu-satunya syaratnya.
     */
    public function bolehMasukTingkat2(): bool
    {
        return $this->posisi === null && $this->semuaTuntas();
    }

    /* ================================================================
       HASIL — sumbu Itjen
       ================================================================ */

    /**
     * Putusan resmi atas seluruh rekomendasi, diambil dari surat CHV terakhir.
     *
     * Bukan dihitung dari barisnya. Belum ada suratnya berarti belum ada
     * putusannya — dan itu tidak sama dengan belum memadai.
     */
    public function putusan(): ?HasilTelaah
    {
        return $this->keputusan->sortBy(fn ($k) => $k->verifikasi?->tgl_surat)
            ->last()?->hasil;
    }

    /**
     * Keadaan rekomendasi menurut BPSDM sendiri — dipakai MENGHITUNG.
     *
     * Dibaca dari barisnya: memadai kalau seluruh satuan kerjanya memadai.
     * Bukan dari surat CHV.
     *
     * Alasannya ada di datanya. Di lembar pemantauan mereka, nomor CHV cuma
     * tercatat pada 47 dari 123 rekomendasi — sisanya suratnya ada di kertas
     * tapi nomornya tidak pernah dimasukkan. Menghitung dari surat membuat 76
     * rekomendasi yang seluruh barisnya sudah memadai terbaca belum memadai.
     *
     * Aman dibaca begini karena baris cuma bertanda memadai sesudah
     * INSPEKTORAT memutus — bukan UKI. Jadi membaca barisnya sama dengan
     * membaca putusan Inspektorat, cuma tanpa bergantung pada nomor suratnya
     * tercatat atau tidak.
     *
     * Ini kolom `Status Rekomendasi Unor` di lembar mereka, dan rumus yang
     * menghitungnya bernama `Max Rank Verifikasi per Reff IDT` — status
     * terburuk menang. Angkanya sudah dicocokkan: 117 memadai, 6 belum.
     *
     * `putusan()` tetap dipakai untuk menjawab "apa bunyi suratnya".
     */
    public function keadaanUnor(): HasilTelaah
    {
        $s = $this->daftarSasaran();

        return $s->isNotEmpty() && $s->every(fn ($x) => $x->hasil?->memadai())
            ? HasilTelaah::M
            : HasilTelaah::BM;
    }

    /**
     * Nilai yang sudah diakui, dua sumbu, dijumlah dari barisnya.
     *
     * Nilainya bisa diakui SEBAGIAN — rekomendasi Rp 792 juta yang buktinya
     * baru diterima Rp 192 juta menyisakan Rp 600 juta walau statusnya belum
     * berubah. Medannya ada di tiap baris; lihat `Sasaran::nilaiDiakuiBpk()`.
     */
    public function nilaiDiakuiBpk(): int
    {
        return (int) $this->daftarSasaran()->sum(fn ($x) => $x->nilaiDiakuiBpk());
    }

    public function nilaiDiakuiItjen(): int
    {
        return (int) $this->daftarSasaran()->sum(fn ($x) => $x->nilaiDiakuiItjen());
    }

    /**
     * Sisa nilai: yang belum diakui. BUKAN uang yang belum masuk kas —
     * untuk itu ada `sisaPemulihan()`. Rekomendasi yang uangnya sudah lunas
     * tapi buktinya ditolak sebagian tetap punya sisa nilai.
     */
    public function sisaNilaiBpk(): int
    {
        return max(0, $this->nilaiSasaran() - $this->nilaiDiakuiBpk());
    }

    public function sisaNilaiItjen(): int
    {
        return max(0, $this->nilaiSasaran() - $this->nilaiDiakuiItjen());
    }

    /** Berapa satuan kerja yang sudah ditandai memadai, dari berapa. */
    public function hitungMemadai(): array
    {
        $s = $this->daftarSasaran();
        return [$s->filter(fn ($x) => $x->hasil?->memadai())->count(), $s->count()];
    }

    /**
     * Satuan kerja yang belum ditandai memadai. Dipakai memperingatkan
     * Inspektorat sebelum ia menerbitkan CHV berbunyi memadai — kata Pak Iwan,
     * "kalau di saat 3 satker itu belum beres, dia dianggap belum memadai
     * semua."
     */
    public function sasaranBelumMemadai(): Collection
    {
        return $this->daftarSasaran()->reject(fn ($x) => $x->hasil?->memadai())->values();
    }

    /* ================================================================
       PROGRES — sumbu ketiga, tidak pernah disimpan
       ================================================================ */

    public function nilaiTerpulihkan(): int
    {
        return (int) $this->daftarSasaran()->sum(fn ($x) => $x->nilaiTerpulihkan());
    }

    public function sisaPemulihan(): int
    {
        return max(0, (int) $this->nilai_pulih - $this->nilaiTerpulihkan());
    }

    public function lunas(): bool
    {
        return (int) $this->nilai_pulih === 0 || $this->sisaPemulihan() === 0;
    }

    /**
     * Jumlah nilai seluruh sasaran. Harus sama dengan nilai_pulih; kalau
     * berbeda, salah satunya salah dan halaman laporan akan menjumlahkan angka
     * yang bukan miliknya.
     */
    public function nilaiSasaran(): int
    {
        return (int) $this->daftarSasaran()->sum('nilai');
    }

    public function nilaiSelaras(): bool
    {
        return $this->daftarSasaran()->isEmpty()
            || $this->nilaiSasaran() === (int) $this->nilai_pulih;
    }

    public function progresDokumen(): ?array
    {
        $butir = $this->permintaanDokumen->flatMap->item;
        if ($butir->isEmpty()) {
            return null;
        }
        return [$butir->where('terpenuhi', true)->count(), $butir->count()];
    }

    public function progresAngsuran(): ?array
    {
        if ((int) $this->rencana_angsur <= 0) {
            return null;
        }
        return [$this->pemulihan->count(), (int) $this->rencana_angsur, (bool) $this->kunci_angsur];
    }

    public function bolehTambahPemulihan(): bool
    {
        $a = $this->progresAngsuran();
        return $a === null || ! $a[2] || $a[0] < $a[1];
    }

    public function dokumenLengkap(): bool
    {
        $p = $this->progresDokumen();
        return $p === null || $p[0] === $p[1];
    }

    /* ================================================================
       TENGGAT
       ================================================================ */

    public function lewatTenggat(): int
    {
        if ($this->posisi === PosisiBerkas::SELESAI
            || in_array($this->status, [StatusTindakLanjut::SS, StatusTindakLanjut::TD], true)
            || ! $this->tenggat_jawab) {
            return 0;
        }
        $selisih = now()->startOfDay()->diffInDays($this->tenggat_jawab->startOfDay(), false);
        return $selisih < 0 ? (int) abs($selisih) : 0;
    }

    /**
     * Rencana aksi yang mengikat satu satuan kerja: tanggal paling awal di
     * antara bentuk tindak lanjut yang membebaninya.
     *
     * Satuan kerja yang kena dua bentuk dengan tanggal berbeda harus mengejar
     * yang lebih dulu jatuh tempo. Tanpa satker, yang dikembalikan tanggal
     * rekomendasinya seutuhnya — itu yang dipakai peran selain satuan kerja.
     */
    public function renaksiUntuk(?int $satkerId = null): ?CarbonInterface
    {
        if ($satkerId === null) {
            return $this->tenggat_jawab;
        }

        $tanggal = $this->daftarSasaran()
            ->filter(fn ($s) => $s->satker_id === $satkerId)
            ->map(fn ($s) => $s->renaksi())
            ->filter()
            ->sort();

        return $tanggal->first() ?? $this->tenggat_jawab;
    }

    /**
     * Perlu perhatian: masih berjalan, dan tenggatnya sudah lewat atau tinggal
     * sepekan.
     *
     * Berbeda dari lewatTenggat(), yang cuma menghitung yang sudah telat.
     * Perbedaannya penting di kepala blok temuan: yang jatuh tempo lusa belum
     * telat, tapi kalau baru disebut sesudah lewat, penyebutannya selalu
     * terlambat.
     *
     * Yang sudah sesuai atau sudah ditetapkan tidak dihitung walaupun tanggal
     * tenggatnya terlewat — tenggatnya memang sudah tidak berlaku.
     */
    public function perluPerhatian(): bool
    {
        if ($this->posisiTampil() === PosisiBerkas::SELESAI
            || in_array($this->status, [StatusTindakLanjut::SS, StatusTindakLanjut::TD], true)) {
            return false;
        }
        if ($this->lewatTenggat() > 0) {
            return true;
        }
        if (! $this->tenggat_jawab) {
            return false;
        }
        $sisa = (int) now()->startOfDay()->diffInDays($this->tenggat_jawab->startOfDay(), false);
        return $sisa >= 0 && $sisa <= 7;
    }

    public static function hitungTenggat(CarbonInterface $mulai, SumberLaporan $sumber): CarbonInterface
    {
        $t = $mulai->copy();
        if (! $sumber->pakaiHariKerja()) {
            return $t->addDays($sumber->hariTenggat());
        }
        $sisa = $sumber->hariTenggat();
        while ($sisa > 0) {
            $t->addDay();
            if (! $t->isWeekend()) {
                $sisa--;
            }
        }
        return $t;
    }

    /* ================================================================
       WEWENANG
       ================================================================ */

    public function pemegang(): ?PeranPengguna
    {
        return $this->posisiTampil()?->pemegang();
    }

    public function pemutusPerubahan(): PeranPengguna
    {
        return $this->pemegang() ?? PeranPengguna::SETBA;
    }

    public function adaPermintaanMenunggu(): bool
    {
        return $this->permintaanUbah
            ->contains(fn ($p) => $p->status === StatusPermintaanUbah::MENUNGGU);
    }

    /**
     * Begitu surat verifikasi terbit, isian tidak bisa lagi ditarik — data itu
     * sudah jadi dasar surat resmi bernomor. Penarikan berkas pribadi tetap
     * bisa, karena berkas salah unggah memang tidak pernah jadi dasarnya.
     */
    public function terkunciOlehSurat(): bool
    {
        return $this->keputusan->isNotEmpty();
    }

    /* ================================================================
       KESIMPULAN STATUS
       ================================================================ */

    public static function simpulkan(Collection $daftar): StatusTindakLanjut
    {
        if ($daftar->isEmpty()) {
            return StatusTindakLanjut::BT;
        }
        $aktif = $daftar->reject(fn ($r) => $r->status === StatusTindakLanjut::TD);
        if ($aktif->isEmpty()) {
            return StatusTindakLanjut::TD;
        }
        if ($aktif->every(fn ($r) => $r->status === StatusTindakLanjut::SS)) {
            return StatusTindakLanjut::SS;
        }
        if ($aktif->contains(fn ($r) => in_array($r->status, [StatusTindakLanjut::SS, StatusTindakLanjut::BS], true))) {
            return StatusTindakLanjut::BS;
        }
        return StatusTindakLanjut::BT;
    }

    /* ================================================================
       SARINGAN
       ================================================================ */

    /**
     * Berkasnya sedang di meja peran ini.
     *
     * Dibaca dari sasaran, bukan dari kolom posisi rekomendasi — kalau tidak,
     * rekomendasi yang salah satu satkernya sudah di UKI tidak akan pernah
     * muncul di daftar kerja UKI.
     */
    public function scopeDiMeja($q, PeranPengguna $peran)
    {
        $tingkat1 = collect(PosisiBerkas::tingkat1())
            ->filter(fn ($p) => $p->pemegang() === $peran)->map->value->all();
        $tingkat2 = collect(PosisiBerkas::tingkat2())
            ->filter(fn ($p) => $p->pemegang() === $peran)->map->value->all();

        /* Peran yang tidak pernah memegang berkas — Pimpinan, Admin — tidak
           punya antrean sama sekali. Tanpa penjaga ini penutupnya tidak
           menambahkan syarat apa pun, dan kueri yang tanpa syarat
           mengembalikan SELURUH rekomendasi: lencana menunya berbunyi 114
           padahal tidak ada satu pun yang bisa ia kerjakan. */
        if (! $tingkat1 && ! $tingkat2) {
            return $q->whereRaw('1 = 0');
        }

        return $q->where(function ($x) use ($tingkat1, $tingkat2) {
            if ($tingkat1) {
                $x->whereHas('sasaran', fn ($s) => $s->whereIn('sasarans.posisi', $tingkat1));
            }
            if ($tingkat2) {
                $x->orWhereIn('rekomendasis.posisi', $tingkat2);
            }
        });
    }

    public function scopeBerjalan($q)
    {
        return $q->where(fn ($x) => $x->whereNull('posisi')
            ->orWhere('posisi', '!=', PosisiBerkas::SELESAI->value));
    }
}
