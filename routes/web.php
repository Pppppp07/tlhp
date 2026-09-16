<?php

use App\Http\Controllers\BerkasController;
use App\Http\Controllers\CariController;
use App\Http\Controllers\DataMasterController;
use App\Http\Controllers\KabarController;
use App\Http\Controllers\LaporanBaruController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\MasukController;
use App\Http\Controllers\RekomendasiController;
use App\Http\Controllers\RingkasanController;
use App\Http\Controllers\SasaranController;
use App\Http\Controllers\SiptlController;
use App\Http\Controllers\TanggapanController;
use Illuminate\Support\Facades\Route;

Route::get('/masuk', [MasukController::class, 'form'])->name('masuk')->middleware('guest');
Route::post('/masuk', [MasukController::class, 'masuk'])->middleware('guest');
Route::post('/keluar', [MasukController::class, 'keluar'])->name('keluar');

Route::middleware('auth')->group(function () {
    /* Layar awal mengikuti perannya, sama seperti prototipe: Pimpinan
       mendarat di Ringkasan (berandanya), yang lain di Rekomendasi. */
    Route::get('/', [RekomendasiController::class, 'beranda'])->name('beranda');

    Route::get('/rekomendasi', [RekomendasiController::class, 'index'])->name('rekomendasi.index');
    Route::get('/rekomendasi/{rekomendasi}', [RekomendasiController::class, 'show'])->name('rekomendasi.show');

    /* Semua gerak berkas bekerja pada SASARAN — satu satuan kerja pada satu
       bentuk tindak lanjut. Rekomendasi tidak menempuh proses apa pun. */
    Route::post('/sasaran/{sasaran}/tanggapan', [TanggapanController::class, 'simpan'])->name('tanggapan.simpan');
    Route::post('/sasaran/{sasaran}/teruskan', [SasaranController::class, 'teruskan'])->name('sasaran.teruskan');
    Route::post('/sasaran/{sasaran}/putus', [SasaranController::class, 'putus'])->name('sasaran.putus');
    Route::post('/sasaran/{sasaran}/kirim-ulang', [SasaranController::class, 'kirimUlang'])->name('sasaran.kirimUlang');

    /* Urusan SIPTL milik Setba, per satuan kerja. */
    Route::post('/sasaran/{sasaran}/siptl/unggah', [SiptlController::class, 'unggah'])->name('siptl.unggah');
    Route::post('/sasaran/{sasaran}/siptl/status', [SiptlController::class, 'status'])->name('siptl.status');
    Route::post('/rekomendasi/{rekomendasi}/ulang-bpk', [SiptlController::class, 'ulangBpk'])->name('siptl.ulangBpk');

    /* Ditaruh sebelum rute {laporan}, kalau tidak 'baru' terbaca sebagai
       nomor laporan dan halamannya tidak pernah terbuka. */
    Route::get('/laporan/baru', [LaporanBaruController::class, 'form'])->name('laporan.baru');
    Route::post('/laporan/baru', [LaporanBaruController::class, 'simpan'])->name('laporan.baru.simpan');
    Route::post('/laporan/baru/buang', [LaporanBaruController::class, 'buang'])->name('laporan.baru.buang');

    Route::get('/laporan', [LaporanController::class, 'index'])->name('laporan.index');
    Route::get('/laporan/{laporan}', [LaporanController::class, 'show'])->name('laporan.show');

    Route::get('/kabar', [KabarController::class, 'index'])->name('kabar');
    Route::post('/kabar/semua', [KabarController::class, 'tandaiSemua'])->name('kabar.semua');
    Route::get('/kabar/{notifikasi}', [KabarController::class, 'buka'])->name('kabar.buka');

    Route::get('/ringkasan', RingkasanController::class)->name('ringkasan');

    /* Pencarian di batang atas: rekomendasi dan laporan yang boleh dilihat. */
    Route::get('/cari', CariController::class)->name('cari');

    Route::get('/data-master', [DataMasterController::class, 'index'])->name('master');
    Route::post('/data-master/kategori', [DataMasterController::class, 'tambah'])->name('master.tambah');
    Route::post('/data-master/kategori/{referensi}', [DataMasterController::class, 'simpan'])->name('master.simpan');
    Route::post('/data-master/kategori/{referensi}/saklar', [DataMasterController::class, 'saklar'])->name('master.saklar');
    Route::post('/data-master/temuan', [DataMasterController::class, 'tambahTemuan'])->name('master.temuan.tambah');
    Route::post('/data-master/temuan/{kategori}', [DataMasterController::class, 'simpanTemuan'])->name('master.temuan.simpan');
    Route::post('/data-master/temuan/{kategori}/saklar', [DataMasterController::class, 'saklarTemuan'])->name('master.temuan.saklar');

    Route::get('/berkas/{lampiran}', [BerkasController::class, 'show'])->name('berkas.show');
});
