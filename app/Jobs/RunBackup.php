<?php

namespace App\Jobs;

use App\Domain\Backup\Actions\CreateBackup;
use App\Domain\Backup\Actions\PruneBackups;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** A backup asked for from the Backups page: it can take minutes, so it runs on the queue and not in the web request. */
class RunBackup implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(public readonly ?int $userId = null) {}

    public function handle(CreateBackup $backup, PruneBackups $prune): void
    {
        $backup($this->userId ? User::find($this->userId) : null);
        $prune();
    }
}
