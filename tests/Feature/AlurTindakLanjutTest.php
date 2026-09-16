<?php

namespace Tests\Feature;

use App\Enums\HasilTelaah;
use App\Enums\PosisiBerkas;
use App\Enums\StatusTindakLanjut;
use App\Models\Sasaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\PakaiDataContoh;
use Tests\TestCase;

/**
 * Satu berkas menempuh seluruh rantainya: satuan kerja → Setba → UKI → Setba →
 * Inspektorat → SIPTL, berikut jalan pulangnya waktu ditolak.
 *
 * Yang dijaga di sini bukan cuma posisinya berpindah, melainkan juga siapa yang
 * boleh memindahkannya — dan bahwa berkas yang ditolak kembali lewat Setba,
 * bukan langsung ke satuan kerja.
 */
class AlurTindakLanjutTest extends TestCase
{
    use PakaiDataContoh, RefreshDatabase;

    private Sasaran $baris;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanDataContoh();

        $this->baris = Sasaran::with('tindakan.rekomendasi.temuan.laporan', 'satker')->get()
            ->first(fn ($x) => $x->pos() === PosisiBerkas::SATKER
                && $x->tindakan->rekomendasi->jenis()->melewatiSiptl());

        $this->assertNotNull($this->baris, 'Data contoh perlu memuat baris yang masih di satuan kerja.');
    }

    private function satker(): User
    {
        return User::where('satker_id', $this->baris->satker_id)->firstOrFail();
    }

    private function segar(): Sasaran
    {
        return Sasaran::with('tindakan.rekomendasi', 'satker')->find($this->baris->id);
    }

    /** Kirim dari satuan kerja, berikut bukti untuk tiap dokumen yang diminta. */
    private function kirimDariSatker(): void
    {
        $baris = $this->segar();
        $rek = $baris->tindakan->rekomendasi;
        $rek->load('permintaanDokumen.item');

        $bukti = $rek->permintaanUntuk($baris->satker_id, $baris->tindakan_id)
            ->flatMap->item->where('terpenuhi', false)
            ->map(fn ($i) => ['nama' => 'Bukti '.$i->nama, 'tautan' => 'https://contoh.test/'.$i->id,
                'jenis' => $i->nama, 'untuk' => $i->id])
            ->values()->all();

        $this->actingAs($this->satker())
            ->post(route('tanggapan.simpan', $baris), [
                'aksi' => 'kirim',
                'uraian' => 'Tindak lanjut sudah dilaksanakan sesuai bunyi rekomendasi.',
                'bukti' => $bukti,
            ])
            ->assertRedirect(route('rekomendasi.index'));
    }

    private function teruskan(): void
    {
        $this->masuk('setba@contoh.test')
            ->post(route('sasaran.teruskan', $this->segar()), [
                'nomor' => 'PW.02.02-Sb/9001',
                'tanggal' => now()->toDateString(),
                'perihal' => 'Penyampaian tindak lanjut untuk ditelaah',
            ])
            ->assertRedirect(route('rekomendasi.index'));
    }

    public function test_rantai_penuh_sampai_tuntas_dan_siptl(): void
    {
        /* 1. Satuan kerja mengirim. */
        $this->kirimDariSatker();
        $this->assertSame(PosisiBerkas::SETBA_TINJAU, $this->segar()->pos());
        $this->assertNotEmpty($this->segar()->tanggapan);

        /* 2. Setba meneruskan ke UKI. */
        $this->teruskan();
        $this->assertSame(PosisiBerkas::UKI, $this->segar()->pos());

        /* 3. UKI menolak: berkas pulang ke meja pemberkasan ulang Setba —
              bukan langsung ke satuan kerjanya. */
        $this->masuk('uki@contoh.test')
            ->post(route('sasaran.putus', $this->segar()), [
                'hasil' => 'BM',
                'catatan' => 'Bukti yang dilampirkan belum bernomor.',
                'dokumen' => ['Surat bernomor'],
            ])
            ->assertRedirect(route('rekomendasi.index'));
        $baris = $this->segar();
        $this->assertSame(PosisiBerkas::SETBA_KEMBALI, $baris->pos());
        $this->assertNotEmpty($baris->alasan_perbaikan);

        /* 4. Setba mengirim ulang ke satuan kerjanya. */
        $this->masuk('setba@contoh.test')
            ->post(route('sasaran.kirimUlang', $this->segar()), [
                'keterangan' => 'Mohon dilengkapi nomor suratnya.',
                'dokumen' => ['Surat bernomor'],
            ])
            ->assertRedirect(route('rekomendasi.index'));
        $this->assertSame(PosisiBerkas::SATKER, $this->segar()->pos());

        /* 5. Putaran kedua: kirim, teruskan, lalu UKI menyatakan memadai. */
        $this->kirimDariSatker();
        $this->teruskan();
        $this->masuk('uki@contoh.test')
            ->post(route('sasaran.putus', $this->segar()), [
                'hasil' => 'M',
                'nomor' => '099/VAL-UKI/BPSDM/VIII/2026',
                'tgl_surat' => now()->toDateString(),
                'catatan' => 'Bukti lengkap dan sesuai rekomendasi.',
            ])
            ->assertRedirect(route('rekomendasi.index'));
        $this->assertSame(PosisiBerkas::SETBA_TERUSKAN, $this->segar()->pos());

        /* 6. Setba meneruskan ke Inspektorat, Inspektorat memutus. */
        $this->teruskan();
        $this->assertSame(PosisiBerkas::INSPEKTORAT, $this->segar()->pos());

        $this->masuk('inspektorat@contoh.test')
            ->post(route('sasaran.putus', $this->segar()), [
                'hasil' => 'M',
                'nomor' => '1/5/2/16/2026/900',
                'tgl_surat' => now()->toDateString(),
                'catatan' => 'Tindak lanjut sudah sesuai.',
            ])
            ->assertRedirect(route('rekomendasi.index'));

        $baris = $this->segar();
        $this->assertSame(PosisiBerkas::TUNTAS, $baris->pos());
        $this->assertSame(HasilTelaah::M, $baris->hasil);

        /* 7. Urusan SIPTL: unggah dulu, baru statusnya. */
        $this->masuk('setba@contoh.test')
            ->post(route('siptl.unggah', $baris), ['tanggal' => now()->toDateString()])
            ->assertRedirect(route('rekomendasi.index'));
        $this->assertNotNull($this->segar()->siptl_tanggal);

        /* Tanggal unggah tidak bisa diganggu gugat sesudah tercatat. */
        $this->masuk('setba@contoh.test')
            ->post(route('siptl.unggah', $this->segar()), ['tanggal' => now()->subDay()->toDateString()])
            ->assertStatus(422);

        $this->masuk('setba@contoh.test')
            ->post(route('siptl.status', $this->segar()), [
                'status' => 'BS',
                'catatan' => 'Bukti setor belum mencantumkan NTPN.',
            ])
            ->assertRedirect(route('rekomendasi.index'));
        $this->assertSame(StatusTindakLanjut::BS, $this->segar()->status_bpk);

        /* 8. BPK menolak: berkas dikirim ulang ke satuan kerjanya. */
        $baris = $this->segar();
        $this->masuk('setba@contoh.test')
            ->post(route('siptl.ulangBpk', $baris->tindakan->rekomendasi), [
                'sasaran_id' => $baris->id,
                'alasan' => 'BPK meminta NTPN dicantumkan.',
                'dokumen' => ['Bukti setor dengan NTPN'],
            ])
            ->assertRedirect(route('rekomendasi.index'));
        $this->assertSame(PosisiBerkas::SATKER, $this->segar()->pos());
    }

    public function test_yang_bukan_pemegangnya_ditolak(): void
    {
        /* Berkas masih di satuan kerja: UKI belum boleh memutus apa pun. */
        $this->masuk('uki@contoh.test')
            ->post(route('sasaran.putus', $this->baris), ['hasil' => 'M'])
            ->assertForbidden();

        /* Setba belum boleh meneruskan yang belum dikirim satuan kerjanya. */
        $this->masuk('setba@contoh.test')
            ->post(route('sasaran.teruskan', $this->baris), [
                'nomor' => 'X', 'tanggal' => now()->toDateString(), 'perihal' => 'Y',
            ])
            ->assertStatus(422);
    }

    public function test_kirim_ditahan_kalau_dokumennya_belum_lengkap(): void
    {
        $baris = $this->segar();
        $rek = $baris->tindakan->rekomendasi->load('permintaanDokumen.item');
        $belum = $rek->permintaanUntuk($baris->satker_id, $baris->tindakan_id)
            ->flatMap->item->where('terpenuhi', false);

        if ($belum->isEmpty()) {
            $this->markTestSkipped('Baris contoh ini tidak diminta dokumen apa pun.');
        }

        $this->actingAs($this->satker())
            ->post(route('tanggapan.simpan', $baris), [
                'aksi' => 'kirim',
                'uraian' => 'Sudah dikerjakan.',
            ])
            ->assertSessionHasErrors('kirim');

        $this->assertSame(PosisiBerkas::SATKER, $this->segar()->pos());
    }

    public function test_draf_disimpan_tanpa_memindahkan_berkas(): void
    {
        $this->actingAs($this->satker())
            ->post(route('tanggapan.simpan', $this->baris), [
                'aksi' => 'draf',
                'uraian' => 'Sedang dikumpulkan buktinya.',
            ])
            ->assertRedirect();

        $baris = $this->segar();
        $this->assertSame(PosisiBerkas::SATKER, $baris->pos());
        $this->assertNotNull($baris->draf);
        $this->assertSame('Sedang dikumpulkan buktinya.', $baris->draf->uraian);
    }
}
