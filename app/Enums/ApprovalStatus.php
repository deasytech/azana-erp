<?php

namespace App\Enums;

/** Decision state of a record that needs someone else's approval. */
enum ApprovalStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
