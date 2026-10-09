<?php

namespace App\Domain\Backup\Actions;

use App\Domain\Backup\BackupException;
use App\Domain\Backup\Drivers\BackupDriver;
use App\Domain\Backup\Models\BackupRun;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Takes a backup of the database, checks the file can be read back, and copies it off the server when an off-site disk is named.
 * Every attempt is recorded, failures included, so the monitor and the Backups page show the truth. A backup counts as good only
 * when it is also off-site, if an off-site disk is configured: the point is to survive losing the server.
 */
class CreateBackup
{
    public function __construct(private readonly BackupDriver $driver) {}

    public function __invoke(?User $actor = null): BackupRun
    {
        $run = BackupRun::create(['kind' => BackupRun::BACKUP, 'status' => 'failed', 'started_at' => now(), 'created_by' => $actor?->getKey()]);

        $disk = Storage::disk(config('backup.disk'));
        $name = sprintf('%s/azana-%s.sql.gz', trim(config('backup.directory'), '/'), now()->format('Ymd-His'));
        $local = tempnam(sys_get_temp_dir(), 'azana-backup');
        $stored = false;

        try {
            $this->driver->dump($local);
            $this->assertReadable($local);

            $checksum = hash_file('sha256', $local);
            $size = filesize($local);

            $stream = fopen($local, 'rb');
            $disk->put($name, $stream) || throw new BackupException('Could not store the backup on the backup disk.');
            is_resource($stream) && fclose($stream);
            $stored = true;

            $offsite = $this->copyOffsite($local, $name);

            $run->update(['status' => 'success', 'file' => $name, 'size_bytes' => $size, 'checksum' => $checksum, 'offsite' => $offsite,
                'message' => config('backup.offsite_disk') ? null : 'Stored on this server only: set BACKUP_OFFSITE_DISK to keep a copy elsewhere.', 'finished_at' => now()]);
        } catch (Throwable $e) {
            report($e);

            // A copy that was stored but then failed a later step is not a backup anyone should trust or restore from.
            $stored && $disk->delete($name);
            $run->update(['status' => 'failed', 'message' => mb_substr($e->getMessage(), 0, 1000), 'finished_at' => now()]);

            throw $e;
        } finally {
            @unlink($local);
        }

        return $run->refresh();
    }

    private function copyOffsite(string $local, string $name): bool
    {
        $offsite = config('backup.offsite_disk');

        if (! $offsite) {
            return false;
        }

        $stream = fopen($local, 'rb');
        $ok = Storage::disk($offsite)->put($name, $stream);
        is_resource($stream) && fclose($stream);

        $ok || throw new BackupException("The backup was taken but could not be copied to the off-site disk \"{$offsite}\".");

        return true;
    }

    /** Reads the whole file through gzip: a truncated or corrupt file fails here, not on the day it is needed. */
    private function assertReadable(string $path): void
    {
        $handle = gzopen($path, 'rb');
        $bytes = 0;

        while ($handle && ! gzeof($handle)) {
            $chunk = gzread($handle, 1 << 20);
            $chunk === false && throw new BackupException('The backup file is corrupt.');
            $bytes += strlen($chunk);
        }

        $handle && gzclose($handle);

        $bytes > 0 || throw new BackupException('The backup is empty.');
    }
}
