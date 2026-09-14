<?php

namespace Tests\Feature;

use App\Enums\PosisiBerkas;
use App\Models\Pengembalian;
use App\Models\Rekomendasi;
use App\Models\Telaah;
use App\Models\User;
use Database\Seeders\ContohLaporanSeeder;
use Database\Seeders\DataMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tindakan yang menahan atau memulangkan berkas wajib meninggalkan catatannya.
 *
 * Sebelum ini keduanya hanya menggeser posisi berkas: alasan pengembalian
 * terkubur di satu kalimat rekam jejak, dan hasil telaah bahkan tidak bisa
 * ditulis sama sekali — UKI cuma menekan satu tombol "bukti cukup".
 */
class PencatatanTindakanTest extends TestCase
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

    /* Berkas dicari lewat SASARAN, bukan lewat kolom posisi rekomendasi:
       gerak tingkat 1 hidup di barisnya, dan kolom rekomendasi bernilai NULL
       selama tingkat 1 masih berjalan. */
    private function diMeja(PosisiBerkas $posisi): \App\Models\Sasaran
    {
        return \App\Models\Sasaran::where('posisi', $posisi->value)->firstOrFail();
    }

    public function test_telaah_uki_tercatat_dengan_kesimpulannya(): void
    {
        $r = $this->diMeja(PosisiBerkas::UKI);
        $sebelum = Telaah::count();

        $this->actingAs($this->akun('uki@contoh.test'))
            ->post(route('sasaran.telaah', $r), [
                'hasil' => 'M',
                'catatan' => 'Bukti setor dan Nota Konfirmasi KPPN sudah lengkap dan cocok dengan nilai temuan.',
            ])->assertRedirect();

        $this->assertSame($sebelum + 1, Telaah::count());

        $telaah = Telaah::where('sasaran_id', $r->id)->latest('id')->first();
        $this->assertSame('UKI', $telaah->label_oleh);
        $this->assertStringContainsString('Nota Konfirmasi KPPN', $telaah->catatan);
        $this->assertSame(PosisiBerkas::SETBA_TERUSKAN, $r->refresh()->posisi);
    }

    public function test_telaah_tanpa_kesimpulan_ditolak(): void
    {
        $r = $this->diMeja(PosisiBerkas::UKI);

        $this->actingAs($this->akun('uki@contoh.test'))
            ->post(route('sasaran.telaah', $r), ['hasil' => 'M', 'catatan' => ''])
            ->assertSessionHasErrors('catatan');

        $this->assertSame(PosisiBerkas::UKI, $r->refresh()->posisi,
            'berkas tidak boleh bergerak kalau kesimpulannya belum ditulis');
    }

    public function test_pengembalian_tercatat_dengan_alasannya(): void
    {
        $r = $this->diMeja(PosisiBerkas::UKI);
        $sebelum = Pengembalian::count();

        $this->actingAs($this->akun('uki@contoh.test'))
            ->post(route('sasaran.kembalikan', $r), [
                'alasan' => 'Berita acara belum ditandatangani pengelola BMN.',
            ])->assertRedirect();

        $this->assertSame($sebelum + 1, Pengembalian::count());

        $kembali = Pengembalian::where('rekomendasi_id', $r->id)->latest('id')->first();
        $this->assertStringContainsString('pengelola BMN', $kembali->alasan);
        $this->assertSame(PosisiBerkas::SATKER, $r->refresh()->posisi);
    }

    public function test_permintaan_dokumen_tersimpan_dengan_alasannya(): void
    {
        $r = $this->diMeja(PosisiBerkas::SETBA_TERUSKAN);
        $posisi = $r->posisi;

        $this->actingAs($this->akun('setba@contoh.test'))
            ->post(route('sasaran.minta', $r), [
                'alasan' => 'Bukti setor sudah ada tapi belum dilampiri Nota Konfirmasi KPPN.',
                'item' => ['Nota Konfirmasi KPPN', '', 'Rekapitulasi penyetoran per pegawai'],
            ])->assertRedirect();

        $permintaan = $r->refresh()->permintaanDokumen->last();
        $this->assertNotNull($permintaan);
        $this->assertStringContainsString('Nota Konfirmasi KPPN', $permintaan->catatan);
        // baris kosong tidak ikut tersimpan
        $this->assertCount(2, $permintaan->item);
        // permintaan kelengkapan bukan keputusan: berkasnya tidak ke mana-mana
        $this->assertSame($posisi, $r->posisi);
    }

    public function test_permintaan_tanpa_alasan_ditolak(): void
    {
        $r = $this->diMeja(PosisiBerkas::SETBA_TERUSKAN);
        $sebelum = $r->permintaanDokumen->count();

        $this->actingAs($this->akun('setba@contoh.test'))
            ->post(route('sasaran.minta', $r), ['alasan' => '', 'item' => ['Nota Konfirmasi KPPN']])
            ->assertSessionHasErrors('alasan');

        $this->assertCount($sebelum, $r->refresh()->permintaanDokumen);
    }

    public function test_satker_tidak_boleh_meminta_dokumen(): void
    {
        $r = $this->diMeja(PosisiBerkas::SETBA_TERUSKAN);

        $this->actingAs($this->akun('bandung@contoh.test'))
            ->post(route('sasaran.minta', $r), [
                'alasan' => 'Mencoba meminta dokumen padahal bukan wewenangnya.',
                'item' => ['Apa saja'],
            ])->assertForbidden();
    }

    /* Status tidak boleh ikut bergerak: itu hanya berubah lewat surat
       verifikasi bernomor, bukan lewat telaah maupun pengembalian. */
    public function test_telaah_dan_pengembalian_tidak_mengubah_status(): void
    {
        $r = $this->diMeja(PosisiBerkas::UKI);
        $status = $r->status;

        $this->actingAs($this->akun('uki@contoh.test'))
            ->post(route('sasaran.kembalikan', $r), ['alasan' => 'Bukti masih kurang lengkap.']);

        $this->assertSame($status, $r->refresh()->status);
    }
}
