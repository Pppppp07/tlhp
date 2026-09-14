<?php

namespace App\Enums;

/* Daftar pilihan yang bisa berubah mengikuti peraturan disimpan sebagai data
   master, bukan ditanam di kode. */
enum JenisReferensi: string
{
    case BENTUK_TL       = 'bentuk_tl';
    case KATEGORI_INTERN = 'kategori_intern';
    case JENIS_DOKUMEN   = 'jenis_dokumen';
    case ALASAN_TD       = 'alasan_td';
    /* Sifat rekomendasi menurut suratnya: administratif, atau menyangkut
       kerugian negara. Menentukan apakah rekomendasinya menuntut penyetoran. */
    case SIFAT_REKOM     = 'sifat_rekom';
}
