<?php

namespace Database\Seeders;

use App\Enums\JenisPemulihan;
use App\Enums\HasilTelaah;
use App\Enums\JenisReferensi;
use App\Enums\PeranPengguna;
use App\Enums\PosisiBerkas;
use App\Enums\StatusTindakLanjut;
use App\Enums\SumberLaporan;
use App\Models\KategoriTemuan;
use App\Models\KeputusanVerifikasi;
use App\Models\Laporan;
use App\Models\Lampiran;
use App\Models\Pemulihan;
use App\Models\PermintaanDokumen;
use App\Models\Referensi;
use App\Models\Rekomendasi;
use App\Models\RiwayatBerkas;
use App\Models\Satker;
use App\Models\Temuan;
use App\Models\TindakLanjut;
use App\Models\User;
use App\Models\Verifikasi;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Data contoh, disusun supaya setiap posisi dan setiap status ada wakilnya.
 * Isinya sama dengan prototipe agar keduanya bisa dibandingkan berdampingan.
 */
class ContohLaporanSeeder extends Seeder
{
    private $satker;
    private $bentuk;
    private $kategoriIntern;
    private $jenisDok;
    private $sifat;
    private $setba;

    public function run(): void
    {
        $this->satker = Satker::pluck('id', 'kode');
        $this->bentuk = Referensi::jenis(JenisReferensi::BENTUK_TL)->pluck('id', 'nama');
        $this->kategoriIntern = Referensi::jenis(JenisReferensi::KATEGORI_INTERN)->pluck('id', 'nama');
        $this->jenisDok = Referensi::jenis(JenisReferensi::JENIS_DOKUMEN)->pluck('id', 'nama');
        $this->sifat = Referensi::jenis(JenisReferensi::SIFAT_REKOM)->pluck('id', 'nama');
        $this->setba = User::where('peran', PeranPengguna::SETBA->value)->first();

        $this->laporanLhp();
        $this->laporanLha();

        /* Keadaan yang baru bisa ditangani sistem ini belakangan. Ditaruh
           terpisah, bukan disisipkan ke cerita di atas: dua laporan itu memang
           disusun untuk memperagakan alur baku, dan menempeli keduanya dengan
           setiap perkecualian membuat yang baku ikut sulit dibaca. */
        $this->keadaanKhusus();
    }

