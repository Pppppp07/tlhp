<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Surat pengantar antar unit.
 *
 * Berkas tidak berpindah begitu saja. Setba mengantar ke UKI dengan surat
 * bernomor, UKI mengembalikan dengan surat, Setba meneruskan ke Inspektorat
 * dengan surat lagi. Nomornya dipakai menelusuri berkas di luar sistem -
 * kalau ada yang menanyakan lewat telepon, nomor surat itulah yang disebut.
 *
 * tanggal adalah tanggal pada suratnya; tanggal_catat kapan ia dimasukkan ke
 * sistem. Keduanya kerap berbeda, dan yang dipakai menghitung adalah yang
 * pertama.
 */
class Surat extends Model
{
    protected $fillable = [
        'rekomendasi_id', 'sasaran_id', 'dari', 'ke', 'nomor',
        'tanggal', 'tanggal_catat', 'perihal', 'catatan',
        'lampiran_id', 'dicatat_oleh',
    ];

    protected $casts = [
        'tanggal'       => 'date',
        'tanggal_catat' => 'date',
    ];

    public function rekomendasi() { return $this->belongsTo(Rekomendasi::class); }
    public function sasaran()     { return $this->belongsTo(Sasaran::class); }
    public function lampiran()    { return $this->belongsTo(Lampiran::class); }
    public function pencatat()    { return $this->belongsTo(User::class, 'dicatat_oleh'); }

    public function ringkas(): string
    {
        return trim($this->dari.' → '.$this->ke);
    }
}
