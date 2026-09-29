<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Lets desk:prelaunch-check confirm the scheduler (cron) is actually running.
Schedule::call(fn () => Cache::forever('desk.scheduler.last_run', now()->toIso8601String()))
    ->name('desk:scheduler-heartbeat')
    ->everyFiveMinutes();

Schedule::command('desk:timesheet-reminder')
    ->weeklyOn(5, '16:00')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping();

Schedule::command('desk:prune-ai-requests')->dailyAt('03:15')->timezone(config('app.timezone'));

Schedule::command('desk:draft-retainer-invoices')
    ->monthlyOn(1, '06:00')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping();
