<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Satker extends Model
{
    use HasFactory;

    protected $fillable = ['kode', 'nama', 'provinsi', 'jenis', 'aktif'];
    protected $casts = ['aktif' => 'boolean'];

    /** Baris penugasan yang jadi tanggungannya. Inilah pintu satu-satunya
        untuk menjawab "apa pekerjaan satuan kerja ini". */
    public function sasaran()
    {
        return $this->hasMany(Sasaran::class);
    }

    /** Temuan yang terjadi di tempatnya — belum tentu jadi pekerjaannya. */
    public function temuan()
    {
        return $this->belongsToMany(Temuan::class, 'temuan_satker');
    }

    public function pengguna()
    {
        return $this->hasMany(User::class);
    }

    /** Nama pendek untuk daftar dan kartu. */
    public function namaPendek(): string
    {
        return str_replace('Balai Pengembangan Kompetensi PU ', 'Balai ', $this->nama);
    }
}
