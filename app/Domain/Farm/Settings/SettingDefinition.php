<?php

namespace App\Domain\Farm\Settings;

final readonly class SettingDefinition
{
    /** @param 'int'|'decimal'|'string'|'bool' $type */
    public function __construct(
        public string $key,
        public string $group,
        public string $label,
        public string $type,
        public string $default,
        public ?string $description = null,
    ) {}
}
