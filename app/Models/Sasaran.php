<?php

namespace App\Models;

use App\Enums\HasilTelaah;
use App\Enums\PeranPengguna;
use App\Enums\PosisiBerkas;
use App\Enums\StatusTindakLanjut;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu satuan kerja pada satu bentuk tindak lanjut.
 *
 * Di sinilah tingkat 1 hidup. Semua yang dikerjakan satuan kerja menggantung
 * di sini, bukan di rekomendasi: jawabannya, setorannya, dokumen yang diminta
 * darinya, telaah atas berkasnya, dan jejak perpindahannya.
 *
 * Alasannya sederhana. Selama gantungannya di rekomendasi, jawaban Balai Medan
 * dan Politeknik PU bercampur dalam satu daftar, dan tidak ada cara memisahkan
 * mana yang milik siapa - sementara keduanya bisa berada di tahap yang sama
 * sekali berbeda.
 */
class Sasaran extends Model
{
    protected $fillable = ['tindakan_id', 'satker_id', 'nilai',
        'nilai_ss', 'nilai_memadai', 'posisi', 'hasil', 'catatan'];

    protected $casts = [
        'posisi' => PosisiBerkas::class,
        'hasil'  => HasilTelaah::class,
    ];

    /**
     * Nilai yang sudah DIAKUI, dua sumbu karena yang mengakui ada dua.
     *
     * Medannya boleh kosong. Kosong berarti "belum ada angkanya sendiri",
     * bukan nol — yang membacanya jatuh ke keadaannya: utuh bila sudah beres,
     * nol bila belum. Kalau kosong diperlakukan nol, rekomendasi yang sudah
     * dinyatakan sesuai akan terbaca belum diakui sepeser pun.
     *
     * Nilainya bisa diakui SEBAGIAN, dan itu bukan hal langka — Mas Naufal:
     * "setoran Rp 100 juta tapi ternyata yang diterima cuman Rp 80 juta,
     * Rp 20 jutanya dibatalkan pemenuhannya."
     */
    public function nilaiDiakuiItjen(): int
    {
        return $this->nilai_memadai !== null
            ? (int) $this->nilai_memadai
            : ($this->hasil === HasilTelaah::M ? (int) $this->nilai : 0);
    }

    /**
     * Sisi BPK. Statusnya sendiri milik REKOMENDASI — BPK menutup rekomendasi,
     * bukan satuan kerja satu per satu — tapi nilainya per baris, karena
     * uangnya memang ditagihkan per satuan kerja.
     */
    public function nilaiDiakuiBpk(): int
    {
        if ($this->nilai_ss !== null) {
            return (int) $this->nilai_ss;
        }
        $rek = $this->rekomendasi();

        return $rek && $rek->status === StatusTindakLanjut::SS ? (int) $this->nilai : 0;
    }

    /** Nilai yang belum diakui, menurut masing-masing sumbu. */
    public function sisaNilaiItjen(): int { return max(0, (int) $this->nilai - $this->nilaiDiakuiItjen()); }
    public function sisaNilaiBpk(): int   { return max(0, (int) $this->nilai - $this->nilaiDiakuiBpk()); }

    public function tindakan() { return $this->belongsTo(Tindakan::class); }
    public function satker()   { return $this->belongsTo(Satker::class); }

    public function tanggapan()        { return $this->hasMany(Tanggapan::class)->orderBy('tanggal'); }
    public function pemulihan()        { return $this->hasMany(Pemulihan::class)->orderBy('tanggal'); }
    public function permintaanDokumen(){ return $this->hasMany(PermintaanDokumen::class)->orderBy('tanggal'); }
    public function pengembalian()     { return $this->hasMany(Pengembalian::class)->orderBy('tanggal'); }
    public function telaah()           { return $this->hasMany(Telaah::class)->orderBy('tanggal'); }
    public function riwayat()          { return $this->hasMany(RiwayatBerkas::class)->orderBy('waktu'); }
    public function lampiran()         { return $this->hasMany(Lampiran::class); }
    public function surat()            { return $this->hasMany(Surat::class)->orderBy('tanggal'); }

    /** Jalan pintas ke rekomendasinya - dipakai di banyak tempat. */
    public function rekomendasi(): ?Rekomendasi
    {
        return $this->tindakan?->rekomendasi;
    }

    /* ---------- progres, tidak pernah disimpan ---------- */

    public function nilaiTerpulihkan(): int
    {
        return (int) $this->pemulihan->sum('nilai');
    }

    public function sisaPemulihan(): int
    {
        return max(0, (int) $this->nilai - $this->nilaiTerpulihkan());
    }

    public function lunas(): bool
    {
        return (int) $this->nilai === 0 || $this->sisaPemulihan() === 0;
    }

