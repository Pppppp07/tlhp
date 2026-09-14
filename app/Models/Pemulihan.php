<?php

namespace App\Models;

use App\Enums\JenisPemulihan;
use Illuminate\Database\Eloquent\Model;

/** Satu baris satu peristiwa pemulihan. Nilai terkumpul selalu dijumlahkan
    dari sini — tidak pernah ada kolom yang menyimpannya. */
class Pemulihan extends Model
{
    protected $fillable = [
        'rekomendasi_id', 'sasaran_id', 'jenis', 'tanggal', 'nilai',
        'no_ssbp', 'ntpn', 'no_nota_kppn', 'no_berita_acara',
        'lampiran_id', 'dicatat_oleh',
    ];
    protected $casts = ['jenis' => JenisPemulihan::class, 'tanggal' => 'date'];

    public function rekomendasi() { return $this->belongsTo(Rekomendasi::class); }
    /* Setoran selalu milik satu satuan kerja. Nominal rekomendasi dipecah
       antar satker, jadi setoran yang tidak menyebut satkernya tidak bisa
       dikurangkan dari tagihan siapa pun. */
    public function sasaran()     { return $this->belongsTo(Sasaran::class); }
    public function lampiran()    { return $this->belongsTo(Lampiran::class); }

    /** Setoran tunai butuh NTPN 16 karakter; perbaikan butuh berita acara. */
    public function buktiLengkap(): bool
    {
        return $this->jenis->perluNtpn()
            ? (bool) preg_match('/^[0-9A-Za-z]{16}$/', (string) $this->ntpn)
            : filled($this->no_berita_acara);
    }
}
