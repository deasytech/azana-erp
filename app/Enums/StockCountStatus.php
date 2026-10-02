<?php

namespace App\Enums;

enum StockCountStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** Counts that are finished and can no longer change. */
    public function isFinal(): bool
    {
        return in_array($this, [self::Approved, self::Rejected, self::Cancelled], true);
    }
}