    /* ================= LHP dari BPK ================= */
    private function laporanLhp(): void
    {
        $lap = Laporan::create([
            'sumber' => SumberLaporan::LHP->value,
            'nomor' => '117/LHP/XVIII/06/2026',
            'tgl_surat' => '2026-06-10',
            'tgl_terima' => '2026-06-14',
            // Dicatat empat hari setelah suratnya sampai. Jaraknya sengaja tidak
            // nol: tenggat jawaban sudah berjalan sejak surat diterima, dan
            // selisih itu yang dimakan sebelum ada yang bisa mengerjakannya.
            'dicatat_pada' => '2026-06-18',
            'dicatat_oleh' => $this->setba->id,
        ]);
        $this->berkasLaporan($lap, 'LHP-117-2026.pdf', '2026-06-14');

        /* -- temuan 1 -- */
        $t1 = Temuan::create([
            'laporan_id' => $lap->id,
            // Kelebihan bayarnya terjadi di Balai Bandung — merekalah yang
            // diperiksa. Perbaikan prosedurnya nanti jatuh ke Setba, dan itu
            // memang boleh berbeda.
            'kode' => 'TMN-2026-014',
            'nomor_pada_surat' => '3.1.2',
            'judul' => 'Kelebihan pembayaran biaya penginapan perjalanan dinas',
            'sebab' => 'Pejabat pembuat komitmen tidak memverifikasi tarif sebelum menyetujui pembayaran.',
            'akibat' => 'Belanja negara keluar melebihi hak yang seharusnya diterima pegawai.',
            'kategori_temuan_id' => $this->kategori(SumberLaporan::LHP, 'Laporan Realisasi Anggaran'),
            'kategori_intern_id' => $this->kategoriIntern['Perjalanan dinas dan penginapan'],
            'nilai' => 24_750_000,
        ]);
        /* Satu temuan bisa mengenai beberapa satuan kerja; yang di sini
           baru satu, tapi bentuk datanya sudah siap. */
        $t1->satkers()->attach($this->satker['BPK-BDG']);

        /* Masih di satuan kerja: Setba baru meminta dokumen tambahan, dan
           dananya baru terkumpul sebagian. Dipakai memperagakan progres. */
        $r1 = $this->rekomendasi($t1, 1, 'REK-2026-014.1',
            'Menarik kelebihan pembayaran dari 14 pegawai dan menyetorkannya ke kas negara.',
            'Penyetoran ke kas negara', 24_750_000, 'BPK-BDG',
            '2026-08-13', '2027-06-30', StatusTindakLanjut::BT, PosisiBerkas::SATKER,
            'Penyetoran boleh bertahap. Tiap angsuran wajib dilampiri bukti setor dan Nota Konfirmasi KPPN.',
            // Direncanakan tiga kali, tapi tidak dikunci: satuan kerja tetap boleh
            // menyetor sekaligus kalau dananya keburu ada.
            rencanaAngsur: 3, kunciAngsur: false);

        TindakLanjut::create(['rekomendasi_id' => $r1->id, 'sasaran_id' => $this->sasaranPertama($r1)?->id, 'tanggal' => '2026-08-05',
            'label_pencatat' => 'Balai Bandung',
            'uraian' => 'Sepuluh dari empat belas pegawai telah mengembalikan kelebihan. Sisanya menyusul melalui pemotongan tunjangan.']);

        $buktiSetor = $this->berkas($r1, 'setor-angsuran-1.pdf', 'Bukti setor kas negara (SSBP)', '2026-08-05');
        $rekap = $this->berkas($r1, 'rekap-selisih-per-pegawai.xlsx', 'Dokumen perbaikan', '2026-08-05');

        Pemulihan::create([
            'rekomendasi_id' => $r1->id, 'jenis' => JenisPemulihan::SETOR->value,
            'tanggal' => '2026-08-05', 'nilai' => 17_600_000,
            'no_ssbp' => 'SSBP/2026/08/00412', 'ntpn' => 'A1B2C3D4E5F6G7H8',
            'no_nota_kppn' => 'NK-018/KPPN-BDG/2026', 'lampiran_id' => $buktiSetor->id,
        ]);

        $p = PermintaanDokumen::create([
            'rekomendasi_id' => $r1->id, 'peran_peminta' => PeranPengguna::SETBA->value,
            'tanggal' => '2026-08-06',
        ]);
        $butir = [
            ['Rekapitulasi selisih per pegawai', true, $rekap],
            ['Surat pernyataan kesanggupan pengembalian', true, null],
            ['Bukti setor angsuran kedua', false, null],
            ['Nota Konfirmasi KPPN angsuran kedua', false, null],
        ];
        foreach ($butir as [$nama, $penuh, $lamp]) {
            $it = $p->item()->create([
                'nama' => $nama, 'terpenuhi' => $penuh,
                'dipenuhi_pada' => $penuh ? '2026-08-05' : null,
            ]);
            if ($lamp) {
                $it->lampiran()->attach($lamp->id);
            }
        }

        $this->riwayat($r1, [
            ['2026-06-14', 'Setba', 'Rekomendasi dikirim ke Balai Bandung'],
            ['2026-08-05', 'Balai Bandung', 'Mengisi tanggapan, mencatat setoran angsuran pertama'],
            ['2026-08-06', 'Setba', 'Meminta 4 dokumen tambahan — status tidak berubah'],
        ]);

        /* Di UKI. */
        $r2 = $this->rekomendasi($t1, 2, 'REK-2026-014.2',
            'Menerbitkan prosedur verifikasi tarif sebelum persetujuan pembayaran.',
            'Perbaikan sistem pengendalian intern', 0, 'SETBA',
            '2026-08-13', '2026-10-31', StatusTindakLanjut::BT, PosisiBerkas::UKI);
        TindakLanjut::create(['rekomendasi_id' => $r2->id, 'sasaran_id' => $this->sasaranPertama($r2)?->id, 'tanggal' => '2026-07-30',
            'label_pencatat' => 'Sekretariat Badan',
            'uraian' => 'Prosedur verifikasi tarif telah disusun dan ditetapkan melalui surat keputusan sekretaris badan.']);
        $this->berkas($r2, 'SK-prosedur-verifikasi-tarif.pdf', 'Surat keputusan', '2026-07-30');
        $this->riwayat($r2, [
            ['2026-06-14', 'Setba', 'Rekomendasi dikirim ke Sekretariat Badan'],
            ['2026-07-30', 'Sekretariat Badan', 'Mengirim tanggapan dan bukti dukung'],
            ['2026-08-03', 'Setba', 'Berkas lengkap, diteruskan ke UKI'],
        ]);

        /* -- temuan 2 -- */
        $t2 = Temuan::create([
            'laporan_id' => $lap->id,
            // Satuan kerja kedua pada laporan yang sama. Inilah yang tidak bisa
            // diperagakan sebelum satker pindah dari laporan ke temuan.
            'kode' => 'TMN-2026-018',
            'nomor_pada_surat' => '4.1',
            'judul' => 'Kekurangan volume pekerjaan pemeliharaan gedung asrama',
            'sebab' => 'Pengawas lapangan tidak melakukan pengukuran akhir sebelum pembayaran.',
            'akibat' => 'Pembayaran melebihi pekerjaan yang benar-benar dikerjakan.',
            'kategori_temuan_id' => $this->kategori(SumberLaporan::LHP, 'Neraca'),
            'kategori_intern_id' => $this->kategoriIntern['Pengadaan barang dan jasa'],
            'nilai' => 41_300_000,
        ]);
        /* Satu temuan bisa mengenai beberapa satuan kerja; yang di sini
           baru satu, tapi bentuk datanya sudah siap. */
        $t2->satkers()->attach($this->satker['SETBA']);

        /* Dua rekomendasi menunggu surat — dipakai memperagakan satu surat
           yang memuat keputusan untuk beberapa rekomendasi sekaligus. */
        $r3 = $this->rekomendasi($t2, 1, 'REK-2026-018.1',
            'Menagih kekurangan volume kepada penyedia dan menyetorkannya ke kas negara.',
            'Penyetoran ke kas negara', 41_300_000, 'SETBA',
            '2026-08-13', '2026-12-31', StatusTindakLanjut::BT, PosisiBerkas::TUNTAS);
        $b3 = $this->berkas($r3, 'setor-volume.pdf', 'Bukti setor kas negara (SSBP)', '2026-07-09');
        Pemulihan::create([
            'rekomendasi_id' => $r3->id, 'jenis' => JenisPemulihan::SETOR->value,
            'tanggal' => '2026-07-09', 'nilai' => 41_300_000,
            'no_ssbp' => 'SSBP/2026/07/00187', 'ntpn' => 'F9E8D7C6B5A40312',
            'no_nota_kppn' => 'NK-092/KPPN-JKT/2026', 'lampiran_id' => $b3->id,
        ]);
        $this->riwayat($r3, [
            ['2026-06-14', 'Setba', 'Rekomendasi dikirim ke Sekretariat Badan'],
            ['2026-07-09', 'Sekretariat Badan', 'Mencatat setoran lunas'],
            ['2026-07-14', 'Setba', 'Diteruskan ke UKI'],
            ['2026-07-25', 'UKI', 'Telaah selesai, bukti dinilai cukup'],
            ['2026-07-28', 'Setba', 'Diteruskan ke Inspektorat'],
            ['2026-08-10', 'Inspektorat', 'Verifikasi selesai, surat dikirim dalam bentuk kertas'],
        ]);
        $this->telaah($r3, '2026-07-25', 'UKI',
            'Bukti setor dan Nota Konfirmasi KPPN sudah lengkap dan cocok dengan nilai temuan.');
        $this->telaah($r3, '2026-08-10', 'Inspektorat',
            'Seluruh kewajiban terpenuhi. Surat hasil verifikasi diterbitkan pada periode berjalan.');

        $r4 = $this->rekomendasi($t2, 2, 'REK-2026-018.2',
            'Memberikan sanksi kepada pengawas lapangan yang lalai melakukan pengukuran akhir.',
            'Tindakan administratif atau hukuman disiplin', 0, 'BPK-BDG',
            '2026-08-13', '2026-09-30', StatusTindakLanjut::BT, PosisiBerkas::TUNTAS);
        $this->berkas($r4, 'surat-teguran-114.pdf', 'Surat keputusan', '2026-07-15');
        /* Sengaja masih ada dokumen yang belum terpenuhi walau sudah sampai
           tahap menunggu surat — dipakai memperagakan peringatan saat surat
           dicatat menyatakan sesuai padahal kewajiban belum tuntas. */
        $p4 = PermintaanDokumen::create([
            'rekomendasi_id' => $r4->id, 'peran_peminta' => PeranPengguna::UKI->value,
            'tanggal' => '2026-07-24',
        ]);
        $p4->item()->createMany([
            ['nama' => 'Surat teguran bernomor dan bertanda tangan', 'terpenuhi' => true, 'dipenuhi_pada' => '2026-07-15'],
            ['nama' => 'Tanda terima surat oleh yang bersangkutan', 'terpenuhi' => false],
        ]);
        $this->riwayat($r4, [
            ['2026-06-14', 'Setba', 'Rekomendasi dikirim ke Balai Bandung'],
            ['2026-07-15', 'Balai Bandung', 'Mengirim tanggapan dan bukti dukung'],
            ['2026-07-24', 'UKI', 'Meminta 2 dokumen — status tidak berubah'],
            ['2026-08-10', 'Inspektorat', 'Verifikasi selesai, surat dikirim dalam bentuk kertas'],
        ]);

        /* Sudah sesuai, tinggal diunggah ke SIPTL — hanya ada di jalur LHP. */
        $r5 = $this->rekomendasi($t2, 3, 'REK-2026-018.3',
            'Menyempurnakan prosedur pengawasan pekerjaan dengan mewajibkan pengukuran bersama sebelum serah terima.',
            'Perbaikan sistem pengendalian intern', 0, 'SETBA',
            '2026-08-13', '2026-11-30', StatusTindakLanjut::SS, PosisiBerkas::SIPTL);

        /* Sudah diunggah, menunggu penilaian BPK. */
        $r6 = $this->rekomendasi($t2, 4, 'REK-2026-018.4',
            'Mengikutsertakan pengawas lapangan dalam pelatihan pengendalian mutu pekerjaan konstruksi.',
            'Pengenaan sanksi daftar hitam', 0, 'BPK-BDG',
            '2026-08-13', '2026-11-30', StatusTindakLanjut::SS, PosisiBerkas::BPK);
        $r6->update(['siptl_tanggal' => '2026-08-04', 'siptl_tanda_terima' => 'TT/SIPTL/2026/003118']);
        /* Buktinya wajib ada sebelum statusnya bisa jadi sesuai. Tanpa ini
           berkasnya lolos sampai BPK tanpa satu pun lampiran — persis keadaan
           yang sistem ini dibangun untuk mencegahnya. */
        TindakLanjut::create(['rekomendasi_id' => $r6->id, 'sasaran_id' => $this->sasaranPertama($r6)?->id, 'tanggal' => '2026-07-06',
            'label_pencatat' => 'Balai Bandung',
            'uraian' => 'Dua pengawas lapangan diikutsertakan pada pelatihan pengendalian mutu pekerjaan konstruksi angkatan III.']);
        $this->berkas($r6, 'surat-tugas-pelatihan.pdf', 'Surat keputusan', '2026-07-06');
        $this->berkas($r6, 'sertifikat-pelatihan.pdf', 'Laporan hasil perbaikan', '2026-07-20');

        /* Satu surat, dua keputusan — sesuai kenyataan kertasnya. */
        $suratChv = $this->berkas($r5, 'chv-88-2026.pdf', 'Surat hasil verifikasi', '2026-07-28');
        $v = Verifikasi::create([
            'periode' => 'Semester I 2026', 'nomor_surat' => '88/CHV/ITJEN/VII/2026',
            'tgl_surat' => '2026-07-28', 'pejabat' => 'Inspektur II',
            'lampiran_id' => $suratChv->id, 'dicatat_oleh' => $this->setba->id,
        ]);
        foreach ([$r5, $r6] as $r) {
            KeputusanVerifikasi::create([
                'verifikasi_id' => $v->id, 'rekomendasi_id' => $r->id,
                'hasil' => HasilTelaah::M->value,
                'catatan' => 'Tindak lanjut telah sesuai dengan rekomendasi.',
            ]);
            $this->riwayat($r, [
                ['2026-06-14', 'Setba', 'Rekomendasi dikirim ke satuan kerja'],
                ['2026-07-28', 'Setba', 'Mencatat surat 88/CHV/ITJEN/VII/2026 — status menjadi sesuai rekomendasi'],
            ]);
        }
        $this->riwayat($r6, [['2026-08-04', 'Setba', 'Diunggah ke SIPTL, tanda terima TT/SIPTL/2026/003118']]);
    }

