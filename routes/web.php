<?php

use App\Http\Controllers\BerkasController;
use App\Http\Controllers\KabarController;
use App\Http\Controllers\LaporanBaruController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\MasukController;
use App\Http\Controllers\RekomendasiController;
use App\Http\Controllers\RiwayatController;
use App\Http\Controllers\SasaranController;
use App\Http\Controllers\DataMasterController;
use App\Http\Controllers\RingkasanController;
use App\Http\Controllers\SuratController;
use App\Http\Controllers\TanggapanController;
use Illuminate\Support\Facades\Route;

Route::get('/masuk', [MasukController::class, 'form'])->name('masuk')->middleware('guest');
Route::post('/masuk', [MasukController::class, 'masuk'])->middleware('guest');
Route::post('/keluar', [MasukController::class, 'keluar'])->name('keluar');

Route::middleware('auth')->group(function () {
    /* Tidak ada layar beranda. Prototipe tidak punya, dan memang tidak
       perlu: daftar rekomendasi sudah menjawab "apa yang harus saya kerjakan"
       lewat keranjangnya sendiri. Layar beranda cuma mengulangnya dengan
       bentuk lain, lalu dua tempat itu perlahan berbeda. */
    Route::redirect('/', '/rekomendasi')->name('beranda');

    Route::get('/rekomendasi', [RekomendasiController::class, 'index'])->name('rekomendasi.index');
    Route::get('/rekomendasi/{rekomendasi}', [RekomendasiController::class, 'show'])->name('rekomendasi.show');
    /* Gerak tingkat 1 bekerja pada SASARAN, bukan rekomendasi. Rekomendasi
       yang dipikul tiga satuan kerja berada di tiga tahap sekaligus; satu rute
       ber-{rekomendasi} akan memindahkan berkas dua satker yang belum selesai. */
    Route::post('/sasaran/{sasaran}/teruskan', [SasaranController::class, 'teruskan'])->name('sasaran.teruskan');
    Route::post('/sasaran/{sasaran}/kembalikan', [SasaranController::class, 'kembalikan'])->name('sasaran.kembalikan');
    Route::post('/sasaran/{sasaran}/telaah', [SasaranController::class, 'telaah'])->name('sasaran.telaah');
    Route::post('/sasaran/{sasaran}/minta-dokumen', [SasaranController::class, 'mintaDokumen'])->name('sasaran.minta');
    Route::post('/sasaran/{sasaran}/tanggapan', [TanggapanController::class, 'simpan'])->name('tanggapan.simpan');

    /* Satu-satunya jalan mengubah kiriman yang sudah lepas dari meja satuan
       kerja. Penjaganya ada di pengendalinya, bukan di sini: siapa boleh
       mengajukan bergantung pada posisi berkasnya, bukan cuma pada perannya. */
    Route::post('/sasaran/{sasaran}/minta-ubah',
        [\App\Http\Controllers\PermintaanUbahController::class, 'ajukan'])->name('ubah.ajukan');
    Route::post('/permintaan-ubah/{permintaan}/putus',
        [\App\Http\Controllers\PermintaanUbahController::class, 'putus'])->name('ubah.putus');

    /* Ditaruh sebelum rute {laporan}, kalau tidak 'baru' terbaca sebagai
       nomor laporan dan halamannya tidak pernah terbuka. */
    Route::get('/laporan/baru', [LaporanBaruController::class, 'form'])->name('laporan.baru');
    Route::post('/laporan/baru/surat', [LaporanBaruController::class, 'surat'])->name('laporan.baru.surat');
    Route::post('/laporan/baru/temuan', [LaporanBaruController::class, 'temuan'])->name('laporan.baru.temuan');
    Route::post('/laporan/baru/langkah/{langkah}', [LaporanBaruController::class, 'keLangkah'])->name('laporan.baru.langkah');
    Route::post('/laporan/baru/ajukan', [LaporanBaruController::class, 'ajukan'])->name('laporan.baru.ajukan');
    Route::post('/laporan/baru/batal', [LaporanBaruController::class, 'batal'])->name('laporan.baru.batal');

    Route::get('/laporan', [LaporanController::class, 'index'])->name('laporan.index');
    Route::get('/laporan/{laporan}', [LaporanController::class, 'show'])->name('laporan.show');

    /* Tidak ada layar "Catat surat CHV" untuk Setba. Peta tindakan
       prototipe menyebutnya apa adanya: yang Setba kerjakan adalah meneruskan
       berkas, mengunggah ke SIPTL, dan menyalin status BPK. Surat CHV terbit
       dari panel verifikasi Inspektorat, dan hanya di sana. */
    Route::post('/rekomendasi/{rekomendasi}/siptl', [SuratController::class, 'siptl'])->name('rekomendasi.siptl');
    Route::post('/rekomendasi/{rekomendasi}/bpk', [SuratController::class, 'bpk'])->name('rekomendasi.bpk');

    /* Seluruh urusan SIPTL di satu layar: mengunggah dan mencatat statusnya
       adalah dua pekerjaan yang selalu berurutan dan selalu borongan. */
    Route::get('/siptl', \App\Http\Controllers\SiptlController::class)->name('siptl');

    /* UKI menerbitkan LHV bernomor. Satu surat memuat beberapa berkas
       sekaligus, sama seperti CHV — bedanya cakupannya: LHV memvalidasi berkas
       tiap satuan kerja, CHV memutus seluruh rekomendasi. */
    Route::get('/validasi', [\App\Http\Controllers\ValidasiController::class, 'form'])->name('validasi.form');
    Route::post('/validasi', [\App\Http\Controllers\ValidasiController::class, 'simpan'])->name('validasi.simpan');

    Route::get('/rekomendasi-saya', [RiwayatController::class, 'saya'])->name('riwayatku');
    Route::get('/sudah-selesai', [RiwayatController::class, 'selesai'])->name('selesai');

    Route::get('/kabar', [KabarController::class, 'index'])->name('kabar');
    Route::post('/kabar/{notifikasi}/buka', [KabarController::class, 'buka'])->name('kabar.buka');
    Route::post('/kabar/semua', [KabarController::class, 'tandaiSemua'])->name('kabar.semua');

    Route::get('/ringkasan', RingkasanController::class)->name('ringkasan');

    /* Data master — daftar pilihan yang boleh berubah mengikuti peraturan.
       Penjaganya di pengendalinya: yang boleh cuma Setba dan Admin, dan yang
       bisa disunting cuma daftar yang memang milik kita sendiri. */
    Route::get('/data-master', [DataMasterController::class, 'index'])->name('master');
    Route::post('/data-master/tambah', [DataMasterController::class, 'tambah'])->name('master.tambah');
    Route::post('/data-master/{referensi}', [DataMasterController::class, 'simpan'])->name('master.simpan');
    Route::post('/data-master/{referensi}/saklar', [DataMasterController::class, 'saklar'])->name('master.saklar');
    Route::get('/berkas/{lampiran}', [BerkasController::class, 'show'])->name('berkas.show');
});
