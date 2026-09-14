<?php

namespace Database\Seeders;

use App\Enums\JenisPemulihan;
use App\Enums\HasilTelaah;
use App\Enums\JenisReferensi;
use App\Enums\PosisiBerkas;
use App\Enums\StatusTindakLanjut;
use App\Enums\SumberLaporan;
use App\Models\ItemPermintaan;
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
use App\Models\Telaah;
use App\Models\Temuan;
use App\Models\TindakLanjut;
use App\Models\User;
use App\Models\Verifikasi;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Data contoh berskala uji beban: kira-kira dua puluh laporan.
 *
 * Dua laporan buatan tangan di ContohLaporanSeeder memperagakan skenario
 * tertentu dengan teliti; yang ini menambah banyaknya. Gunanya bukan sekadar
 * ramai — tata letak yang tampak rapi dengan sepuluh baris kerap berantakan
 * dengan seratus, dan pertanyaan yang jawabannya jelas pada dua laporan jadi
 * tidak terjawab pada dua puluh.
 *
 * Tiga hal dijaga ketat, karena data contoh yang tidak masuk akal membuat
 * seluruh tampilan di atasnya ikut tidak bisa dipercaya:
 *
 *   1. Tanggal maju terus dan tidak pernah melewati hari ini.
 *   2. Status dan posisi berkas selalu cocok. Yang berstatus selain BT wajib
 *      punya surat verifikasi — status hanya berubah lewat surat bernomor.
 *   3. Jumlah setoran tidak pernah melebihi nilai yang harus dipulihkan.
 */
class BanyakLaporanSeeder extends Seeder
{
    private array $satker;
    private array $bentuk;
    private array $sifat;
    private array $kategoriIntern;
    private array $jenisDok;
    private User $setba;
    private int $nomorTemuan = 100;
    private int $nomorLaporan = 100;

    /* Bibit tetap: data contoh yang berubah tiap kali diseed membuat tangkapan
       layar dan catatan uji jadi tidak cocok satu sama lain. */
    private const BIBIT = 20260826;

    public function run(): void
    {
        mt_srand(self::BIBIT);

        $this->satker = Satker::pluck('id', 'kode')->all();
        $this->bentuk = Referensi::jenis(JenisReferensi::BENTUK_TL)->pluck('id', 'nama')->all();
        $this->sifat = Referensi::jenis(JenisReferensi::SIFAT_REKOM)->pluck('id', 'nama')->all();
        $this->kategoriIntern = Referensi::jenis(JenisReferensi::KATEGORI_INTERN)->pluck('id', 'nama')->all();
        $this->jenisDok = Referensi::jenis(JenisReferensi::JENIS_DOKUMEN)->pluck('id', 'nama')->all();
        $this->setba = User::where('peran', 'setba')->firstOrFail();

        /* Umurnya dibuat bertingkat: yang tua sudah jauh melangkah, yang muda
           masih di awal. Kalau semuanya seumur, seluruh berkas menumpuk di satu
           tahap dan tidak ada yang bisa diperagakan. */
        for ($i = 0; $i < 18; $i++) {
            $umur = match (true) {
                $i < 6 => mt_rand(240, 400),   // tua
                $i < 12 => mt_rand(90, 240),   // tengah
                default => mt_rand(10, 90),    // muda
            };
            $this->buatLaporan($umur, $i);
        }
    }

