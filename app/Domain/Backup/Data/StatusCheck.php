<?php

namespace App\Domain\Backup\Data;

/** One line of the operations status: what was checked, how it stands (ok, warning or failed) and why. */
final class StatusCheck
{
    public const OK = 'ok';

    public const WARNING = 'warning';

    public const FAILED = 'failed';

    public function __construct(public readonly string $name, public readonly string $state, public readonly string $detail) {}
}
