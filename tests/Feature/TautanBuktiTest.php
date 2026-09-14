<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Enums\PosisiBerkas;
use App\Models\ItemPermintaan;
use App\Models\Lampiran;
use App\Models\PermintaanDokumen;
use App\Models\Sasaran;
use App\Models\User;
use Database\Seeders\ContohLaporanSeeder;
use Database\Seeders\DataMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Satuan kerja melampirkan buktinya berupa TAUTAN, per butir yang diminta.
 *
 * Rapat 30 Agustus: berkas tindak lanjut sudah tersimpan di arsip masing-masing
 * satuan kerja, dan menyalinnya ke sistem ini berarti dua tempat menyimpan satu
 * berkas — lalu keduanya bisa berbeda tanpa ada yang tahu mana yang benar.
 *
 * Yang dijaga uji ini terutama satu hal: butir ditandai terpenuhi HANYA kalau
 * tautannya memang ada. Sebelumnya centangnya berdiri sendiri, jadi satuan
 * kerja bisa menyatakan dokumen terkirim padahal tidak ada apa-apa yang
 * dilampirkan — dan yang memeriksanya berikutnya baru tahu sesudah membuka
 * berkasnya.
 */
class TautanBuktiTest extends TestCase
{
    use RefreshDatabase;

    private Sasaran $s;
    private User $pemilik;
    private ItemPermintaan $butir;

    /* Baris contoh sudah membawa berkasnya sendiri. Yang dihitung uji ini
       hanya yang lahir dari kiriman di dalamnya. */
    private int $berkasAwal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([DataMasterSeeder::class, ContohLaporanSeeder::class]);
        \App\Support\PautkanSasaran::jalankan();

        /* Baris yang berkasnya masih di meja satuan kerjanya, dan satuan
           kerjanya punya pengguna di data contoh. */
        $punyaPengguna = User::where('peran', PeranPengguna::SATKER)
            ->pluck('satker_id')->filter()->all();

        $this->s = Sasaran::whereIn('satker_id', $punyaPengguna)->get()
            ->first(fn ($x) => $x->posisi === PosisiBerkas::SATKER)
            ?? Sasaran::whereIn('satker_id', $punyaPengguna)->firstOrFail();

        $this->s->update(['posisi' => PosisiBerkas::SATKER->value]);
        $this->s->refresh();

        $this->pemilik = User::where('peran', PeranPengguna::SATKER)
            ->where('satker_id', $this->s->satker_id)->firstOrFail();

        $permintaan = PermintaanDokumen::create([
            'rekomendasi_id' => $this->s->rekomendasi()->id,
            'sasaran_id' => $this->s->id,
            'peran_peminta' => PeranPengguna::SETBA->value,
            'tanggal' => now()->subDays(5)->toDateString(),
            'catatan' => 'Bukti yang sudah masuk belum cukup.',
        ]);

        /* Butir yang sudah ada dari data contoh ditandai terpenuhi dulu:
           yang diuji perlakuan terhadap SATU butir yang sengaja dibuat, bukan
           sisa permintaan yang kebetulan menumpuk di baris itu. */
        ItemPermintaan::whereHas('permintaan',
            fn ($q) => $q->where('sasaran_id', $this->s->id))
            ->update(['terpenuhi' => true, 'dipenuhi_pada' => now()->toDateString()]);

        $this->butir = ItemPermintaan::create([
            'permintaan_dokumen_id' => $permintaan->id,
            'nama' => 'Berita acara serah terima barang',
        ]);