    private function buatLaporan(int $umurHari, int $ke): void
    {
        $sumber = $ke % 3 === 0 ? SumberLaporan::LHA : SumberLaporan::LHP;
        $terima = Carbon::today()->subDays($umurHari);
        $suratTgl = $terima->copy()->subDays(mt_rand(3, 12));
        $dicatat = $terima->copy()->addDays(mt_rand(0, 14));
        if ($dicatat->isFuture()) {
            $dicatat = Carbon::today();
        }

        /* Nomor yang sudah dipakai laporan buatan tangan dilewati. Penyemai
           ini menghitung sendiri mulai 100, dan ContohLaporanSeeder memakai
           101-118 — selama urutan acaknya kebetulan tidak bertemu tidak ada
           yang kelihatan, dan begitu bertemu seluruh rangkaian ujinya mati
           karena satu nomor kembar. */
        do {
            $this->nomorLaporan++;
            $nomor = $sumber === SumberLaporan::LHP
                ? sprintf('%d/LHP/XVIII/%02d/%d', $this->nomorLaporan, $suratTgl->month, $suratTgl->year)
                : sprintf('%d/LHA/ITJ/%02d/%d', $this->nomorLaporan, $suratTgl->month, $suratTgl->year);
        } while (Laporan::where('nomor', $nomor)->exists());

        $lap = Laporan::create([
            'sumber' => $sumber->value,
            'nomor' => $nomor,
            'tgl_surat' => $suratTgl->toDateString(),
            'tgl_terima' => $terima->toDateString(),
            'dicatat_pada' => $dicatat->toDateString(),
            'dicatat_oleh' => $this->setba->id,
        ]);

        Lampiran::create([
            'laporan_id' => $lap->id,
            'jenis_dokumen_id' => $this->jenisDok['Surat laporan pemeriksaan'] ?? null,
            'nama_asli' => 'surat-' . $this->nomorLaporan . '.pdf',
            'nama_simpan' => bin2hex(random_bytes(8)) . '.pdf',
            'mime' => 'application/pdf', 'ukuran' => mt_rand(120_000, 4_000_000),
            'diunggah_pada' => $dicatat->toDateString(),
        ]);

        /* Satu laporan lazim memeriksa lebih dari satu satuan kerja — itulah
           sebabnya satker melekat di temuan, bukan di kepala laporan. */
        $kodeSatker = array_keys($this->satker);
        shuffle($kodeSatker);
        $jumlahTemuan = mt_rand(2, 4);

        for ($t = 0; $t < $jumlahTemuan; $t++) {
            $this->buatTemuan($lap, $sumber, $kodeSatker[$t % count($kodeSatker)], $terima);
        }
    }

    private function buatTemuan(Laporan $lap, SumberLaporan $sumber, string $kodeSatker, Carbon $terima): void
    {
        $judul = [
            'Kelebihan pembayaran biaya penginapan perjalanan dinas',
            'Kekurangan volume pekerjaan pemeliharaan gedung',
            'Barang milik negara tidak ditemukan saat opname fisik',
            'Penerimaan sewa fasilitas terlambat disetor ke kas negara',
            'Honorarium dibayarkan kepada pegawai yang tidak bertugas',
            'Pelaksanaan pelatihan tidak sesuai pedoman penyelenggaraan',
            'Pengadaan jasa konsultansi tanpa dokumen pemilihan lengkap',
            'Sisa uang persediaan belum disetor pada akhir tahun anggaran',
        ];
        $intern = array_keys($this->kategoriIntern);

        $this->nomorTemuan++;
        $nilai = mt_rand(0, 3) === 0 ? 0 : mt_rand(8, 380) * 1_000_000;

        $tem = Temuan::create([
            'laporan_id' => $lap->id,
            'kode' => 'TMN-2026-' . str_pad((string) $this->nomorTemuan, 3, '0', STR_PAD_LEFT),
            'nomor_pada_surat' => mt_rand(1, 5) . '.' . mt_rand(1, 4) . '.' . mt_rand(1, 3),
            'judul' => $judul[array_rand($judul)],
            'sebab' => 'Pengendalian internal belum berjalan sebagaimana mestinya.',
            'akibat' => 'Kerugian atau potensi kerugian bagi keuangan negara.',
            'kategori_temuan_id' => $this->kategoriAcak($sumber),
            'kategori_intern_id' => $this->kategoriIntern[$intern[array_rand($intern)]],
            'nilai' => $nilai,
        ]);

        /* Satu dari enam temuan mengenai DUA satuan kerja. Lembar pemantauan
           menyimpannya persis begitu: satu perkara, dua tempat kejadian — dan
           selama pivotnya tidak pernah terisi lebih dari satu, kolom "Satuan
           kerja terperiksa" selalu tampak tunggal padahal tabelnya memang
           dibuat untuk jamak. */
        $kena = [$this->satker[$kodeSatker]];
        if (mt_rand(1, 6) === 1) {
            $lain = array_values(array_diff(array_keys($this->satker), [$kodeSatker]));
            $kena[] = $this->satker[$lain[array_rand($lain)]];
        }
        $tem->satkers()->attach(array_unique($kena));

        $jumlahRekom = mt_rand(1, 3);
        for ($r = 1; $r <= $jumlahRekom; $r++) {
            $this->buatRekomendasi($tem, $sumber, $r, $kodeSatker, $terima, $nilai, $jumlahRekom);
        }
    }

