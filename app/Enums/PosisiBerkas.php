<?php

namespace App\Enums;

/**
 * Di meja siapa berkas satu tindak lanjut satuan kerja berada.
 *
 * Satu rel, milik BARIS PENUGASAN (`sasarans.posisi`) — bukan milik
 * rekomendasinya. Rekomendasi tidak menempuh proses apa pun: yang dikirim,
 * ditelaah, dan diunggah ke SIPTL adalah tindak lanjut tiap satuan kerja.
 *
 *   satker → setba_kembali → setba_tinjau → uki → setba_teruskan → inspektorat → tuntas
 *
 * Dulu ada tingkat dua sesudah `tuntas` — siptl → bpk → selesai — yang disimpan
 * di `rekomendasis.posisi`. Dibuang bersama kolomnya (prototipe, 10 Sep):
 * unggahan SIPTL dan status BPK kini dicatat per baris, sebagai catatan, bukan
 * sebagai posisi.
 *
 * URUTAN CASE ADALAH URUTAN RANTAI. `urutan()` membacanya dari sini, dan
 * pembanding "mana yang paling tertinggal" memakai urutan itu — bukan nomor
 * tahap, yang seri di dua tempat (satker dan setba_tinjau sama-sama tahap 2).
 */
enum PosisiBerkas: string
{
    case SATKER          = 'satker';
    /* Ditolak UKI, Inspektorat, atau BPK. Berkasnya TIDAK langsung pulang ke
       satuan kerja: Setba yang menerima penolakannya, lalu mengirimkannya
       ulang bersama alasan dan dokumen yang diminta. Tahapnya tetap 2 dan
       bukan transit — tindak lanjutnya memang sedang kembali di tahap
       tanggapan; yang berbeda cuma siapa yang memegangnya. */
    case SETBA_KEMBALI   = 'setba_kembali';
    case SETBA_TINJAU    = 'setba_tinjau';
    case UKI             = 'uki';
    case SETBA_TERUSKAN  = 'setba_teruskan';
    case INSPEKTORAT     = 'inspektorat';
    case TUNTAS          = 'tuntas';

    /**
     * Labelnya menyebut POSISI, bukan putusan. `TUNTAS` dulu berbunyi
     * "Tindak lanjutnya sudah memadai" — meminjam kata milik sumbu
     * Inspektorat, yang punya kolomnya sendiri persis di sebelahnya.
     */
    public function label(): string
    {
        return match ($this) {
            self::SATKER         => 'Menunggu tanggapan satuan kerja',
            self::SETBA_KEMBALI  => 'Dikembalikan — menunggu dikirim ulang Setba',
            self::SETBA_TINJAU   => 'Tanggapan perlu ditinjau Setba',
            self::UKI            => 'Menunggu telaah UKI',
            self::SETBA_TERUSKAN => 'Hasil telaah UKI perlu diteruskan',
            self::INSPEKTORAT    => 'Menunggu verifikasi Inspektorat',
            self::TUNTAS         => 'Sudah selesai diperiksa',
        };
    }

    /** Letaknya di rantai, dari 0. */
    public function urutan(): int
    {
        return array_search($this, self::cases(), true);
    }

    /**
     * Peran yang berkasnya sedang di tangannya. Null pada `TUNTAS`: berkasnya
     * sudah berhenti berpindah meja — urusan SIPTL-nya dibaca terpisah.
     */
    public function pemegang(): ?PeranPengguna
    {
        return match ($this) {
            self::SATKER         => PeranPengguna::SATKER,
            self::SETBA_KEMBALI,
            self::SETBA_TINJAU,
            self::SETBA_TERUSKAN => PeranPengguna::SETBA,
            self::UKI            => PeranPengguna::UKI,
            self::INSPEKTORAT    => PeranPengguna::INSPEKTORAT,
            self::TUNTAS         => null,
        };
    }

    /** Sebutan pemegangnya di rel prototipe: "Balai", "Setba", "UKI", "Inspektorat", "—". */
    public function pegang(): string
    {
        return match ($this) {
            self::SATKER => 'Balai',
            self::TUNTAS => '—',
            default      => $this->pemegang()->pendek(),
        };
    }

    /** Tahap keberapa pada rel lima tahap. */
    public function tahap(): int
    {
        return match ($this) {
            self::SATKER, self::SETBA_KEMBALI, self::SETBA_TINJAU => 2,
            self::UKI, self::SETBA_TERUSKAN                       => 3,
            self::INSPEKTORAT                                     => 4,
            self::TUNTAS                                          => 5,
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

    /** Lima tahap, sama untuk LHP dan LHA. */
    public const TAHAP = ['Registrasi', 'Tanggapan balai', 'Telaah UKI',
        'Verifikasi Inspektorat', 'Surat & penetapan'];

    /** Yang paling tertinggal dari sederet posisi. Kosong berarti masih di satuan kerja. */
    public static function palingBelakang(iterable $posisi): self
    {
        $terkecil = null;
        foreach ($posisi as $p) {
            if ($terkecil === null || $p->urutan() < $terkecil->urutan()) {
                $terkecil = $p;
            }
        }

        return $terkecil ?? self::SATKER;
    }
}
