<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notifikasi extends Model
{
    protected $table = 'notifikasis';
    protected $fillable = [
        'rekomendasi_id', 'waktu', 'label_pelaku', 'aksi', 'untuk_peran', 'satker_id', 'blok',
    ];
    protected $casts = ['waktu' => 'datetime', 'untuk_peran' => 'array'];

    public function rekomendasi() { return $this->belongsTo(Rekomendasi::class); }
    public function satker()      { return $this->belongsTo(Satker::class); }
    public function dibaca()      { return $this->belongsToMany(User::class, 'notifikasi_bacas')->withPivot('dibaca_pada'); }
}
