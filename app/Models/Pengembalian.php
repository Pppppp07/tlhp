<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Catatan bahwa berkas pernah dikembalikan ke satuan kerja, berikut alasannya.
 * Dicatat tersendiri supaya alasannya terbaca di tempat orang mencarinya, bukan
 * terkubur di satu baris rekam jejak.
 */
class Pengembalian extends Model
{
    protected $table = 'pengembalians';

    protected $fillable = [
        'rekomendasi_id', 'sasaran_id', 'tanggal', 'oleh_id', 'label_oleh', 'alasan',
    ];

    protected $casts = ['tanggal' => 'date'];

    public function rekomendasi() { return $this->belongsTo(Rekomendasi::class); }
    public function sasaran()     { return $this->belongsTo(Sasaran::class); }
    public function oleh()        { return $this->belongsTo(User::class, 'oleh_id'); }
}