    /* ================= LHA dari Inspektorat ================= */
    private function laporanLha(): void
    {
        $lap = Laporan::create([
            'sumber' => SumberLaporan::LHA->value,
            'nomor' => '42/LHA/ITJ/05/2026',
            'tgl_surat' => '2026-05-16',
            'tgl_terima' => '2026-05-20',
            // Dicatat di hari yang sama — jalur LHA lebih pendek dan suratnya
            // datang dari dalam instansi sendiri.
            'dicatat_pada' => '2026-05-20',
            'dicatat_oleh' => $this->setba->id,
        ]);
        $this->berkasLaporan($lap, 'LHA-42-2026.pdf', '2026-05-20');

        $t3 = Temuan::create([
            'laporan_id' => $lap->id,
            'kode' => 'TMN-2026-021',
            'nomor_pada_surat' => '2.4',
            'judul' => 'Barang milik negara berupa laptop tidak ditemukan saat opname fisik',
            'sebab' => 'Serah terima barang antar pegawai tidak dicatat dalam berita acara.',
            'akibat' => 'Nilai aset dalam laporan tidak menggambarkan keadaan sebenarnya.',
            'kategori_temuan_id' => $this->kategori(SumberLaporan::LHA, 'Ketaatan dalam peraturan perundangan'),
            'kategori_intern_id' => $this->kategoriIntern['Barang milik negara'],
            'nilai' => 18_200_000,
        ]);
        /* Satu temuan bisa mengenai beberapa satuan kerja; yang di sini
           baru satu, tapi bentuk datanya sudah siap. */
        $t3->satkers()->attach($this->satker['BPK-MKS']);

        /* Sudah pernah dinilai belum sesuai, dikembalikan dengan tenggat baru.
           Menunjukkan bahwa verifikasi berulang tiap periode. */
        $r7 = $this->rekomendasi($t3, 1, 'REK-2026-021.1',
            'Menelusuri keberadaan barang dan membuat berita acara kehilangan bila tidak ditemukan.',
            'Penyerahan barang atau aset kepada negara', 18_200_000, 'BPK-MKS',
            '2026-09-30', '2026-12-31', StatusTindakLanjut::BS, PosisiBerkas::SATKER,
            'Berita acara harus ditandatangani pengelola BMN dan kepala balai.');
        TindakLanjut::create(['rekomendasi_id' => $r7->id, 'sasaran_id' => $this->sasaranPertama($r7)?->id, 'tanggal' => '2026-06-24',
            'label_pencatat' => 'Balai Makassar',
            'uraian' => 'Penelusuran dilakukan pada gudang dan ruang kerja. Satu unit ditemukan, dua unit belum.']);
        $baPenelusuran = $this->berkas($r7, 'ba-penelusuran.pdf', 'Berita acara', '2026-06-24');
        $suratChv31 = $this->berkas($r7, 'chv-31-2026.pdf', 'Surat hasil verifikasi', '2026-07-15');

        $p7 = PermintaanDokumen::create([
            'rekomendasi_id' => $r7->id, 'peran_peminta' => PeranPengguna::UKI->value,
            'tanggal' => '2026-06-30',
        ]);
        foreach ([
            ['Berita acara penelusuran barang', true, $baPenelusuran],
            ['Berita acara kehilangan dua unit', false, null],
            ['Surat pernyataan tanggung jawab pengguna barang', false, null],
        ] as [$nama, $penuh, $lamp]) {
            $it = $p7->item()->create([
                'nama' => $nama, 'terpenuhi' => $penuh,
                'dipenuhi_pada' => $penuh ? '2026-06-24' : null,
            ]);
            if ($lamp) {
                $it->lampiran()->attach($lamp->id);
            }
        }

        $v31 = Verifikasi::create([
            'periode' => 'Triwulan II 2026', 'nomor_surat' => '31/CHV/ITJEN/VII/2026',
            'tgl_surat' => '2026-07-15', 'pejabat' => 'Inspektur I',
            'lampiran_id' => $suratChv31->id, 'dicatat_oleh' => $this->setba->id,
        ]);
        KeputusanVerifikasi::create([
            'verifikasi_id' => $v31->id, 'rekomendasi_id' => $r7->id,
            'hasil' => HasilTelaah::BM->value, 'tenggat_baru' => '2026-09-30',
            'catatan' => 'Penelusuran baru sebagian. Diberikan tenggat baru.',
        ]);

        /* Rantai lengkap — surat verifikasi tidak muncul begitu saja, berkasnya
           harus lebih dulu melewati telaah UKI dan verifikasi Inspektorat.
           Dua jalan balik yang berbeda terlihat berdampingan di sini. */
        $this->riwayat($r7, [
            ['2026-05-20', 'Setba', 'Rekomendasi dikirim ke Balai Makassar'],
            ['2026-06-24', 'Balai Makassar', 'Mengirim tanggapan dan bukti dukung ke Setba'],
            ['2026-06-26', 'Setba', 'Berkas lengkap secara administrasi, diteruskan ke UKI'],
            ['2026-06-30', 'UKI', 'Telaah: bukti belum cukup, meminta 3 dokumen — status tidak berubah'],
            ['2026-07-08', 'Balai Makassar', 'Memenuhi 1 dari 3 dokumen, mengirim kembali ke Setba'],
            ['2026-07-10', 'UKI', 'Telaah selesai, dikembalikan ke Setba dengan catatan bukti masih kurang'],
            ['2026-07-11', 'Setba', 'Diteruskan ke Inspektorat'],
            ['2026-07-15', 'Inspektorat', 'Menerbitkan surat 31/CHV/ITJEN/VII/2026 — belum sesuai'],
            ['2026-07-15', 'Setba', 'Mencatat surat, status menjadi belum sesuai, berkas kembali dengan tenggat baru'],
        ]);
        /* Dua telaah pada berkas yang sama: yang pertama menolak, yang kedua
           menyerah pada keadaan dan meneruskannya apa adanya. */
        $this->telaah($r7, '2026-06-30', 'UKI',
            'Berita acara penelusuran belum ditandatangani pengelola BMN. Dua unit laptop belum ada keterangan keberadaannya.');
        $this->telaah($r7, '2026-07-10', 'UKI',
            'Satu dari tiga dokumen terpenuhi. Bukti masih kurang, tapi tenggat telaah sudah lewat — diteruskan dengan catatan.');
        $this->pengembalian($r7, '2026-07-15', 'Setba',
            'Surat 31/CHV/ITJEN/VII/2026 menyatakan belum sesuai. Berita acara kehilangan dua unit laptop belum ada, dan tenggat barunya 30 September 2026.');

        /* Di Inspektorat. */
        $r8 = $this->rekomendasi($t3, 2, 'REK-2026-021.2',
            'Menerapkan berita acara serah terima untuk setiap perpindahan barang antar pegawai.',
            'Perbaikan dokumen administrasi', 0, 'BPK-MKS',
            '2026-07-01', '2026-08-31', StatusTindakLanjut::BT, PosisiBerkas::INSPEKTORAT);
        $this->berkas($r8, 'format-bast-bmn.pdf', 'Dokumen perbaikan', '2026-06-24');
        $this->riwayat($r8, [
            ['2026-05-20', 'Setba', 'Rekomendasi dikirim ke Balai Makassar'],
            ['2026-06-24', 'Balai Makassar', 'Mengirim tanggapan dan bukti dukung'],
            ['2026-06-28', 'Setba', 'Diteruskan ke UKI'],
            ['2026-07-22', 'UKI', 'Telaah selesai, bukti dinilai cukup'],
            ['2026-07-26', 'Setba', 'Diteruskan ke Inspektorat'],
        ]);
        $this->telaah($r8, '2026-07-22', 'UKI',
            'Kartu inventaris sudah diperbarui dan cocok dengan daftar barang. Bukti dinilai cukup.');

        /* -- temuan 4 -- */
        $t4 = Temuan::create([
            'laporan_id' => $lap->id,
            'kode' => 'TMN-2026-008',
            'nomor_pada_surat' => '1.3',
            'judul' => 'Pelaksanaan pelatihan tidak sesuai pedoman penyelenggaraan',
            'sebab' => 'Jadwal dipadatkan menyesuaikan ketersediaan narasumber.',
            'akibat' => 'Mutu pelatihan berpotensi tidak memenuhi standar kompetensi.',
            'kategori_temuan_id' => $this->kategori(SumberLaporan::LHA, 'Efektivitas dan efisiensi'),
            'kategori_intern_id' => $this->kategoriIntern['Ketidaksesuaian pedoman'],
            'nilai' => 0,
        ]);
        /* Satu temuan bisa mengenai beberapa satuan kerja; yang di sini
           baru satu, tapi bentuk datanya sudah siap. */
        $t4->satkers()->attach($this->satker['PUSBANG']);

        /* Hasil telaah UKI menunggu diteruskan Setba. */
        $r9 = $this->rekomendasi($t4, 1, 'REK-2026-008.1',
            'Menyusun mekanisme pengendalian jadwal agar jam pelajaran terpenuhi.',
            'Perbaikan sistem pengendalian intern', 0, 'PUSBANG',
            '2026-07-01', '2026-09-30', StatusTindakLanjut::BT, PosisiBerkas::SETBA_TERUSKAN);
        $this->berkas($r9, 'SK-mekanisme-jadwal.pdf', 'Surat keputusan', '2026-06-20');
        $this->riwayat($r9, [
            ['2026-05-20', 'Setba', 'Rekomendasi dikirim ke Pusat Pengembangan Kompetensi'],
            ['2026-06-20', 'Puspengkom', 'Mengirim tanggapan dan bukti dukung'],
            ['2026-06-25', 'Setba', 'Diteruskan ke UKI'],
            ['2026-08-12', 'UKI', 'Telaah selesai, bukti dinilai cukup'],  // dicatat di bawah
        ]);

        /* Tidak dapat ditindaklanjuti dengan alasan sah dari daftar baku. */
        $alasan = Referensi::jenis(JenisReferensi::ALASAN_TD)
            ->where('nama', 'Alasan sah lain berdasarkan peraturan perundang-undangan')->first();
        $r10 = $this->rekomendasi($t4, 2, 'REK-2026-008.2',
            'Mengusulkan penambahan pagu honorarium narasumber agar penjadwalan tidak dipadatkan.',
            'Lainnya sesuai LHP', 0, 'SETBA',
            '2026-07-01', null, StatusTindakLanjut::TD, PosisiBerkas::SELESAI);
        $r10->update([
            'alasan_td_id' => $alasan->id,
            'catatan_td' => 'Penetapan pagu berada pada kewenangan Kementerian Keuangan, bukan pada instansi.',
        ]);
        $this->berkas($r10, 'nota-dinas-usulan-pagu.pdf', 'Dokumen perbaikan', '2026-06-18');
        KeputusanVerifikasi::create([
            'verifikasi_id' => $v31->id, 'rekomendasi_id' => $r10->id,
            'hasil' => HasilTelaah::BM->value, 'alasan_td_id' => $alasan->id,
            'catatan' => 'Kewenangan berada di luar instansi.',
        ]);
        $this->riwayat($r10, [
            ['2026-05-20', 'Setba', 'Rekomendasi dikirim ke Sekretariat Badan'],
            ['2026-06-18', 'Sekretariat Badan', 'Mengirim tanggapan dan bukti dukung'],
            ['2026-07-15', 'Setba', 'Mencatat surat 31/CHV/ITJEN/VII/2026 — tidak dapat ditindaklanjuti'],
        ]);
    }

