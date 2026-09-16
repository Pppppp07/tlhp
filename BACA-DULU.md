# Monitoring Tindak Lanjut Hasil Pemeriksaan (SIMTLHP)

Aplikasi web untuk memantau tindak lanjut temuan LHP (BPK) dan LHA (Inspektorat)
di lingkungan Sekretariat Badan.

Laravel 13 · PHP 8.3 · MySQL 8 (SQLite dipakai saat uji otomatis).

Tampilan dan aturannya mengikuti prototipe (`../Prototipe`). Kalau keduanya
berbeda, prototipe yang benar — dan bedanya dicatat di `../CATATAN-PERUBAHAN.md`.

---

## Cara menjalankan

**Klik dua kali `jalankan.bat`.**

Peramban terbuka sendiri ke <http://localhost:8000> setelah kira-kira tiga detik.
Jendela hitam yang muncul adalah servernya — biarkan terbuka selama dipakai.
Untuk berhenti: tekan `Ctrl+C` di jendela itu, atau tutup saja jendelanya.

Kalau berkasnya mengeluh PHP tidak ditemukan, berarti Laragon atau XAMPP belum
terpasang di komputer itu. Pasang salah satunya, lalu jalankan lagi.

## Akun contoh

Semuanya bersandi **`rahasia123`**. Di halaman masuk ada daftar akun yang tinggal
diklik — surel dan sandinya terisi sendiri.

| Surel | Peran | Yang bisa dilakukan |
|---|---|---|
| `setba@contoh.test` | Sekretariat Badan | melihat semuanya, mencatat laporan baru, meneruskan berkas, mengurus SIPTL |
| `uki@contoh.test` | Unit Kepatuhan Internal | menelaah kecukupan bukti |
| `inspektorat@contoh.test` | Inspektorat | verifikasi akhir |
| `pimpinan@contoh.test` | Pimpinan | hanya melihat; berandanya Ringkasan |
| `admin@contoh.test` | Administrator | sama seperti Setba |

Tiap satuan kerja punya akunnya sendiri, surelnya nama pendeknya:
`medan@contoh.test`, `palembang@contoh.test`, `jakarta@contoh.test`,
`bandung@contoh.test`, `yogyakarta@contoh.test`, `surabaya@contoh.test`,
`banjarmasin@contoh.test`, `makassar@contoh.test`, `jayapura@contoh.test`,
`sekretariat@contoh.test`, `talenta@contoh.test`, `sdackps@contoh.test`,
`bmpipiw@contoh.test`, `manajemen@contoh.test`, `politeknik@contoh.test`,
`penilaian@contoh.test`.

Blok akun contoh di halaman masuk hanya ada pada pemasangan contoh. Sebelum
dipakai sungguhan, hapus bagian itu dari `resources/views/masuk.blade.php`
dan ganti seluruh sandi.

## "Hari ini" pada peragaan

Data contoh disusun untuk **17 Agustus 2026**, sama dengan prototipe. Tanggalnya
dipatok lewat `.env`:

```
SIMTLHP_HARI_INI=2026-08-17
SIMTLHP_DATA_CONTOH=true
```

Tanpa itu, tenggat dan keterlambatan dihitung dari tanggal komputer — dan kedua
artefak yang diperagakan berdampingan akan menyebut keadaan berbeda untuk berkas
yang sama. Kosongkan `SIMTLHP_HARI_INI` untuk pemakaian sungguhan.

## Mengembalikan data contoh

Setelah dicoba-coba, datanya berubah. Untuk mengembalikan ke keadaan semula:
**klik dua kali `atur-ulang.bat`** (sama dengan `php artisan migrate:fresh --seed`).

Untuk mengosongkan berkasnya saja — data master dan akun tetap, seperti `?kosong`
di prototipe:

```bash
php artisan tlhp:kosongkan
```

---

## Alur yang bisa dicoba

Coba runtut supaya kelihatan seluruh jalurnya. Yang bergerak adalah **baris
penugasan** — satu satuan kerja pada satu bentuk tindak lanjut — bukan
rekomendasinya.

1. Masuk sebagai **Balai Wil. I Medan** → keranjang *Perlu saya kerjakan*.
   Buka satu berkas, isi uraiannya, lampirkan tautan bukti untuk tiap dokumen
   yang diminta, tambah baris pemulihan bila ada nilainya.
   **Simpan draf** menyimpan tanpa memindahkan berkas; **Kirim ke Setba** baru
   bisa dipakai kalau dokumennya sudah lengkap.
