<?php

use App\Enums\JenisReferensi;
use App\Models\Referensi;
use Illuminate\Database\Migrations\Migration;

/**
 * Mengisi daftar sifat rekomendasi untuk basis data yang sudah berjalan.
 *
 * `rekomendasis.sifat_id` sudah lama berdiri, relasinya ada, pengendalinya ikut
 * memuatnya — tapi tidak ada satu pun baris rujukan untuk dipilih. Akibatnya
 * kolom itu kosong pada seluruh 114 rekomendasi, dan isian di formulirnya tidak
 * pernah bisa dibuat.
 *
 * Isinya sama dengan yang dipakai penyemai, supaya basis data lama dan basis
 * data baru berisi daftar yang sama.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'Administratif',
            'Informasi kerugian negara',
        ] as $i => $nama) {
            Referensi::firstOrCreate(
                ['jenis' => JenisReferensi::SIFAT_REKOM->value, 'nama' => $nama],
                ['urutan' => $i + 1, 'perlu_nilai' => $nama === 'Informasi kerugian negara'],
            );
        }
    }

    /**
     * Tidak dibalik. Membuangnya akan mengosongkan `sifat_id` pada rekomendasi
     * yang sudah terlanjur memilihnya.
     */
    public function down(): void {}
};
