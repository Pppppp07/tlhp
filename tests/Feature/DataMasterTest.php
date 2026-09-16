<?php

namespace Tests\Feature;

use App\Enums\JenisReferensi;
use App\Models\KategoriTemuan;
use App\Models\Referensi;
use App\Models\Temuan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\PakaiDataContoh;
use Tests\TestCase;

/**
 * Data master: dua daftar yang boleh berubah, dan satu aturan yang tidak boleh
 * dilanggar — tidak ada yang dihapus, hanya dinonaktifkan.
 */
class DataMasterTest extends TestCase
{
    use PakaiDataContoh, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanDataContoh();
        $this->masuk('setba@contoh.test');
    }

    public function test_kategori_internal_bisa_ditambah_diganti_nama_dan_dipadamkan(): void
    {
        $this->post(route('master.tambah'), ['nama' => 'Kelebihan pembayaran'])->assertRedirect();
        $baru = Referensi::where('jenis', JenisReferensi::KATEGORI_INTERN->value)
            ->where('nama', 'Kelebihan pembayaran')->first();
        $this->assertNotNull($baru);
        $this->assertTrue($baru->aktif);

        $this->post(route('master.simpan', $baru), ['nama' => 'Kelebihan pembayaran belanja', 'warna' => 'ungu'])
            ->assertRedirect();
        $baru->refresh();
        $this->assertSame('Kelebihan pembayaran belanja', $baru->nama);
        $this->assertSame('ungu', $baru->warna->value);

        $this->post(route('master.saklar', $baru))->assertRedirect();
        $this->assertFalse($baru->fresh()->aktif);
    }

    public function test_kategori_yang_dipadamkan_tidak_ikut_hilang_dari_temuan_lama(): void
    {
        $dipakai = Referensi::where('jenis', JenisReferensi::KATEGORI_INTERN->value)
            ->whereIn('id', Temuan::whereNotNull('kategori_intern_id')->pluck('kategori_intern_id'))
            ->firstOrFail();
        $jumlah = Temuan::where('kategori_intern_id', $dipakai->id)->count();

        $this->post(route('master.saklar', $dipakai))->assertRedirect();

        $this->assertFalse($dipakai->fresh()->aktif);
        $this->assertSame($jumlah, Temuan::where('kategori_intern_id', $dipakai->id)->count());
    }

    public function test_kategori_temuan_bertambah_menurut_sumber_laporannya(): void
    {
        $this->post(route('master.temuan.tambah'), ['nama' => 'Kelemahan SPI lanjutan', 'sumber' => 'LHP'])
            ->assertRedirect();

        $baru = KategoriTemuan::where('nama', 'Kelemahan SPI lanjutan')->first();
        $this->assertNotNull($baru);
        $this->assertSame('LHP', $baru->sumber->value);

        /* Nama yang sama pada sumber yang sama ditolak. */
        $this->post(route('master.temuan.tambah'), ['nama' => 'kelemahan spi lanjutan', 'sumber' => 'LHP'])
            ->assertSessionHas('gagal');
        $this->assertSame(1, KategoriTemuan::where('sumber', 'LHP')
            ->whereRaw('LOWER(nama) = ?', ['kelemahan spi lanjutan'])->count());

        /* Yang belum dipakai temuan boleh berganti nama … */
        $this->post(route('master.temuan.simpan', $baru), ['nama' => 'Kelemahan SPI'])->assertRedirect();
        $this->assertSame('Kelemahan SPI', $baru->fresh()->nama);

        /* … yang sudah dipakai tidak. */
        $dipakai = KategoriTemuan::whereIn('id', Temuan::whereNotNull('kategori_temuan_id')
            ->pluck('kategori_temuan_id'))->firstOrFail();
        $nama = $dipakai->nama;
        $this->post(route('master.temuan.simpan', $dipakai), ['nama' => 'Nama baru'])
            ->assertSessionHas('gagal');
        $this->assertSame($nama, $dipakai->fresh()->nama);
    }

    public function test_hanya_setba_yang_boleh_mengubah(): void
    {
        $kategori = Referensi::where('jenis', JenisReferensi::KATEGORI_INTERN->value)->firstOrFail();

        $this->masuk('uki@contoh.test');
        $this->get(route('master'))->assertForbidden();
        $this->post(route('master.saklar', $kategori))->assertForbidden();

        $this->masuk('medan@contoh.test');
        $this->post(route('master.tambah'), ['nama' => 'Coba'])->assertForbidden();
    }

    public function test_daftar_yang_menyalin_sop_tidak_bisa_disunting_dari_sini(): void
    {
        $bentuk = Referensi::where('jenis', JenisReferensi::BENTUK_TL->value)->firstOrFail();

        $this->post(route('master.simpan', $bentuk), ['nama' => 'Diubah diam-diam'])->assertForbidden();
        $this->post(route('master.saklar', $bentuk))->assertForbidden();
    }
}
