<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Penangguhan & pemulihan murid mengikuti umur tunggakan, dicek sekali sehari
// pagi hari sebelum jam operasional. Perlu cron ke `php artisan schedule:run`.
Schedule::command('students:suspend-overdue')->dailyAt('06:00');
