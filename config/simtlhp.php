<?php

return [

    /*
     * "Hari ini" untuk peragaan.
     *
     * Data contoh disusun untuk 17 Agustus 2026, sama dengan `HARI_INI` di
     * prototipe. Tanpa ini tenggat, keterlambatan, dan kabar "baru" dihitung
     * dari tanggal komputer — dan kedua artefak yang diperagakan berdampingan
     * menyebut keadaan berbeda untuk berkas yang sama.
     *
     * Kosongkan untuk pemakaian sungguhan. Jamnya tetap jam komputer; yang
     * dipatok hanya tanggalnya.
     */
    'hari_ini' => env('SIMTLHP_HARI_INI'),

    /* Semai data contoh bersama data master. `false` = padanan `?kosong`. */
    'data_contoh' => (bool) env('SIMTLHP_DATA_CONTOH', true),

    /* Draf tanggapan yang mengendap sekian hari dikirim sendiri bila
       kewajibannya sudah terpenuhi. */
    'hari_endap_draf' => 7,

];
