<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Suratnya sendiri. Satu surat bernomor dapat memuat keputusan untuk beberapa
 * rekomendasi sekaligus — itu kenyataan kertasnya.
 *
 * Dua jenis surat memakai tabel ini, dan cakupannya berbeda:
 *
 *   LHV  Laporan Hasil Validasi, dari UKI, atas berkas satu satuan kerja
 *   CHV  Catatan Hasil Verifikasi, dari Inspektorat, memutus SELURUH
 *        rekomendasi sekaligus
 *
 * Menyamakan keduanya membuat satu satuan kerja yang belum beres bisa
 * "menuntaskan" rekomendasi yang dipikul lima satuan kerja.
 */
class Verifikasi extends Model
{
    public const LHV = 'LHV';
    public const CHV = 'CHV';

    protected $fillable = [
        'jenis', 'periode', 'nomor_surat', 'tgl_surat', 'pejabat',
        'lampiran_id', 'dicatat_oleh',
    ];
    protected $casts = ['tgl_surat' => 'date'];

    public function scopeChv($q) { return $q->where('jenis', self::CHV); }
    public function scopeLhv($q) { return $q->where('jenis', self::LHV); }

    public function namaJenis(): string
    {
        return $this->jenis === self::LHV
            ? 'Laporan Hasil Validasi'
            : 'Catatan Hasil Verifikasi';
    }

    public function keputusan() { return $this->hasMany(KeputusanVerifikasi::class); }
    public function lampiran()  { return $this->belongsTo(Lampiran::class); }
    public function pencatat()  { return $this->belongsTo(User::class, 'dicatat_oleh'); }
}
