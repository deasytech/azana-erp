<?php

use App\Domain\Backup\Actions\GetOperationsStatus;
use App\Domain\Finance\Actions\SyncOperationalPostings;
use App\Domain\Semen\Actions\ExpireSemenBatches;
use App\Domain\Tasks\Actions\GenerateDailyTasks;
use App\Domain\Tasks\Actions\SendAlertNotifications;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(fn () => app(ExpireSemenBatches::class)())->daily()->name('expire-semen-batches')->withoutOverlapping();
Schedule::call(fn () => app(SyncOperationalPostings::class)())->hourly()->name('post-operational-journals')->withoutOverlapping();
Schedule::call(fn () => app(GenerateDailyTasks::class)())->dailyAt('05:00')->name('generate-daily-tasks')->withoutOverlapping();
Schedule::call(fn () => app(SendAlertNotifications::class)())->hourly()->name('send-alert-notifications')->withoutOverlapping();

// Backups, a restore test, and the monitor that raises the alarm when any of it stops. The heartbeat shows the scheduler itself is alive.
Schedule::call(fn () => Cache::put(GetOperationsStatus::HEARTBEAT, now()->toIso8601String(), now()->addHour()))->everyMinute()->name('scheduler-heartbeat');
Schedule::command('erp:backup')->dailyAt('02:00')->name('database-backup')->withoutOverlapping(120)->onOneServer();
Schedule::command('erp:backup:test-restore')->weeklyOn(0, '04:00')->name('backup-restore-test')->withoutOverlapping(180)->onOneServer();
Schedule::command('erp:reconcile')->dailyAt('03:00')->name('reconcile-ledgers')->withoutOverlapping(60)->onOneServer();
Schedule::command('erp:monitor')->everyFifteenMinutes()->name('system-monitor')->withoutOverlapping();
