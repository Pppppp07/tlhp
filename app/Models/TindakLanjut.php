<?php

namespace App\Models;

/**
 * Nama lama Tanggapan.
 *
 * Dipertahankan supaya kode dan uji lama tidak patah seketika. Yang ditulis
 * baru memakai Tanggapan - namanya menyebut isinya (jawaban satuan kerja),
 * bukan seluruh urusan tindak lanjut yang sekarang tersebar di banyak tabel.
 *
 * @deprecated pakai Tanggapan
 */
class TindakLanjut extends Tanggapan
{
    protected $table = 'tindak_lanjuts';
}
