<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu perubahan penilaian pada satu baris penugasan: UKI menandai,
 * Inspektorat memutus, atau status SIPTL berubah.
 *
 * Satu putusan bisa menulis beberapa butir sekaligus; `aksi_id` yang
 * menyatukannya kembali jadi satu baris di tabel riwayat. Urutannya urutan
 * pencatatan (`id`), bukan tanggal — dua peristiwa di hari yang sama tetap
 * terbaca berurutan.
 */
class RiwayatStatus extends Model
{
    protected $table = 'riwayat_statuses';

    public const UKI = 'UKI';
    public const ITJEN = 'Itjen';
    public const SIPTL = 'SIPTL';

    protected $fillable = ['sasaran_id', 'sumber', 'dari', 'ke', 'oleh', 'tanggal', 'catatan', 'aksi_id', 'jenis_aksi'];

    protected $casts = ['tanggal' => 'date'];

    public function sasaran() { return $this->belongsTo(Sasaran::class); }
}
