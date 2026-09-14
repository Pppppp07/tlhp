<?php

namespace App\Support;

/**
 * Delapan bentuk tindak lanjut menurut SOP, ditambah satu penampung.
 *
 * Daftar ini satu-satunya sumbernya: dipakai penyemai untuk basis data baru dan
 * migrasi untuk basis data yang sudah berjalan, supaya keduanya tidak pernah
 * berselisih. Namanya sama persis dengan prototipe — nama bentuk ikut tercetak
 * di surat dan dipakai berkoordinasi, jadi dua sebutan untuk hal yang sama
 * berarti dua istilah yang harus dihafal.
 *
 * Tiap bentuk membawa usulan dokumen buktinya. Usulan, bukan paksaan: peminta
 * tetap bisa mengubah, menghapus, atau menambah barisnya. Gunanya menghemat
 * pengetikan dan menyeragamkan penamaan — dokumen yang sama disebut dengan nama
 * yang sama di semua berkas, supaya setahun kemudian masih bisa dicari.
 */
class BentukTindakLanjut
{
    /** @return array<string,list<string>> nama bentuk => usulan dokumennya */
    public static function peta(): array
    {
        return [
            'Penyetoran ke kas negara' => [
                'Bukti setor ke kas negara (SSBP)',
                'Nota Konfirmasi KPPN',
                'Rekapitulasi nilai yang disetor',
            ],
            'Penyerahan barang atau aset kepada negara' => [
                'Berita acara serah terima barang',
                'Kartu inventaris yang sudah diperbarui',
                'Foto barang yang diserahkan',
            ],
            'Pelimpahan kepada aparat penegak hukum' => [
                'Surat pelimpahan kepada aparat penegak hukum',
                'Tanda terima dari instansi penegak hukum',
                'Laporan perkembangan penanganan perkara',
            ],
            'Tindakan administratif atau hukuman disiplin' => [
                'Surat keputusan atau surat teguran bernomor',
                'Tanda terima oleh yang bersangkutan',
                'Bukti pencatatan pada berkas kepegawaian',
            ],
            'Perbaikan sistem pengendalian intern' => [
                'Prosedur atau pedoman hasil revisi',
                'Surat penetapan oleh pejabat berwenang',
                'Bukti sosialisasi kepada pelaksana',
            ],
            'Perbaikan hasil pekerjaan' => [
                'Laporan hasil pelaksanaan perbaikan',
                'Foto dokumentasi 0%, 50%, dan 100%',
                'Back up data kuantitas dan kualitas',
                'Surat pernyataan biaya perbaikan ditanggung penyedia jasa',
            ],
            'Pengenaan sanksi daftar hitam' => [
                'Surat penetapan sanksi daftar hitam',
                'Bukti penayangan pada Daftar Hitam Nasional',
                'Berita acara hasil evaluasi penyedia',
            ],
            'Perbaikan dokumen administrasi' => [
                'Dokumen yang sudah diperbaiki',
                'Surat pengantar penyampaian dokumen',
            ],
            'Lainnya sesuai LHP' => [
                'Dokumen pendukung sesuai bunyi rekomendasi',
            ],
        ];
    }

    /** Nama bentuknya saja, urut sesuai SOP. @return list<string> */
    public static function nama(): array
    {
        return array_keys(self::peta());
    }

    /**
     * Sebutan lama yang pernah dipakai simtlhp, dipetakan ke sebutan SOP.
     *
     * Dipakai migrasi untuk mengganti nama DI TEMPAT, bukan membuat baris baru:
     * seratusan tindakan sudah menunjuk baris-baris itu, dan menggantinya
     * dengan baris baru berarti seluruhnya kehilangan bentuknya.
     *
     * "Pengembangan kompetensi pejabat" tidak punya padanan di SOP. Ia tidak
     * dihapus — sepuluh tindakan memakainya — melainkan dinonaktifkan, jadi
     * berkas lama tetap terbaca sementara berkas baru tidak bisa memilihnya.
     *
     * @return array<string,?string> nama lama => nama SOP, null berarti nonaktif
     */
    public static function petaLama(): array
    {
        return [
            'Penyetoran ke kas negara'        => 'Penyetoran ke kas negara',
            'Perbaikan fisik pekerjaan'       => 'Perbaikan hasil pekerjaan',
            'Penyerahan aset'                 => 'Penyerahan barang atau aset kepada negara',
            'Perbaikan dokumen administrasi'  => 'Perbaikan dokumen administrasi',
            'Perbaikan prosedur atau pedoman' => 'Perbaikan sistem pengendalian intern',
            'Sanksi kepegawaian'              => 'Tindakan administratif atau hukuman disiplin',
            'Pengembangan kompetensi pejabat' => null,
            'Lainnya'                         => 'Lainnya sesuai LHP',
        ];
    }
}
