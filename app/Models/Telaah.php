<?php

namespace App\Models;

use App\Enums\HasilTelaah;
use Illuminate\Database\Eloquent\Model;

/**
 * Kesimpulan telaah UKI atau verifikasi Inspektorat atas berkas SATU satuan
 * kerja.
 *
 * Gantungannya di sasaran, bukan di rekomendasi. Kata Mbak Puspi di rapat
 * 26 Agustus, "yang akan divalidasi oleh UKI adalah tempatnya Medan" — bukan
 * rekomendasinya seutuhnya.
 *
 * Hasilnya (M/BM) inilah yang mengisi sasarans.hasil. Tidak mengubah status
 * BPK — status itu hanya berubah lewat SIPTL.
 */
class Telaah extends Model
{
    protected $table = 'telaahs';

    protected $fillable = [
        'rekomendasi_id', 'sasaran_id', 'tanggal', 'oleh_id', 'label_oleh',
        'hasil', 'nomor_surat', 'tgl_surat', 'perihal', 'catatan', 'lampiran_id',
    ];

    protected $casts = [
        'tanggal'   => 'date',
        'tgl_surat' => 'date',
        'hasil'     => HasilTelaah::class,
    ];

    public function rekomendasi() { return $this->belongsTo(Rekomendasi::class); }
    public function sasaran()     { return $this->belongsTo(Sasaran::class); }
    public function oleh()        { return $this->belongsTo(User::class, 'oleh_id'); }
    public function lampiran()    { return $this->belongsTo(Lampiran::class); }

    /** Telaah yang sudah berbunyi. Yang belum punya hasil adalah catatan
        kerja, bukan kesimpulan — dan tidak boleh dibaca sebagai putusan. */
    public function scopeBerbunyi($q) { return $q->whereNotNull('hasil'); }

    public function bersurat(): bool
    {
        return filled($this->nomor_surat);
    }
}
