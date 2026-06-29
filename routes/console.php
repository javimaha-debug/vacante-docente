<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// GVA monitor: scan the DOGV RSS feed + adjudicaciones page once a day,
// at 08:00 Spain time (Europe/Madrid). Laravel 12 registers scheduled work
// here rather than in a Console\Kernel class.
// Run the monitor inline (as a command) rather than queued, so it works with
// just the scheduler cron — no separate queue worker required.
Schedule::command('gva:monitor')
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

// Document monitor: scan official sources/sindicatos for new listings.
// Weekdays every 2h between 06:00–22:00, weekends every 6h. Inline (not queued)
// so it runs with just the scheduler cron.
Schedule::command('documents:monitor')
    ->weekdays()
    ->hourlyAt(5)
    ->between('6:00', '22:00')
    ->when(fn () => (int) now('Europe/Madrid')->hour % 2 === 0)
    ->timezone('Europe/Madrid')
    ->name('monitor-documents-weekday')
    ->withoutOverlapping();

Schedule::command('documents:monitor')
    ->weekends()
    ->everySixHours()
    ->timezone('Europe/Madrid')
    ->name('monitor-documents-weekend')
    ->withoutOverlapping();
