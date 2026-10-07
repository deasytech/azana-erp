<?php

namespace App\Enums;

enum AccountType: string
{
    case Asset = 'asset';
    case Liability = 'liability';
    case Equity = 'equity';
    case Revenue = 'revenue';
    case Expense = 'expense';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->ucfirst()->toString();
    }

    /** Assets and expenses grow with debits; liabilities, equity and revenue with credits. */
    public function isDebitNormal(): bool
    {
        return $this === self::Asset || $this === self::Expense;
    }
}
