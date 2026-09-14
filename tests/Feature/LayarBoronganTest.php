<?php

namespace Tests\Feature;

use App\Enums\HasilTelaah;
use App\Enums\PosisiBerkas;
use App\Enums\StatusTindakLanjut;
use App\Models\Rekomendasi;
use App\Models\Sasaran;
use App\Models\User;
use App\Models\Verifikasi;
use Database\Seeders\ContohLaporanSeeder;
use Database\Seeders\DataMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dua layar borongan: UKI menerbitkan LHV, Setba mengurus SIPTL.
 *
 * Keduanya ada karena pekerjaannya memang borongan — satu surat memuat
 * beberapa berkas, satu sesi membuka SIPTL menyentuh banyak berkas — dan
 * mengerjakannya satu per satu di halaman rincian berarti membuka belasan
 * halaman hanya untuk tahu mana yang perlu dikerjakan.
 */
class LayarBoronganTest extends TestCase
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

    /* ================================================================
       LHV — UKI
       ================================================================ */

    public function test_uki_menerbitkan_lhv_bernomor(): void
    {
        $s = Sasaran::where('posisi', PosisiBerkas::UKI->value)->firstOrFail();
        $rek = $s->tindakan->rekomendasi;

        $this->actingAs($this->akun('uki@contoh.test'))
            ->post(route('validasi.simpan'), [
                'periode' => 'Triwulan III 2026',
                'nomor_surat' => '77/VAL/UKI-BPSDM/IX/2026',
                'tgl_surat' => now()->toDateString(),
                'pejabat' => 'Kepala Unit Kepatuhan Internal',
                'putusan' => [$s->id => [
                    'hasil' => 'M',
                    'catatan' => 'Bukti setor dan Nota Konfirmasi KPPN sudah lengkap dan cocok.',
                ]],
            ])->assertRedirect();

        $surat = Verifikasi::where('nomor_surat', '77/VAL/UKI-BPSDM/IX/2026')->firstOrFail();
        $this->assertSame(Verifikasi::LHV, $surat->jenis, 'LHV harus dibedakan dari CHV');

        $s->refresh();
        $this->assertSame(HasilTelaah::M, $s->hasil);
        $this->assertSame(PosisiBerkas::SETBA_TERUSKAN, $s->posisi);

        // Telaahnya membawa nomor suratnya — itu yang dipakai menelusuri
        // berkasnya di luar sistem.
        $telaah = $s->telaah()->latest('id')->first();
        $this->assertSame('77/VAL/UKI-BPSDM/IX/2026', $telaah->nomor_surat);
        $this->assertTrue($telaah->bersurat());

        // Surat pengantarnya ikut tercatat.
        $this->assertTrue($rek->fresh()->surat->contains('nomor', '77/VAL/UKI-BPSDM/IX/2026'));

        // LHV tidak menyentuh status BPK.
        $this->assertSame(StatusTindakLanjut::BT, $rek->fresh()->status);
    }

    public function test_lhv_belum_memadai_memulangkan_berkasnya(): void
    {
        $s = Sasaran::where('posisi', PosisiBerkas::UKI->value)->firstOrFail();

        $this->actingAs($this->akun('uki@contoh.test'))
            ->post(route('validasi.simpan'), [
                'periode' => 'Triwulan III 2026',
                'nomor_surat' => '78/VAL/UKI-BPSDM/IX/2026',
                'tgl_surat' => now()->toDateString(),
                'pejabat' => 'Kepala Unit Kepatuhan Internal',
                'putusan' => [$s->id => [
                    'hasil' => 'BM',
                    'catatan' => 'Nota Konfirmasi KPPN belum dilampirkan sama sekali.',
                ]],
            ])->assertRedirect();

        $s->refresh();
        $this->assertSame(HasilTelaah::BM, $s->hasil);
        $this->assertSame(PosisiBerkas::SATKER, $s->posisi);
    }

    /* Telaah tanpa kesimpulan bukan telaah — yang membaca berikutnya perlu
       tahu apa yang dinilai, bukan cuma bahwa sesuatu sudah dinilai. */
    public function test_lhv_tanpa_kesimpulan_ditolak(): void
    {
        $s = Sasaran::where('posisi', PosisiBerkas::UKI->value)->firstOrFail();

        $this->actingAs($this->akun('uki@contoh.test'))
            ->post(route('validasi.simpan'), [
                'periode' => 'Triwulan III 2026',
                'nomor_surat' => '79/VAL/UKI-BPSDM/IX/2026',
                'tgl_surat' => now()->toDateString(),
                'pejabat' => 'Kepala Unit Kepatuhan Internal',
                'putusan' => [$s->id => ['hasil' => 'M', 'catatan' => '']],
            ])->assertSessionHasErrors("putusan.{$s->id}.catatan");

        $this->assertSame(PosisiBerkas::UKI, $s->refresh()->posisi);
        $this->assertNull(Verifikasi::where('nomor_surat', '79/VAL/UKI-BPSDM/IX/2026')->first());
    }

    public function test_selain_uki_tidak_boleh_menerbitkan_lhv(): void
    {
        foreach (['setba@contoh.test', 'inspektorat@contoh.test', 'bandung@contoh.test'] as $surel) {
            $this->actingAs($this->akun($surel))
                ->get(route('validasi.form'))->assertForbidden();
        }
    }

    /* ================================================================
       SIPTL — Setba
       ================================================================ */

    public function test_layar_siptl_mengumpulkan_kedua_pekerjaannya(): void
    {
        $rek = Rekomendasi::firstOrFail();
        $rek->update(['posisi' => PosisiBerkas::SIPTL->value]);

        $lain = Rekomendasi::where('id', '!=', $rek->id)->firstOrFail();
        $lain->update([
            'posisi' => PosisiBerkas::BPK->value,
            'siptl_tanggal' => now()->subDays(40)->toDateString(),
            'siptl_tanda_terima' => 'TT-SIPTL/2026/00318',
        ]);

        $halaman = $this->actingAs($this->akun('setba@contoh.test'))->get(route('siptl'));

        $halaman->assertOk();
        $halaman->assertSee($rek->kode);
        $halaman->assertSee($lain->kode);
        $halaman->assertSee('TT-SIPTL/2026/00318');
    }

    public function test_status_siptl_dicatat_dari_layar_siptl(): void
    {
        $rek = Rekomendasi::firstOrFail();
        $rek->update([
            'posisi' => PosisiBerkas::BPK->value,
            'siptl_tanggal' => now()->subDays(30)->toDateString(),
        ]);

        $this->actingAs($this->akun('setba@contoh.test'))
            ->post(route('rekomendasi.bpk', $rek), [
                'hasil' => 'BS',
                'catatan' => 'Bukti setor belum dilampiri Nota Konfirmasi KPPN.',
            ])->assertRedirect();

        $r = $rek->fresh();
        $this->assertSame(StatusTindakLanjut::BS, $r->status);
        $this->assertSame(StatusTindakLanjut::BS, $r->siptl_status);

        /* BS memulangkan SELURUH barisnya ke satuan kerja: yang dinilai BPK
           adalah rekomendasinya, bukan satu satuan kerja. */
        $this->assertNull($r->posisi);
        $this->assertTrue($r->daftarSasaran()->every(
            fn ($x) => $x->posisi === PosisiBerkas::SATKER && $x->hasil === null));
    }

    public function test_satuan_kerja_tidak_boleh_membuka_layar_siptl(): void
    {
        $this->actingAs($this->akun('bandung@contoh.test'))
            ->get(route('siptl'))->assertForbidden();
    }
}
