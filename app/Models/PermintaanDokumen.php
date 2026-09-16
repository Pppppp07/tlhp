<?php

namespace App\Models;

use App\Enums\PeranPengguna;
use Illuminate\Database\Eloquent\Model;

/**
 * Daftar dokumen yang diminta dari satu satuan kerja pada satu bentuk tindak
 * lanjut. Yang meminta selalu Setba — saat mencatat laporan, atau saat
 * mengirim ulang untuk pemberkasan ulang; `dari` menyebut penolakan siapa
 * yang melahirkannya.
 */
class PermintaanDokumen extends Model
{
    protected $fillable = [
        'rekomendasi_id', 'sasaran_id', 'peran_peminta', 'dari', 'diminta_oleh', 'tanggal', 'catatan', 'alasan',
    ];

    protected $casts = ['peran_peminta' => PeranPengguna::class, 'tanggal' => 'date'];

    public function rekomendasi() { return $this->belongsTo(Rekomendasi::class); }
    /* Permintaan ditujukan ke satu satuan kerja. Permintaan "kepada
       rekomendasi" tidak ada artinya, dan satuan kerja lain tidak boleh ikut
       terbebani. */
    public function sasaran()     { return $this->belongsTo(Sasaran::class); }
    public function item()        { return $this->hasMany(ItemPermintaan::class)->orderBy('id'); }
}
