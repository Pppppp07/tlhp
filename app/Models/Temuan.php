<?php

namespace App\Models;

use App\Enums\StatusTindakLanjut;
use Illuminate\Database\Eloquent\Model;

class Temuan extends Model
{
    protected $fillable = [
        'laporan_id', 'kode', 'nomor_pada_surat', 'judul',
        'sebab', 'akibat',
        'kategori_temuan_id', 'kategori_intern_id', 'nilai',
    ];

    public function laporan()        { return $this->belongsTo(Laporan::class); }
    /* Satuan kerja tempat temuannya terjadi — bukan yang menanggung
       perbaikannya. Yang menanggung ada di sasaran tiap rekomendasi, dan
       keduanya memang bisa berbeda.

       Jamak, bukan tunggal: satu temuan lazim mengenai beberapa satuan kerja
       sekaligus. Urutannya urutan pencatatan, sama dengan di suratnya. */
    public function satkers()        { return $this->belongsToMany(Satker::class, 'temuan_satker')->orderBy('temuan_satker.id'); }

    /** Apakah temuan ini terjadi di satuan kerja tertentu. */
    public function mengenai(?int $satkerId): bool
    {
        return $satkerId !== null && $this->satkers->contains('id', $satkerId);
    }

    public function rekomendasi()    { return $this->hasMany(Rekomendasi::class)->orderBy('nomor_urut')->orderBy('id'); }
    public function kategori()       { return $this->belongsTo(KategoriTemuan::class, 'kategori_temuan_id'); }
    public function kategoriIntern() { return $this->belongsTo(Referensi::class, 'kategori_intern_id'); }

    /**
     * Nilai temuan: yang tertulis di suratnya, atau — kalau kosong — jumlah
     * tagihan rekomendasinya. Isian nilai temuan sudah dihapus dari formulir,
     * jadi membaca medannya mentah membuat rekap berbunyi Rp 0.
     */
    public function nilaiTemuan(): int
    {
        return (int) $this->nilai ?: (int) $this->rekomendasi->sum('nilai_pulih');
    }

    /* Sama seperti laporan — disimpulkan, tidak disimpan. */
    public function statusSimpulan(): StatusTindakLanjut
    {
        return Rekomendasi::simpulkan($this->rekomendasi);
    }
}
