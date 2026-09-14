<?php

namespace Database\Seeders;

use App\Enums\JenisReferensi;
use App\Enums\PeranPengguna;
use App\Enums\SumberLaporan;
use App\Models\KategoriTemuan;
use App\Models\Referensi;
use App\Models\Satker;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Data master. Semuanya bisa diubah admin lewat aplikasi — tidak ada satu pun
 * daftar pilihan yang ditanam di dalam kode, karena istilah dan klasifikasi
 * dapat berubah mengikuti peraturan.
 */
class DataMasterSeeder extends Seeder
{
    public function run(): void
    {
        /* ---------- satuan kerja ---------- */
        $satker = collect([
            ['SETBA', 'Sekretariat Badan', 'sekretariat'],
            ['PUSBANG', 'Pusat Pengembangan Kompetensi', 'pusat'],
            ['BPK-MDN', 'Balai Pengembangan Kompetensi PU Medan', 'balai'],
            ['BPK-PLG', 'Balai Pengembangan Kompetensi PU Palembang', 'balai'],
            ['BPK-JKT', 'Balai Pengembangan Kompetensi PU Jakarta', 'balai'],
            ['BPK-BDG', 'Balai Pengembangan Kompetensi PU Bandung', 'balai'],
            ['BPK-YOG', 'Balai Pengembangan Kompetensi PU Yogyakarta', 'balai'],
            ['BPK-SBY', 'Balai Pengembangan Kompetensi PU Surabaya', 'balai'],
            ['BPK-BJM', 'Balai Pengembangan Kompetensi PU Banjarmasin', 'balai'],
            ['BPK-MKS', 'Balai Pengembangan Kompetensi PU Makassar', 'balai'],
        ])->mapWithKeys(fn ($s) => [
            $s[0] => Satker::firstOrCreate(
                ['kode' => $s[0]],
                ['nama' => $s[1], 'jenis' => $s[2]],
            ),
        ]);

        /* ---------- kategori temuan ----------
           Pilihannya bertingkat: isi kolom ini ditentukan sumber laporan.
           Yang LHA berupa aspek pemeriksaan, yang LHP berupa komponen laporan
           keuangan — beda jenis, karena itu judul kolomnya harus umum. */
        $kategori = [
            [SumberLaporan::LHA, ['Aspek efisiensi', 'Efektivitas dan efisiensi',
                'Ketaatan dalam peraturan perundangan', 'Risiko fraud']],
            [SumberLaporan::LHP, ['Laporan Realisasi Anggaran', 'Neraca',
                'Laporan Operasional', 'Laporan Perubahan Ekuitas']],
        ];
        foreach ($kategori as [$sumber, $daftar]) {
            foreach ($daftar as $i => $nama) {
                KategoriTemuan::firstOrCreate([
                    'sumber' => $sumber->value, 'nama' => $nama, 'urutan' => $i + 1,
                ]);
            }
        }

        /* ---------- bentuk tindak lanjut ----------
           perlu_nilai menandai bentuk yang lazimnya membawa kewajiban nilai.
           Yang menentukan munculnya panel pemulihan tetap nilai_pulih pada
           rekomendasi, bukan bentuknya — SOP menyebut sisa nilai temuan
           setelah perbaikan fisik pun tetap wajib disetor. */
        /* Daftarnya dibaca dari App\Support\BentukTindakLanjut — satu sumber
           yang dipakai bersama migrasi 000300, supaya basis data lama dan basis
           data baru tidak pernah berisi daftar yang berbeda. Namanya sama
           persis dengan SOP dan dengan prototipe: nama bentuk ikut tercetak di
           surat, jadi dua sebutan untuk hal yang sama berarti dua istilah yang
           harus dihafal.

           Tiga bentuk menuntut nilai: yang menyetor, yang menyerahkan aset, dan
           yang memperbaiki pekerjaan — sisa nilai temuan setelah perbaikan
           fisik pun tetap wajib disetor. */
        $perluNilai = [
            'Penyetoran ke kas negara',
            'Penyerahan barang atau aset kepada negara',
            'Perbaikan hasil pekerjaan',
        ];
        foreach (\App\Support\BentukTindakLanjut::nama() as $i => $nama) {
            Referensi::firstOrCreate([
                'jenis' => JenisReferensi::BENTUK_TL->value,
                'nama' => $nama,
            ], [
                'perlu_nilai' => in_array($nama, $perluNilai, true),
                'urutan' => $i + 1,
            ]);
        }

        /* ---------- kategori internal ----------
           Dipakai untuk grafik sebaran di halaman ringkasan. */
        foreach ([
            'Perjalanan dinas dan penginapan', 'Barang milik negara',
            'Pengadaan barang dan jasa', 'Honorarium dan standar biaya',
            'Penerimaan negara bukan pajak', 'Pertanggungjawaban belanja',
            'Ketidaksesuaian pedoman', 'Lainnya',
        ] as $i => $nama) {
            Referensi::firstOrCreate([
                'jenis' => JenisReferensi::KATEGORI_INTERN->value,
                'nama' => $nama,
                ], ['urutan' => $i + 1,
            ]);
        }

        /* ---------- jenis dokumen ----------
           Tiap berkas wajib diberi jenisnya saat diunggah. Tanpa itu, setelah
           setahun tidak akan ada yang bisa menemukan apa pun. */
        foreach ([
            'Surat laporan pemeriksaan', 'Bukti setor kas negara (SSBP)',
            'Nota Konfirmasi KPPN', 'Berita acara', 'Surat keputusan',
            'Dokumen perbaikan', 'Laporan hasil perbaikan',
            'Surat hasil verifikasi', 'Foto atau dokumentasi', 'Lainnya',
        ] as $i => $nama) {
            Referensi::firstOrCreate([
                'jenis' => JenisReferensi::JENIS_DOKUMEN->value,
                'nama' => $nama,
                ], ['urutan' => $i + 1,
            ]);
        }

        /* ---------- alasan sah untuk status TD ----------
           Daftarnya baku menurut SOP, bukan teks bebas. Alasan sah tidak
           membebaskan kewajiban menindaklanjuti. */
        foreach ([
            'Keadaan kahar',
            'Sakit, dibuktikan surat keterangan dokter',
            'Menjadi tersangka dan ditahan',
            'Menjadi terpidana',
            'Alasan sah lain berdasarkan peraturan perundang-undangan',
        ] as $i => $nama) {
            Referensi::firstOrCreate([
                'jenis' => JenisReferensi::ALASAN_TD->value,
                'nama' => $nama,
                ], ['urutan' => $i + 1,
            ]);
        }

        /* ---------- sifat rekomendasi ----------
           Kolomnya sudah lama berdiri di `rekomendasis.sifat_id` tapi tidak
           pernah punya daftar pilihannya. Dua saja, dan bedanya menentukan:
           yang menyangkut kerugian negara menuntut penyetoran, yang
           administratif cukup dilengkapi dokumen atau diperbaiki
           prosedurnya. */
        foreach ([
            'Administratif',
            'Informasi kerugian negara',
        ] as $i => $nama) {
            Referensi::firstOrCreate([
                'jenis' => JenisReferensi::SIFAT_REKOM->value,
                'nama' => $nama,
                ], ['urutan' => $i + 1,
                'perlu_nilai' => $nama === 'Informasi kerugian negara',
            ]);
        }

        /* ---------- akun contoh ----------
           Ganti seluruh kata sandi ini sebelum aplikasi dipakai sungguhan. */
        $akun = [
            ['Petugas Setba', 'setba@contoh.test', PeranPengguna::SETBA, 'SETBA'],
            ['Petugas Balai Bandung', 'bandung@contoh.test', PeranPengguna::SATKER, 'BPK-BDG'],
            ['Petugas Balai Makassar', 'makassar@contoh.test', PeranPengguna::SATKER, 'BPK-MKS'],
            ['Petugas UKI', 'uki@contoh.test', PeranPengguna::UKI, 'SETBA'],
            ['Petugas Inspektorat', 'inspektorat@contoh.test', PeranPengguna::INSPEKTORAT, null],
            ['Pimpinan', 'pimpinan@contoh.test', PeranPengguna::PIMPINAN, null],
            ['Administrator', 'admin@contoh.test', PeranPengguna::ADMIN, 'SETBA'],
        ];
        foreach ($akun as [$nama, $surel, $peran, $kodeSatker]) {
            /* Kuncinya surel saja. Kata sandinya di-hash ulang tiap
               dipanggil, jadi memasukkannya ke kunci pencarian membuat akun
               kembar tiap kali disemai — dan kata sandi yang sudah diganti
               tidak boleh ditimpa kembali jadi kata sandi contoh. */
            User::firstOrCreate([
                'email' => $surel,
            ], [
                'name' => $nama,
                'password' => Hash::make('rahasia123'),
                'peran' => $peran->value,
                'satker_id' => $kodeSatker ? $satker[$kodeSatker]->id : null,
                'jabatan' => $peran->nama(),
            ]);
        }
    }
}