    private function buatRekomendasi(
        Temuan $tem, SumberLaporan $sumber, int $urut, string $kodeSatker,
        Carbon $terima, int $nilaiTemuan, int $dariBerapa
    ): void {
        /* Yang menanggung perbaikan tidak selalu yang diperiksa: rekomendasi
           perbaikan prosedur lazim jatuh ke Setba walau temuannya di balai. */
        $penanggung = $urut > 1 && mt_rand(0, 2) === 0 ? 'SETBA' : $kodeSatker;

        $bentukNama = array_rand($this->bentuk);
        $perluNilai = in_array($bentukNama,
            ['Penyetoran ke kas negara', 'Perbaikan hasil pekerjaan', 'Penyerahan barang atau aset kepada negara'], true);
        $nilaiPulih = $perluNilai && $nilaiTemuan > 0
            ? (int) round($nilaiTemuan / $dariBerapa / 1000) * 1000
            : 0;

        $tenggat = Rekomendasi::hitungTenggat($terima, $sumber);

        /* Posisi ditentukan umurnya, lalu statusnya menyusul posisi — bukan
           sebaliknya. Status hanya boleh selain BT kalau suratnya sudah ada. */
        $umur = (int) $terima->diffInDays(Carbon::today());
        [$posisi, $status] = $this->keadaan($umur);

        $tingkat2 = $posisi->tingkat() === 2;

        $r = Rekomendasi::create([
            'temuan_id' => $tem->id,
            'kode' => str_replace('TMN', 'REK', $tem->kode) . '.' . $urut,
            'nomor_urut' => $urut,
            'uraian' => $this->uraian($bentukNama),
            'nilai_pulih' => $nilaiPulih,
            'rencana_angsur' => $nilaiPulih > 100_000_000 ? mt_rand(2, 4) : 0,
            'kunci_angsur' => $nilaiPulih > 200_000_000,
            'tenggat_jawab' => $tenggat->toDateString(),
            'target_selesai' => $tenggat->copy()->addMonths(mt_rand(2, 10))->toDateString(),
            'status' => $status->value,
            // Hanya tingkat 2. NULL berarti satuan kerjanya masih bekerja.
            'posisi' => $tingkat2 ? $posisi->value : null,
            /* Diturunkan dari nilainya: yang menuntut penyetoran menyangkut
               kerugian negara, yang tidak administratif. */
            'sifat_id' => $this->sifat[$nilaiPulih > 0
                ? 'Informasi kerugian negara' : 'Administratif'] ?? null,
        ]);

        $tindakan = \App\Models\Tindakan::create([
            'rekomendasi_id' => $r->id,
            'bentuk_id' => $this->bentuk[$bentukNama],
            'urutan' => 1,
            'tgl_renaksi' => $tenggat->toDateString(),
            'target_selesai' => $r->target_selesai,
        ]);



        /* Sepertiga rekomendasi sengaja dipikul dua atau tiga satuan kerja.
           Inilah keadaan yang paling sering salah ditangani — berkas yang
           berjalan sendiri-sendiri, nominal yang dipecah, tanda memadai per
           satuan kerja — jadi data contoh harus benar-benar memperagakannya,
           bukan menyisakannya sebagai kemungkinan di atas kertas. */
        $pemikul = [$penanggung];
        if (mt_rand(0, 2) === 0) {
            $kandidat = array_values(array_diff(array_keys($this->satker), $pemikul));
            shuffle($kandidat);
            foreach (array_slice($kandidat, 0, mt_rand(1, 2)) as $tambahan) {
                $pemikul[] = $tambahan;
            }
        }
        $pemikul = array_values(array_unique($pemikul));

        $bagi = count($pemikul);
        $sisa = $nilaiPulih;
        foreach (array_values($pemikul) as $i => $kode) {
            $nilaiBagian = $i === $bagi - 1 ? $sisa : (int) round($nilaiPulih / $bagi / 1000) * 1000;
            $sisa -= $nilaiBagian;

            /* Satuan kerja kedua dan seterusnya sengaja tertinggal di tahap
               yang berbeda. Kalau semuanya berposisi sama, seluruh alasan
               memisahkan gerak per satuan kerja jadi tidak terperagakan. */
            $posisiBaris = $tingkat2 ? PosisiBerkas::TUNTAS : $posisi;
            if (! $tingkat2 && $i > 0 && mt_rand(0, 1) === 0) {
                $posisiBaris = PosisiBerkas::SATKER;
            }

            \App\Models\Sasaran::create([
                'tindakan_id' => $tindakan->id,
                'satker_id' => $this->satker[$kode],
                'nilai' => max(0, $nilaiBagian),
                'posisi' => $posisiBaris->value,
                'hasil' => $posisiBaris === PosisiBerkas::TUNTAS ? 'M' : null,
            ]);
        }

        /* Satu dari tujuh rekomendasi menuntut DUA bentuk sekaligus — surat
           yang meminta menyetor kelebihannya sekaligus membenahi prosedurnya.
           Bentuk keduanya diberi tenggat lebih panjang: menetapkan prosedur
           menuntut penetapan pejabat dan sosialisasi, dan itu memang tidak
           selesai dalam waktu yang sama dengan menyetor.

           Dibuat SESUDAH pemikulnya diketahui, dan diberi satuan kerjanya
           sendiri: bentuk tanpa satu pun satuan kerja tidak pernah tampil di
           tabel, jadi menambahkannya tanpa itu cuma menambah baris di basis
           data tanpa mengubah apa pun di layar.

           Nilainya nol — yang menuntut penyetoran sudah bentuk pertama, dan
           membenahi prosedur memang tidak menagih uang. */
        if (mt_rand(1, 7) === 1) {
            $lain = array_values(array_diff(
                array_keys($this->bentuk),
                [$bentukNama, 'Penyetoran ke kas negara'],
            ));

            $tindakanKedua = \App\Models\Tindakan::create([
                'rekomendasi_id' => $r->id,
                'bentuk_id' => $this->bentuk[$lain[array_rand($lain)]],
                'urutan' => 2,
                'tgl_renaksi' => $tenggat->copy()->addDays(mt_rand(30, 90))->toDateString(),
                'target_selesai' => $r->target_selesai,
            ]);

            \App\Models\Sasaran::create([
                'tindakan_id' => $tindakanKedua->id,
                'satker_id' => $this->satker[$pemikul[array_rand($pemikul)]],
                'nilai' => 0,
                /* Bentuk kedua sengaja tertinggal di satuan kerjanya: itulah
                   yang membuat rekomendasinya belum bisa maju walau bentuk
                   pertamanya sudah beres. */
                'posisi' => $tingkat2 ? PosisiBerkas::TUNTAS->value : PosisiBerkas::SATKER->value,
                'hasil' => $tingkat2 ? 'M' : null,
            ]);
        }

        $r->load('sasaran');
        $sasaranUtama = $r->daftarSasaran()->first();

        $jam = $terima->copy();
        $maju = function (int $min, int $maks) use (&$jam): Carbon {
            $jam = $jam->copy()->addDays(mt_rand($min, $maks));
            if ($jam->isFuture()) {
                $jam = Carbon::today();
            }
            return $jam->copy();
        };

        RiwayatBerkas::create([
            'rekomendasi_id' => $r->id, 'waktu' => $terima,
            'label_aktor' => 'Setba', 'aksi' => 'Rekomendasi dikirim ke satuan kerja',
            'posisi_ke' => PosisiBerkas::SATKER->value,
        ]);

        /* Jejaknya dibuat per BARIS yang memang sudah bergerak, bukan sekali
           untuk baris pertama saja.

           Berkas yang sudah lepas dari meja satuan kerja pasti punya
           tanggapan — tanpa itu tidak ada yang bisa diteruskan, dan tidak ada
           yang bisa dinilai memadai. Sebelumnya posisinya diberikan ke semua
           baris sementara jejaknya cuma untuk yang pertama, jadi rekomendasi
           yang dipikul tiga satuan kerja punya satu laporan dan tiga baris
           yang mengaku sudah beres. */
        foreach ($r->daftarSasaran() as $baris) {
            if ($baris->posisi === PosisiBerkas::SATKER) {
                continue;
            }

            $namaBaris = $baris->satker?->namaPendek() ?? 'satuan kerja';
            $tglTanggap = $maju(14, 60);

            TindakLanjut::create([
                'rekomendasi_id' => $r->id,
                'sasaran_id' => $baris->id,
                'tanggal' => $tglTanggap->toDateString(),
                'label_pencatat' => $namaBaris,
                'uraian' => 'Tindak lanjut bagian '.$namaBaris
                    .' sudah dilaksanakan dan buktinya ditautkan.',
            ]);

            $this->berkas($r, 'bukti-'.$r->id.'-'.$baris->id.'.pdf',
                'Dokumen perbaikan', $tglTanggap, $baris);

            /* Setorannya menyusul nilai BARIS itu, bukan nilai rekomendasi
               seutuhnya: yang ditagihkan kepada masing-masing memang
               bagiannya sendiri. */
            if ((int) $baris->nilai > 0) {
                $this->setoran($r, (int) $baris->nilai, $maju(3, 30), $baris);
            }

            /* Yang sudah lewat meja UKI punya telaahnya sendiri. Tanda memadai
               di satu baris tidak pernah lahir dari telaah baris lain. */
            if (in_array($baris->posisi, [PosisiBerkas::SETBA_TERUSKAN,
                PosisiBerkas::INSPEKTORAT, PosisiBerkas::TUNTAS], true)) {
                Telaah::create([
                    'rekomendasi_id' => $r->id,
                    'sasaran_id' => $baris->id,
                    'tanggal' => $maju(5, 25)->toDateString(),
                    'label_oleh' => 'UKI',
                    'hasil' => $baris->hasil?->value,
                    'catatan' => 'Bukti '.$namaBaris
                        .' dinilai cukup dan cocok dengan nilai temuannya.',
                ]);
            }
        }

        /* Dokumen yang diminta: dipasang pada sebagian berkas supaya progres
           kelengkapan ada yang bisa diperagakan. */
        if (mt_rand(0, 2) === 0) {
            $this->mintaDokumen($r, $maju(5, 25));
        }

        /* Surat verifikasi wajib ada begitu statusnya bukan BT. Inilah yang
           menjaga sumbu status tetap berarti: ia hanya berubah lewat surat
           bernomor, tidak pernah lewat perpindahan berkas. */
        if ($status !== StatusTindakLanjut::BT) {
            $this->suratVerifikasi($r, $status, $maju(7, 40));
        }
    }

