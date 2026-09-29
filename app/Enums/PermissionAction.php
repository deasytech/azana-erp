<?php

namespace App\Enums;

enum PermissionAction: string
{
    case View = 'view';
    case Create = 'create';
    case Edit = 'edit';
    case Approve = 'approve';
    case Delete = 'delete';
    case Export = 'export';
    case Print = 'print';

    public function label(): string
    {
        return $this === self::Delete ? 'Delete / cancel' : ucfirst($this->value);
    }
}
