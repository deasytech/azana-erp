<?php

namespace App\Console\Commands;

use App\Domain\Backup\Actions\GetOperationsStatus;
use App\Domain\Backup\Actions\RunPreflightChecks;
use App\Domain\Backup\Data\StatusCheck;
use Illuminate\Console\Command;

class Preflight extends Command
{
    protected $signature = 'erp:preflight';

    protected $description = 'Check that this server is set up to run the ERP in production (the deployment script runs it before going live)';

    public function handle(RunPreflightChecks $checks): int
    {
        $results = $checks();

        foreach ($results as $check) {
            $this->line(sprintf('%-8s %-18s %s', strtoupper($check->state), $check->name, $check->detail));
        }

        $worst = GetOperationsStatus::worst($results);
        $worst === StatusCheck::FAILED ? $this->error('Not ready for production.') : $this->info($worst === StatusCheck::WARNING ? 'Ready, with warnings.' : 'Ready.');

        return $worst === StatusCheck::FAILED ? self::FAILURE : self::SUCCESS;
    }
}
