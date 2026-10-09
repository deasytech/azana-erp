<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Counts the unexpected errors the application reports, an hour at a time, so the monitor can say "the system is throwing errors"
 * without anybody reading a log file. Stores only the count and the kind and place of the latest error - never its message,
 * which can hold a customer's details - and never lets a failing cache turn one error into two.
 */
class ErrorTally
{
    private const LATEST = 'monitor:errors:latest';

    public static function record(Throwable $e): void
    {
        try {
            $hour = self::hourKey(now());
            Cache::add($hour, 0, now()->addHours(26));
            Cache::increment($hour);
            Cache::put(self::LATEST, ['at' => now()->toIso8601String(), 'where' => class_basename($e).' in '.str_replace(base_path().'/', '', $e->getFile()).':'.$e->getLine()], now()->addDays(2));
        } catch (Throwable) {
            // Monitoring must never be the reason a request fails.
        }
    }

    /** @return array{count: int, latest: ?array{at: string, where: string}} errors in the last 24 hours */
    public static function lastDay(): array
    {
        $count = 0;

        for ($i = 0; $i < 24; $i++) {
            $count += (int) Cache::get(self::hourKey(now()->subHours($i)), 0);
        }

        return ['count' => $count, 'latest' => Cache::get(self::LATEST)];
    }

    private static function hourKey(CarbonInterface $at): string
    {
        return 'monitor:errors:'.$at->format('YmdH');
    }
}
