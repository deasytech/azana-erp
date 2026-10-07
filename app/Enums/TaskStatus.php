<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Done = 'done';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->ucfirst()->toString();
    }
}