    /** Kelengkapan dokumen sebagai [terpenuhi, total]. Null bila memang tidak
        ada permintaan sama sekali. */
    public function progresDokumen(): ?array
    {
        $butir = $this->permintaanDokumen->flatMap->item;
        if ($butir->isEmpty()) {
            return null;
        }
        return [$butir->where('terpenuhi', true)->count(), $butir->count()];
    }

    public function dokumenLengkap(): bool
    {
        $p = $this->progresDokumen();
        return $p === null || $p[0] === $p[1];
    }

    /**
     * Boleh dikirim ke Setba.
     *
     * Kelunasan adalah tanda, bukan penguncian. Satuan kerja yang baru menyetor
     * separuh tetap boleh mengirim berkasnya - pemulihan dana bisa memakan
     * bertahun-tahun, dan menahan berkasnya sampai lunas berarti tidak ada yang
     * bisa memeriksa kemajuannya selama itu. Yang ditahan hanya kalau dokumen
     * yang diminta belum lengkap: tanpa dokumennya, tidak ada yang bisa
     * ditelaah.
     */
    public function bolehDikirim(): bool
    {
        return $this->dokumenLengkap();
    }

    /* ---------- keadaan ---------- */

    public function tuntas(): bool
    {
        return $this->posisi === PosisiBerkas::TUNTAS;
    }

    /**
     * Sebutan posisi baris ini, dibaca bersama rekomendasinya.
     *
     * `PosisiBerkas::TUNTAS` sendirian berbunyi "Menunggu satuan kerja lain" —
     * benar selama memang masih ada yang ditunggu. Begitu seluruh baris tuntas
     * dan rekomendasinya naik ke tingkat dua, tidak ada lagi yang ditunggu,
     * dan kalimat itu jadi keliru: pembacanya mengira berkasnya masih
     * tertahan, padahal bagiannya sudah selesai.
     */
    public function sebutanPosisi(): string
    {
        if ($this->posisi !== PosisiBerkas::TUNTAS) {
            return $this->posisi?->sebutanPemegang() ?? '—';
        }

        $rek = $this->rekomendasi();
        $adaYangDitunggu = $rek
            ? $rek->daftarSasaran()->contains(fn ($x) => $x->posisi !== PosisiBerkas::TUNTAS)
            : false;

        return $adaYangDitunggu
            ? PosisiBerkas::TUNTAS->sebutanPemegang()
            : 'Tindak lanjutnya sudah memadai';
    }

    /**
     * Rencana aksi yang mengikat baris ini — tanggal bentuk tindak lanjutnya.
     *
     * Satuan kerja yang kena dua bentuk memikul dua tanggal; yang ditagih
     * lebih dulu itulah yang harus dikejar, dan itu urusan pemanggilnya.
     * Baris ini cuma tahu tanggalnya sendiri.
     */
    /**
     * Keadaan berkas baris ini, bukan nama unit yang memegangnya.
     *
     * "Setba" tidak menjawab apa yang sedang terjadi: berkas di mejanya bisa
     * berarti tanggapan perlu ditinjau, atau hasil telaah UKI perlu
     * diteruskan — dan keduanya pekerjaan yang berbeda oleh orang yang sama.
     *
     * Yang tuntas dibaca bersama rekomendasinya, sama seperti sebutanPosisi().
     */
    public function keadaanPosisi(): string
    {
        if ($this->posisi !== PosisiBerkas::TUNTAS) {
            return $this->posisi?->label() ?? '—';
        }

        $rek = $this->rekomendasi();
        $adaYangDitunggu = $rek
            ? $rek->daftarSasaran()->contains(fn ($x) => $x->posisi !== PosisiBerkas::TUNTAS)
            : false;

        return $adaYangDitunggu
            ? PosisiBerkas::TUNTAS->label()
            : 'Tindak lanjutnya sudah memadai';
    }

    public function renaksi(): ?\Carbon\CarbonInterface
    {
        return $this->tindakan?->renaksi();
    }

    public function pemegang(): ?PeranPengguna
    {
        return $this->posisi?->pemegang();
    }

    public function diMeja(PeranPengguna $peran): bool
    {
        return $this->pemegang() === $peran;
    }

    /** Telaah terakhir yang berbunyi - itulah yang menetapkan hasilnya. */
    public function telaahTerakhir(): ?Telaah
    {
        return $this->telaah->whereNotNull('hasil')->last();
    }

    /* ---------- saringan ---------- */

    public function scopeMilik($q, ?int $satkerId)
    {
        return $satkerId ? $q->where('satker_id', $satkerId) : $q;
    }

    public function scopeDiMejaPeran($q, PeranPengguna $peran)
    {
        $posisi = collect(PosisiBerkas::tingkat1())
            ->filter(fn ($p) => $p->pemegang() === $peran)
            ->map->value->all();

        return $q->whereIn('posisi', $posisi);
    }
}
