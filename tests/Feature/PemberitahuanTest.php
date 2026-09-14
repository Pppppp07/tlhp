<?php

namespace Tests\Feature;

use App\Enums\PosisiBerkas;
use App\Models\Notifikasi;
use App\Models\Rekomendasi;
use App\Models\User;
use App\Support\Kabar;
use Database\Seeders\ContohLaporanSeeder;
use Database\Seeders\DataMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pemberitahuan: ditulis tiap tindakan, dan mengantar ke bagian yang berubah.
 *
 * Tabelnya sudah ada sejak awal lengkap dengan kolom `blok`, tapi tidak ada
 * satu pun controller yang membaca maupun menulisnya — berkas berpindah meja
 * tanpa ada yang tahu kecuali orang itu membuka daftarnya sendiri.
 */
class PemberitahuanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([DataMasterSeeder::class, ContohLaporanSeeder::class]);
    }

    private function akun(string $surel): User
    {
        return User::where('email', $surel)->firstOrFail();
    }

    private function diMeja(PosisiBerkas $p): \App\Models\Sasaran
    {
        // Lewat sasaran: gerak tingkat 1 tidak tersimpan di rekomendasi.
        return \App\Models\Sasaran::where('posisi', $p->value)->firstOrFail();
    }

    public function test_telaah_menulis_kabar_yang_menunjuk_hasil_telaah(): void
    {
        $r = $this->diMeja(PosisiBerkas::UKI);

        $this->actingAs($this->akun('uki@contoh.test'))
            ->post(route('sasaran.telaah', $r), ['hasil' => 'M', 'catatan' => 'Bukti dinilai sudah cukup dan cocok.']);

        $kabar = Notifikasi::where('rekomendasi_id', $r->id)->latest('id')->firstOrFail();
        $this->assertSame('r-telaah', $kabar->blok);
        $this->assertContains('setba', $kabar->untuk_peran);
        $this->assertStringContainsString('cocok', $kabar->aksi);
    }

    /* Yang mengerjakannya sudah tahu — kabar itu untuk pihak lain. Tanpa ini
       tiap tindakan memantulkan lonceng balik ke wajah orang yang baru saja
       menekan tombolnya. */
    public function test_pelaku_tidak_dikabari_perbuatannya_sendiri(): void
    {
        $uki = $this->akun('uki@contoh.test');
        $r = $this->diMeja(PosisiBerkas::UKI);
        $sebelum = Kabar::belumDibaca($uki);

        $this->actingAs($uki)
            ->post(route('sasaran.telaah', $r), ['hasil' => 'M', 'catatan' => 'Bukti dinilai sudah cukup.']);

        $this->assertSame($sebelum, Kabar::belumDibaca($uki->refresh()));
    }

    public function test_penerima_melihat_kabarnya(): void
    {
        $r = $this->diMeja(PosisiBerkas::UKI);

        $this->actingAs($this->akun('uki@contoh.test'))
            ->post(route('sasaran.telaah', $r), ['hasil' => 'M', 'catatan' => 'Bukti dinilai sudah cukup.']);

        $setba = $this->akun('setba@contoh.test');
        $this->assertGreaterThan(0, Kabar::belumDibaca($setba));

        $this->actingAs($setba)->get(route('kabar'))
            ->assertOk()
            ->assertSee('Hasil telaah');   // keterangan tujuan pada barisnya
    }

    /** Menekan kabar mengantar ke bagiannya, bukan ke pucuk halaman. */
    public function test_membuka_kabar_mengantar_ke_bagian_yang_berubah(): void
    {
        $r = $this->diMeja(PosisiBerkas::UKI);

        $this->actingAs($this->akun('uki@contoh.test'))
            ->post(route('sasaran.telaah', $r), ['hasil' => 'M', 'catatan' => 'Bukti dinilai sudah cukup.']);

        $kabar = Notifikasi::where('rekomendasi_id', $r->id)->latest('id')->firstOrFail();
        $setba = $this->akun('setba@contoh.test');

        $this->actingAs($setba)->post(route('kabar.buka', $kabar))
            ->assertRedirect(route('rekomendasi.show', $r) . '#r-telaah');

        // sekali dibuka, tidak dihitung lagi sebagai belum dibaca
        $this->assertTrue($kabar->refresh()->dibaca->contains('id', $setba->id));
    }

    /* Kabar untuk satuan kerja lain tidak boleh terbuka — termasuk lewat
       menebak nomornya di alamat. */
    public function test_kabar_peran_lain_tidak_bisa_dibuka(): void
    {
        $r = $this->diMeja(PosisiBerkas::UKI);

        $this->actingAs($this->akun('uki@contoh.test'))
            ->post(route('sasaran.telaah', $r), ['hasil' => 'M', 'catatan' => 'Bukti dinilai sudah cukup.']);

        $kabar = Notifikasi::where('rekomendasi_id', $r->id)->latest('id')->firstOrFail();

        $this->actingAs($this->akun('inspektorat@contoh.test'))
            ->post(route('kabar.buka', $kabar))
            ->assertForbidden();
    }

    public function test_tandai_semua_terbaca(): void
    {
        $r = $this->diMeja(PosisiBerkas::UKI);
        $this->actingAs($this->akun('uki@contoh.test'))
            ->post(route('sasaran.telaah', $r), ['hasil' => 'M', 'catatan' => 'Bukti dinilai sudah cukup.']);

        $setba = $this->akun('setba@contoh.test');
        $this->assertGreaterThan(0, Kabar::belumDibaca($setba));

        $this->actingAs($setba)->post(route('kabar.semua'))->assertRedirect();
        $this->assertSame(0, Kabar::belumDibaca($setba->refresh()));
    }
}
