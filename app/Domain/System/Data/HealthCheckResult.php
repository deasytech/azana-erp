<?php

namespace App\Domain\System\Data;

final readonly class HealthCheckResult
{
    public function __construct(
        public string $name,
        public bool $ok,
        public ?string $detail = null,
    ) {}

    /** @return array{name: string, ok: bool, detail: string|null} */
    public function toArray(): array
    {
        return ['name' => $this->name, 'ok' => $this->ok, 'detail' => $this->detail];
    }
}
