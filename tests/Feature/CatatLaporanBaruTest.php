<?php

namespace Tests\Feature;

use App\Enums\PosisiBerkas;
use App\Models\DrafLaporan;
use App\Models\KategoriTemuan;
use App\Models\Lampiran;
use App\Models\Laporan;
use App\Models\Notifikasi;
use App\Models\Referensi;
use App\Models\Rekomendasi;
use App\Models\Satker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\PakaiDataContoh;
use Tests\TestCase;

/**
 * Mencatat laporan baru: tiga langkah, draf yang tidak hilang, dan satu laporan
 * yang pecah jadi penugasan terpisah untuk tiap satuan kerja.
 */
class CatatLaporanBaruTest extends TestCase
{
    use PakaiDataContoh, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanDataContoh();
        $this->masuk('setba@contoh.test');
    }

    private function isiSurat(): void
    {
        $this->post(route('laporan.baru.simpan'), [
            'aksi' => 'maju',
            'surat' => ['sumber' => 'LHP', 'nomor' => '77/LHP/XVIII/09/2026',
                'tgl_surat' => '2026-08-01', 'tgl_terima' => '2026-08-05'],
            'berkas' => ['judul' => 'Laporan Hasil Pemeriksaan 77/LHP/XVIII/09/2026',
                'tautan' => 'https://contoh.test/77.pdf'],
        ])->assertRedirect(route('laporan.baru'));
    }

    /** Isian langkah dua, berikut tombolnya. */
    private function isiTemuan(string $aksi = 'maju'): void
    {
        $d = DrafLaporan::where('user_id', auth()->id())->firstOrFail()->isian;
        $t = $d['temuan'][0];
        $r = $t['rekom'][0];
        $tk = $r['tindakan'][0];

        $medan = Satker::where('nama_pendek', 'Balai Wil. I Medan')->firstOrFail();
        $poli = Satker::where('nama_pendek', 'Politeknik PU')->firstOrFail();
        $kategori = KategoriTemuan::where('sumber', 'LHP')->where('nama', 'Kepatuhan')->firstOrFail();
        $kerugian = Referensi::where('jenis', 'sifat_rekom')->where('nama', 'Informasi kerugian negara')->firstOrFail();

        $this->post(route('laporan.baru.simpan'), [
            'aksi' => $aksi,
            't' => [
                'id' => $t['id'],
                'nomor' => '1.1',
                'judul' => 'Honorarium Narasumber Dibayarkan Melebihi Standar Biaya',
                'sebab' => 'Verifikasi SPJ tidak membandingkan dengan standar biaya masukan.',
                'akibat' => 'Kelebihan pembayaran honorarium yang membebani negara.',
                'kategori' => (string) $kategori->id,
                'intern' => $t['intern'],
                'satker' => [$medan->id, $poli->id],
                'rekom' => [
                    $r['id'] => [
                        'uraian' => 'Menarik kelebihan pembayaran dan menyetorkannya ke kas negara.',
                        'ref_lhp' => 'I.1.1.a',
                        'sifat' => (string) $kerugian->id,
                        'tindakan' => [
                            $tk['id'] => [
                                'bentuk' => 'Penyetoran ke kas negara',
                                'satker' => [$medan->id, $poli->id],
                                'nilai' => [$medan->id => '7500000', $poli->id => '12500000'],
                                'dokumen' => ['Bukti setor ke kas negara (SSBP)', 'Nota Konfirmasi KPPN'],
                                'tgl_renaksi' => '2026-10-04',
                                'target' => '',
                                'catatan' => 'Lampirkan bukti setor dan NTPN.',
                            ],
                        ],
                    ],
                ],
            ],
        ])->assertRedirect(route('laporan.baru'));
    }

    public function test_laporan_baru_pecah_jadi_penugasan_tiap_satuan_kerja(): void
    {
        $sebelum = Laporan::count();

        $this->isiSurat();
        $this->isiTemuan();
        $this->post(route('laporan.baru.simpan'), ['aksi' => 'ajukan'])
            ->assertRedirect();

        $lap = Laporan::where('nomor', '77/LHP/XVIII/09/2026')->first();
        $this->assertNotNull($lap);
        $this->assertSame($sebelum + 1, Laporan::count());

        $tem = $lap->temuan->first();
        $this->assertSame('1.1', $tem->nomor_pada_surat);
        $this->assertCount(2, $tem->satkers);

        /* Kodenya Ref IDT: tahun LHP . nomor pendek suratnya . Ref LHP. */
        $rek = $tem->rekomendasi->first();
        $this->assertSame('2026.77.I.1.1.a', $rek->kode);
        $this->assertSame(20000000, (int) $rek->nilai_pulih);
        $this->assertSame('2026-10-04', $rek->tenggat_jawab->toDateString());

        /* Satu tindak lanjut, dua penugasan — masing-masing dengan bagiannya. */
        $baris = $rek->daftarSasaran();
        $this->assertCount(2, $baris);
        foreach ($baris as $x) {
            $this->assertSame(PosisiBerkas::SATKER, $x->pos());
            /* Dokumen yang diminta berdiri sendiri untuk tiap satuan kerja. */
            $this->assertCount(2, $x->permintaanDokumen->first()->item);
        }

        /* Surat pemeriksaannya melekat pada laporan dan pada rekomendasinya,
           dan keduanya bertanda surat asli. */
        $this->assertTrue(Lampiran::where('laporan_id', $lap->id)->where('surat_asli', true)->exists());
        $this->assertTrue(Lampiran::where('rekomendasi_id', $rek->id)->where('surat_asli', true)->exists());

        /* Tiap satuan kerja dikabari bebannya sendiri. */
        $kabar = Notifikasi::where('rekomendasi_id', $rek->id)->get();
        $this->assertCount(2, $kabar);
        foreach ($kabar as $k) {
            $this->assertStringContainsString('Rekomendasi baru untuk Anda', $k->aksi);
            $this->assertSame('r-kepala', $k->blok);
            $this->assertCount(1, $k->satker);
        }

        $this->assertStringContainsString('Rekomendasi dikirim ke', $rek->riwayat->last()->aksi);

        /* Drafnya habis begitu terkirim. */
        $this->assertSame(0, DrafLaporan::count());
    }

    public function test_draf_tersimpan_dan_bisa_dilanjutkan(): void
    {
        $this->isiSurat();
        $this->post(route('laporan.baru.simpan'), ['aksi' => 'simpan-draf'])
            ->assertRedirect(route('rekomendasi.index'));

        $this->assertSame(1, DrafLaporan::count());

        /* Tombol di batang atas berganti jadi "Lanjutkan draf laporan". */
        $this->get(route('rekomendasi.index'))->assertOk()->assertSee('Lanjutkan draf laporan');

        $this->get(route('laporan.baru'))->assertOk()
            ->assertSee('Melanjutkan draf yang disimpan');

        /* Isiannya utuh, termasuk langkah terakhir yang sedang dikerjakan. */
        $isian = DrafLaporan::where('user_id', auth()->id())->firstOrFail()->isian;
        $this->assertSame('77/LHP/XVIII/09/2026', $isian['surat']['nomor']);
        $this->assertSame(2, $isian['n']);
    }

    public function test_kosongkan_membuang_drafnya(): void
    {
        $this->isiSurat();
        $this->assertSame(1, DrafLaporan::count());

        $this->post(route('laporan.baru.simpan'), ['aksi' => 'kosongkan'])
            ->assertRedirect(route('laporan.baru'));

        $this->assertSame(0, DrafLaporan::count());
    }

    public function test_langkah_tidak_maju_kalau_isiannya_kurang(): void
    {
        $this->post(route('laporan.baru.simpan'), [
            'aksi' => 'maju',
            'surat' => ['sumber' => 'LHP', 'nomor' => '', 'tgl_surat' => '', 'tgl_terima' => ''],
        ])->assertRedirect(route('laporan.baru'))->assertSessionHas('gagal');

        $this->assertSame(1, DrafLaporan::where('user_id', auth()->id())->firstOrFail()->isian['n']);
    }

    public function test_nomor_surat_yang_sudah_pernah_dicatat_ditolak(): void
    {
        $nomor = Laporan::first()->nomor;

        $this->isiSurat();
        $this->isiTemuan();

        /* Nomornya diganti jadi nomor laporan yang sudah ada. */
        $this->post(route('laporan.baru.simpan'), [
            'aksi' => 'ajukan',
            'surat' => ['sumber' => 'LHP', 'nomor' => $nomor,
                'tgl_surat' => '2026-08-01', 'tgl_terima' => '2026-08-05'],
        ])->assertRedirect(route('laporan.baru'))->assertSessionHas('gagal');

        $this->assertSame(1, Laporan::where('nomor', $nomor)->count());
    }

    public function test_bukan_setba_tidak_boleh_mencatat(): void
    {
        $this->masuk('medan@contoh.test')
            ->post(route('laporan.baru.simpan'), ['aksi' => 'maju'])
            ->assertForbidden();
    }
}
