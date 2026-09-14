<?php

namespace App\Models;

use App\Enums\PeranPengguna;
use Illuminate\Database\Eloquent\Model;

class PermintaanDokumen extends Model
{
    protected $fillable = [
        'rekomendasi_id', 'sasaran_id', 'peran_peminta', 'diminta_oleh', 'tanggal', 'catatan',
    ];
    protected $casts = ['peran_peminta' => PeranPengguna::class, 'tanggal' => 'date'];

    public function rekomendasi() { return $this->belongsTo(Rekomendasi::class); }
    /* Permintaan selalu ditujukan ke satu satuan kerja tertentu — meminta
       "kepada rekomendasi" tidak ada artinya, dan satuan kerja lain tidak
       boleh ikut terbebani. */
    public function sasaran()     { return $this->belongsTo(Sasaran::class); }
    public function item()        { return $this->hasMany(ItemPermintaan::class); }
}
