<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Application roles. Mirrored into the `roles` table by RoleSeeder.
 *
 * Hierarchy (descending privilege): Admin > Manager > Employee.
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Employee = 'employee';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Manager => 'Manager',
            self::Employee => 'Employee',
        };
    }

    /**
     * Lower number == higher privilege. Used to prevent privilege escalation
     * (a Manager may never create or edit an Admin).
     */
    public function level(): int
    {
        return match ($this) {
            self::Admin => 0,
            self::Manager => 1,
            self::Employee => 2,
        };
    }

    public function outranks(self $other): bool
    {
        return $this->level() < $other->level();
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * The permission set granted to this role. Consumed by RoleSeeder.
     *
     * @return array<int, string>
     */
    public function permissions(): array
    {
        return match ($this) {
            // Admin receives every permission defined in the system.
            self::Admin => Permission::values(),

            // Manager runs day-to-day operations: full CRUD on infrastructure
            // records, but cannot manage users, roles or application settings.
            self::Manager => [
                Permission::ViewHosting->value,
                Permission::CreateHosting->value,
                Permission::UpdateHosting->value,
                Permission::DeleteHosting->value,
                Permission::ExportHosting->value,

                Permission::ViewDomain->value,
                Permission::CreateDomain->value,
                Permission::UpdateDomain->value,
                Permission::DeleteDomain->value,
                Permission::ExportDomain->value,

                Permission::ViewProvider->value,
                Permission::CreateProvider->value,
                Permission::UpdateProvider->value,
                Permission::DeleteProvider->value,
                Permission::ExportProvider->value,

                Permission::ViewClient->value,
                Permission::CreateClient->value,
                Permission::UpdateClient->value,
                Permission::DeleteClient->value,
                Permission::ExportClient->value,

                Permission::ViewProject->value,
                Permission::CreateProject->value,
                Permission::UpdateProject->value,
                Permission::DeleteProject->value,
                Permission::ExportProject->value,

                Permission::ViewUser->value,
                Permission::ViewActivityLog->value,
                Permission::ViewCredential->value,
                Permission::ViewDashboard->value,
            ],

            // Employee may read and create infrastructure records only.
            // No update, delete, export, user administration or credential reveal.
            self::Employee => [
                Permission::ViewHosting->value,
                Permission::CreateHosting->value,

                Permission::ViewDomain->value,
                Permission::CreateDomain->value,

                Permission::ViewProvider->value,
                Permission::CreateProvider->value,
                Permission::ViewClient->value,
                Permission::CreateClient->value,
                Permission::ViewProject->value,
                Permission::CreateProject->value,
                Permission::ViewDashboard->value,
            ],
        };
    }
}
