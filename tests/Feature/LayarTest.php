<?php

namespace Tests\Feature;

use App\Models\Laporan;
use App\Models\Rekomendasi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\PakaiDataContoh;
use Tests\TestCase;

/**
 * Tiap layar terbuka untuk tiap peran yang berhak — dan yang tidak berhak
 * ditolak, bukan dibiarkan melihat halaman kosong.
 *
 * Uji paling murah yang ada, dan yang paling sering menangkap: satu nama view
 * yang salah ketik atau satu pembantu yang terhapus langsung terlihat di sini.
 */
class LayarTest extends TestCase
{
    use PakaiDataContoh, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanDataContoh();
    }

    public static function peranBiasa(): array
    {
        return [
            'setba' => ['setba@contoh.test'],
            'uki' => ['uki@contoh.test'],
            'inspektorat' => ['inspektorat@contoh.test'],
            'satker' => ['medan@contoh.test'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('peranBiasa')]
    public function test_layar_utama_terbuka(string $surel): void
    {
        $this->masuk($surel);

        $this->get('/rekomendasi')->assertOk();
        $this->get('/laporan')->assertOk();
        $this->get('/kabar')->assertOk();
        $this->get('/cari?q=aset')->assertOk();
    }

    public function test_beranda_mengikuti_peran(): void
    {
        $this->masuk('setba@contoh.test')->get('/')->assertRedirect(route('rekomendasi.index'));
        $this->masuk('pimpinan@contoh.test')->get('/')->assertRedirect(route('ringkasan'));
    }

    public function test_rincian_rekomendasi_dan_laporan_terbuka(): void
    {
        $this->masuk('setba@contoh.test');

        $rek = Rekomendasi::first();
        $this->get(route('rekomendasi.show', $rek))->assertOk()->assertSee($rek->kode);

        $lap = Laporan::first();
        $this->get(route('laporan.show', $lap))->assertOk()->assertSee($lap->nomor);
    }

    public function test_ringkasan_dan_data_master_hanya_untuk_yang_berhak(): void
    {
        $this->masuk('setba@contoh.test');
        $this->get('/ringkasan')->assertOk()->assertSee('Menurut BPK · SIPTL');
        $this->get('/data-master')->assertOk()->assertSee('Kategori internal');
        $this->get('/laporan/baru')->assertOk()->assertSee('Data surat');

        $this->masuk('medan@contoh.test');
        $this->get('/data-master')->assertForbidden();
        $this->get('/laporan/baru')->assertForbidden();

        $this->masuk('uki@contoh.test');
        $this->get('/data-master')->assertForbidden();
        $this->get('/laporan/baru')->assertForbidden();
    }

    public function test_tamu_diantar_ke_halaman_masuk(): void
    {
        $this->get('/rekomendasi')->assertRedirect(route('masuk'));
        $this->get('/ringkasan')->assertRedirect(route('masuk'));
    }

    public function test_angka_keranjang_setba_sama_dengan_yang_diperagakan(): void
    {
        $halaman = $this->masuk('setba@contoh.test')->get('/rekomendasi');

        $halaman->assertOk()
            ->assertSee('Perlu saya kerjakan')
            ->assertSee('Sedang menunggu')
            ->assertSee('Urusan SIPTL');

        /* 19 dari 44 rekomendasi menunggu Setba pada data contoh — angka yang
           sama tertulis di menu dan di keranjang pertama. */
        $this->assertSame(19, $this->perluDikerjakan('setba@contoh.test'));
    }

    private function perluDikerjakan(string $surel): int
    {
        $u = $this->akun($surel);
        $terlihat = \App\Support\Terlihat::untuk($u);

        return $terlihat->rekomendasi()->with(\App\Http\Controllers\RekomendasiController::MUAT_DAFTAR)->get()
            ->map(fn ($r) => $terlihat->pangkasRekomendasi($r))
            ->filter(fn ($r) => $r->diMeja($u->peran, $u->satker_id))
            ->count();
    }
}