    private function keadaan(int $umur): array
    {
        $pilihan = match (true) {
            $umur > 300 => [
                [PosisiBerkas::SELESAI, StatusTindakLanjut::SS],
                [PosisiBerkas::SELESAI, StatusTindakLanjut::SS],
                [PosisiBerkas::SELESAI, StatusTindakLanjut::TD],
                [PosisiBerkas::BPK, StatusTindakLanjut::SS],
                [PosisiBerkas::SATKER, StatusTindakLanjut::BS],
                [PosisiBerkas::TUNTAS, StatusTindakLanjut::BT],
            ],
            $umur > 150 => [
                [PosisiBerkas::SIPTL, StatusTindakLanjut::SS],
                [PosisiBerkas::TUNTAS, StatusTindakLanjut::BT],
                [PosisiBerkas::INSPEKTORAT, StatusTindakLanjut::BT],
                [PosisiBerkas::SATKER, StatusTindakLanjut::BS],
                [PosisiBerkas::SELESAI, StatusTindakLanjut::SS],
                [PosisiBerkas::UKI, StatusTindakLanjut::BT],
            ],
            $umur > 60 => [
                [PosisiBerkas::UKI, StatusTindakLanjut::BT],
                [PosisiBerkas::SETBA_TINJAU, StatusTindakLanjut::BT],
                [PosisiBerkas::SETBA_TERUSKAN, StatusTindakLanjut::BT],
                [PosisiBerkas::SATKER, StatusTindakLanjut::BT],
                [PosisiBerkas::INSPEKTORAT, StatusTindakLanjut::BT],
            ],
            default => [
                [PosisiBerkas::SATKER, StatusTindakLanjut::BT],
                [PosisiBerkas::SATKER, StatusTindakLanjut::BT],
                [PosisiBerkas::SETBA_TINJAU, StatusTindakLanjut::BT],
            ],
        };

        return $pilihan[array_rand($pilihan)];
    }

