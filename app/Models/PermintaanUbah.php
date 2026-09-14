<?php

namespace App\Models;

use App\Enums\JenisPermintaanUbah;
use App\Enums\StatusPermintaanUbah;
use Illuminate\Database\Eloquent\Model;

class PermintaanUbah extends Model
{
    protected $fillable = [
        'rekomendasi_id', 'sasaran_id', 'jenis', 'alasan', 'lampiran_sasaran_id',
        'diajukan_oleh', 'label_pengaju', 'tanggal',
        'status', 'diputus_oleh', 'label_pemutus', 'tgl_putus', 'catatan_putus',
    ];
    protected $casts = [
        'jenis' => JenisPermintaanUbah::class,
        'status' => StatusPermintaanUbah::class,
        'tanggal' => 'date',
        'tgl_putus' => 'date',
    ];

    public function rekomendasi()    { return $this->belongsTo(Rekomendasi::class); }
    public function sasaran()        { return $this->belongsTo(Sasaran::class); }
    public function lampiranSasaran(){ return $this->belongsTo(Lampiran::class, 'lampiran_sasaran_id'); }
}
