<?php

namespace App\Providers;

use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /* Tanggal peragaan, lihat config/simtlhp.php. Hanya tanggalnya yang
           dipatok — jam kabar tetap berjalan. Uji otomatis mengatur waktunya
           sendiri, jadi tidak disentuh. */
        $hari = config('simtlhp.hari_ini');
        if ($hari && ! $this->app->runningUnitTests()) {
            $kini = Carbon::now();
            Carbon::setTestNow(Carbon::parse($hari)->setTime($kini->hour, $kini->minute, $kini->second));
        }
    }
}
