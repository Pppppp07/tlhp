<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Dulu: mengisi `sasaran_id` pada catatan tindak lanjut yang sudah terlanjur
 * ada, lewat App\Support\PautkanSasaran.
 *
 * Sekarang tidak mengerjakan apa pun. Penyemai menulis `sasaran_id` sejak awal
 * dan seluruh pengendali menulisnya sendiri, jadi tidak ada lagi catatan tanpa
 * pemilik yang perlu disambungkan — dan pada basis data yang baru, tabelnya
 * memang masih kosong saat migrasi ini jalan.
 *
 * Berkasnya dipertahankan, bukan dihapus: basis data yang sudah berjalan
 * mencatat migrasi ini sebagai sudah dijalankan, dan menghapusnya membuat
 * daftar migrasi keduanya berselisih.
 */
return new class extends Migration
{
    public function up(): void {}

    public function down(): void {}
};
