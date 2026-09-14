<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Apa yang sudah dikerjakan satuan kerja atas bagiannya.
 *
 * Dulu bernama TindakLanjut dan menggantung pada rekomendasi. Sekarang
 * menggantung pada sasaran: yang menjawab adalah satu satuan kerja, dan
 * jawaban Balai Medan tidak boleh tercampur dengan jawaban Politeknik PU
 * hanya karena rekomendasinya sama.
 *
 * Rekam jejak - hanya bertambah, tidak pernah menimpa yang sebelumnya.
 */
class Tanggapan extends Model
{
    protected $table = 'tindak_lanjuts';

    protected $fillable = [
        'rekomendasi_id', 'sasaran_id', 'tanggal', 'uraian',
        'dicatat_oleh', 'label_pencatat',
    ];

    protected $casts = ['tanggal' => 'date'];

    public function rekomendasi() { return $this->belongsTo(Rekomendasi::class); }
    public function sasaran()     { return $this->belongsTo(Sasaran::class); }
    public function pencatat()    { return $this->belongsTo(User::class, 'dicatat_oleh'); }
}
