<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Bentuk tindak lanjut yang dituntut sebuah rekomendasi.
 *
 * Satu rekomendasi bisa menuntut lebih dari satu bentuk — menyetor ke kas
 * negara dan sekaligus memperbaiki dokumen administrasinya — dan tiap bentuk
 * bisa dibebankan ke satuan kerja yang berbeda.
 */
class Tindakan extends Model
{
    protected $fillable = [
        'rekomendasi_id', 'bentuk_id', 'urutan',
        'tgl_renaksi', 'target_selesai', 'catatan',
    ];

    protected $casts = [
        'tgl_renaksi'    => 'date',
        'target_selesai' => 'date',
    ];

    public function rekomendasi() { return $this->belongsTo(Rekomendasi::class); }
    public function bentuk()      { return $this->belongsTo(Referensi::class, 'bentuk_id'); }
    public function sasaran()     { return $this->hasMany(Sasaran::class)->orderBy('id'); }

    /**
     * Rencana aksi yang berlaku untuk bentuk tindak lanjut ini.
     *
     * Kosong berarti ikut tanggal rekomendasinya — dasar hukumnya memang
     * melekat di sana, dihitung dari tanggal laporan diterima.
     */
    public function renaksi(): ?\Carbon\CarbonInterface
    {
        return $this->tgl_renaksi ?? $this->rekomendasi?->tenggat_jawab;
    }

    /** Nama bentuknya, atau sebutan baku kalau belum diisi. */
    public function namaBentuk(): string
    {
        return $this->bentuk?->nama ?? 'Tindak lanjut';
    }

    public function totalNilai(): int
    {
        return (int) $this->sasaran->sum('nilai');
    }
}
