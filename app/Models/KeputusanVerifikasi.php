<?php

namespace App\Models;

use App\Enums\HasilTelaah;
use Illuminate\Database\Eloquent\Model;

/**
 * Putusan sebuah surat verifikasi atas satu rekomendasi.
 *
 * Cakupannya SELURUH rekomendasi — berbeda dari tanda memadai per baris di
 * sasarans.hasil, yang cuma penilaian atas satu satuan kerja. Kata Pak Iwan di
 * rapat 26 Agustus: "Itjen tuh lihat dari satu rekomendasi kan. Jadi kalau di
 * saat 3 satker itu belum beres, dia dianggap belum memadai semua."
 */
class KeputusanVerifikasi extends Model
{
    protected $fillable = [
        'verifikasi_id', 'rekomendasi_id', 'hasil',
        'tenggat_baru', 'alasan_td_id', 'catatan', 'diakui_masih_terbuka',
    ];
    protected $casts = [
        /* Sumbu Itjen (M/BM), bukan sumbu BPK (SS/BS/TD). Keduanya pernah
           dicampur di sini, dan akibatnya status BPK berubah tanpa BPK pernah
           menilai apa pun. */
        'hasil' => HasilTelaah::class,
        'tenggat_baru' => 'date',
        'diakui_masih_terbuka' => 'boolean',
    ];

    public function verifikasi()  { return $this->belongsTo(Verifikasi::class); }
    public function rekomendasi() { return $this->belongsTo(Rekomendasi::class); }
    public function alasanTd()    { return $this->belongsTo(Referensi::class, 'alasan_td_id'); }
}
