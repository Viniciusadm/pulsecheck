<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('monitors:dispatch-due')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('maintenance:sync')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('incidents:send-reminders')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::call(fn () => touch(storage_path('framework/scheduler-heartbeat')))
    ->name('scheduler-heartbeat')
    ->everyMinute();

Schedule::command('uptime:rollup')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('model:prune')
    ->dailyAt('03:00')
    ->onOneServer();

Schedule::command('queue:prune-failed', ['--hours' => 168])
    ->dailyAt('03:15')
    ->onOneServer();
