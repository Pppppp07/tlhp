<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DataMasterSeeder::class);

        /* Padanan `?kosong` di prototipe: sistem tanpa data contoh, dengan
           data master dan akun yang tetap ada. Pasang
           SIMTLHP_DATA_CONTOH=false sebelum menyemai. */
        if (config('simtlhp.data_contoh')) {
            $this->call(DataContohSeeder::class);

            /* Prototipe menjalankan penyapu kiriman otomatis sekali saat
               dibuka. Tanpa ini, data contoh yang baru disemai berbeda satu
               berkas dan satu kabar dari prototipenya. */
            \Illuminate\Support\Facades\Artisan::call('tlhp:kirim-draf');
        }
    }
}
