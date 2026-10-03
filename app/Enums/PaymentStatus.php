<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Paid = 'paid';
    case PendingApproval = 'pending_approval';
    case Rejected = 'rejected';
    case Voided = 'voided';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->ucfirst()->toString();
    }
}
