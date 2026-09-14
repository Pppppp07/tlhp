<?php

namespace App\Models;

use App\Enums\JenisReferensi;
use Illuminate\Database\Eloquent\Model;

class Referensi extends Model
{
    protected $table = 'referensis';
    protected $fillable = [
        'jenis', 'nama', 'keterangan', 'warna', 'perlu_nilai', 'urutan', 'aktif',
    ];
    protected $casts = [
        'jenis' => JenisReferensi::class,
        'warna' => \App\Enums\WarnaLabel::class,
        'perlu_nilai' => 'boolean',
        'aktif' => 'boolean',
    ];

    /** Warnanya, dengan abu sebagai cadangan bagi yang belum dipilih. */
    public function warnaLabel(): \App\Enums\WarnaLabel
    {
        return $this->warna ?? \App\Enums\WarnaLabel::ABU;
    }

    public function scopeJenis($q, JenisReferensi $j)
    {
        return $q->where('jenis', $j->value)->where('aktif', true)->orderBy('urutan');
    }
}