    private function uraian(string $bentuk): string
    {
        return match ($bentuk) {
            'Penyetoran ke kas negara' => 'Menarik kelebihan pembayaran dan menyetorkannya ke kas negara.',
            'Perbaikan hasil pekerjaan' => 'Menyelesaikan kekurangan volume pekerjaan sesuai kontrak.',
            'Penyerahan barang atau aset kepada negara' => 'Menelusuri keberadaan barang dan menyerahkannya kembali kepada negara.',
            'Perbaikan dokumen administrasi' => 'Melengkapi dokumen pertanggungjawaban yang masih kurang.',
            'Perbaikan sistem pengendalian intern' => 'Menyempurnakan prosedur pengawasan dengan mewajibkan pemeriksaan berjenjang.',
            'Tindakan administratif atau hukuman disiplin' => 'Memberikan sanksi kepada pejabat yang lalai menjalankan tugasnya.',
            'Pengenaan sanksi daftar hitam' => 'Mengikutsertakan pejabat terkait pada pelatihan yang relevan.',
            default => 'Menindaklanjuti temuan sesuai ketentuan yang berlaku.',
        };
    }

    /**
     * Setoran satu BARIS satuan kerja, bukan satu rekomendasi.
     *
     * Yang ditagihkan kepada masing-masing memang bagiannya sendiri, dan
     * setoran yang menggantung di rekomendasi membuat baris mana pun terlihat
     * sudah menyetor padahal cuma satu yang membayar.
     */
    private function setoran(Rekomendasi $r, int $target, Carbon $tgl,
        ?\App\Models\Sasaran $sasaran = null): void
    {
        /* Tidak pernah melebihi tagihannya. Sebagian sengaja dibuat baru
           sebagian supaya sisa tagihan ada yang bisa diperagakan. */
        $bagian = mt_rand(0, 3) === 0 ? $target : (int) round($target * mt_rand(30, 80) / 100 / 1000) * 1000;
        if ($bagian <= 0) {
            return;
        }

        $baris = $sasaran ?? $r->daftarSasaran()->first();
        $lampiran = $this->berkas($r, 'setor-' . $r->id . '-' . ($baris?->id ?? 0) . '.pdf',
            'Bukti setor kas negara (SSBP)', $tgl, $baris);

        Pemulihan::create([
            'rekomendasi_id' => $r->id,
            'sasaran_id' => $baris?->id,
            'tanggal' => $tgl->toDateString(),
            'jenis' => JenisPemulihan::SETOR->value,
            'nilai' => min($bagian, $target),
            'ntpn' => strtoupper(bin2hex(random_bytes(8))),
            'no_ssbp' => 'SSBP/' . $tgl->year . '/' . str_pad((string) mt_rand(1, 99999), 5, '0', STR_PAD_LEFT),
            'lampiran_id' => $lampiran->id,
        ]);
    }

