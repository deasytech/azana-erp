<?php

namespace App\Enums;

/**
 * Permission-bearing modules. Permission names are "{module}.{action}".
 * Later phases add their modules here and re-run the PermissionSeeder.
 */
enum Module: string
{
    case Users = 'users';
    case Roles = 'roles';
    case AuditLogs = 'audit-logs';
    case LoginActivity = 'login-activity';

    public function label(): string
    {
        return match ($this) {
            self::Users => 'Users',
            self::Roles => 'Roles & permissions',
            self::AuditLogs => 'Audit log',
            self::LoginActivity => 'Login activity',
        };
    }

    /** @return list<PermissionAction> */
    public function actions(): array
    {
        return match ($this) {
            // Read-only trails: never editable or deletable, by anyone.
            self::AuditLogs, self::LoginActivity => [PermissionAction::View, PermissionAction::Export],
            default => PermissionAction::cases(),
        };
    }

    public function permission(PermissionAction $action): string
    {
        return $this->value.'.'.$action->value;
    }
}
