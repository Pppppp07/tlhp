<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Models\Laporan;
use App\Models\Rekomendasi;
use App\Models\User;
use Database\Seeders\ContohLaporanSeeder;
use Database\Seeders\DataMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uji asap: tiap layar utama benar-benar terbuka dengan data contoh.
 *
 * Ganti dari ExampleTest bawaan Laravel, yang menuntut halaman depan
 * mengembalikan 200 padahal seluruh aplikasi ini berada di balik autentikasi —
 * jadi ia gagal terus tanpa memberi tahu apa pun tentang keadaan aplikasinya.
 */
class LayarUtamaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([DataMasterSeeder::class, ContohLaporanSeeder::class]);
    }

    private function setba(): User
    {
        return User::where('peran', PeranPengguna::SETBA->value)->firstOrFail();
    }

    public function test_tamu_diantar_ke_halaman_masuk(): void
    {
        $this->get('/')->assertRedirect(route('masuk'));
    }

    public function test_layar_utama_terbuka_untuk_setba(): void
    {
        $this->actingAs($this->setba());

        /* 'beranda' dibuang: layarnya tidak ada lagi, dan alamatnya sekarang
           mengantar ke daftar rekomendasi. Yang menguji pengalihannya ada di
           NavigasiKontekstualTest. */
        foreach (['rekomendasi.index', 'laporan.index', 'ringkasan',
                  'siptl', 'riwayatku', 'selesai', 'kabar'] as $rute) {
            $this->get(route($rute))->assertOk();
        }
    }

    public function test_rincian_laporan_dan_rekomendasi_terbuka(): void
    {
        $this->actingAs($this->setba());

        $this->get(route('laporan.show', Laporan::firstOrFail()))->assertOk();
        $this->get(route('rekomendasi.show', Rekomendasi::firstOrFail()))->assertOk();
    }

    /* Yang paling mudah patah setelah satker pindah dari laporan ke temuan:
       satu laporan kini memeriksa beberapa satuan kerja, dan nama-namanya
       diturunkan dari temuannya, bukan disimpan di kepala laporan. */
    public function test_laporan_memuat_lebih_dari_satu_satuan_kerja(): void
    {
        $lap = Laporan::with('temuan.satkers')->where('nomor', '117/LHP/XVIII/06/2026')->firstOrFail();

        $this->assertGreaterThan(1, $lap->satkerDiperiksa()->count(),
            'laporan contoh harus memeriksa lebih dari satu satuan kerja');
        $this->assertStringContainsString('Balai Bandung', $lap->daftarSatkerDiperiksa());
        $this->assertStringContainsString('Sekretariat Badan', $lap->daftarSatkerDiperiksa());
    }

    /* Satker terperiksa dan satker penanggung jawab memang boleh berbeda —
       itu justru sebabnya keduanya dipisah. */
    public function test_penanggung_jawab_boleh_berbeda_dari_yang_diperiksa(): void
    {
        $temuan = \App\Models\Temuan::with('satkers', 'rekomendasi.sasaran.satker')
            ->where('kode', 'TMN-2026-014')->firstOrFail();

        $this->assertTrue($temuan->satkers->contains('kode', 'BPK-BDG'));
        /* Penanggungnya dibaca dari sasaran: satu rekomendasi bisa dipikul
           beberapa satuan kerja, dan yang diperiksa belum tentu termasuk. */
        $penanggung = $temuan->rekomendasi
            ->flatMap(fn ($r) => $r->daftarSasaran()->pluck('satker.kode'))->unique();
        $this->assertContains('SETBA', $penanggung->all());
    }

    public function test_tanggal_pencatatan_tidak_mendahului_tanggal_terima(): void
    {
        foreach (Laporan::all() as $l) {
            $this->assertNotNull($l->dicatat_pada, "{$l->nomor} belum punya tanggal pencatatan");
            $this->assertGreaterThanOrEqual(0, $l->jedaPencatatan(),
                "{$l->nomor} tercatat sebelum suratnya diterima");
        }
    }
}
