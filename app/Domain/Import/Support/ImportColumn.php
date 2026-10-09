<?php

namespace App\Domain\Import\Support;

/** One column of an import template: its heading, whether it must be filled, and what it means. */
final class ImportColumn
{
    public function __construct(
        public readonly string $name,
        public readonly string $help,
        public readonly bool $required = false,
        public readonly string $example = '',
    ) {}
}
