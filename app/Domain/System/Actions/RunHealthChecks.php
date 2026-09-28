<?php

namespace App\Domain\System\Actions;

use App\Domain\System\Data\HealthCheckResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Throwable;

class RunHealthChecks
{
    /** @return list<HealthCheckResult> */
    public function __invoke(): array
    {
        return [
            $this->check('database', function () {
                DB::connection()->getPdo();

                return DB::connection()->getDriverName();
            }),
            $this->check('cache', function () {
                $key = 'health:'.bin2hex(random_bytes(4));
                Cache::put($key, 'ok', 10);
                $value = Cache::pull($key);
                throw_unless($value === 'ok', 'Cache read-back failed');

                return config('cache.default');
            }),
            // Backend reachability only; says nothing about whether a worker is running.
            $this->check('queue', function () {
                Queue::connection()->size();

                return config('queue.default');
            }),
            $this->check('storage', function () {
                $disk = Storage::disk();
                $path = 'health/'.bin2hex(random_bytes(4)).'.txt';
                throw_unless($disk->put($path, 'ok'), 'Storage write failed');

                try {
                    $ok = $disk->get($path) === 'ok';
                } finally {
                    $deleted = $disk->delete($path);
                }

                throw_unless($ok, 'Storage read-back failed');
                throw_unless($deleted, 'Storage cleanup failed');

                return config('filesystems.default');
            }),
        ];
    }

    private function check(string $name, callable $probe): HealthCheckResult
    {
        try {
            return new HealthCheckResult($name, true, (string) $probe());
        } catch (Throwable $e) {
            report($e);

            return new HealthCheckResult($name, false, 'Check failed');
        }
    }
}
