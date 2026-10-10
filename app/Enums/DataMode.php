<?php

namespace App\Enums;

/** Whether the system still holds practice data (the go-live reset is allowed) or real records (it is not). */
enum DataMode: string
{
    case Demo = 'demo';
    case Live = 'live';

    public function label(): string
    {
        return $this === self::Demo ? 'Demo / practice data' : 'Live data';
    }
}
