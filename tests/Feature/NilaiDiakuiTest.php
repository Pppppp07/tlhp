<?php

namespace Tests\Feature;

use App\Enums\HasilTelaah;
use App\Enums\StatusTindakLanjut;
use App\Models\Sasaran;
use Database\Seeders\ContohLaporanSeeder;
use Database\Seeders\DataMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Nilai yang sudah diakui, dan sisanya.
 *
 * Dua hal yang gampang dikira sama padahal berbeda:
 *
 *   belum disetor   = tagihan dikurangi uang yang masuk kas
 *   sisa nilai      = nilai dikurangi yang sudah DIAKUI
 *
 * Rekomendasi yang uangnya sudah lunas tapi buktinya diterima sebagian tetap
 * punya sisa nilai. Itu kolom "Sisa Nilai" pada lembar pemantauan mereka, dan
 * keterangannya sendiri berbunyi: "membantu identifikasi rekomendasi dengan
 * potensi nilai yang masih perlu ditindaklanjuti."
 *
 * Bukti bahwa pengakuan sebagian nyata ada di datanya: Reff IDT
 * 2022.5b.I.2.9.d bernilai Rp 792 juta, diakui BPK Rp 192 juta, diakui Itjen
 * Rp 247 juta — dan statusnya tetap Belum Sesuai.
 */
class NilaiDiakuiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([DataMasterSeeder::class, ContohLaporanSeeder::class]);
    }

    private function baris(): Sasaran
    {
        return Sasaran::with('tindakan.rekomendasi')->firstOrFail();
    }

    /**
     * Medan kosong berarti "ikut keadaannya", bukan nol.
     *
     * Ini penjaga terpentingnya. Kalau kosong diperlakukan nol, seluruh
     * rekomendasi yang sudah dinyatakan memadai akan terbaca belum diakui
     * sepeser pun — dan angkanya tetap tampil rapi, jadi salahnya tidak
     * kelihatan.
     */
    public function test_medan_kosong_jatuh_ke_keadaannya(): void
    {
        $b = $this->baris();
        $b->update(['nilai' => 100_000_000, 'nilai_memadai' => null,
            'hasil' => HasilTelaah::M]);

        $this->assertSame(100_000_000, $b->fresh()->nilaiDiakuiItjen(),
            'baris memadai tanpa angka sendiri seharusnya diakui utuh');
        $this->assertSame(0, $b->fresh()->sisaNilaiItjen());

        $b->update(['hasil' => HasilTelaah::BM]);
        $this->assertSame(0, $b->fresh()->nilaiDiakuiItjen(),
            'baris belum memadai tanpa angka sendiri seharusnya belum diakui');
        $this->assertSame(100_000_000, $b->fresh()->sisaNilaiItjen());
    }

    /**
     * Pengakuan sebagian: skenario Rp 100 juta yang diterima Rp 80 juta.
     *
     * Yang diuji di sini justru pasangannya — angkanya berdiri sendiri
     * sementara keadaannya masih "belum memadai", dan keduanya benar.
     */
    public function test_nilai_bisa_diakui_sebagian(): void
    {
        $b = $this->baris();
        $b->update(['nilai' => 100_000_000, 'nilai_memadai' => 80_000_000,
            'hasil' => HasilTelaah::BM]);

        $b = $b->fresh();
        $this->assertSame(80_000_000, $b->nilaiDiakuiItjen());
        $this->assertSame(20_000_000, $b->sisaNilaiItjen(),
            'Rp 20 juta yang buktinya ditolak seharusnya jadi sisa nilai');
    }

    /**
     * Sumbu BPK: statusnya milik rekomendasi, nilainya milik baris.
     *
     * BPK menutup rekomendasi, bukan satuan kerja satu per satu — tapi uangnya
     * ditagihkan per satuan kerja, jadi waktu buktinya diterima sebagian yang
     * perlu diketahui bagian siapa yang ditolak.
     */
    public function test_sumbu_bpk_membaca_status_rekomendasi_tapi_nilai_baris(): void
    {
        $b = $this->baris();
        $rek = $b->rekomendasi();

        $b->update(['nilai' => 50_000_000, 'nilai_ss' => null]);
        $rek->update(['status' => StatusTindakLanjut::SS]);
        $this->assertSame(50_000_000, $b->fresh()->nilaiDiakuiBpk(),
            'rekomendasi yang sudah SS seharusnya mengakui seluruh nilai barisnya');

        $rek->update(['status' => StatusTindakLanjut::BS]);
        $this->assertSame(0, $b->fresh()->nilaiDiakuiBpk());

        /* Angka sendiri mengalahkan tebakan dari status. */
        $b->update(['nilai_ss' => 30_000_000]);
        $this->assertSame(30_000_000, $b->fresh()->nilaiDiakuiBpk());
        $this->assertSame(20_000_000, $b->fresh()->sisaNilaiBpk());
    }

    /**
     * Nol bukan kosong.
     *
     * Nol yang ditulis sendiri berarti "diperiksa, tidak ada yang diakui" —
     * dan itu harus mengalahkan tebakan dari statusnya.
     */
    public function test_nol_yang_ditulis_sendiri_bukan_berarti_kosong(): void
    {
        $b = $this->baris();
        $b->update(['nilai' => 70_000_000, 'nilai_memadai' => 0,
            'hasil' => HasilTelaah::M]);

        $this->assertSame(0, $b->fresh()->nilaiDiakuiItjen(),
            'nol yang ditulis sendiri seharusnya tidak jatuh ke tebakan status');
        $this->assertSame(70_000_000, $b->fresh()->sisaNilaiItjen());
    }
}
