<?php

namespace App\Domain\Backup\Actions;

use App\Domain\Backup\Models\BackupRun;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Applies the retention rule: all backups of the last few days, then the newest of each week, then the newest of each month.
 * The newest good backup, and the backup behind the latest passed restore test, are never removed. Run records stay as history.
 */
class PruneBackups
{
    /** @return int how many backups were removed */
    public function __invoke(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();
        $rule = config('backup.retention');

        $backups = BackupRun::where('kind', BackupRun::BACKUP)->where('status', 'success')->whereNull('pruned_at')->orderByDesc('started_at')->get();
        $keep = [];
        $weeks = [];
        $months = [];

        foreach ($backups as $backup) {
            $at = CarbonImmutable::instance($backup->started_at);
            $week = $at->format('o-W');
            $month = $at->format('Y-m');

            if ($at->gte($now->subDays($rule['daily_days']))) {
                $keep[$backup->id] = true;
            }

            if ($at->gte($now->subWeeks($rule['weekly_weeks'])) && ! isset($weeks[$week])) {
                $weeks[$week] = $keep[$backup->id] = true;
            }

            if ($at->gte($now->subMonths($rule['monthly_months'])) && ! isset($months[$month])) {
                $months[$month] = $keep[$backup->id] = true;
            }
        }

        if ($newest = $backups->first()) {
            $keep[$newest->id] = true;
        }

        if ($proven = BackupRun::where('kind', BackupRun::RESTORE_TEST)->where('status', 'success')->orderByDesc('started_at')->value('backup_run_id')) {
            $keep[$proven] = true;
        }

        $removed = 0;

        foreach ($backups->reject(fn (BackupRun $b) => isset($keep[$b->id])) as $backup) {
            if ($this->delete($backup)) {
                $backup->update(['pruned_at' => now()]);
                $removed++;
            }
        }

        return $removed;
    }

    private function delete(BackupRun $backup): bool
    {
        try {
            Storage::disk(config('backup.disk'))->delete($backup->file);
            config('backup.offsite_disk') && Storage::disk(config('backup.offsite_disk'))->delete($backup->file);

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
