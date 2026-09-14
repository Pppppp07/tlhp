<?php

namespace Tests\Feature;

use App\Models\Laporan;
use App\Models\User;
use Database\Seeders\DataMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Tanggal laporan: yang diakui, yang dicatat mesin, dan yang tidak bisa
 * diubah lagi.
 *
 * Kekhawatirannya masuk akal — tanggal yang diketik sendiri bisa dipakai
 * menggeser tenggat. Tapi menggantinya dengan "otomatis hari ini" tidak
 * menutup celahnya, cuma memindahkannya: kalau tanggal diterima = tanggal
 * simpan, maka yang memilih KAPAN menekan simpan yang memilih tenggatnya, dan
 * tidak ada lagi angka pembanding untuk memergokinya.
 *
 * Jadi keduanya disimpan terpisah:
 *
 *   tgl_terima    diakui   — dari cap terima suratnya, bisa dicek ke berkasnya
 *   dicatat_pada  fakta    — diisi `now()`, tidak pernah dari permintaan
 *
 * dan jarak antara keduanya ditampilkan. Berkas ini menjaga ketiga hal yang
 * membuat susunan itu bekerja.
 */
class TanggalLaporanTest extends TestCase
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

    /**
     * Tanggal pencatatan diisi sistem, bukan dari permintaan.
     *
     * Ini penjaga terpenting di berkas ini: kalau `dicatat_pada` bisa
     * dititipkan lewat formulir, seluruh gunanya sebagai pembanding hilang —
     * dan hilangnya diam-diam, karena angkanya tetap tampil seperti biasa.
     */
    public function test_tanggal_pencatatan_tidak_bisa_dititipkan_lewat_formulir(): void
    {
        $this->actingAs($this->setba());

        $this->post(route('laporan.baru.surat'), [
            'sumber' => 'LHP',
            'nomor' => '901/LHP/XVIII/08/2026',
            'tgl_surat' => now()->subDays(20)->toDateString(),
            'tgl_terima' => now()->subDays(16)->toDateString(),
            /* Dititipkan dengan sengaja. Kalau lolos, laporannya akan mengaku
               tercatat empat tahun lalu. */
            'dicatat_pada' => now()->subYears(4)->toDateString(),
            'dicatat_oleh' => 999,
            'ke' => 2,
        ])->assertRedirect();

        $this->kirimTemuanLalu();

        $lap = Laporan::latest('id')->firstOrFail();
        $this->assertSame(now()->toDateString(), $lap->dicatat_pada->toDateString(),
            'tanggal pencatatan terbaca dari permintaan, bukan dari sistem');
        $this->assertSame($this->setba()->id, $lap->dicatat_oleh,
            'pencatat terbaca dari permintaan, bukan dari yang sedang masuk');
    }

    /**
     * Jarak antara surat diterima dan surat dicatat terhitung, dan tandanya
     * hanya muncul kalau memang jauh.
     */
    public function test_jeda_pencatatan_terhitung_dan_tandanya_punya_ambang(): void
    {
        $buat = fn (int $terima) => Laporan::create([
            'sumber' => 'LHP',
            'nomor' => "9{$terima}/LHP/XVIII/08/2026",
            'tgl_surat' => now()->subDays($terima + 10)->toDateString(),
            'tgl_terima' => now()->subDays($terima)->toDateString(),
            'dicatat_pada' => now()->toDateString(),
            'dicatat_oleh' => $this->setba()->id,
        ]);

        $lap = $buat(20);
        $this->assertSame(20, $lap->jedaPencatatan());
        $this->assertGreaterThan(Laporan::BATAS_JEDA_CATAT, $lap->jedaPencatatan());

        $dekat = $buat(2);

        $this->assertSame(2, $dekat->jedaPencatatan());
        $this->assertLessThanOrEqual(Laporan::BATAS_JEDA_CATAT, $dekat->jedaPencatatan());
    }

    /**
     * Laporan yang sudah tercatat tidak punya pintu untuk diubah.
     *
     * Manipulasi yang benar-benar berbahaya bukan saat mengetik pertama kali —
     * waktu itu suratnya masih di tangan dan gampang dicocokkan. Yang berbahaya
     * mengubahnya belakangan, saat tenggatnya sudah mepet dan tidak ada lagi
     * yang membuka surat aslinya.
     *
     * Sekarang pintunya memang tidak ada. Penjaga ini supaya tetap begitu:
     * kalau suatu saat ada yang menambahkan layar ubah laporan, uji ini gagal
     * lebih dulu dan pertanyaannya terpaksa dijawab — siapa yang boleh, dan
     * jejaknya di mana.
     */
    public function test_tidak_ada_rute_yang_bisa_mengubah_laporan_yang_sudah_dicatat(): void
    {
        $menulis = collect(Route::getRoutes())
            ->filter(fn ($r) => str_contains($r->uri(), '{laporan}'))
            ->filter(fn ($r) => array_intersect($r->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']))
            ->map(fn ($r) => implode('|', $r->methods()).' '.$r->uri());

        $this->assertTrue($menulis->isEmpty(),
            'ada rute yang bisa menulis ke laporan yang sudah tercatat: '
            .$menulis->join('; ')
            .' — kalau ini memang disengaja, tanggal terima wajib punya jejak perubahannya');
    }

    /**
     * Pindaian surat tidak lagi menahan pencatatan.
     *
     * Surat kerap sampai lebih dulu daripada pindaiannya. Menahan pencatatan
     * sampai pindaiannya ada justru merugikan: tenggat jawabannya sudah
     * berjalan sementara belum ada satu pun satuan kerja yang bisa
     * mengerjakannya. Ketiadaannya ditandai, bukan ditahan.
     */
    public function test_laporan_tanpa_pindaian_surat_tetap_bisa_dicatat(): void
    {
        $this->actingAs($this->setba());

        $this->post(route('laporan.baru.surat'), [
            'sumber' => 'LHP',
            'nomor' => '902/LHP/XVIII/08/2026',
            'tgl_surat' => now()->subDays(20)->toDateString(),
            'tgl_terima' => now()->subDays(16)->toDateString(),
            'berkas' => 0,
            'ke' => 2,
        ])->assertRedirect();

        $this->kirimTemuanLalu();

        $lap = Laporan::latest('id')->firstOrFail();
        $this->assertSame('902/LHP/XVIII/08/2026', $lap->nomor);
        $this->assertTrue($lap->lampiran->isEmpty(),
            'laporan tanpa pindaian seharusnya tidak melahirkan lampiran kosong');
    }

    /** Halaman laporan menyebut yang belum bertautan, bukan diam saja. */
    public function test_halaman_laporan_menyebut_tautan_yang_belum_ada(): void
    {
        $this->actingAs($this->setba());

        $this->post(route('laporan.baru.surat'), [
            'sumber' => 'LHP',
            'nomor' => '903/LHP/XVIII/08/2026',
            'tgl_surat' => now()->subDays(20)->toDateString(),
            'tgl_terima' => now()->subDays(16)->toDateString(),
            'berkas' => 0,
            'ke' => 2,
        ])->assertRedirect();

        $this->kirimTemuanLalu();

        $lap = Laporan::latest('id')->firstOrFail();
        $this->get(route('laporan.show', $lap))
            ->assertOk()
            ->assertSee('belum ditautkan', false);
    }

    /**
     * Langkah dua dan pengajuannya, dipakai bersama beberapa uji di atas.
     * Isinya paling sederhana yang masih sah — yang diuji di sini tanggalnya,
     * bukan temuannya.
     */
    private function kirimTemuanLalu(): void
    {
        $satker = \App\Models\Satker::firstOrFail();
        $kategori = \App\Models\KategoriTemuan::where('sumber', 'LHP')->firstOrFail();
        $bentuk = \App\Models\Referensi::jenis(\App\Enums\JenisReferensi::BENTUK_TL)->firstOrFail();

        $this->post(route('laporan.baru.temuan'), [
            'aksi' => 'lanjut',
            'temuan' => [[
                'nomor_pada_surat' => '1.1',
                'satker' => [$satker->id],
                'judul' => 'Kelebihan pembayaran honorarium narasumber',
                'sebab' => 'Verifikasi tarif tidak dilakukan.',
                'akibat' => 'Belanja negara melebihi hak.',
                'kategori' => $kategori->id,
                'kategori_intern' => \App\Models\Referensi::jenis(
                    \App\Enums\JenisReferensi::KATEGORI_INTERN)->value('id'),
                'nilai' => '12400000',
                'rekom' => [[
                    'uraian' => 'Menarik kelebihan pembayaran dan menyetorkannya ke kas negara.',
                    'sifat' => \App\Models\Referensi::jenis(\App\Enums\JenisReferensi::SIFAT_REKOM)
                        ->where('nama', 'Informasi kerugian negara')->value('id'),
                    'tindakan' => [[
                        'bentuk' => $bentuk->id,
                        'sasaran' => [['satker' => $satker->id, 'nilai' => '12400000']],
                    ]],
                ]],
            ]],
        ])->assertRedirect();

        $this->post(route('laporan.baru.ajukan'))->assertRedirect();
    }
}
