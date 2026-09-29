<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Monetary amount in integer minor units (2 decimal places, e.g. kobo).
 * No floating point anywhere: parsing and formatting are string/integer based.
 */
final readonly class Money
{
    private function __construct(public int $minor, public string $currency) {}

    public static function ofMinor(int $minor, string $currency): self
    {
        return new self($minor, strtoupper($currency));
    }

    public static function parse(string $decimal, string $currency): self
    {
        $decimal = str_replace(',', '', trim($decimal));

        if (! preg_match('/^(-)?(\d+)(?:\.(\d{1,2}))?$/', $decimal, $m)) {
            throw new InvalidArgumentException("Invalid monetary amount [{$decimal}].");
        }

        $minor = ((int) $m[2]) * 100 + (int) str_pad($m[3] ?? '', 2, '0');

        return new self($m[1] === '-' ? -$minor : $minor, strtoupper($currency));
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor + $other->minor, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor - $other->minor, $this->currency);
    }

    public function multiply(int $quantity): self
    {
        return new self($this->minor * $quantity, $this->currency);
    }

    /** Plain decimal string, e.g. "1250.50". */
    public function toDecimal(): string
    {
        $abs = abs($this->minor);

        return ($this->minor < 0 ? '-' : '').intdiv($abs, 100).'.'.str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
    }

    public function format(): string
    {
        [$whole, $fraction] = explode('.', ltrim($this->toDecimal(), '-'));

        return ($this->minor < 0 ? '-' : '').$this->currency.' '.number_format((int) $whole).'.'.$fraction;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException("Currency mismatch: {$this->currency} vs {$other->currency}.");
        }
    }
}
