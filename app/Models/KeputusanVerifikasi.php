<?php

namespace App\Models;

use App\Enums\HasilTelaah;
use Illuminate\Database\Eloquent\Model;

/**
 * Bunyi satu surat hasil atas satu rekomendasi, pada satu putusan.
 *
 * Validasi UKI memutus satu baris (`sasaran_id` terisi). Verifikasi
 * Inspektorat menyebut hasil suratnya untuk rekomendasi seutuhnya — memadai
 * hanya kalau seluruh barisnya memadai — dengan catatan per satuan kerja di
 * `catatan_satker`.
 */
class KeputusanVerifikasi extends Model
{
    protected $fillable = [
        'verifikasi_id', 'rekomendasi_id', 'sasaran_id', 'hasil',
        'tenggat_baru', 'alasan_td_id', 'catatan', 'catatan_umum', 'catatan_satker',
        'diakui_masih_terbuka', 'aksi_id',
    ];

    protected $casts = [
        'hasil'                => HasilTelaah::class,
        'tenggat_baru'         => 'date',
        'catatan_satker'       => 'array',
        'diakui_masih_terbuka' => 'boolean',
    ];

    public function verifikasi()  { return $this->belongsTo(Verifikasi::class); }
    public function rekomendasi() { return $this->belongsTo(Rekomendasi::class); }
    public function sasaran()     { return $this->belongsTo(Sasaran::class); }
    public function alasanTd()    { return $this->belongsTo(Referensi::class, 'alasan_td_id'); }
}