    /* ================= pembantu ================= */

    private function kategori(SumberLaporan $s, string $nama): int
    {
        return KategoriTemuan::where('sumber', $s->value)->where('nama', $nama)->value('id');
    }

    private function rekomendasi(
        Temuan $t, int $urut, string $kode, string $uraian, string $bentuk,
        int $nilaiPulih, string $kodeSatker, ?string $tenggat, ?string $target,
        StatusTindakLanjut $status, PosisiBerkas $posisi, ?string $catatan = null,
        int $rencanaAngsur = 0, bool $kunciAngsur = false,
        ?string $sifat = null
    ): Rekomendasi {
        /* Posisi tingkat 1 turun ke sasaran; kolom posisi rekomendasi hanya
           menyimpan tingkat 2, dan NULL selama tingkat 1 masih berjalan. */
        $tingkat2 = $posisi->tingkat() === 2;

        $rek = Rekomendasi::create([
            'temuan_id' => $t->id, 'nomor_urut' => $urut, 'kode' => $kode,
            'uraian' => $uraian,
            'nilai_pulih' => $nilaiPulih,
            'rencana_angsur' => $rencanaAngsur, 'kunci_angsur' => $kunciAngsur,
            'tenggat_jawab' => $tenggat, 'target_selesai' => $target,
            'status' => $status->value,
            'posisi' => $tingkat2 ? $posisi->value : null,
            'catatan' => $catatan,
            /* Kalau tidak disebut, diturunkan dari nilainya: yang menuntut
               penyetoran menyangkut kerugian negara, yang tidak administratif.
               Itu memang aturannya, dan menuliskannya satu per satu di setiap
               pemanggilan cuma mengulang hal yang sama. */
            'sifat_id' => $this->sifat[$sifat
                ?? ($nilaiPulih > 0 ? 'Informasi kerugian negara' : 'Administratif')] ?? null,
        ]);

        $tindakan = \App\Models\Tindakan::create([
            'rekomendasi_id' => $rek->id,
            'bentuk_id' => $this->bentuk[$bentuk],
            'urutan' => 1,
            /* Tanggalnya ikut turun ke tindakannya. Selama bentuknya satu,
               isinya sama dengan tanggal rekomendasi — yang berbeda
               diperagakan di keadaanKhusus(). */
            'tgl_renaksi' => $tenggat,
            'target_selesai' => $target,
        ]);

        $baris = \App\Models\Sasaran::create([
            'tindakan_id' => $tindakan->id,
            'satker_id' => $this->satker[$kodeSatker],
            'nilai' => $nilaiPulih,
            // Berkas yang sudah masuk tingkat 2 berarti bagian satuan kerjanya
            // memang sudah tuntas.
            'posisi' => $tingkat2 ? PosisiBerkas::TUNTAS->value : $posisi->value,
            'hasil' => $tingkat2 ? 'M' : null,
        ]);

        /* Tanda memadai tidak pernah muncul sendiri — ia lahir dari telaah.
           Baris yang sudah naik ke tingkat 2 berarti UKI memang sudah
           menilainya, jadi telaahnya ikut dicatat di sini. Tanpa itu,
           halamannya menunjukkan baris bertanda memadai yang tidak punya satu
           pun catatan penilaian, dan pembacanya wajar bertanya dari mana
           tandanya datang. */
        if ($tingkat2) {
            \App\Models\Telaah::create([
                'rekomendasi_id' => $rek->id,
                'sasaran_id' => $baris->id,
                'tanggal' => $tenggat ?: now()->toDateString(),
                'label_oleh' => 'UKI',
                'hasil' => 'M',
                'catatan' => 'Bukti '.($baris->satker?->namaPendek() ?? 'satuan kerja')
                    .' dinilai cukup dan cocok dengan nilai temuannya.',
            ]);
        }

        return $rek->load('sasaran');
    }

