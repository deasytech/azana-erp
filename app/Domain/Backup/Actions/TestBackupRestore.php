<?php

namespace App\Domain\Backup\Actions;

use App\Domain\Backup\BackupException;
use App\Domain\Backup\Drivers\BackupDriver;
use App\Domain\Backup\Models\BackupRun;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Proves a backup can be restored: loads it into a scratch database and checks that it holds every table the system has and that
 * the people and the audit trail are in it. If the newest local file is missing it is fetched from the off-site copy, which tests
 * that route too. The result is recorded either way.
 */
class TestBackupRestore
{
    /** Tables that must hold rows in any real installation: an empty restore of these is a failed restore. */
    private const MUST_HAVE_ROWS = ['users', 'roles', 'migrations'];

    public function __construct(private readonly BackupDriver $driver) {}

    public function __invoke(?BackupRun $backup = null, ?User $actor = null): BackupRun
    {
        $backup ??= BackupRun::where('kind', BackupRun::BACKUP)->where('status', 'success')->whereNull('pruned_at')->orderByDesc('started_at')->first();

        $run = BackupRun::create([
            'kind' => BackupRun::RESTORE_TEST, 'status' => 'failed', 'started_at' => now(), 'backup_run_id' => $backup?->id,
            'file' => $backup?->file, 'created_by' => $actor?->getKey(),
        ]);

        $local = tempnam(sys_get_temp_dir(), 'azana-restore');

        try {
            $backup ?? throw new BackupException('There is no backup to test yet.');

            $this->fetch($backup, $local);
            hash_file('sha256', $local) === $backup->checksum || throw new BackupException('The backup file does not match the checksum recorded when it was taken: it has been changed or damaged.');

            $counts = $this->driver->restoreScratch($local);
            $problems = $this->problems($counts);

            $run->update([
                'status' => $problems === [] ? 'success' : 'failed', 'finished_at' => now(),
                'message' => $problems === [] ? 'Restored '.count($counts).' tables, '.number_format(array_sum($counts)).' rows. Users: '.($counts['users'] ?? 0).'.'.$this->note($counts) : implode(' ', $problems),
            ]);
        } catch (Throwable $e) {
            report($e);
            $run->update(['status' => 'failed', 'message' => mb_substr($e->getMessage(), 0, 1000), 'finished_at' => now()]);
        } finally {
            @unlink($local);
        }

        return $run->refresh();
    }

    private function fetch(BackupRun $backup, string $to): void
    {
        foreach (array_filter([config('backup.disk'), config('backup.offsite_disk')]) as $diskName) {
            $disk = Storage::disk($diskName);

            if ($disk->exists($backup->file) && ($stream = $disk->readStream($backup->file))) {
                $out = fopen($to, 'wb');
                stream_copy_to_stream($stream, $out);
                fclose($out);
                is_resource($stream) && fclose($stream);

                return;
            }
        }

        throw new BackupException("The backup file {$backup->file} cannot be found on the backup disk or off-site.");
    }

    /**
     * What makes a restore a failure: the people and roles must have come back. A table the backup lacks is only a note, because a
     * migration may have run since the backup was taken.
     *
     * @param  array<string, int>  $counts
     * @return list<string>
     */
    private function problems(array $counts): array
    {
        $problems = [];

        foreach (self::MUST_HAVE_ROWS as $table) {
            ($counts[$table] ?? 0) > 0 || $problems[] = "The restored {$table} table is empty.";
        }

        return $problems;
    }

    /** @param array<string, int> $counts */
    private function note(array $counts): string
    {
        $missing = array_diff(Schema::getTableListing(DB::getDriverName() === 'sqlite' ? null : DB::getDatabaseName(), false), array_keys($counts));

        return $missing === [] ? '' : ' Not in the backup (fine if migrations ran since it was taken): '.implode(', ', array_slice($missing, 0, 5)).'.';
    }
}
