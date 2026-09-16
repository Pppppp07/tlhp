<?php

namespace Tests;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Carbon;

/**
 * Data contoh yang sama dengan yang diperagakan, berikut tanggalnya.
 *
 * Angka di uji ini boleh disebut apa adanya — 44 rekomendasi, 24 laporan, 7
 * kabar — karena data contohnya tetap. Kalau datanya berubah, ujinya yang
 * memberi tahu lebih dulu, bukan orang yang membaca layar.
 */
trait PakaiDataContoh
{
    /** Tanggal peragaan; `AppServiceProvider` sengaja tidak memasangnya saat uji. */
    protected function siapkanDataContoh(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-17 09:00:00'));
        $this->seed(DatabaseSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function akun(string $surel): User
    {
        return User::where('email', $surel)->firstOrFail();
    }

    protected function masuk(string $surel): static
    {
        return $this->actingAs($this->akun($surel));
    }
}
