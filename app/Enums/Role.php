<?php

namespace App\Enums;

enum Role: string
{
    case Owner = 'OWNER';
    case Admin = 'ADMIN';
    case Manager = 'MANAGER';
    case Dispatcher = 'DISPATCHER';
    case Technician = 'TECHNICIAN';
    case Customer = 'CUSTOMER';
    case Accountant = 'ACCOUNTANT';

    public function canManageUsers(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }

    public function canManageCoreRecords(): bool
    {
        return in_array($this, [self::Owner, self::Admin, self::Manager], true);
    }

    public function canAccessAdmin(): bool
    {
        return in_array($this, [
            self::Owner,
            self::Admin,
            self::Manager,
            self::Dispatcher,
            self::Accountant,
        ], true);
    }

    public function canManageJobs(): bool
    {
        return in_array($this, [self::Owner, self::Admin, self::Manager, self::Dispatcher], true);
    }

    public function canManageInventory(): bool
    {
        return in_array($this, [self::Owner, self::Admin, self::Manager, self::Accountant], true);
    }

    public function canManageBilling(): bool
    {
        return in_array($this, [self::Owner, self::Admin, self::Manager, self::Accountant], true);
    }

    public function canManageWorkforce(): bool
    {
        return in_array($this, [self::Owner, self::Admin, self::Manager], true);
    }

    public function canManageContent(): bool
    {
        return in_array($this, [self::Owner, self::Admin, self::Manager], true);
    }

    public function canViewAnalytics(): bool
    {
        return in_array($this, [self::Owner, self::Admin, self::Manager, self::Accountant], true);
    }
}
