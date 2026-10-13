<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled work
|--------------------------------------------------------------------------
|
| One cron entry drives all of this. See docs/DEPLOYMENT.md:
|
|   * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
|
*/

// Nightly database backup, with pruning and an alert to the administrators
// when it fails.
Schedule::command('pms:backup')
    ->dailyAt('01:30')
    ->withoutOverlapping()
    ->onFailure(fn () => logger()->error('The scheduled pms:backup command did not run.'));

// Buyer installment reminders. Each reminder is logged so it is never sent
// twice, which makes a second run on the same day harmless.
Schedule::command('pms:send-reminders')
    ->dailyAt('08:00')
    ->withoutOverlapping();
