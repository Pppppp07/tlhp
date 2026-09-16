<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Arsip satu pengiriman ulang untuk pemberkasan ulang.
 *
 * Yang mengirim selalu Setba; `dari` menyebut siapa yang menolak — UKI,
 * Inspektorat, atau BPK. Keadaan yang sedang berlaku ada di baris
 * penugasannya (`sasarans.alasan_perbaikan` dan kawan-kawan) dan dikosongkan
 * saat perbaikannya dikirim; yang di sini tidak pernah dikosongkan.
 */
class Pengembalian extends Model
{
    protected $table = 'pengembalians';

    protected $fillable = [
        'rekomendasi_id', 'sasaran_id', 'tanggal', 'oleh_id', 'label_oleh', 'dari', 'alasan',
        'batas_waktu', 'keterangan_setba', 'dokumen', 'aksi_id',
    ];

    protected $casts = ['tanggal' => 'date', 'batas_waktu' => 'date', 'dokumen' => 'array'];

    public function rekomendasi() { return $this->belongsTo(Rekomendasi::class); }
    public function sasaran()     { return $this->belongsTo(Sasaran::class); }
    public function oleh()        { return $this->belongsTo(User::class, 'oleh_id'); }
}
