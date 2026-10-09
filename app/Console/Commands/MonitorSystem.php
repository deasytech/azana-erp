<?php

namespace App\Console\Commands;

use App\Domain\Backup\Actions\GetOperationsStatus;
use App\Domain\Backup\Data\StatusCheck;
use App\Domain\Backup\Notifications\OperationsAlert;
use App\Domain\Tasks\Notifications\Notifier;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MonitorSystem extends Command
{
    protected $signature = 'erp:monitor';

    protected $description = 'Check backups, scheduler, queue, disk and services, and alert the people who look after the system';

    public function handle(GetOperationsStatus $status, Notifier $notifier): int
    {
        $checks = $status();
        $problems = array_values(array_filter($checks, fn (StatusCheck $c) => $c->state === StatusCheck::FAILED));

        foreach ($checks as $check) {
            $this->line(sprintf('%-8s %s: %s', strtoupper($check->state), $check->name, $check->detail));
        }

        if ($problems === []) {
            Cache::forget('monitor:last-alert');

            return self::SUCCESS;
        }

        Log::error('Operations check failed', ['problems' => array_map(fn (StatusCheck $c) => "{$c->name}: {$c->detail}", $problems)]);

        // The same set of problems is announced once a day, not every time the monitor runs.
        $signature = hash('sha256', implode('|', array_map(fn (StatusCheck $c) => $c->name, $problems)));

        if (Cache::get('monitor:last-alert') !== $signature) {
            Cache::put('monitor:last-alert', $signature, now()->addDay());

            User::where('is_active', true)->get()->filter(fn (User $u) => $u->can('backups.view'))
                ->each(fn (User $u) => $notifier->send($u, new OperationsAlert($problems)));
        }

        return self::FAILURE;
    }
}
