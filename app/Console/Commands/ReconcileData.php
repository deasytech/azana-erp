<?php

namespace App\Console\Commands;

use App\Domain\Backup\Actions\ReconcileLedgers;
use App\Domain\Backup\Data\StatusCheck;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ReconcileData extends Command
{
    public const LAST_RESULT = 'monitor:reconcile';

    protected $signature = 'erp:reconcile';

    protected $description = 'Check that the stock ledger, the journal, receivables and payables agree with the documents behind them';

    public function handle(ReconcileLedgers $reconcile): int
    {
        $checks = $reconcile();

        foreach ($checks as $check) {
            $this->line(sprintf('%-8s %s: %s', strtoupper($check->state), $check->name, $check->detail));
        }

        // The monitor reads this instead of recomputing the checks every fifteen minutes.
        Cache::put(self::LAST_RESULT, ['at' => now()->toIso8601String(), 'checks' => array_map(fn (StatusCheck $c) => [$c->name, $c->state, $c->detail], $checks)], now()->addDays(2));

        $failed = array_filter($checks, fn (StatusCheck $c) => $c->state === StatusCheck::FAILED);
        $failed && Log::error('Reconciliation failed', ['problems' => array_map(fn (StatusCheck $c) => "{$c->name}: {$c->detail}", $failed)]);

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
