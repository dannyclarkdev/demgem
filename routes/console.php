<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Every fifteen minutes is a quarter-hour of slack on a lead time measured in days.
// The compose stack runs this from the scheduler service; a bare install needs
// `php artisan schedule:work` or a cron line for `schedule:run`.
Schedule::command('demgem:send-reminders')->everyFifteenMinutes()->withoutOverlapping();
