<?php

use App\Jobs\MonitorGvaJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// GVA monitor: scan the DOGV RSS feed + adjudicaciones page once a day,
// at 08:00 Spain time (Europe/Madrid). Laravel 12 registers scheduled work
// here rather than in a Console\Kernel class.
Schedule::job(new MonitorGvaJob())
    ->dailyAt('08:00')
    ->timezone('Europe/Madrid')
    ->name('monitor-gva')
    ->withoutOverlapping();

// Daily SaaS metrics snapshot at 02:00 Spain time.
Schedule::command('metricas:calcular')
    ->dailyAt('02:00')
    ->timezone('Europe/Madrid')
    ->name('metricas-calcular')
    ->withoutOverlapping();
