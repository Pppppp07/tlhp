<?php

namespace Tests\Feature;

use App\Models\Laporan;
use App\Models\Rekomendasi;
use App\Models\User;
use Database\Seeders\ContohLaporanSeeder;
use Database\Seeders\DataMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Sapuan asap: tiap peran membuka tiap layar.
 *
 * Bukan uji perilaku — uji ini hanya memastikan tidak ada halaman yang mati
 * sesudah bentuk datanya berubah. Halaman yang melempar galat 500 tidak
 * ketahuan dari uji perilaku mana pun kalau layarnya memang tidak diuji.
 */
class SapuLayarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([DataMasterSeeder::class, ContohLaporanSeeder::class]);
    }

    public static function peran(): array
    {
        return [
            'setba'       => ['setba@contoh.test'],
            'satker'      => ['bandung@contoh.test'],
            'satker lain' => ['makassar@contoh.test'],
            'uki'         => ['uki@contoh.test'],
            'inspektorat' => ['inspektorat@contoh.test'],
            'pimpinan'    => ['pimpinan@contoh.test'],
            'admin'       => ['admin@contoh.test'],
        ];
    }

    #[DataProvider('peran')]
    public function test_semua_layar_terbuka(string $surel): void
    {
        $u = User::where('email', $surel)->firstOrFail();

        /* 403 dan 302 sah — bukan tiap peran boleh membuka tiap layar. Yang
           tidak boleh ada adalah 500: itu halaman yang mati, bukan halaman
           yang ditolak. */
        foreach (['/', '/rekomendasi', '/laporan', '/ringkasan', '/kabar',
                  '/rekomendasi-saya', '/sudah-selesai', '/siptl',
                  '/validasi', '/laporan/baru'] as $jalur) {
            $r = $this->actingAs($u)->get($jalur);
            $this->assertLessThan(500, $r->getStatusCode(),
                "{$surel} membuka {$jalur} — halaman mati");
        }
    }

    #[DataProvider('peran')]
    public function test_rincian_terbuka_untuk_yang_berhak(string $surel): void
    {
        $u = User::where('email', $surel)->firstOrFail();
        $terlihat = \App\Support\Terlihat::untuk($u);

        $lap = $terlihat->laporan()->take(3)->get();
        foreach ($lap as $l) {
            $this->assertLessThan(500,
                $this->actingAs($u)->get(route('laporan.show', $l))->getStatusCode(),
                "{$surel} membuka laporan {$l->nomor}");
        }

        $rek = $terlihat->rekomendasi()->take(5)->get();
        foreach ($rek as $r) {
            $this->assertLessThan(500,
                $this->actingAs($u)->get(route('rekomendasi.show', $r))->getStatusCode(),
                "{$surel} membuka rekomendasi {$r->kode}");
        }
    }

    /**
     * Setiap rekomendasi dibuka Setba. Sapuan ini yang menangkap halaman yang
     * mati hanya pada keadaan tertentu — mis. rekomendasi tanpa sasaran, atau
     * yang sudah di tingkat 2.
     */
    public function test_setiap_rekomendasi_bisa_dibuka_setba(): void
    {
        $setba = User::where('email', 'setba@contoh.test')->firstOrFail();

        foreach (Rekomendasi::all() as $r) {
            $this->assertLessThan(500,
                $this->actingAs($setba)->get(route('rekomendasi.show', $r))->getStatusCode(),
                "rekomendasi {$r->kode} tidak bisa dibuka");
        }

        foreach (Laporan::all() as $l) {
            $this->assertLessThan(500,
                $this->actingAs($setba)->get(route('laporan.show', $l))->getStatusCode(),
                "laporan {$l->nomor} tidak bisa dibuka");
        }
    }

    /**
     * Rekomendasi tanpa sasaran sama sekali — keadaan yang mungkin terjadi
     * kalau Setba mencatat laporan tanpa menyebut satuan kerjanya. Halamannya
     * harus tetap terbuka dan mengatakan apa adanya.
     */
    public function test_rekomendasi_tanpa_sasaran_tetap_terbuka(): void
    {
        $r = Rekomendasi::firstOrFail();
        $r->tindakan->each(fn ($t) => $t->sasaran()->delete());

        $halaman = $this->actingAs(User::where('email', 'setba@contoh.test')->firstOrFail())
            ->get(route('rekomendasi.show', $r));

        $halaman->assertOk();
        $this->assertNull($r->fresh()->posisiTampil());
    }
}
