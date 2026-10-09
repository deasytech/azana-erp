<?php

namespace App\Jobs;

use App\Domain\Backup\Actions\TestBackupRestore;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunBackupRestoreTest implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(public readonly ?int $userId = null) {}

    public function handle(TestBackupRestore $test): void
    {
        $test(null, $this->userId ? User::find($this->userId) : null);
    }
}
