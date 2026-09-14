<?php

namespace Tests\Feature;

use App\Enums\JenisReferensi;
use App\Enums\PosisiBerkas;
use App\Enums\StatusTindakLanjut;
use App\Models\KategoriTemuan;
use App\Models\Laporan;
use App\Models\Referensi;
use App\Models\Satker;
use App\Models\User;
use Database\Seeders\DataMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mencatat laporan pemeriksaan baru, tiga langkah.
 *
 * Isian disimpan di sesi antarlangkah. Yang dijaga di sini bukan cuma
 * "berhasil tersimpan", tapi juga hal-hal yang kalau lolos akan merusak
 * seluruh angka di atasnya: tanggal yang mustahil, tagihan yang melebihi nilai
 * temuannya, dan orang yang bukan Setba ikut mencatat.
 */
class CatatLaporanBaruTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([DataMasterSeeder::class]);
    }

    private function setba(): User
    {
        return User::where('peran', 'setba')->firstOrFail();
    }

    private function isiSurat(array $ganti = []): array
    {
        return array_merge([
            'sumber' => 'LHP',
            'nomor' => '900/LHP/XVIII/08/2026',
            'tgl_surat' => now()->subDays(20)->toDateString(),
            'tgl_terima' => now()->subDays(16)->toDateString(),
            'berkas' => 1,
            'ke' => 2,
        ], $ganti);
    }

    private function isiTemuan(array $ganti = []): array
    {
        $satker = Satker::firstOrFail();
        $kategori = KategoriTemuan::where('sumber', 'LHP')->firstOrFail();
        $bentuk = Referensi::jenis(JenisReferensi::BENTUK_TL)->firstOrFail();

        return array_merge([
            'aksi' => 'lanjut',
            'temuan' => [[
                'nomor_pada_surat' => '4.2.1',
                'satker' => [$satker->id],
                'judul' => 'Kelebihan pembayaran honorarium narasumber',
                'sebab' => 'Verifikasi tarif tidak dilakukan.',
                'akibat' => 'Belanja negara melebihi hak.',
                'kategori' => $kategori->id,
                'kategori_intern' => Referensi::jenis(JenisReferensi::KATEGORI_INTERN)->value('id'),
                'nilai' => '12400000',
                'rekom' => [[
                    'uraian' => 'Menarik kelebihan pembayaran dan menyetorkannya ke kas negara.',
                    /* Rekomendasinya menuntut penyetoran, jadi sifatnya yang
                       menyangkut kerugian negara — bukan sekadar baris pertama
                       daftarnya. */
                    'sifat' => Referensi::jenis(JenisReferensi::SIFAT_REKOM)
                        ->where('nama', 'Informasi kerugian negara')->value('id'),
                    'catatan' => 'Bukti setor wajib dilampiri Nota Konfirmasi KPPN.',
                    /* Bentuk tindak lanjut dan satuan kerjanya berdiri satu
                       tingkat di bawah rekomendasi: satu rekomendasi bisa
                       menuntut beberapa bentuk sekaligus.

                       Nominalnya disebut per satuan kerja, bukan sekali untuk
                       seluruh rekomendasi: itulah yang ditagihkan kepada
                       masing-masing, dan itulah yang mereka lihat. */
                    'tindakan' => [[
                        'bentuk' => $bentuk->id,
                        'sasaran' => [['satker' => $satker->id, 'nilai' => '12400000']],
                    ]],
                ]],
            ]],
        ], $ganti);
    }

    public function test_setba_bisa_mencatat_laporan_dari_awal_sampai_terkirim(): void
    {
        $this->actingAs($this->setba());

        $this->post(route('laporan.baru.surat'), $this->isiSurat())->assertRedirect();
        $this->post(route('laporan.baru.temuan'), $this->isiTemuan())->assertRedirect();
        $this->post(route('laporan.baru.ajukan'))->assertRedirect();

        $lap = Laporan::where('nomor', '900/LHP/XVIII/08/2026')->firstOrFail();

        $this->assertCount(1, $lap->temuan);
        $this->assertSame(12_400_000, (int) $lap->temuan->first()->nilai);

        $rek = $lap->temuan->first()->rekomendasi->first();
        $this->assertSame(StatusTindakLanjut::BT, $rek->status);
        /* Tingkat 2 belum berjalan: kolomnya NULL, dan yang memegang
           berkasnya terbaca dari sasarannya. */
        $this->assertNull($rek->posisi);
        $this->assertSame(PosisiBerkas::SATKER, $rek->posisiTampil());

        $this->assertSame(
            $lap->temuan->first()->satkers->first()->id,
            $rek->daftarSasaran()->first()->satker_id);

        // Tagihan rekomendasi = jumlah bagian tiap satuan kerja.
        $this->assertSame(12_400_000, (int) $rek->nilai_pulih);
        $this->assertTrue($rek->nilaiSelaras());

        // tenggatnya dihitung dari tanggal terima, bukan tanggal pencatatan
        $this->assertTrue($rek->tenggat_jawab->greaterThan($lap->tgl_terima));

        // rekam jejaknya dimulai
        $this->assertNotEmpty($rek->riwayat);

        /* Sifat rekomendasi ikut tersimpan. Kolomnya sudah lama berdiri tapi
           tidak pernah punya isian maupun daftar pilihan — dan selama begitu,
           seluruh 114 rekomendasi contoh kosong sifatnya. */
        $this->assertNotNull($rek->sifat_id);
        $this->assertSame('Informasi kerugian negara', $rek->sifat->nama);

        /* Tanggalnya ikut turun ke tindakannya, bukan cuma berdiri di kepala
           rekomendasi. Selama satu rekomendasi masih satu bentuk, isinya sama;
           penjaga ini yang membuatnya tetap sama saat bentuknya jadi dua. */
        $tindakan = $rek->tindakan->first();
        $this->assertNotNull($tindakan->tgl_renaksi);
        $this->assertTrue($tindakan->renaksi()->isSameDay($rek->tenggat_jawab));
    }

    /**
     * Satu rekomendasi, dua bentuk tindak lanjut, dua satuan kerja berbeda.
     *
     * Inilah yang selama ini tidak bisa dicatat: surat menuntut menyetor
     * kelebihannya DAN membenahi prosedurnya, dan yang menyetor bukan yang
     * membenahi. Selama formulirnya memaksa satu bentuk, yang begitu harus
     * dipecah jadi dua rekomendasi — dan uraiannya ditulis dua kali padahal
     * suratnya cuma menyebut satu.
     */
    public function test_satu_rekomendasi_bisa_menuntut_dua_bentuk_tindak_lanjut(): void
    {
        $a = Satker::orderBy('id')->firstOrFail();
        $b = Satker::orderBy('id')->skip(1)->firstOrFail();

        $setor = Referensi::jenis(JenisReferensi::BENTUK_TL)
            ->where('nama', 'Penyetoran ke kas negara')->firstOrFail();
        $prosedur = Referensi::jenis(JenisReferensi::BENTUK_TL)
            ->where('nama', 'Perbaikan sistem pengendalian intern')->firstOrFail();

        $this->actingAs($this->setba());
        $this->post(route('laporan.baru.surat'), $this->isiSurat(['nomor' => '903/LHP/XVIII/08/2026']));
        $this->post(route('laporan.baru.temuan'), $this->isiTemuan([
            'temuan' => [array_merge(
                collect($this->isiTemuan()['temuan'][0])->except(['satker', 'rekom'])->all(),
                [
                    'satker' => [$a->id, $b->id],
                    'nilai' => '25000000',
                    'rekom' => [[
                        'uraian' => 'Menyetorkan kelebihannya dan membenahi prosedur verifikasinya.',
                        'sifat' => '',
                        'catatan' => '',
                        'tindakan' => [
                            [
                                'bentuk' => $setor->id,
                                'sasaran' => [['satker' => $a->id, 'nilai' => '25000000']],
                            ],
                            [
                                'bentuk' => $prosedur->id,
                                'sasaran' => [['satker' => $b->id, 'nilai' => '']],
                            ],
                        ],
                    ]],
                ]
            )],
        ]))->assertRedirect();
        $this->post(route('laporan.baru.ajukan'))->assertRedirect();

        $lap = Laporan::where('nomor', '903/LHP/XVIII/08/2026')->firstOrFail();
        $rek = $lap->temuan->first()->rekomendasi->first();

        $this->assertCount(2, $rek->tindakan, 'dua bentuk tindak lanjut harus tersimpan');

        $bentuk = $rek->tindakan->map(fn ($tk) => $tk->namaBentuk())->all();
        $this->assertSame(['Penyetoran ke kas negara', 'Perbaikan sistem pengendalian intern'],
            $bentuk, 'urutannya mengikuti urutan pengisian');

        /* Tiap bentuk membawa satuan kerjanya sendiri — bukan keduanya
           menanggung keduanya. */
        $this->assertSame([$a->id], $rek->tindakan[0]->sasaran->pluck('satker_id')->all());
        $this->assertSame([$b->id], $rek->tindakan[1]->sasaran->pluck('satker_id')->all());

        // Tagihannya dijumlah lintas bentuk, bukan cuma bentuk pertama.
        $this->assertSame(25_000_000, (int) $rek->nilai_pulih);
        $this->assertSame(25_000_000, (int) $rek->tindakan[0]->sasaran->first()->nilai);
        $this->assertSame(0, (int) $rek->tindakan[1]->sasaran->first()->nilai);
    }

    /**
     * Rencana aksi per bentuk: yang diketik menang, yang kosong ikut hitungan.
     *
     * Surat susulan kerap memberi tenggat berbeda untuk bentuk yang berbeda —
     * perbaikan fisik lazim diberi waktu lebih panjang daripada yang cuma
     * menuntut surat.
     */
    public function test_rencana_aksi_bisa_berbeda_tiap_bentuk(): void
    {
        $a = Satker::orderBy('id')->firstOrFail();
        $b = Satker::orderBy('id')->skip(1)->firstOrFail();

        $setor = Referensi::jenis(JenisReferensi::BENTUK_TL)
            ->where('nama', 'Penyetoran ke kas negara')->firstOrFail();
        $fisik = Referensi::jenis(JenisReferensi::BENTUK_TL)
            ->where('nama', 'Perbaikan hasil pekerjaan')->firstOrFail();

        $lebihPanjang = now()->addDays(120)->toDateString();

        $this->actingAs($this->setba());
        $this->post(route('laporan.baru.surat'), $this->isiSurat(['nomor' => '905/LHP/XVIII/08/2026']));
        $this->post(route('laporan.baru.temuan'), $this->isiTemuan([
            'temuan' => [array_merge(
                collect($this->isiTemuan()['temuan'][0])->except(['satker', 'rekom'])->all(),
                [
                    'satker' => [$a->id, $b->id],
                    'nilai' => '40000000',
                    'rekom' => [[
                        'uraian' => 'Menyetorkan kelebihannya dan memperbaiki pekerjaannya.',
                        'sifat' => '', 'catatan' => '',
                        'tindakan' => [
                            [
                                'bentuk' => $setor->id,
                                'tgl_renaksi' => '',
                                'sasaran' => [['satker' => $a->id, 'nilai' => '40000000']],
                            ],
                            [
                                'bentuk' => $fisik->id,
                                'tgl_renaksi' => $lebihPanjang,
                                'sasaran' => [['satker' => $b->id, 'nilai' => '']],
                            ],
                        ],
                    ]],
                ]
            )],
        ]))->assertRedirect();
        $this->post(route('laporan.baru.ajukan'))->assertRedirect();

        $rek = Laporan::where('nomor', '905/LHP/XVIII/08/2026')->firstOrFail()
            ->temuan->first()->rekomendasi->first();

        // yang dikosongkan ikut tenggat rekomendasinya
        $this->assertTrue($rek->tindakan[0]->tgl_renaksi->isSameDay($rek->tenggat_jawab));
        // yang diketik menang
        $this->assertSame($lebihPanjang, $rek->tindakan[1]->tgl_renaksi->toDateString());

        /* Tiap satuan kerja mengejar tanggal yang mengikat DIRINYA, bukan
           tanggal rekomendasinya. */
        $this->assertTrue($rek->renaksiUntuk($a->id)->isSameDay($rek->tenggat_jawab));
        $this->assertSame($lebihPanjang, $rek->renaksiUntuk($b->id)->toDateString());
    }

    public function test_rencana_aksi_tidak_boleh_mendahului_tanggal_terima(): void
    {
        $this->actingAs($this->setba());
        $this->post(route('laporan.baru.surat'), $this->isiSurat(['nomor' => '906/LHP/XVIII/08/2026']));

        $isi = $this->isiTemuan();
        $isi['temuan'][0]['rekom'][0]['tindakan'][0]['tgl_renaksi'] =
            now()->subDays(60)->toDateString();

        $this->post(route('laporan.baru.temuan'), $isi)->assertSessionHas('gagal');
        $this->assertNull(Laporan::where('nomor', '906/LHP/XVIII/08/2026')->first());
    }

    /**
     * Draf yang tersimpan dalam bentuk lama tidak boleh hilang.
     *
     * Setba yang sedang mengisi formulir saat bentuk datanya berubah tidak
     * boleh kehilangan ketikannya. Yang lama menaruh `bentuk` dan `sasaran`
     * langsung di rekomendasinya; keduanya harus terbaca sebagai satu bentuk
     * tindak lanjut.
     */
    public function test_draf_bentuk_lama_ikut_terbaca(): void
    {
        $satker = Satker::firstOrFail();
        $bentuk = Referensi::jenis(JenisReferensi::BENTUK_TL)->firstOrFail();

        $this->actingAs($this->setba())->withSession(['laporan_baru' => [
            'langkah' => 2,
            'surat' => ['sumber' => 'LHP', 'nomor' => '904/LHP/XVIII/08/2026',
                'tgl_surat' => now()->subDays(20)->toDateString(),
                'tgl_terima' => now()->subDays(16)->toDateString(), 'berkas' => true],
            'temuan' => [[
                'nomor_pada_surat' => '1.1', 'satker' => [$satker->id],
                'judul' => 'Temuan dari draf lama', 'sebab' => 's', 'akibat' => 'a',
                'kategori' => KategoriTemuan::where('sumber', 'LHP')->value('id'),
                'kategori_intern' => '', 'nilai' => '1000000',
                'rekom' => [[
                    'uraian' => 'Uraian dari draf lama.',
                    'bentuk' => $bentuk->id,
                    'sifat' => '', 'catatan' => '',
                    'sasaran' => [['satker' => $satker->id, 'nilai' => '1000000']],
                ]],
            ]],
        ]])->get(route('laporan.baru'))->assertOk()
            ->assertSee('Uraian dari draf lama.', false)
            ->assertSee('tindakan][0][bentuk', false);
    }

    /* Inilah keadaan yang jadi alasan seluruh perubahan bentuk data:
       satu temuan mengenai dua satuan kerja, dan satu rekomendasi ditujukan ke
       keduanya dengan nominal dipecah. */
    public function test_temuan_dan_rekomendasi_bisa_menyebut_beberapa_satuan_kerja(): void
    {
        $a = Satker::orderBy('id')->firstOrFail();
        $b = Satker::orderBy('id')->skip(1)->firstOrFail();

        $this->actingAs($this->setba());
        $this->post(route('laporan.baru.surat'), $this->isiSurat(['nomor' => '901/LHP/XVIII/08/2026']));
        $this->post(route('laporan.baru.temuan'), $this->isiTemuan([
            'temuan' => [array_merge(
                collect($this->isiTemuan()['temuan'][0])->except(['satker', 'rekom'])->all(),
                [
                    'satker' => [$a->id, $b->id],
                    'nilai' => '30000000',
                    'rekom' => [[
                        'uraian' => 'Menyetorkan kelebihan pembayaran ke kas negara.',
                        'catatan' => '',
                        'tindakan' => [[
                            'bentuk' => Referensi::jenis(JenisReferensi::BENTUK_TL)->value('id'),
                            'sasaran' => [
                                ['satker' => $a->id, 'nilai' => '20000000'],
                                ['satker' => $b->id, 'nilai' => '10000000'],
                            ],
                        ]],
                    ]],
                ]
            )],
        ]))->assertRedirect();
        $this->post(route('laporan.baru.ajukan'))->assertRedirect();

        $lap = Laporan::where('nomor', '901/LHP/XVIII/08/2026')->firstOrFail();
        $tem = $lap->temuan->first();

        $this->assertCount(2, $tem->satkers, 'temuan harus menyebut dua satuan kerja');

        $rek = $tem->rekomendasi->first();
        $baris = $rek->daftarSasaran();

        $this->assertCount(2, $baris);
        $this->assertSame(30_000_000, (int) $rek->nilai_pulih);
        $this->assertSame(20_000_000, (int) $baris->firstWhere('satker_id', $a->id)->nilai);
        $this->assertSame(10_000_000, (int) $baris->firstWhere('satker_id', $b->id)->nilai);
        $this->assertTrue($rek->nilaiSelaras());

        /* Keduanya berangkat dari titik yang sama, tapi sejak ini berjalan
           sendiri-sendiri. */
        $this->assertTrue($baris->every(fn ($x) => $x->posisi === PosisiBerkas::SATKER));
        $this->assertNull($rek->posisi);
    }

    /* Rekomendasi tanpa satuan kerja akan berdiri di daftar tanpa pernah
       bergerak, dan tidak ada yang tahu itu salah. */
    public function test_rekomendasi_tanpa_satuan_kerja_ditahan(): void
    {
        $this->actingAs($this->setba());
        $this->post(route('laporan.baru.surat'), $this->isiSurat(['nomor' => '902/LHP/XVIII/08/2026']));

        $isi = $this->isiTemuan();
        $isi['temuan'][0]['rekom'][0]['tindakan'][0]['sasaran'] = [['satker' => '', 'nilai' => '']];

        $this->post(route('laporan.baru.temuan'), $isi)
            ->assertSessionHas('gagal');

        $this->assertNull(Laporan::where('nomor', '902/LHP/XVIII/08/2026')->first());
    }

    public function test_tanggal_terima_tidak_boleh_mendahului_tanggal_surat(): void
    {
        $this->actingAs($this->setba())
            ->post(route('laporan.baru.surat'), $this->isiSurat([
                'tgl_surat' => now()->subDays(5)->toDateString(),
                'tgl_terima' => now()->subDays(9)->toDateString(),
            ]))
            ->assertSessionHasErrors('tgl_terima');
    }

    public function test_tanggal_tidak_boleh_di_masa_depan(): void
    {
        $this->actingAs($this->setba())
            ->post(route('laporan.baru.surat'), $this->isiSurat([
                'tgl_terima' => now()->addDay()->toDateString(),
            ]))
            ->assertSessionHasErrors('tgl_terima');
    }

    /* Kalau tagihan melebihi nilai temuannya, salah satunya salah ketik — dan
       mana pun yang salah, angkanya akan salah selamanya. */
    public function test_tagihan_tidak_boleh_melebihi_nilai_temuan(): void
    {
        $this->actingAs($this->setba());
        $this->post(route('laporan.baru.surat'), $this->isiSurat());

        $isi = $this->isiTemuan();
        $isi['temuan'][0]['nilai'] = '5000000';
        $isi['temuan'][0]['rekom'][0]['nilai_pulih'] = '12400000';

        $this->post(route('laporan.baru.temuan'), $isi)
            ->assertRedirect()
            ->assertSessionHas('gagal');

        $this->post(route('laporan.baru.ajukan'))->assertRedirect();
        $this->assertSame(0, Laporan::count(), 'laporan tersimpan padahal isiannya tidak sah');
    }

    public function test_isian_yang_belum_lengkap_tidak_bisa_diajukan(): void
    {
        $this->actingAs($this->setba());
        $this->post(route('laporan.baru.surat'), $this->isiSurat());

        $isi = $this->isiTemuan();
        $isi['temuan'][0]['judul'] = '';

        $this->post(route('laporan.baru.temuan'), $isi);
        $this->post(route('laporan.baru.ajukan'))->assertRedirect();

        $this->assertSame(0, Laporan::count());
    }

    public function test_menambah_temuan_tidak_menghapus_isian_yang_sudah_diketik(): void
    {
        $this->actingAs($this->setba());
        $this->post(route('laporan.baru.surat'), $this->isiSurat());

        $isi = $this->isiTemuan(['aksi' => 'tambah-temuan']);
        $this->post(route('laporan.baru.temuan'), $isi);

        $draf = session('laporan_baru');
        $this->assertCount(2, $draf['temuan'], 'temuan kedua tidak ditambahkan');
        $this->assertSame('Kelebihan pembayaran honorarium narasumber', $draf['temuan'][0]['judul'],
            'isian temuan pertama hilang saat menambah temuan');
    }

    public function test_hanya_setba_yang_boleh_mencatat(): void
    {
        $balai = User::where('peran', 'satker')->firstOrFail();

        $this->actingAs($balai)->get(route('laporan.baru'))->assertForbidden();
        $this->actingAs($balai)->post(route('laporan.baru.surat'), $this->isiSurat())->assertForbidden();
    }
}
