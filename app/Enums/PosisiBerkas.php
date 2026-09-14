<?php

namespace App\Enums;

/**
 * Di meja siapa berkasnya berada. Sumbu terpisah dari status.
 *
 * Sembilan posisi ini terbagi dua tingkat, dan pembagian itu bukan penggolongan
 * di atas kertas — keduanya disimpan di kolom yang berbeda:
 *
 *   TINGKAT 1  sasarans.posisi       ditempuh berkas tiap satuan kerja sendiri
 *              satker → setba_tinjau → uki → setba_teruskan → inspektorat → tuntas
 *
 *   TINGKAT 2  rekomendasis.posisi   ditempuh rekomendasinya, baru berjalan
 *              siptl → bpk → selesai   sesudah SELURUH sasaran tuntas
 *
 * Balai Medan bisa sudah di inspektorat sementara Politeknik PU masih di
 * satker. Rekomendasi yang memikul keduanya belum bergerak sama sekali —
 * rekomendasis.posisi bernilai NULL, dan itulah penanda gerbangnya tertutup.
 *
 * Jalur LHA berhenti di tuntas: tidak ada SIPTL, tidak ada BPK.
 */
enum PosisiBerkas: string
{
    /* ---- tingkat 1: berkas tiap satuan kerja ---- */
    case SATKER          = 'satker';
    case SETBA_TINJAU    = 'setba_tinjau';
    case UKI             = 'uki';
    case SETBA_TERUSKAN  = 'setba_teruskan';
    case INSPEKTORAT     = 'inspektorat';
    case TUNTAS          = 'tuntas';

    /* ---- tingkat 2: rekomendasinya sendiri ---- */
    case SIPTL           = 'siptl';
    case BPK             = 'bpk';
    case SELESAI         = 'selesai';

    public function label(): string
    {
        return match ($this) {
            self::SATKER         => 'Menunggu tanggapan satuan kerja',
            self::SETBA_TINJAU   => 'Tanggapan perlu ditinjau Setba',
            self::UKI            => 'Menunggu telaah UKI',
            self::SETBA_TERUSKAN => 'Hasil telaah UKI perlu diteruskan',
            self::INSPEKTORAT    => 'Menunggu verifikasi Inspektorat',
            self::TUNTAS         => 'Tindak lanjutnya sudah memadai, menunggu satuan kerja lain',
            self::SIPTL          => 'Perlu diunggah Setba ke SIPTL',
            self::BPK            => 'Menunggu BPK — dicek Setba di SIPTL',
            self::SELESAI        => 'Sudah ditetapkan',
        };
    }

    /** Tingkat 1 atau 2 — menentukan kolom mana yang menyimpannya. */
    public function tingkat(): int
    {
        return in_array($this, [self::SIPTL, self::BPK, self::SELESAI], true) ? 2 : 1;
    }

    /** @return list<self> */
    public static function tingkat1(): array
    {
        return array_values(array_filter(self::cases(), fn ($p) => $p->tingkat() === 1));
    }

    /** @return list<self> */
    public static function tingkat2(): array
    {
        return array_values(array_filter(self::cases(), fn ($p) => $p->tingkat() === 2));
    }

    /**
     * Peran yang berkasnya sedang di tangannya.
     *
     * Null berarti tidak dipegang siapa pun — entah karena sudah ditetapkan,
     * atau karena bagian satuan kerja ini sudah beres dan yang ditunggu adalah
     * satuan kerja lain. Permintaan perubahan pada keadaan itu diputus Setba.
     */
    public function pemegang(): ?PeranPengguna
    {
        return match ($this) {
            self::SATKER         => PeranPengguna::SATKER,
            self::SETBA_TINJAU,
            self::SETBA_TERUSKAN,
            self::SIPTL          => PeranPengguna::SETBA,
            self::UKI            => PeranPengguna::UKI,
            self::INSPEKTORAT    => PeranPengguna::INSPEKTORAT,
            default              => null,
        };
    }

    /**
     * Sebutan pemegangnya untuk dibaca sekilas di kolom "Posisi berkas".
     * Yang perlu terbaca adalah unit kerjanya, bukan kalimat panjangnya.
     */
    public function sebutanPemegang(): string
    {
        return match ($this) {
            self::TUNTAS  => 'Menunggu satuan kerja lain',
            self::SELESAI => 'Selesai',
            self::BPK     => 'BPK',
            default       => $this->pemegang()?->pendek() ?? '—',
        };
    }

    /** Tahap keberapa pada rel. Jalur LHA berhenti di tahap 5. */
    public function tahap(): int
    {
        return match ($this) {
            self::SATKER, self::SETBA_TINJAU        => 2,
            self::UKI, self::SETBA_TERUSKAN         => 3,
            self::INSPEKTORAT                       => 4,
            self::TUNTAS                            => 5,
            self::SIPTL, self::BPK, self::SELESAI   => 6,
        };
    }

    /**
     * Tahapnya sudah tuntas, berkasnya tinggal diteruskan. Duduk di tahap yang
     * sama dengan yang sedang dikerjakan, tapi artinya berbeda: yang satu
     * "sedang dikerjakan", yang satu "sudah selesai, tinggal dioper".
     */
    public function transit(): bool
    {
        return in_array($this, [self::SETBA_TINJAU, self::SETBA_TERUSKAN], true);
    }

    /**
     * Keadaan tahap ke-$n bagi berkas yang sedang di posisi ini.
     *
     * @return 'selesai'|'aktif'|'antre'|'belum'
     */
    public function keadaanTahap(int $n): string
    {
        if ($this === self::SELESAI) {
            return 'selesai';
        }
        $kini = $this->transit() ? $this->tahap() + 1 : $this->tahap();

        if ($n < $kini) return 'selesai';
        if ($n > $kini) return 'belum';
        return $this->transit() ? 'antre' : 'aktif';
    }
}
