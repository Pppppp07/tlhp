<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Isian formulir Catat laporan baru yang belum diajukan, satu per pengguna. */
class DrafLaporan extends Model
{
    protected $fillable = ['user_id', 'isian'];

    protected $casts = ['isian' => 'array'];

    public function pengguna() { return $this->belongsTo(User::class, 'user_id'); }
}