        $this->berkasAwal = Lampiran::where('sasaran_id', $this->s->id)->count();
        $this->s->refresh()->load('permintaanDokumen.item');
    }

    /** Berkas yang lahir sesudah setUp. */
    private function berkasBaru(): int
    {
        return Lampiran::where('sasaran_id', $this->s->id)->count() - $this->berkasAwal;
    }

    public function test_bukti_tersimpan_sebagai_tautan_bukan_unggahan(): void
    {
        $this->actingAs($this->pemilik)
            ->post(route('tanggapan.simpan', $this->s), [
                'uraian' => 'Barangnya sudah diserahkan dan berita acaranya ditandatangani.',
                'bukti' => [
                    $this->butir->id => [
                        'judul' => 'Berita acara serah terima 12 Mei 2026',
                        'tautan' => 'https://arsip.contoh.test/ba-12-mei.pdf',
                    ],
                ],
            ])->assertRedirect();

        $berkas = Lampiran::where('sasaran_id', $this->s->id)
            ->where('nama_asli', 'Berita acara serah terima 12 Mei 2026')->firstOrFail();

        $this->assertTrue($berkas->berupaTautan(),
            'bukti harus berupa tautan, bukan salinan yang diunggah');
        $this->assertSame('https://arsip.contoh.test/ba-12-mei.pdf', $berkas->tautan);
        $this->assertNull($berkas->nama_simpan);

        /* Judulnya yang ditulis pengirim, bukan dikarang dari nama butirnya:
           yang membacanya perlu tahu isi tautannya apa tanpa harus membukanya
           satu per satu. */
        $this->assertNotSame($this->butir->nama, $berkas->nama_asli);

        // dan butirnya tertandai terpenuhi, tersambung ke berkasnya
        $this->assertTrue($this->butir->fresh()->terpenuhi);
        $this->assertTrue($berkas->butir->contains('id', $this->butir->id));
    }

    /**
     * Inilah yang paling penting: tanpa tautan, butirnya TIDAK terpenuhi.
     *
     * Sebelumnya centangnya berdiri sendiri — satuan kerja bisa menyatakan
     * dokumen terkirim padahal tidak ada apa-apa yang dilampirkan.
     */
    public function test_butir_tidak_terpenuhi_tanpa_tautan(): void
    {
        $this->actingAs($this->pemilik)
            ->post(route('tanggapan.simpan', $this->s), [
                'uraian' => 'Sudah dikerjakan, berkasnya menyusul.',
                'bukti' => [
                    $this->butir->id => ['judul' => 'Berita acaranya', 'tautan' => ''],
                ],
            ])->assertRedirect();

        $this->assertFalse($this->butir->fresh()->terpenuhi);
        $this->assertSame(0, $this->berkasBaru());
    }

    public function test_judul_tanpa_tautan_ditolak_diam_diam_bukan_disimpan_setengah(): void
    {
        $this->actingAs($this->pemilik)
            ->post(route('tanggapan.simpan', $this->s), [
                'uraian' => 'Sudah dikerjakan seluruhnya.',
                'bukti' => [
                    $this->butir->id => ['judul' => '', 'tautan' => 'https://arsip.contoh.test/x.pdf'],
                ],
            ])->assertRedirect();

        /* Tautan tanpa judul tidak disimpan: berkas tanpa judul memaksa yang
           membacanya membuka satu per satu untuk tahu isinya apa. */
        $this->assertSame(0, $this->berkasBaru());
        $this->assertFalse($this->butir->fresh()->terpenuhi);
    }

    public function test_kirim_tertahan_selama_dokumennya_belum_bertautan(): void
    {
        $this->actingAs($this->pemilik)
            ->post(route('tanggapan.simpan', $this->s), [
                'uraian' => 'Sudah dikerjakan, berkasnya menyusul.',
                'kirim' => 1,
            ])->assertRedirect();

        // berkasnya tetap di satuan kerja
        $this->assertSame(PosisiBerkas::SATKER, $this->s->fresh()->posisi);

        // sesudah bertautan, baru bisa dikirim
        $this->actingAs($this->pemilik)
            ->post(route('tanggapan.simpan', $this->s), [
                'uraian' => 'Berita acaranya sudah dilampirkan.',
                'kirim' => 1,
                'bukti' => [
                    $this->butir->id => [
                        'judul' => 'Berita acara serah terima 12 Mei 2026',
                        'tautan' => 'https://arsip.contoh.test/ba-12-mei.pdf',
                    ],
                ],
            ])->assertRedirect();

        $this->assertSame(PosisiBerkas::SETBA_TINJAU, $this->s->fresh()->posisi);
    }

    public function test_bukti_lain_boleh_tanpa_butir(): void
    {
        $this->actingAs($this->pemilik)
            ->post(route('tanggapan.simpan', $this->s), [
                'uraian' => 'Ditambahkan lampiran pendukung yang tidak diminta namanya.',
                'lain' => [
                    'judul' => 'Foto barang yang diserahkan',
                    'tautan' => 'https://arsip.contoh.test/foto.zip',
                ],
            ])->assertRedirect();

        $berkas = Lampiran::where('sasaran_id', $this->s->id)
            ->where('nama_asli', 'Foto barang yang diserahkan')->firstOrFail();

        $this->assertTrue($berkas->berupaTautan());
        $this->assertTrue($berkas->butir->isEmpty(), 'bukti lain tidak menjawab butir mana pun');
    }

    /**
     * Tanggal pelaksanaan tidak lagi ditanyakan.
     *
     * Kata Bang Kamal di rapat, "tanggal nggak perlu, ya kan?" — dan memang
     * begitu: orang mengisinya pada hari ia mengerjakannya.
     */
    public function test_tanggal_tidak_ditanyakan_dan_diisi_hari_ini(): void
    {
        $isi = $this->actingAs($this->pemilik)
            ->get(route('rekomendasi.show', $this->s->rekomendasi()))
            ->assertOk()->getContent();

        $this->assertStringNotContainsString('Tanggal pelaksanaan', $isi);

        $this->actingAs($this->pemilik)
            ->post(route('tanggapan.simpan', $this->s), [
                'uraian' => 'Dikerjakan hari ini.',
            ])->assertRedirect();

        $this->assertTrue(
            $this->s->fresh()->tanggapan->last()->tanggal->isToday());
    }

    /**
     * Bentuk isiannya: nama butir berikut lingkaran keadaannya, dan isiannya
     * baru turun sesudah tombolnya ditekan.
     *
     * Delapan kotak isian kosong berdiri permanen terbaca sebagai formulir
     * panjang yang harus diisi semuanya sekarang — padahal satuan kerja memang
     * mengirimnya bertahap.
     */
    public function test_isian_tautan_tersembunyi_sampai_tombolnya_ditekan(): void
    {
        $isi = $this->actingAs($this->pemilik)
            ->get(route('rekomendasi.show', $this->s->rekomendasi()))
            ->assertOk()->getContent();

        // nama butirnya tampil, berikut tombolnya
        $this->assertStringContainsString(e($this->butir->nama), $isi);
        $this->assertStringContainsString('Tambah tautan', $isi);
        $this->assertStringContainsString('Tambah tautan lain', $isi);

        /* Isiannya dibungkus <details> yang tertutup — bukan disembunyikan
           dengan skrip, jadi tanpa JavaScript pun tetap bisa dibuka. */
        $this->assertMatchesRegularExpression(
            '/<details class="tautanbutir">(?!.*open)/s', $isi);

        // dan hitungannya menyebut berapa yang belum
        $this->assertStringContainsString('belum ditautkan', $isi);

        /* Butir yang sudah bertautan tidak lagi menawarkan isian — menyunting
           yang sudah terkirim bukan lewat sini, melainkan permintaan
           perubahan. */
        $this->butir->update(['terpenuhi' => true]);
        $isi2 = $this->actingAs($this->pemilik)
            ->get(route('rekomendasi.show', $this->s->rekomendasi()))
            ->assertOk()->getContent();
        $this->assertStringNotContainsString('bukti['.$this->butir->id.'][judul]', $isi2);
    }

    public function test_butir_satuan_kerja_lain_tidak_bisa_ditandai(): void
    {
        $lain = Sasaran::where('satker_id', '!=', $this->s->satker_id)
            ->whereNotNull('satker_id')->firstOrFail();

        $permintaanLain = PermintaanDokumen::create([
            'rekomendasi_id' => $lain->rekomendasi()->id,
            'sasaran_id' => $lain->id,
            'peran_peminta' => PeranPengguna::SETBA->value,
            'tanggal' => now()->toDateString(),
        ]);
        $butirLain = ItemPermintaan::create([
            'permintaan_dokumen_id' => $permintaanLain->id,
            'nama' => 'Punya satuan kerja lain',
        ]);

        $this->actingAs($this->pemilik)
            ->post(route('tanggapan.simpan', $this->s), [
                'uraian' => 'Mencoba menandai butir milik satuan kerja lain.',
                'bukti' => [
                    $butirLain->id => [
                        'judul' => 'Berkas karangan',
                        'tautan' => 'https://arsip.contoh.test/karangan.pdf',
                    ],
                ],
            ])->assertRedirect();

        $this->assertFalse($butirLain->fresh()->terpenuhi);
        $this->assertSame(0, $this->berkasBaru());
    }
}
