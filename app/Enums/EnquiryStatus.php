<?php

namespace App\Enums;

enum EnquiryStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Quoted = 'quoted';
    case Converted = 'converted';
    case Closed = 'closed';
    case Spam = 'spam';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** Converted, closed and spam enquiries are finished. */
    public function isOpen(): bool
    {
        return in_array($this, [self::New, self::Contacted, self::Quoted], true);
    }
}
