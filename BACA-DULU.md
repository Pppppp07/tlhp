# Monitoring Tindak Lanjut Hasil Pemeriksaan (SIMTLHP)

Aplikasi web untuk memantau tindak lanjut temuan LHA (Inspektorat) dan LHP (BPK)
di lingkungan Sekretariat Badan.

Laravel 13 · PHP 8.3 · SQLite untuk contoh, MySQL 8 untuk pemasangan sungguhan.

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
| `setba@contoh.test` | Sekretariat Badan | melihat semuanya, meneruskan dan mengembalikan berkas |
| `bandung@contoh.test` | Balai Bandung | mengisi tindak lanjut untuk rekomendasi satkernya |
| `makassar@contoh.test` | Balai Makassar | sama, untuk satker lain |
| `uki@contoh.test` | Unit Kepatuhan Intern | menelaah kecukupan bukti |
| `inspektorat@contoh.test` | Inspektorat Jenderal | verifikasi akhir |
| `pimpinan@contoh.test` | Pimpinan | hanya melihat |

Blok akun contoh di halaman masuk hanya ada pada pemasangan contoh. Sebelum
dipakai sungguhan, hapus bagian itu dari `resources/views/masuk.blade.php`
dan ganti seluruh sandi.

## Mengembalikan data contoh

Setelah dicoba-coba, datanya berubah. Untuk mengembalikan ke keadaan semula:
**klik dua kali `atur-ulang.bat`.**

---

## Alur yang bisa dicoba

Coba runtut supaya kelihatan seluruh jalurnya:

1. Masuk sebagai **Balai Bandung** → buka `REK-2026-014.1`
   (Menarik kelebihan pembayaran dari 14 pegawai).
   Isi tindak lanjutnya, centang dokumen, tambah satu baris pemulihan.
   Perhatikan: tombol **Kirim ke Setba** baru bisa dipakai kalau dokumen sudah
   lengkap *dan* nilainya sudah lunas. Kalau belum, **Simpan pembaruan** tetap
   menyimpan kemajuannya tanpa memindahkan berkas.
2. Masuk sebagai **Setba** → berkas yang tadi dikirim muncul di
   *Perlu tindakan saya*. Teruskan ke UKI, atau kembalikan dengan alasan.
3. Masuk sebagai **UKI** → telaah, kembalikan hasilnya ke Setba.
4. Masuk sebagai **Inspektorat** → verifikasi.
5. Kembali sebagai **Setba** → menu **Catat surat** di bilah kiri. Isi nomor surat,
   tanggal, pejabat, lalu pilih hasil (SS/BS/TD) untuk tiap rekomendasi yang disebut
   surat itu. **Baru di titik ini status resmi berubah.**
   - Satu surat bisa memuat keputusan untuk beberapa rekomendasi sekaligus — begitulah
     bentuk kertasnya, jadi begitu pula bentuk pencatatannya.
   - Coba pilih SS untuk rekomendasi yang dokumennya belum lengkap. Sistem menolak,
     dan baru mengizinkan setelah pengakuan "kewajibannya belum tuntas" dicentang.
     Sistem tidak menghakimi isi surat — hanya memastikan keadaannya tercatat.
6. Jalur **LHA** berhenti di situ. Jalur **LHP** belum: berkasnya masih harus diunggah
   ke SIPTL, lalu BPK memberi penilaian akhir. Keduanya dicatat dari halaman rincian
   rekomendasi, sebagai Setba.

Yang perlu diperhatikan di sepanjang jalur itu: **status tidak pernah berubah
karena orang mengeklik tombol.** Status hanya berubah ketika surat bernomor dari
Inspektorat dicatat. Yang berubah karena klik hanyalah *posisi berkas* —
berkasnya ada di meja siapa — dan *progres* — berapa dokumen terkumpul dan
berapa rupiah sudah masuk.

## Tiga sumbu yang sengaja dipisah

| Sumbu | Isi | Berubah karena |
|---|---|---|
| **Status** | BT · SS · BS · TD | hanya surat bernomor dari Inspektorat |
| **Posisi** | berkas ada di meja siapa | perpindahan sehari-hari |
| **Progres** | *n* dari *m* dokumen, Rp *x* dari Rp *y* | dihitung, tidak pernah diketik |

Memisahkan ketiganya membuat kenyataan "sudah 80% selesai tapi status resminya
masih BT" bisa dicatat apa adanya — hal yang tidak bisa dilakukan kalau hanya
ada satu kolom status.

## Yang tidak disimpan, tapi dihitung

Status laporan, status temuan, nilai terpulihkan, sisa, dan progres tidak
disimpan sebagai kolom. Semuanya disimpulkan dari rekomendasi di bawahnya setiap
kali halaman dibuka. Kalau disimpan, cepat atau lambat angkanya akan berbeda
dengan kenyataan.

---

## Susunan berkas

```
app/Enums/        9 enum — aturan domain ada di sini, bukan tersebar di controller
app/Models/       17 model — Rekomendasi.php memuat sebagian besar aturannya
app/Http/         8 controller — SuratController.php satu-satunya yang mengubah status
app/Support/      pembantu tampilan (rupiah, tanggal, rel posisi)
database/         19 migrasi + 2 seeder
resources/views/  tampilan Blade
public/css/       satu berkas gaya, palet mengikuti prototipe
```

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
Seluruh migrasi sudah memakai tipe yang aman di MySQL 8 — nilai rupiah disimpan
sebagai `bigInteger` dalam satuan rupiah penuh, bukan `float`.
