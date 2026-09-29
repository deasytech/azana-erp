<?php

namespace App\Domain\System\Actions;

use Illuminate\Support\Facades\DB;

/** Race-safe next value of a named counter (row-locked inside a transaction). */
class NextNumber
{
    private const TABLE = 'number_sequences';

    public function __invoke(string $key): int
    {
        return DB::transaction(function () use ($key) {
            DB::table(self::TABLE)->insertOrIgnore([
                'key' => $key, 'last_value' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);

            $row = DB::table(self::TABLE)->where('key', $key)->lockForUpdate()->first();
            $next = $row->last_value + 1;

            DB::table(self::TABLE)->where('key', $key)->update(['last_value' => $next, 'updated_at' => now()]);

            return $next;
        });
    }
}
