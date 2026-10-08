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
    case FarmStructure = 'farm-structure';
    case MasterData = 'master-data';
    case Settings = 'settings';
    case PriceLists = 'price-lists';
    case Animals = 'animals';
    case Breeding = 'breeding';
    case Health = 'health';
    case Biosecurity = 'biosecurity';
    case Production = 'production';
    case Inventory = 'inventory';
    case Procurement = 'procurement';
    case FeedMill = 'feed-mill';
    case Semen = 'semen';
    case Sales = 'sales';
    case Slaughter = 'slaughter';
    case Finance = 'finance';
    case Tasks = 'tasks';
    case Reports = 'reports';
    case Mobile = 'mobile';
    case Website = 'website';

    public function label(): string
    {
        return match ($this) {
            self::Users => 'Users',
            self::Roles => 'Roles & permissions',
            self::AuditLogs => 'Audit log',
            self::LoginActivity => 'Login activity',
            self::FarmStructure => 'Farm structure',
            self::MasterData => 'Master data',
            self::Settings => 'Farm settings',
            self::PriceLists => 'Price lists',
            self::Animals => 'Animals',
            self::Breeding => 'Breeding & litters',
            self::Health => 'Animal health',
            self::Biosecurity => 'Biosecurity & visitors',
            self::Production => 'Growers & finishers',
            self::Inventory => 'Inventory & stores',
            self::Procurement => 'Purchasing & suppliers',
            self::FeedMill => 'Feed mill',
            self::Semen => 'Semen & laboratory',
            self::Sales => 'Customers & sales',
            self::Slaughter => 'Slaughter & meat',
            self::Finance => 'Finance & costing',
            self::Tasks => 'Tasks & alerts',
            self::Reports => 'Dashboards & reports',
            self::Mobile => 'Mobile app',
            self::Website => 'Public website',
        };
    }

    /** @return list<PermissionAction> */
    public function actions(): array
    {
        return match ($this) {
            // Read-only trails: never editable or deletable, by anyone.
            self::AuditLogs, self::LoginActivity => [PermissionAction::View, PermissionAction::Export],
            self::Settings => [PermissionAction::View, PermissionAction::Edit],
            // View: sign in to the mobile app (what a person may then do is decided by their other permissions). Edit: review the sync log's conflicts.
            self::Mobile => [PermissionAction::View, PermissionAction::Edit],
            default => PermissionAction::cases(),
        };
    }

    public function permission(PermissionAction $action): string
    {
        return $this->value.'.'.$action->value;
    }
}
