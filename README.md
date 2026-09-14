# SIMTLHP - Monitoring Tindak Lanjut Hasil Pemeriksaan

Aplikasi web untuk memantau dan mengelola tindak lanjut temuan LHA (Inspektorat) dan LHP (BPK) di lingkungan Sekretariat Badan.

Dibangun menggunakan **Laravel** &middot; **PHP 8.3+** &middot; Mendukung **SQLite** (pengujian/lokal) dan **MySQL 8** (lingkungan produksi).

---

## Fitur Utama

- **Tiga Sumbu Terpisah**:
  - **Status Resmi**: BT (Belum Tindak Lanjut), SS (Sesuai), BS (Belum Sesuai), TD (Tidak Dapat Ditindaklanjuti) &mdash; hanya berubah melalui surat resmi bernomor dari Inspektorat.
  - **Posisi Berkas**: Mengetahui dengan transparan berkas sedang berada di meja siapa (Satker, Setba, UKI, Inspektorat).
  - **Progres Riil**: Perhitungan dinamis jumlah dokumen terkumpul dan nominal pengembalian/pemulihan keuangan yang telah disetor.
- **Multi-Peran (RBAC)**:
  - Satuan Kerja (Satker)
  - Sekretariat Badan (Setba)
  - Unit Kepatuhan Intern (UKI)
  - Inspektorat Jenderal
  - Pimpinan (Monitoring Eksekutif)
- **Otomatisasi Penilaian & Integrasi SIPTL**: Pencatatan riwayat berkas, validasi dokumen, dan monitoring pelaporan ke BPK.

---

## Cara Menjalankan

### 1. Cara Cepat (Windows)
Cukup klik dua kali berkas:
- **`jalankan.bat`**: Menjalankan server lokal dan otomatis membuka browser ke `http://localhost:8000`.
- **`atur-ulang.bat`**: Mengembalikan basis data ke contoh awal untuk simulasi ulang.

### 2. Cara Manual

1. Salin berkas lingkungan dan pasang dependensi:
   ```bash
   cp .env.example .env
   composer install
   npm install
   ```

2. Generate application key:
   ```bash
   php artisan key:generate
   ```

3. Jalankan migrasi dan seeder awal:
   ```bash
   # Untuk SQLite (default lokal):
   php artisan migrate --seed

   # Atau migrasi ulang bersih:
   php artisan migrate:fresh --seed
   ```

4. Jalankan server lokal:
   ```bash
   php artisan serve
   ```
   Buka di peramban: `http://127.0.0.1:8000`

---

## Akun Pengujian (Demo)

Semua akun pengujian menggunakan kata sandi bawaan: **`rahasia123`**

| Surel | Peran | Hak Akses Utama |
|---|---|---|
| `setba@contoh.test` | Sekretariat Badan | Memantau seluruh proses, meneruskan, dan mencatat keputusan surat |
| `bandung@contoh.test` | Balai Bandung (Satker) | Mengisi tindak lanjut dan mengunggah dokumen satker |
| `makassar@contoh.test` | Balai Makassar (Satker) | Mengisi tindak lanjut satker Makassar |
| `uki@contoh.test` | Unit Kepatuhan Intern | Menelaah kecukupan bukti dan rekomendasi tindak lanjut |
| `inspektorat@contoh.test` | Inspektorat Jenderal | Melakukan verifikasi akhir hasil pemeriksaan |
| `pimpinan@contoh.test` | Pimpinan | Monitoring eksekutif (hanya melihat) |

> **Perhatian**: Akun pengujian di atas hanya ditujukan untuk keperluan simulasi lokal/demo. Hapus atau sesuaikan akun contoh sebelum implementasi produksi.

---

## Menjalankan Pengujian (Testing)

Proyek ini telah dilengkapi dengan rangkaian automated test menggunakan PHPUnit:

```bash
php artisan test
```

---

## Dokumentasi Tambahan

- [Panduan Penggunaan Cepat](BACA-DULU.md)
- [Panduan Menjalankan Manual](CARA-MENJALANKAN-MANUAL.md)