    /** Baris satuan kerja pertama sebuah rekomendasi — dipakai pembantu lain
        yang perlu menggantungkan catatannya pada sasaran. */
    private function sasaranPertama(Rekomendasi $r): ?\App\Models\Sasaran
    {
        return $r->daftarSasaran()->first();
    }

    /* Pengembalian berkas berikut alasannya — dicatat tersendiri supaya alasannya
       terbaca di tempat orang mencarinya. */
    private function pengembalian(Rekomendasi $r, string $tanggal, string $oleh, string $alasan): void
    {
        \App\Models\Pengembalian::create([
            'rekomendasi_id' => $r->id,
            'sasaran_id' => $this->sasaranPertama($r)?->id,
            'tanggal' => $tanggal,
            'label_oleh' => $oleh, 'alasan' => $alasan,
        ]);
    }

    /* Kesimpulan telaah UKI atau verifikasi Inspektorat. Tidak mengubah status —
       status hanya berubah lewat surat verifikasi bernomor. */
    private function telaah(Rekomendasi $r, string $tanggal, string $oleh, string $catatan): void
    {
        \App\Models\Telaah::create([
            'rekomendasi_id' => $r->id,
            'sasaran_id' => $this->sasaranPertama($r)?->id,
            'tanggal' => $tanggal,
            'label_oleh' => $oleh, 'catatan' => $catatan,
            // Telaah contoh selalu berbunyi memadai; yang belum memadai
            // diperagakan lewat pengembalian.
            'hasil' => 'M',
        ]);
    }

