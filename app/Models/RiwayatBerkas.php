<?php

namespace App\Models;

use App\Enums\PosisiBerkas;
use App\Enums\StatusTindakLanjut;
use Illuminate\Database\Eloquent\Model;

/** Hanya bertambah. Aplikasi tidak menyediakan jalur ubah maupun hapus,
    untuk siapa pun. */
class RiwayatBerkas extends Model
{
    protected $table = 'riwayat_berkas';
    protected $fillable = [
        'rekomendasi_id', 'sasaran_id', 'waktu', 'aktor_id', 'label_aktor', 'aksi',
        'posisi_dari', 'posisi_ke', 'status_dari', 'status_ke',
    ];
    protected $casts = [
        'waktu' => 'datetime',
        'posisi_dari' => PosisiBerkas::class,
        'posisi_ke' => PosisiBerkas::class,
        'status_dari' => StatusTindakLanjut::class,
        'status_ke' => StatusTindakLanjut::class,
    ];

    public function rekomendasi() { return $this->belongsTo(Rekomendasi::class); }

    /* Boleh kosong, dan kosongnya berarti: gerak tingkat 2 memang tidak punya
       satuan kerja — yang berpindah adalah rekomendasinya sendiri. */
    public function sasaran()     { return $this->belongsTo(Sasaran::class); }
}