2. Masuk sebagai **Setba** → berkas tadi muncul di *Perlu saya kerjakan*.
   Teruskan ke UKI dengan surat pengantar (nomor, tanggal, perihal).
3. Masuk sebagai **UKI** → putuskan memadai atau belum.
   - Belum memadai: berkasnya pulang ke **meja pemberkasan ulang Setba**, bukan
     langsung ke satuan kerjanya. Setba yang menyetel dokumen tambahan dan
     mengirimnya ulang.
   - Memadai: berkasnya kembali ke Setba untuk diteruskan ke Inspektorat.
4. Masuk sebagai **Inspektorat** → verifikasi akhir. Memadai berarti tindak
   lanjut itu selesai diperiksa.
5. Kembali sebagai **Setba** → kartu **Urusan SIPTL** pada halaman rincian:
   catat tanggal unggahnya, lalu salin hasil pemantauan BPK (SS atau BS).
   - Tanggal unggah **dikunci** begitu tercatat, dan isiannya cuma muncul saat
     memang sedang tahap itu.
   - BPK menyatakan Belum Sesuai? Kirim ulang ke satuan kerjanya dari kartu yang
     sama, berikut catatan dan dokumen yang diminta.
6. Jalur **LHA** berhenti di langkah 4 — LHA tidak pernah sampai ke BPK.

Draf satuan kerja yang mengendap lebih dari tujuh hari dikirim sendiri oleh
`php artisan tlhp:kirim-draf` (terjadwal tiap hari 00.30), asal kewajibannya
sudah tuntas. Berkas tidak boleh membusuk di satu meja sementara tenggatnya
berjalan.

## Dua sumbu penilaian, satu sumbu posisi

| Sumbu | Isi | Milik siapa |
|---|---|---|
| **Status SIPTL** | BT · BS · SS · TD | BPK, disalin Setba dari SIPTL. Hanya LHP |
| **Hasil verifikasi** | Memadai / Belum memadai (LHA: Sesuai / Belum sesuai) | Inspektorat, lewat suratnya |
| **Posisi berkas** | di meja siapa berkasnya menunggu | perpindahan sehari-hari |

Ketiganya sengaja dipisah. Kenyataan "bagian BPSDM sudah beres tapi SIPTL masih
Belum Sesuai karena unit lain" hanya bisa dicatat kalau sumbunya lebih dari satu.

## Yang tidak disimpan, tapi dihitung

Status laporan, status temuan, nilai temuan, nilai terpulihkan, sisa, dan progres
tidak disimpan sebagai kolom. Semuanya disimpulkan dari barisnya setiap kali
halaman dibuka. Kalau disimpan, cepat atau lambat angkanya akan berbeda dengan
kenyataan.

---

## Susunan berkas

```
app/Enums/        aturan domain: posisi, status, hasil, sumber laporan, peran
app/Models/       Rekomendasi.php memuat sebagian besar hitungannya
app/Aksi/         satu berkas satu perbuatan: kirim, teruskan, putus, SIPTL
app/Support/      Terlihat (hak lihat), Jejak (pencatat), Kabar, PetaData, Tampil
app/Http/         pengendali tiap layar
database/         migrasi, penyemai, dan data contoh (database/data/data-contoh.json)
resources/views/  tampilan Blade
public/css/       simtlhp.css disalin dari prototipe; simtlhp-tambahan.css milik Laravel
public/js/        satu berkas, penambah kenyamanan — bukan penopang
```

Gaya di `public/css/simtlhp.css` diambil dari prototipe lewat
`Prototipe/alat/salin-gaya.mjs`, awalan `.simt` dibuang. Jangan menyuntingnya
tangan: yang perlu diubah sendiri ditulis di `simtlhp-tambahan.css`.

## Menjalankan uji

```bash
php artisan test
```

67 uji: layar tiap peran, hak akses satuan kerja, rantai penuh satu berkas,
Catat laporan baru, Pemberitahuan, Data master, Ringkasan, data contoh, dan
aturan-aturan murni.

## Pindah ke MySQL

Ubah `.env`:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=simtlhp
DB_USERNAME=root
DB_PASSWORD=
```

Buat basis datanya lebih dulu, lalu `php artisan migrate --seed`.
Seluruh migrasi memakai tipe yang aman di MySQL 8 — nilai rupiah disimpan sebagai
`bigInteger` dalam satuan rupiah penuh, bukan `float`.
