<?php

namespace App\Models;

use App\Enums\SumberLaporan;
use Illuminate\Database\Eloquent\Model;

class KategoriTemuan extends Model
{
    protected $fillable = ['sumber', 'nama', 'urutan', 'aktif'];
    protected $casts = ['sumber' => SumberLaporan::class, 'aktif' => 'boolean'];

    /* Pilihannya bertingkat: isi kategori mengikuti sumber laporan. */
    public function scopeUntukSumber($q, SumberLaporan $s)
    {
        return $q->where('sumber', $s->value)->where('aktif', true)->orderBy('urutan');
    }
}
