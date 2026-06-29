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

// Normativa sync (BOE + DOGV): once a week, Sundays at 06:00 Spain time.
Schedule::command('normativa:sync-boe')
    ->weeklyOn(0, '06:00')
    ->timezone('Europe/Madrid')
    ->name('normativa-sync-boe')
    ->withoutOverlapping();

Schedule::command('normativa:sync-dogv')
    ->weeklyOn(0, '06:30')
    ->timezone('Europe/Madrid')
    ->name('normativa-sync-dogv')
    ->withoutOverlapping();

// Convocatorias monitor: weekly, Sundays at 07:00 Spain time.
Schedule::command('convocatorias:monitor')
    ->weeklyOn(0, '07:00')
    ->timezone('Europe/Madrid')
    ->name('convocatorias-monitor')
    ->withoutOverlapping();

// Official temarios sync: first Sunday of each month at 05:00 Spain time.
Schedule::command('temarios:sync-boe')
    ->weeklyOn(0, '05:00')
    ->when(fn () => (int) now('Europe/Madrid')->day <= 7)
    ->timezone('Europe/Madrid')
    ->name('temarios-sync-boe')
    ->withoutOverlapping();

// Curriculum content sync (BOE/DOGV): first Sunday of each month at 04:00.
Schedule::command('curriculo:sync-boe')
    ->weeklyOn(0, '04:00')
    ->when(fn () => (int) now('Europe/Madrid')->day <= 7)
    ->timezone('Europe/Madrid')
    ->name('curriculo-sync-boe')
    ->withoutOverlapping();

Schedule::command('curriculo:sync-dogv')
    ->weeklyOn(0, '04:30')
    ->when(fn () => (int) now('Europe/Madrid')->day <= 7)
    ->timezone('Europe/Madrid')
    ->name('curriculo-sync-dogv')
    ->withoutOverlapping();
