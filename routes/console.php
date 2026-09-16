<?php

use Illuminate\Support\Facades\Schedule;

/* Draf tanggapan yang mengendap seminggu dan kewajibannya sudah tuntas dikirim
   sendiri ke Setba, tiap malam. Jalankan `php artisan schedule:work` (atau cron
   `schedule:run`) supaya ini berjalan. */
Schedule::command('tlhp:kirim-draf')->dailyAt('00:30');
