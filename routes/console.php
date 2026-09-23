<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:send-service-reminders')->hourly()->withoutOverlapping(30);
Schedule::command('app:update-technician-scorecards')->dailyAt('01:00')->withoutOverlapping(120);
Schedule::command('app:generate-scheduled-reports')->everyFifteenMinutes()->withoutOverlapping(30);
Schedule::command('app:run-database-backup')->dailyAt('02:00')->withoutOverlapping(180);
Schedule::command('queue:work database --queue=notifications,default --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping(2);
