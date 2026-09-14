<?php

namespace Tests\Feature;

use App\Models\Laporan;
use App\Models\Rekomendasi;
use App\Models\User;
use Database\Seeders\ContohLaporanSeeder;
use Database\Seeders\DataMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Penjaga hak akses satuan kerja.
 *
 * Sebelum saringan ini ada, Balai Makassar bisa membuka LHP 117 — laporan yang
 * sama sekali tidak menyangkut dirinya — dan membaca enam rekomendasi milik
 * Balai Bandung dan Sekretariat Badan lengkap dengan temuannya. Uji ini ada
 * supaya lubang itu tidak diam-diam terbuka lagi.
 */
class HakAksesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([DataMasterSeeder::class, ContohLaporanSeeder::class]);
    }

    private function makassar(): User
    {
        return User::where('email', 'makassar@contoh.test')->firstOrFail();
    }

    private function bandung(): User
    {
        return User::where('email', 'bandung@contoh.test')->firstOrFail();
    }

    private function lhp117(): Laporan
    {
        return Laporan::where('nomor', '117/LHP/XVIII/06/2026')->firstOrFail();
    }

    public function test_satker_ditolak_membuka_laporan_yang_tidak_menyangkutnya(): void
    {
        $this->actingAs($this->makassar())
            ->get(route('laporan.show', $this->lhp117()))
            ->assertForbidden();
    }

    public function test_daftar_laporan_hanya_memuat_yang_menyangkutnya(): void
    {
        $halaman = $this->actingAs($this->makassar())->get(route('laporan.index'));

        $halaman->assertOk();
        $halaman->assertDontSee('117/LHP/XVIII/06/2026');
        $halaman->assertSee('42/LHA/ITJ/05/2026');
    }

    /* Yang paling berbahaya bukan tautannya yang tersembunyi, melainkan isinya
       yang masih terkirim ke peramban. */
    public function test_isi_satker_lain_tidak_ikut_terkirim(): void
    {
        $halaman = $this->actingAs($this->bandung())->get(route('laporan.show', $this->lhp117()));

        $halaman->assertOk();
        // miliknya sendiri tampil
        $halaman->assertSee('REK-2026-014.1');
        // milik Sekretariat Badan pada laporan yang sama tidak
        $halaman->assertDontSee('REK-2026-018.1');
        $halaman->assertDontSee('REK-2026-018.3');
    }

    /* Temuan yang terjadi di tempatnya tetap terlihat walau seluruh
       rekomendasinya jatuh ke pihak lain — itu haknya untuk tahu. */
    public function test_temuan_di_tempatnya_tetap_terlihat_sebagai_untuk_diketahui(): void
    {
        $laporan = $this->lhp117();
        $terlihat = \App\Support\Terlihat::untuk($this->bandung());
        $laporan->load('temuan.satkers', 'temuan.rekomendasi.sasaran.satker');

        $dipangkas = $terlihat->pangkas($laporan);
        $kode = $dipangkas->temuan->pluck('kode')->all();

        // TMN-2026-014 terjadi di Bandung, TMN-2026-018 terjadi di Setba tapi
        // salah satu rekomendasinya ditujukan ke Bandung — keduanya terlihat.
        $this->assertContains('TMN-2026-014', $kode);
        $this->assertContains('TMN-2026-018', $kode);

        $t18 = $dipangkas->temuan->firstWhere('kode', 'TMN-2026-018');
        /* Barisnya pun ikut dipangkas: bukan cuma rekomendasi milik satker
           lain yang hilang, tapi juga baris satker lain di dalam rekomendasi
           yang dipikul bersama. */
        $this->assertSame(['BPK-BDG'],
            $t18->rekomendasi->flatMap(fn ($r) => $r->daftarSasaran()->pluck('satker.kode'))
                ->unique()->values()->all(),
            'temuan milik satker lain hanya boleh menyisakan baris si pembaca');
    }

    public function test_satker_ditolak_membuka_rekomendasi_satker_lain(): void
    {
        $milikSetba = Rekomendasi::where('kode', 'REK-2026-018.1')->firstOrFail();

        $this->actingAs($this->makassar())
            ->get(route('rekomendasi.show', $milikSetba))
            ->assertForbidden();
    }

    /* Angka yang bocor jauh lebih sulit disadari daripada halaman yang bocor:
       tidak ada kode rekomendasi yang terlihat, cuma bilangan yang diam-diam
       menghitung berkas satuan kerja lain. */
    public function test_angka_ringkasan_ikut_tersaring(): void
    {
        $seluruh = \App\Models\Rekomendasi::count();
        // Lewat sasaran: penugasan tidak lagi tersimpan di rekomendasi.
        $milikMakassar = \App\Models\Rekomendasi::whereHas('sasaran.satker',
            fn ($q) => $q->where('satkers.kode', 'BPK-MKS'))->count();

        $this->assertLessThan($seluruh, $milikMakassar, 'data contoh harus punya berkas satker lain');

        $halaman = $this->actingAs($this->makassar())->get(route('ringkasan'));
        $halaman->assertOk();

        $isi = $halaman->getContent();
        preg_match('/([0-9]+)\s*rekomendasi pada/', $isi, $c);

        $this->assertSame((string) $milikMakassar, $c[1] ?? null,
            'Ringkasan harus menghitung berkas milik pembacanya saja');
    }

    /* Pencarian adalah pintu belakang yang paling mudah terlupa: tautannya
       memang tidak ada, tapi kalau kotak cari mengembalikannya, isinya tetap
       terbuka. */
    public function test_pencarian_tidak_mengembalikan_berkas_satker_lain(): void
    {
        $halaman = $this->actingAs($this->makassar())
            ->get(route('rekomendasi.index', ['cari' => 'REK-2026']));

        $halaman->assertOk();
        $halaman->assertDontSee('REK-2026-014.1');   // Balai Bandung
        $halaman->assertDontSee('REK-2026-018.1');   // Sekretariat Badan
        $halaman->assertSee('REK-2026-021.1');       // miliknya
    }

    /* Cakupan pencarian diperlebar: nomor surat, nama satuan kerja, dan judul
       temuan dulu tidak pernah ketemu walau itu yang paling sering diketik. */
    public function test_pencarian_menemukan_lewat_nomor_surat_dan_judul_temuan(): void
    {
        $akun = User::where('email', 'setba@contoh.test')->firstOrFail();

        $this->actingAs($akun)->get(route('rekomendasi.index', ['cari' => '117/LHP']))
            ->assertOk()->assertSee('REK-2026-014.1');

        $this->actingAs($akun)->get(route('rekomendasi.index', ['cari' => 'laptop']))
            ->assertOk()->assertSee('REK-2026-021.1');

        $this->actingAs($akun)->get(route('rekomendasi.index', ['cari' => 'Makassar']))
            ->assertOk()->assertSee('REK-2026-021.1');
    }

    public function test_setba_tetap_melihat_seluruhnya(): void
    {
        $halaman = $this->actingAs(User::where('email', 'setba@contoh.test')->firstOrFail())
            ->get(route('laporan.show', $this->lhp117()));

        $halaman->assertOk();
        $halaman->assertSee('REK-2026-014.1');
        $halaman->assertSee('REK-2026-018.1');
    }
}