    /**
     * Bukti satuan kerja berupa TAUTAN ke arsipnya sendiri.
     *
     * Rapat 30 Agustus: berkasnya sudah tersimpan di arsip masing-masing
     * satuan kerja, dan menyalinnya ke sistem ini berarti dua tempat menyimpan
     * satu berkas — lalu keduanya bisa berbeda tanpa ada yang tahu mana yang
     * benar.
     *
     * Surat laporan pemeriksaannya sendiri tetap berkas terunggah — lihat
     * berkasLaporan(). Itu dokumen Setba, bukan bukti kiriman satuan kerja.
     */
    private function berkas(Rekomendasi $r, string $nama, string $jenis, string $tgl): Lampiran
    {
        return Lampiran::create([
            'rekomendasi_id' => $r->id,
            /* Berkasnya milik baris satuan kerja, bukan rekomendasi seutuhnya:
               yang mengirimnya satu satuan kerja, dan yang membacanya perlu
               tahu kiriman siapa. */
            'sasaran_id' => $this->sasaranPertama($r)?->id,
            'jenis_dokumen_id' => $this->jenisDok[$jenis] ?? null,
            'nama_asli' => $nama,
            'tautan' => 'https://arsip.contoh.test/'.Str::slug(pathinfo($nama, PATHINFO_FILENAME)).'.pdf',
            'diunggah_pada' => $tgl,
        ]);
    }

