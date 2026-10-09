<?php

namespace App\Domain\Backup\Actions;

use App\Domain\Backup\Data\StatusCheck as C;
use App\Domain\Backup\Models\BackupRun;
use App\Domain\System\Actions\RunHealthChecks;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Is the system looking after itself? Backups taken and tested, scheduler and queue worker running, disk not filling up, the
 * services answering. Read by the Backups & monitoring page and by `erp:monitor`, which raises the alarm.
 */
class GetOperationsStatus
{
    private const BACKUP = 'Database backup';

    private const OFFSITE = 'Off-site copy';

    private const QUEUE = 'Queue worker';

    private const DISK = 'Disk space';

    private const RESTORE_TEST = 'Restore test';

    public const HEARTBEAT = 'monitor:scheduler-heartbeat';

    public function __construct(private readonly RunHealthChecks $health) {}

    /** @return list<C> */
    public function __invoke(): array
    {
        return array_merge($this->services(), [$this->backup(), $this->offsite(), $this->restoreTest(), $this->scheduler(), $this->queue(), $this->disk(), $this->debug()]);
    }

    /** @param list<C> $checks */
    public static function worst(array $checks): string
    {
        $states = array_map(fn (C $c) => $c->state, $checks);

        if (in_array(C::FAILED, $states, true)) {
            return C::FAILED;
        }

        return in_array(C::WARNING, $states, true) ? C::WARNING : C::OK;
    }

    /** @return list<C> */
    private function services(): array
    {
        return array_map(fn ($r) => new C(ucfirst($r->name), $r->ok ? C::OK : C::FAILED, $r->ok ? "Working ({$r->detail})." : 'Not answering.'), ($this->health)());
    }

    private function backup(): C
    {
        $last = BackupRun::where('kind', BackupRun::BACKUP)->where('status', 'success')->latest('started_at')->first();
        $failed = BackupRun::where('kind', BackupRun::BACKUP)->latest('started_at')->first();

        if (! $last) {
            return new C(self::BACKUP, C::FAILED, 'No backup has been taken yet. Run `php artisan erp:backup`.');
        }

        $hours = (int) $last->started_at->diffInHours(now());

        if ($hours > config('backup.max_backup_age_hours')) {
            return new C(self::BACKUP, C::FAILED, "The last good backup is {$hours} hours old ({$last->started_at->format('d M Y H:i')}).".($failed && $failed->status === 'failed' ? " The latest attempt failed: {$failed->message}" : ''));
        }

        return new C(self::BACKUP, C::OK, "Last good backup {$last->started_at->diffForHumans()} ({$last->started_at->format('d M Y H:i')}).");
    }

    private function offsite(): C
    {
        if (! config('backup.offsite_disk')) {
            return new C(self::OFFSITE, C::WARNING, 'No off-site disk is set (BACKUP_OFFSITE_DISK). Backups exist only on this server.');
        }

        $last = BackupRun::where('kind', BackupRun::BACKUP)->where('status', 'success')->latest('started_at')->first();

        return $last?->offsite
            ? new C(self::OFFSITE, C::OK, 'The latest backup is also on "'.config('backup.offsite_disk').'".')
            : new C(self::OFFSITE, C::FAILED, 'The latest backup is not off-site.');
    }

    private function restoreTest(): C
    {
        $last = BackupRun::where('kind', BackupRun::RESTORE_TEST)->latest('started_at')->first();

        if (! $last) {
            return new C(self::RESTORE_TEST, C::WARNING, 'A backup has never been test-restored. Run `php artisan erp:backup:test-restore`.');
        }

        if ($last->status !== 'success') {
            return new C(self::RESTORE_TEST, C::FAILED, "The last restore test failed: {$last->message}");
        }

        $days = (int) $last->started_at->diffInDays(now());

        return $days > config('backup.max_restore_test_age_days')
            ? new C(self::RESTORE_TEST, C::WARNING, "The last restore test passed {$days} days ago; test again.")
            : new C(self::RESTORE_TEST, C::OK, "Passed {$last->started_at->diffForHumans()}: {$last->message}");
    }

    private function scheduler(): C
    {
        $beat = Cache::get(self::HEARTBEAT);

        if (! $beat) {
            return new C('Scheduler', C::FAILED, 'The scheduler has not run. Add `* * * * * php artisan schedule:run` to cron.');
        }

        $minutes = (int) Carbon::parse($beat)->diffInMinutes(now());

        return $minutes > 5
            ? new C('Scheduler', C::FAILED, "The scheduler last ran {$minutes} minutes ago. Is cron running?")
            : new C('Scheduler', C::OK, 'Running (backups, alerts and postings depend on it).');
    }

    private function queue(): C
    {
        if (config('queue.default') !== 'database' || ! Schema::hasTable('jobs')) {
            return new C(self::QUEUE, C::OK, 'Not checked for the "'.config('queue.default').'" queue.');
        }

        $failed = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
        $oldest = DB::table('jobs')->whereNull('reserved_at')->min('available_at');
        $waiting = $oldest ? (int) Carbon::createFromTimestamp($oldest)->diffInMinutes(now()) : 0;

        return match (true) {
            $waiting > 10 => new C(self::QUEUE, C::FAILED, "A job has waited {$waiting} minutes. The worker is probably not running."),
            $failed > 0 => new C(self::QUEUE, C::WARNING, "{$failed} failed job(s). Look at them with `php artisan queue:failed`."),
            default => new C(self::QUEUE, C::OK, 'Jobs are being picked up and none have failed.'),
        };
    }

    private function disk(): C
    {
        $path = storage_path();
        $free = @disk_free_space($path);
        $total = @disk_total_space($path);

        if (! $free || ! $total) {
            return new C(self::DISK, C::WARNING, 'Could not read the free space.');
        }

        $percent = (int) round($free / $total * 100);
        $gb = number_format($free / 1024 ** 3, 1);

        return $percent < config('backup.min_free_disk_percent')
            ? new C(self::DISK, C::FAILED, "Only {$percent}% free ({$gb} GB). Backups and uploads need room.")
            : new C(self::DISK, C::OK, "{$percent}% free ({$gb} GB).");
    }

    private function debug(): C
    {
        if (! app()->isProduction()) {
            return new C('Production settings', C::OK, 'Not a production environment.');
        }

        return config('app.debug')
            ? new C('Production settings', C::FAILED, 'APP_DEBUG is on in production: errors would show internals to visitors.')
            : new C('Production settings', C::OK, 'Debug is off.');
    }
}
