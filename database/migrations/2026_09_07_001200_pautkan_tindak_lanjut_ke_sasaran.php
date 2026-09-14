<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Mengisi `sasaran_id` pada catatan tindak lanjut yang sudah terlanjur ada.
 *
 * Kolomnya ditambahkan migrasi 000600 tapi tidak pernah diisi. Selama halaman
 * menampilkan catatan sebagai satu daftar panjang per rekomendasi, itu tidak
 * kelihatan; begitu rinciannya pindah ke dalam baris tiap satuan kerja,
 * catatan tanpa pemilik jadi tidak punya tempat berdiri.
 *
 * Aturannya ada di App\Support\PautkanSasaran, dipakai bersama penyemai supaya
 * data lama dan data baru disambungkan dengan cara yang sama persis.
 */
return new class extends Migration
{
    public function up(): void
    {
        $hasil = \App\Support\PautkanSasaran::jalankan();
        foreach ($hasil as $tabel => $n) {
            echo "  {$tabel}: {$n} baris disambungkan ke sasaran\n";
        }
    }

    /**
     * Tidak dibalik. Mengosongkan kembali `sasaran_id` akan menghapus juga
     * sambungan yang memang sudah benar sejak awal, dan tidak ada catatan mana
     * yang mana.
     */
    public function down(): void {}
};
