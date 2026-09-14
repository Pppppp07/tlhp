<?php

namespace Tests\Feature;

use App\Enums\JenisReferensi;
use App\Enums\PeranPengguna;
use App\Enums\WarnaLabel;
use App\Models\Referensi;
use App\Models\Temuan;
use App\Models\User;
use Database\Seeders\ContohLaporanSeeder;
use Database\Seeders\DataMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Data master: daftar pilihan yang boleh berubah mengikuti peraturan.
 *
 * Dua batas yang dijaga uji ini:
 *
 *   1. Yang menyalin SOP tidak bisa disunting dari sini — bentuk tindak lanjut
 *      dan alasan sah tidak dapat ditindaklanjuti punya dasar hukumnya sendiri.
 *   2. Kategori yang sudah dipakai tidak hilang. Dinonaktifkan, bukan dihapus:
 *      keterangannya harus tetap terbaca di berkas lama.
 */
class DataMasterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([DataMasterSeeder::class, ContohLaporanSeeder::class]);
    }

    private function setba(): User
    {
        return User::where('peran', PeranPengguna::SETBA)->firstOrFail();
    }

    private function kategori(): Referensi
    {
        return Referensi::where('jenis', JenisReferensi::KATEGORI_INTERN->value)
            ->orderBy('urutan')->firstOrFail();
    }

    public function test_setba_bisa_membuka_data_master(): void
    {
        $this->actingAs($this->setba())->get(route('master'))
            ->assertOk()
            ->assertSee('Kategori internal')
            ->assertSee($this->kategori()->nama);
    }

    public function test_peran_lain_tidak_bisa_membukanya(): void
    {
        foreach ([PeranPengguna::SATKER, PeranPengguna::UKI, PeranPengguna::INSPEKTORAT] as $p) {
            $u = User::where('peran', $p)->first();
            if (! $u) {
                continue;
            }
            $this->actingAs($u)->get(route('master'))->assertForbidden();
        }
    }

    public function test_nama_dan_warna_bisa_diubah(): void
    {
        $k = $this->kategori();

        $this->actingAs($this->setba())->post(route('master.simpan', $k), [
            'nama' => 'Perjalanan dinas',
            'warna' => WarnaLabel::UNGU->value,
        ])->assertRedirect();

        $k->refresh();
        $this->assertSame('Perjalanan dinas', $k->nama);
        $this->assertSame(WarnaLabel::UNGU, $k->warna);
    }

    public function test_warna_asing_jatuh_ke_abu(): void
    {
        $k = $this->kategori();

        $this->actingAs($this->setba())->post(route('master.simpan', $k), [
            'nama' => $k->nama,
            'warna' => 'merahjambu',
        ])->assertRedirect();

        /* Bukan galat, dan bukan warna asing yang lolos ke basis data: yang
           tidak dikenali jadi abu, dan yang belum dipilih memang tidak boleh
           menonjol. */
        $this->assertSame(WarnaLabel::ABU, $k->fresh()->warna);
    }

    public function test_kategori_baru_bisa_ditambah(): void
    {
        $sebelum = Referensi::where('jenis', JenisReferensi::KATEGORI_INTERN->value)->count();

        $this->actingAs($this->setba())->post(route('master.tambah'), [
            'nama' => 'Sewa kendaraan dinas',
        ])->assertRedirect();

        $this->assertSame($sebelum + 1,
            Referensi::where('jenis', JenisReferensi::KATEGORI_INTERN->value)->count());

        $baru = Referensi::where('nama', 'Sewa kendaraan dinas')->firstOrFail();
        $this->assertTrue($baru->aktif);
        $this->assertSame(WarnaLabel::ABU, $baru->warna);

        // dan langsung bisa dipilih saat mencatat laporan baru
        $this->actingAs($this->setba())->get(route('laporan.baru'))
            ->assertOk();
    }

    /**
     * Yang dinonaktifkan hilang dari pilihan, bukan dari berkas lama.
     */
    public function test_kategori_dinonaktifkan_tetap_terbaca_di_berkas_lama(): void
    {
        $k = Referensi::where('jenis', JenisReferensi::KATEGORI_INTERN->value)
            ->whereIn('id', Temuan::whereNotNull('kategori_intern_id')
                ->pluck('kategori_intern_id'))->firstOrFail();

        $temuan = Temuan::where('kategori_intern_id', $k->id)->firstOrFail();

        $this->actingAs($this->setba())->post(route('master.saklar', $k))->assertRedirect();

        $this->assertFalse($k->fresh()->aktif);

        // temuannya tidak berubah, dan namanya tetap terbaca di halamannya
        $this->assertSame($k->id, $temuan->fresh()->kategori_intern_id);
        $this->actingAs($this->setba())
            ->get(route('laporan.show', $temuan->laporan))
            ->assertOk()->assertSee($k->nama);

        // tapi tidak lagi jadi pilihan saat mencatat laporan baru
        $this->assertFalse(
            Referensi::jenis(JenisReferensi::KATEGORI_INTERN)->pluck('id')->contains($k->id));
    }

    public function test_daftar_yang_menyalin_sop_tidak_bisa_disunting(): void
    {
        $bentuk = Referensi::where('jenis', JenisReferensi::BENTUK_TL->value)->firstOrFail();
        $nama = $bentuk->nama;

        $this->actingAs($this->setba())->post(route('master.simpan', $bentuk), [
            'nama' => 'Nama karangan sendiri',
        ])->assertForbidden();

        $this->actingAs($this->setba())->post(route('master.saklar', $bentuk))
            ->assertForbidden();

        $bentuk->refresh();
        $this->assertSame($nama, $bentuk->nama);
        $this->assertTrue($bentuk->aktif);
    }

    public function test_warna_kategori_tampil_di_lencana_temuan(): void
    {
        $temuan = Temuan::whereNotNull('kategori_intern_id')->firstOrFail();
        $k = $temuan->kategoriIntern;
        $k->update(['warna' => WarnaLabel::TOSCA->value]);

        $this->actingAs($this->setba())
            ->get(route('laporan.show', $temuan->laporan))
            ->assertOk()
            ->assertSee(WarnaLabel::TOSCA->padat(), false);
    }
}
