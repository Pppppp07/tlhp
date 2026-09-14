<?php

namespace Tests\Feature;

use App\Enums\PosisiBerkas;
use App\Models\Rekomendasi;
use App\Models\Sasaran;
use App\Models\User;
use Database\Seeders\BanyakLaporanSeeder;
use Database\Seeders\ContohLaporanSeeder;
use Database\Seeders\DataMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sesudah sebuah tindakan, penggunanya diantar ke berkasnya — bukan dilempar
 * ke pucuk daftar dengan satu kalimat "berhasil".
 *
 * Penandanya dihitung peladen, bukan peramban: barisnya sudah bertanda saat
 * HTML-nya sampai, jadi ia tetap terlihat walau JavaScript mati.
 *
 * Dulu semua ini hidup di layar Beranda. Layar itu dibuang — prototipe tidak
 * punya, dan daftar rekomendasi sudah menjawab "apa yang harus saya kerjakan"
 * lewat keranjangnya sendiri.
 */
class NavigasiKontekstualTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([DataMasterSeeder::class, ContohLaporanSeeder::class, BanyakLaporanSeeder::class]);
    }

    private function setba(): User
    {
        return User::where('email', 'setba@contoh.test')->firstOrFail();
    }

    /* Alamat lama tetap mengantar ke tempat yang benar: tautan yang sudah
       terlanjur disalin orang tidak boleh berakhir di halaman kosong. */
    public function test_alamat_pangkal_mengantar_ke_daftar_rekomendasi(): void
    {
        $this->actingAs($this->setba())
            ->get('/')->assertRedirect(route('rekomendasi.index'));
    }

    public function test_sesudah_meneruskan_diantar_ke_berkasnya(): void
    {
        $s = Sasaran::where('posisi', PosisiBerkas::SETBA_TERUSKAN->value)->firstOrFail();
        $rek = $s->tindakan->rekomendasi;

        $this->actingAs($this->setba())
            ->post(route('sasaran.teruskan', $s))
            ->assertRedirect(route('rekomendasi.index', ['tandai' => $rek->id, 'nada' => 'ok']));
    }

    public function test_sesudah_mengembalikan_diantar_ke_berkasnya(): void
    {
        $s = Sasaran::where('posisi', PosisiBerkas::UKI->value)->firstOrFail();
        $rek = $s->tindakan->rekomendasi;

        $this->actingAs(User::where('email', 'uki@contoh.test')->firstOrFail())
            ->post(route('sasaran.kembalikan', $s), ['alasan' => 'Bukti belum lengkap semuanya.'])
            ->assertRedirect(route('rekomendasi.index', ['tandai' => $rek->id, 'nada' => 'jingga']));
    }

    /* Berkas yang ditandai memang ada di halaman yang dituju — kalau tandanya
       menunjuk baris yang tersaring keluar, penggunanya mendarat di tempat
       kosong dan mengira tindakannya gagal. */
    public function test_berkas_yang_ditandai_ada_di_halaman_yang_dituju(): void
    {
        $s = Sasaran::where('posisi', PosisiBerkas::SETBA_TERUSKAN->value)->firstOrFail();
        $rek = $s->tindakan->rekomendasi;

        $this->actingAs($this->setba())->post(route('sasaran.teruskan', $s));

        $isi = $this->actingAs($this->setba())
            ->get(route('rekomendasi.index', ['tandai' => $rek->id, 'nada' => 'ok']))
            ->assertOk()->getContent();

        $rapat = preg_replace('/\s+/', ' ', $isi);

        $this->assertStringContainsString('id="r-' . $rek->id . '"', $rapat,
            'baris yang ditandai tidak ada di halaman yang dituju');
        $this->assertStringContainsString('tandai ok', $rapat,
            'nadanya tidak ikut terpasang');
    }

    public function test_tanpa_penanda_tidak_ada_baris_yang_ditandai(): void
    {
        $isi = $this->actingAs($this->setba())
            ->get(route('rekomendasi.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('class="tandai', $isi);
    }

    /* Keping keranjang membawa keadaannya di alamat, bukan di peramban.
       Tampilan yang sedang dilihat jadi bisa disalin-kirim apa adanya. */
    public function test_keping_keranjang_menyaring_lewat_alamat(): void
    {
        $halaman = $this->actingAs($this->setba())
            ->get(route('rekomendasi.index', ['keadaan' => 'selesai']))->assertOk();

        $isi = preg_replace('/\s+/', ' ', $halaman->getContent());
        $this->assertStringContainsString('aria-pressed="true"', $isi,
            'keping yang sedang menyala harus menyebut dirinya');

        /* Yang tampil memang hanya yang sudah ditetapkan. */
        preg_match_all('#/rekomendasi/(\d+)"#', $isi, $c);
        foreach (array_unique($c[1] ?? []) as $id) {
            $this->assertSame(PosisiBerkas::SELESAI,
                Rekomendasi::findOrFail((int) $id)->posisiTampil(),
                "rekomendasi {$id} muncul di keranjang Sudah selesai padahal belum");
        }
    }

    /* Keping SIPTL berdiri di seberang pemisah karena ia bukan keadaan
       berkas, dan ia hilang pada lingkup LHA — di sana ia keranjang yang tidak
       akan pernah terisi. */
    public function test_keping_siptl_hilang_pada_lingkup_lha(): void
    {
        /* Diperiksa di dalam deret kepingnya saja. Menu di bilah kiri juga
           bernama "Urusan SIPTL" dan selalu ada bagi Setba — mencarinya di
           seluruh halaman membuat ujinya selalu lulus, atau selalu gagal. */
        $keping = function (string $jenis) {
            $isi = $this->actingAs($this->setba())
                ->get(route('rekomendasi.index', ['jenis' => $jenis]))
                ->assertOk()->getContent();

            preg_match_all('#<div class="pilihgrup".*?</div>#s', $isi, $c);

            return implode(' ', $c[0] ?? []);
        };

        $this->assertStringContainsString('Urusan SIPTL', $keping('LHP'));
        $this->assertStringNotContainsString('Urusan SIPTL', $keping('LHA'));
    }
}
