<?php

namespace App\Console\Commands;

use App\Domain\Backup\Actions\TestBackupRestore;
use Illuminate\Console\Command;

class TestBackupRestoreCommand extends Command
{
    protected $signature = 'erp:backup:test-restore';

    protected $description = 'Prove the newest backup restores, by loading it into a scratch database';

    public function handle(TestBackupRestore $test): int
    {
        $run = $test();

        $run->status === 'success' ? $this->info($run->message) : $this->error('Restore test failed: '.$run->message);

        return $run->status === 'success' ? self::SUCCESS : self::FAILURE;
    }
}
