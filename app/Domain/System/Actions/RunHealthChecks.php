<?php

namespace App\Domain\System\Actions;

use App\Domain\System\Data\HealthCheckResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
            $this->check('queue', fn () => config('queue.default')),
            $this->check('storage', function () {
                $disk = Storage::disk();
                $path = 'health/'.bin2hex(random_bytes(4)).'.txt';
                $disk->put($path, 'ok');
                $ok = $disk->get($path) === 'ok';
                $disk->delete($path);
                throw_unless($ok, 'Storage read-back failed');

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

            return new HealthCheckResult($name, false, $e->getMessage());
        }
    }
}