    private function berkasLaporan(Laporan $l, string $nama, string $tgl): Lampiran
    {
        return Lampiran::create([
            'laporan_id' => $l->id,
            'jenis_dokumen_id' => $this->jenisDok['Surat laporan pemeriksaan'] ?? null,
            'nama_asli' => $nama, 'nama_simpan' => bin2hex(random_bytes(8)) . '.pdf',
            'mime' => 'application/pdf', 'ukuran' => random_int(500_000, 6_000_000),
            'diunggah_oleh' => $this->setba->id, 'diunggah_pada' => $tgl,
        ]);
    }

    private function riwayat(Rekomendasi $r, array $baris): void
    {
        foreach ($baris as [$tgl, $aktor, $aksi]) {
            RiwayatBerkas::create([
                'rekomendasi_id' => $r->id, 'waktu' => $tgl . ' 09:00:00',
                'label_aktor' => $aktor, 'aksi' => $aksi,
            ]);
        }
    }

    /* ================================================================
       KEADAAN KHUSUS

       Kemampuan yang ditambahkan belakangan dan belum pernah punya wakil di
       data contoh. Tanpa satu pun barisnya, layar yang menampilkannya selalu
       kosong — dan yang memperagakan sistem ini menyangka fiturnya belum ada.

       Satu laporan tersendiri, bukan disisipkan ke dua laporan di atas: yang
       dua itu disusun memperagakan alur baku, dan menempeli keduanya dengan
       setiap perkecualian membuat yang baku ikut sulit dibaca.
       ================================================================ */
    private function keadaanKhusus(): void
    {
        $lap = Laporan::create([
            'sumber' => SumberLaporan::LHP->value,
            'nomor' => '121/LHP/XVIII/08/2026',
            'tgl_surat' => '2026-08-03',
            'tgl_terima' => '2026-08-10',
            'dicatat_pada' => '2026-08-12',
            'dicatat_oleh' => $this->setba->id,
        ]);

        $this->berkasLaporan($lap, 'LHP-121-2026.pdf', '2026-08-12');

        /* ---- temuan yang mengenai DUA satuan kerja ----
           Lembar pemantauan menyimpannya persis begitu: satu perkara, dua
           tempat kejadian. Selama pivotnya tidak pernah terisi lebih dari
           satu, kolom "Satuan kerja terperiksa" selalu tampak tunggal. */
        $tem = Temuan::create([
            'laporan_id' => $lap->id,
            'kode' => 'TMN-2026-201',
            'nomor_pada_surat' => '2.1.4',
            'judul' => 'Kelebihan pembayaran biaya paket meeting pada dua balai',
            'sebab' => 'Tarif paket meeting tidak diverifikasi terhadap standar biaya '
                . 'sebelum pembayaran disetujui.',
            'akibat' => 'Belanja negara melebihi hak penyedia senilai Rp 62.000.000.',
            'kategori_temuan_id' => $this->kategori(SumberLaporan::LHP, 'Laporan Operasional'),
            'kategori_intern_id' => $this->kategoriIntern['Perjalanan dinas dan penginapan'] ?? null,
            'nilai' => 62_000_000,
        ]);
        $tem->satkers()->sync([
            $this->satker['BPK-PLG'],
            $this->satker['BPK-YOG'],
        ]);

        /* ---- rekomendasi dengan DUA bentuk tindak lanjut ----
           Suratnya menuntut menyetor kelebihannya DAN membenahi prosedur
           verifikasinya, dan yang menyetor bukan yang membenahi. Selama
           formulirnya memaksa satu bentuk, yang begitu harus dipecah jadi dua
           rekomendasi — dan uraiannya ditulis dua kali padahal suratnya cuma
           menyebut satu. */
        $rek = $this->rekomendasi(
            $tem, 1, 'REK-2026-201.1',
            'Menyetorkan kelebihan pembayaran ke kas negara dan menyempurnakan '
                . 'prosedur verifikasi tarif sebelum persetujuan pembayaran.',
            'Penyetoran ke kas negara', 42_000_000, 'BPK-PLG',
            '2026-10-09', '2026-11-30',
            StatusTindakLanjut::BT, PosisiBerkas::UKI,
            'Bukti setor wajib dilampiri Nota Konfirmasi KPPN.',
        );

        /* Bentuk kedua: satuan kerja yang berbeda, tenggat yang lebih panjang.
           Membenahi prosedur menuntut penetapan pejabat dan sosialisasi, dan
           itu memang tidak selesai dalam tenggat yang sama dengan menyetor. */
        $tindakan2 = \App\Models\Tindakan::create([
            'rekomendasi_id' => $rek->id,
            'bentuk_id' => $this->bentuk['Perbaikan sistem pengendalian intern'],
            'urutan' => 2,
            'tgl_renaksi' => '2026-12-08',
            'target_selesai' => '2027-01-31',
            'catatan' => 'Prosedur yang direvisi ditetapkan pejabat berwenang.',
        ]);

        $sasaran2 = \App\Models\Sasaran::create([
            'tindakan_id' => $tindakan2->id,
            'satker_id' => $this->satker['BPK-YOG'],
            'nilai' => 0,
            'posisi' => PosisiBerkas::SATKER->value,
        ]);

        $rek->update(['nilai_pulih' => 42_000_000]);

        $satu = $this->sasaranPertama($rek);

        /* ---- surat pengantar antar unit ----
           Nomornya yang dipakai menelusuri berkas di luar sistem: kalau ada
           yang menanyakan lewat telepon, nomor surat itulah yang disebut. */
        \App\Models\Surat::create([
            'rekomendasi_id' => $rek->id,
            'sasaran_id' => $satu->id,
            'dari' => 'Setba', 'ke' => 'UKI',
            'nomor' => 'PW.0301-Sb/812',
            'tanggal' => '2026-09-02',
            'tanggal_catat' => '2026-09-02',
            'perihal' => 'Permohonan validasi tindak lanjut Balai Palembang',
            'dicatat_oleh' => $this->setba->id,
        ]);

        /* ---- surat LHV: hasil validasi UKI ----
           Cakupannya berbeda dari CHV, dan itu sebabnya tabelnya dipisah: LHV
           menilai berkas SATU satuan kerja, CHV memutus SELURUH rekomendasi.
           Tanpa satu pun baris LHV, kartu "Riwayat validasi" tidak pernah
           tampil dan bedanya tidak pernah terlihat. */
        $lhv = Verifikasi::create([
            'jenis' => Verifikasi::LHV,
            'periode' => 'Triwulan III 2026',
            'nomor_surat' => '44/LHV/UKI/IX/2026',
            'tgl_surat' => '2026-09-19',
            'pejabat' => 'Ketua Unit Kepatuhan Internal',
            'dicatat_oleh' => $this->setba->id,
        ]);
        $lhv->keputusan()->create([
            'rekomendasi_id' => $rek->id,
            'hasil' => 'BM',
            'catatan' => 'Rekapitulasi per pegawai belum dilampirkan; nilai setoran '
                . 'belum bisa dicocokkan dengan bukti.',
        ]);

        $this->telaah($rek, '2026-09-19', 'UKI',
            'Bukti setor sudah ada, tapi rekapitulasi per pegawai belum dilampirkan.');

        /* ---- permintaan perubahan yang menunggu keputusan ----
           Inilah satu-satunya jalan mengubah kiriman yang sudah lepas dari
           meja satuan kerja. Tanpa satu pun barisnya, kartunya tidak pernah
           tampil dan aturannya tidak pernah terlihat. */
        $berkasSalah = $this->berkas($rek, 'rekap-setoran-versi-lama.xlsx',
            'Lainnya', '2026-09-01');
        $berkasSalah->update(['sasaran_id' => $satu->id]);

        \App\Models\PermintaanUbah::create([
            'rekomendasi_id' => $rek->id,
            'sasaran_id' => $satu->id,
            'jenis' => \App\Enums\JenisPermintaanUbah::BATAL_DOKUMEN->value,
            'alasan' => 'Rekapitulasi yang terunggah versi lama, angkanya belum '
                . 'dikoreksi. Mohon dibatalkan supaya bisa diganti.',
            'lampiran_sasaran_id' => $berkasSalah->id,
            'diajukan_oleh' => User::where('satker_id', $this->satker['BPK-PLG'])->value('id'),
            'label_pengaju' => 'Balai Palembang',
            'tanggal' => '2026-09-22',
            'status' => \App\Enums\StatusPermintaanUbah::MENUNGGU->value,
        ]);

        /* Rekam jejaknya menyusul, supaya urutannya terbaca utuh. */
        $this->riwayat($rek, [
            ['2026-08-12', 'Setba', 'Rekomendasi dikirim ke satuan kerja'],
            ['2026-08-30', 'Balai Palembang', 'Tindak lanjut dikirim ke Setba'],
            ['2026-09-02', 'Setba', 'Diteruskan ke UKI dengan surat PW.0301-Sb/812'],
            ['2026-09-19', 'UKI', 'Hasil validasi: belum memadai'],
            ['2026-09-22', 'Balai Palembang', 'Mengajukan permintaan perubahan: Batalkan berkas'],
        ]);

        $satu->update(['hasil' => null]);
        $sasaran2->refresh();
    }

}
