<?php

namespace App\Enums;

enum SemenBatchStatus: string
{
    case PendingQc = 'pending_qc';
    case Passed = 'passed';
    case Failed = 'failed';
    case Released = 'released';
    case Quarantined = 'quarantined';
    case Expired = 'expired';
    case Destroyed = 'destroyed';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->ucfirst()->toString();
    }

    /** Statuses a batch can still leave (anything but expired and destroyed). */
    public function isOpen(): bool
    {
        return $this !== self::Expired && $this !== self::Destroyed;
    }
}
