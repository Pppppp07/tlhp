<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DataMasterSeeder::class,
            ContohLaporanSeeder::class,
            // Dua laporan buatan tangan di atas memperagakan skenario tertentu
            // dengan teliti; yang ini menambah banyaknya sampai skala uji beban.
            BanyakLaporanSeeder::class,
        ]);

        /* Catatan tindak lanjut dibuat menggantung pada rekomendasinya saja.
           Halaman rincian menampilkannya di dalam baris tiap satuan kerja,
           jadi tiap catatan perlu tahu barisnya. Disambungkan di sini, dengan
           aturan yang sama persis yang dipakai migrasi 001200 untuk data
           lama — satu tempat, satu aturan. */
        \App\Support\PautkanSasaran::jalankan();
    }
}
