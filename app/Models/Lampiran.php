<?php

namespace App\Models;

use App\Enums\SebabTarikBerkas;
use Illuminate\Database\Eloquent\Model;

class Lampiran extends Model
{
    protected $fillable = [
        'laporan_id', 'rekomendasi_id', 'sasaran_id', 'jenis_dokumen_id',
        'tautan',
        'nama_asli', 'nama_simpan', 'mime', 'ukuran',
        'diunggah_oleh', 'diunggah_pada',
        'ditarik_pada', 'ditarik_oleh', 'sebab_tarik', 'catatan_tarik',
    ];
    protected $casts = [
        'sebab_tarik' => SebabTarikBerkas::class,
        'diunggah_pada' => 'datetime',
        'ditarik_pada' => 'datetime',
    ];

    public function rekomendasi() { return $this->belongsTo(Rekomendasi::class); }
    /* Berkas milik satu satuan kerja pada tindak lanjutnya sendiri. Kosong
       berarti berkas milik rekomendasi atau laporan seutuhnya — mis. surat
       pemeriksaan aslinya, yang justru tidak boleh dibuka satuan kerja. */
    public function sasaran()     { return $this->belongsTo(Sasaran::class); }
    public function laporan()     { return $this->belongsTo(Laporan::class); }
    public function jenisDokumen(){ return $this->belongsTo(Referensi::class, 'jenis_dokumen_id'); }
    public function butir()       { return $this->belongsToMany(ItemPermintaan::class, 'item_permintaan_lampiran'); }

    public function ditarik(): bool
    {
        return $this->ditarik_pada !== null;
    }

    /**
     * Berkas ini berupa tautan ke arsip, bukan salinan yang diunggah ke sini.
     *
     * Rapat 30 Agustus: berkas tindak lanjut sudah tersimpan di arsip
     * masing-masing satuan kerja, dan mengunggah salinannya ke sistem ini
     * berarti dua tempat menyimpan satu berkas — lalu keduanya bisa berbeda
     * tanpa ada yang tahu mana yang benar.
     */
    public function berupaTautan(): bool
    {
        return filled($this->tautan) && blank($this->nama_simpan);
    }

    /** Ada isinya yang bisa dibuka — entah tautan, entah berkas terunggah. */
    public function adaIsinya(): bool
    {
        return ! $this->ditarik() && (filled($this->tautan) || filled($this->nama_simpan));
    }

    /* Yang dihapus isinya dan namanya. Catatan bahwa berkas pernah ada tetap
       tersimpan — justru itu yang membuat penarikannya bisa
       dipertanggungjawabkan. Nama ikut dihapus karena nama berkas sendiri
       sudah bisa membocorkan isinya. */
    public function tarik(SebabTarikBerkas $sebab, ?User $oleh = null, ?string $catatan = null): void
    {
        $this->forceFill([
            'nama_asli'     => null,
            'nama_simpan'   => null,
            'mime'          => null,
            'ukuran'        => null,
            'ditarik_pada'  => now(),
            'ditarik_oleh'  => $oleh?->id,
            'sebab_tarik'   => $sebab->value,
            // Sebab hanya golongan pilihan; duduk perkaranya ada di sini.
            'catatan_tarik' => $catatan,
        ])->save();

        /* Butir kelengkapan yang bersandar pada berkas ini ikut batal, supaya
           kelengkapan tidak bisa dicurangi dengan mengunggah asal lalu
           menariknya lagi. */
        foreach ($this->butir as $butir) {
            $masihAda = $butir->lampiran()->whereNull('ditarik_pada')->exists();
            if (! $masihAda) {
                $butir->update(['terpenuhi' => false, 'dipenuhi_pada' => null]);
            }
        }
    }

    public function labelTampil(): string
    {
        if ($this->ditarik()) {
            return 'berkas ditarik — isi dan nama dihapus';
        }
        return (string) ($this->nama_asli ?: $this->tautan ?: 'berkas tanpa nama');
    }
}
