<?php

namespace Tests\Feature;

use App\Models\Laporan;
use App\Models\Rekomendasi;
use App\Models\Satker;
use App\Models\User;
use Database\Seeders\ContohLaporanSeeder;
use Database\Seeders\DataMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Tests\TestCase;

/**
 * Perintah pengosongan data contoh.
 *
 * Bahaya sebenarnya bukan salah menghapus, melainkan LUPA menghapus: tabel baru
 * ditambahkan, daftarnya tidak ikut diperbarui, dan basis data yang disangka
 * bersih ternyata masih menyimpan data contoh. Uji pertama menutup itu.
 */
class KosongkanDataTest extends TestCase
{
    use RefreshDatabase;

    /** Daftar tabel dari perintahnya, dibaca lewat pantulan. */
    private function daftar(string $nama): array
    {
        return (new ReflectionClass(\App\Console\Commands\KosongkanData::class))
            ->getConstant($nama);
    }

    /**
     * Tiap tabel harus disebut salah satu daftarnya.
     *
     * Kalau uji ini gagal, ada tabel baru yang belum masuk daftar — dan
     * pengosongan yang melewatkan satu tabel lebih berbahaya daripada tidak
     * mengosongkan sama sekali, karena orang mengira sudah bersih.
     */
    public function test_tiap_tabel_disebut_di_salah_satu_daftar(): void
    {
        $disebut = array_merge(
            $this->daftar('TRANSAKSI'),
            $this->daftar('MASTER'),
            $this->daftar('SISTEM'),
            ['migrations'],   // catatan migrasi, bukan data
        );

        $adaDiBasisData = collect(Schema::getTables())
            ->pluck('name')
            ->reject(fn ($t) => str_starts_with($t, 'sqlite_'))
            ->values();

        $terlewat = $adaDiBasisData->reject(fn ($t) => in_array($t, $disebut, true));

        $this->assertTrue($terlewat->isEmpty(),
            'Tabel belum masuk daftar tlhp:kosongkan: '.$terlewat->join(', '));
    }

    public function test_data_transaksi_dikosongkan_master_dipertahankan(): void
    {
        $this->seed([DataMasterSeeder::class, ContohLaporanSeeder::class]);

        $this->assertGreaterThan(0, Laporan::count());
        $satkerAwal = Satker::count();
        $akunAwal = User::count();

        $this->artisan('tlhp:kosongkan', ['--force' => true])->assertSuccessful();

        $this->assertSame(0, Laporan::count());
        $this->assertSame(0, Rekomendasi::count());
        $this->assertSame(0, DB::table('sasarans')->count());
        $this->assertSame(0, DB::table('lampirans')->count());
        $this->assertSame(0, DB::table('riwayat_berkas')->count());

        /* Yang membuat aplikasinya masih bisa dipakai: akun dan data master
           tidak ikut hilang. */
        $this->assertSame($satkerAwal, Satker::count());
        $this->assertSame($akunAwal, User::count());
        $this->assertGreaterThan(0, DB::table('referensis')->count());
    }

    public function test_semua_ikut_mengosongkan_master_dan_akun(): void
    {
        $this->seed([DataMasterSeeder::class, ContohLaporanSeeder::class]);

        $this->artisan('tlhp:kosongkan', ['--semua' => true, '--force' => true])
            ->assertSuccessful();

        $this->assertSame(0, User::count());
        $this->assertSame(0, Satker::count());
        $this->assertSame(0, DB::table('referensis')->count());
        $this->assertSame(0, DB::table('kategori_temuans')->count());
    }

    public function test_tanpa_force_bertanya_dan_bisa_dibatalkan(): void
    {
        $this->seed([DataMasterSeeder::class, ContohLaporanSeeder::class]);
        $sebelum = Laporan::count();

        $this->artisan('tlhp:kosongkan')
            ->expectsConfirmation('Kosongkan? Ini tidak bisa dibatalkan.', 'no')
            ->assertSuccessful();

        $this->assertSame($sebelum, Laporan::count(), 'dibatalkan tapi tetap menghapus');
    }

    public function test_aman_dijalankan_pada_basis_data_yang_sudah_kosong(): void
    {
        $this->seed([DataMasterSeeder::class]);

        $this->artisan('tlhp:kosongkan', ['--force' => true])->assertSuccessful();
        $this->artisan('tlhp:kosongkan', ['--force' => true])->assertSuccessful();
    }
}
