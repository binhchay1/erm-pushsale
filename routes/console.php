<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('reports:warm-snapshots')->dailyAt('23:50');

// Populate Horizon throughput/runtime graphs. The scheduler itself should run every minute.
Schedule::command('horizon:snapshot')->everyFiveMinutes()->withoutOverlapping();