    private function mintaDokumen(Rekomendasi $r, Carbon $tgl): void
    {
        $p = PermintaanDokumen::create([
            'rekomendasi_id' => $r->id,
            'sasaran_id' => $r->daftarSasaran()->first()?->id,
            'peran_peminta' => 'setba',
            'diminta_oleh' => $this->setba->id,
            'tanggal' => $tgl->toDateString(),
            'catatan' => 'Bukti yang sudah masuk belum cukup menjelaskan penyelesaiannya.',
        ]);

        $usul = \App\Support\UsulDokumen::untuk($r->bentuk?->nama);
        foreach ($usul as $i => $nama) {
            ItemPermintaan::create([
                'permintaan_dokumen_id' => $p->id,
                'nama' => $nama,
                'terpenuhi' => $i === 0 && mt_rand(0, 1) === 1,
            ]);
        }
    }

    private function suratVerifikasi(Rekomendasi $r, StatusTindakLanjut $status, Carbon $tgl): void
    {
        $semester = $tgl->month <= 6 ? 'I' : 'II';
        $nomor = mt_rand(20, 199) . '/CHV/ITJEN/' . $tgl->month . '/' . $tgl->year;

        $v = Verifikasi::firstOrCreate(
            ['nomor_surat' => $nomor],
            [
                'periode' => 'Semester ' . $semester . ' ' . $tgl->year,
                'tgl_surat' => $tgl->toDateString(),
                'pejabat' => 'Inspektur Jenderal',
                'dicatat_oleh' => $this->setba->id,
            ]
        );

        KeputusanVerifikasi::create([
            'verifikasi_id' => $v->id,
            'rekomendasi_id' => $r->id,
            // Sumbu Itjen: surat menilai kecukupan berkas, bukan status BPK.
            'hasil' => $status === StatusTindakLanjut::SS
                ? HasilTelaah::M->value : HasilTelaah::BM->value,
            'tenggat_baru' => $status === StatusTindakLanjut::BS
                ? $tgl->copy()->addMonths(mt_rand(2, 5))->toDateString() : null,
            'catatan' => match ($status) {
                StatusTindakLanjut::SS => 'Seluruh kewajiban terpenuhi.',
                StatusTindakLanjut::BS => 'Tindak lanjut baru sebagian. Diberikan tenggat baru.',
                default => 'Tidak dapat ditindaklanjuti karena alasan sah.',
            },
        ]);
    }

    private function berkas(Rekomendasi $r, string $nama, string $jenis, Carbon $tgl,
        ?\App\Models\Sasaran $sasaran = null): Lampiran
    {
        return Lampiran::create([
            'rekomendasi_id' => $r->id,
            'sasaran_id' => $sasaran?->id,
            'jenis_dokumen_id' => $this->jenisDok[$jenis] ?? null,
            'nama_asli' => $nama,
            /* Tautan ke arsip satuan kerja, bukan salinan di sini — rapat
               30 Agustus. Surat laporannya sendiri tetap terunggah. */
            'tautan' => 'https://arsip.contoh.test/'
                . \Illuminate\Support\Str::slug(pathinfo($nama, PATHINFO_FILENAME)) . '.pdf',
            'diunggah_pada' => $tgl->toDateString(),
        ]);
    }

    private function kategoriAcak(SumberLaporan $s): ?int
    {
        return KategoriTemuan::where('sumber', $s->value)->inRandomOrder()->value('id');
    }
}
