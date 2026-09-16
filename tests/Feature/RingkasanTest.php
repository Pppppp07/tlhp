<?php

namespace Tests\Feature;

use App\Models\Rekomendasi;
use App\Support\PetaData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\PakaiDataContoh;
use Tests\TestCase;

/**
 * Ringkasan: dua blok status, dua tabel rekap, dan peta data yang bisa diatur
 * sendiri.
 *
 * Angka di sini sengaja disebut apa adanya — 44 rekomendasi, 85 penugasan, 36
 * di antaranya LHP. Kalau rumusnya bergeser, ujinya yang memberi tahu, bukan
 * orang yang membaca dasbornya.
 */
class RingkasanTest extends TestCase
{
    use PakaiDataContoh, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanDataContoh();
        $this->masuk('setba@contoh.test');
    }

    public function test_dua_blok_status_berdiri_di_atas_penyebut_yang_berbeda(): void
    {
        $halaman = $this->get('/ringkasan')->assertOk();

        $halaman->assertSee('Menurut BPK · SIPTL')
            ->assertSee('Menurut BPSDM · verifikasi Inspektorat')
            ->assertSee('rekomendasi belum memadai')
            ->assertSee('Per penugasan');

        $isi = $halaman->getContent();
        /* Penyebut BPK cuma LHP; penyebut BPSDM seluruh rekomendasi. */
        $this->assertStringContainsString('85 penugasan', strip_tags($isi));
    }

    public function test_dua_tabel_rekap_lengkap_dengan_barisnya(): void
    {
        $halaman = $this->get('/ringkasan')->assertOk();

        $halaman->assertSee('Pemantauan per satuan kerja')
            ->assertSee('Rekap per tahun LHP')
            ->assertSee('TOTAL')
            ->assertSee('dari LHP');
    }

    public function test_peta_data_bisa_diganti_lewat_alamat(): void
    {
        $halaman = $this->get('/ringkasan?'.http_build_query([
            'p' => [
                ['dim' => 'satker', 'ukur' => 'belumMemadai', 'bentuk' => 'batang', 'urut' => 'nilai', 'lebar' => 1],
            ],
        ]))->assertOk();

        $halaman->assertSee('Satuan kerja')
            ->assertSee('menurut belum memadai')
            ->assertSee('Tiap batang satu satuan kerja. Panjangnya belum memadai.');
    }

    public function test_tombol_tambah_panel_menjawab_dengan_alamat_baru(): void
    {
        $this->get('/ringkasan?aksi=tambah')
            ->assertRedirect()
            ->assertRedirectContains('p%5B0%5D');
    }

    public function test_lingkup_lha_menyembunyikan_blok_bpk(): void
    {
        $this->get('/ringkasan?lingkup=LHA')->assertOk()
            ->assertDontSee('Menurut BPK · SIPTL')
            ->assertSee('rekomendasi belum sesuai');
    }

    public function test_peta_memecah_rekomendasi_yang_dipikul_bersama(): void
    {
        $daftar = Rekomendasi::with('sasaran.satker', 'tindakan.bentuk', 'temuan.laporan',
            'temuan.rekomendasi', 'pemulihan', 'tolakanBpk')->get();

        $peta = PetaData::ringkas($daftar, 'satker', 'jumlah', 'nilai');

        /* Satu rekomendasi bisa masuk beberapa kelompok, jadi jumlah kelompok
           lebih besar daripada jumlah rekomendasinya — dan halamannya memang
           menyebut keduanya. */
        $this->assertSame($daftar->count(), $peta['sebenarnya']);
        $this->assertGreaterThan($peta['sebenarnya'], $peta['total']);
    }

    public function test_bagian_rekomendasi_mengecilkan_nilai_dan_setorannya(): void
    {
        $rek = Rekomendasi::with('sasaran.satker', 'tindakan', 'pemulihan', 'tolakanBpk')->get()
            ->first(fn ($r) => $r->daftarSasaran()->pluck('satker_id')->unique()->count() > 1
                && $r->nilaiRek() > 0);
        $this->assertNotNull($rek);

        $satker = $rek->daftarSasaran()->first()->satker_id;
        $bagian = PetaData::bagianRek($rek, fn ($b) => $b->satker_id === $satker);

        $this->assertLessThan($rek->nilaiRek(), $bagian->nilaiRek() + 1);
        $this->assertSame($rek->nilaiSatker($satker), $bagian->nilaiRek());
        foreach ($bagian->daftarSasaran() as $baris) {
            $this->assertSame($satker, $baris->satker_id);
        }
        /* Rekomendasi aslinya tidak ikut terpangkas. */
        $this->assertGreaterThan($bagian->daftarSasaran()->count(), $rek->daftarSasaran()->count());
    }
}
