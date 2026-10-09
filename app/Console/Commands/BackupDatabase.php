<?php

namespace App\Console\Commands;

use App\Domain\Backup\Actions\CreateBackup;
use App\Domain\Backup\Actions\PruneBackups;
use Illuminate\Console\Command;
use Throwable;

class BackupDatabase extends Command
{
    protected $signature = 'erp:backup {--no-prune : Keep every old backup this time}';

    protected $description = 'Back up the database, copy it off-site and apply the retention rule';

    public function handle(CreateBackup $backup, PruneBackups $prune): int
    {
        try {
            $run = $backup();
        } catch (Throwable $e) {
            $this->error('Backup failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf('Backup %s (%s KB)%s.', $run->file, number_format($run->size_bytes / 1024, 1), $run->offsite ? ', copied off-site' : ', on this server only'));

        if (! $this->option('no-prune')) {
            $this->line('Removed '.$prune().' expired backup(s).');
        }

        return self::SUCCESS;
    }
}
