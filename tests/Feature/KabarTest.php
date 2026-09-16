<?php

namespace Tests\Feature;

use App\Models\Notifikasi;
use App\Support\Kabar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\PakaiDataContoh;
use Tests\TestCase;

/**
 * Pemberitahuan: siapa menerima apa, dan kapan sebuah kabar dianggap terbaca.
 *
 * Membuka kabar TIDAK menandainya terbaca — sama seperti prototipe. Yang
 * menandai cuma tombol "Tandai semua terbaca", supaya kabar yang dibuka sekilas
 * lalu ditinggal tidak hilang dari daftar tanpa dikerjakan.
 */
class KabarTest extends TestCase
{
    use PakaiDataContoh, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanDataContoh();
    }

    public function test_setba_melihat_kabar_yang_ditujukan_kepadanya(): void
    {
        $setba = $this->akun('setba@contoh.test');
        $daftar = Kabar::untuk($setba);

        $this->assertGreaterThan(0, $daftar->count());
        foreach ($daftar as $k) {
            $this->assertContains('setba', $k->untuk_peran);
        }

        $this->actingAs($setba)->get(route('kabar'))->assertOk()
            ->assertSee('kabar belum dibaca')
            ->assertSee('Sudah dibaca');
    }

    public function test_satuan_kerja_hanya_menerima_kabar_yang_menyebutnya(): void
    {
        $medan = $this->akun('medan@contoh.test');

        foreach (Kabar::untuk($medan) as $k) {
            $this->assertContains('satker', $k->untuk_peran);
            $this->assertTrue($k->satker->contains('id', $medan->satker_id),
                "Kabar {$k->id} bukan untuk satuan kerja ini.");
        }
    }

    public function test_membuka_kabar_mengantar_ke_bagiannya_tanpa_menandai_terbaca(): void
    {
        $setba = $this->akun('setba@contoh.test');
        $kabar = Kabar::untuk($setba)->first(fn ($k) => ! Kabar::sudahDibaca($k, $setba));
        $this->assertNotNull($kabar);

        $this->actingAs($setba)->get(route('kabar.buka', $kabar))
            ->assertRedirect(route('rekomendasi.show', ['rekomendasi' => $kabar->rekomendasi_id, 'sorot' => $kabar->blok]));

        $this->assertFalse(Kabar::sudahDibaca($kabar->fresh(), $setba));
    }

    public function test_tandai_semua_terbaca_mengosongkan_yang_belum(): void
    {
        $setba = $this->akun('setba@contoh.test');
        $this->assertGreaterThan(0, Kabar::belumDibaca($setba));

        $this->actingAs($setba)->post(route('kabar.semua'))->assertRedirect();

        $this->assertSame(0, Kabar::belumDibaca($setba->fresh()));
    }

    public function test_kabar_milik_peran_lain_tidak_bisa_dibuka(): void
    {
        $medan = $this->akun('medan@contoh.test');
        $bukan = Notifikasi::all()->first(fn ($k) => ! in_array('satker', $k->untuk_peran, true));
        $this->assertNotNull($bukan);

        $this->actingAs($medan)->get(route('kabar.buka', $bukan))->assertForbidden();
    }

    public function test_kabar_kiriman_sistem_belum_terbaca_siapa_pun(): void
    {
        /* Penyapu draf berjalan sesudah penyemaian: kabarnya tidak punya
           pelaku, jadi Setba pun harus membacanya. */
        $sapuan = Notifikasi::where('aksi', 'like', 'Terkirim otomatis%')->first();
        $this->assertNotNull($sapuan, 'Data contoh perlu memuat kiriman otomatis.');
        $this->assertSame(0, $sapuan->dibaca()->count());
    }
}
