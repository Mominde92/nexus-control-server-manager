<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Every granular ability in the system. Mirrored into the `permissions` table
 * by RoleSeeder and enforced by Policies + the `can:` route middleware.
 */
enum Permission: string
{
    // Dashboard
    case ViewDashboard = 'dashboard.view';

    // Hosting
    case ViewHosting = 'hosting.view';
    case CreateHosting = 'hosting.create';
    case UpdateHosting = 'hosting.update';
    case DeleteHosting = 'hosting.delete';
    case ExportHosting = 'hosting.export';

    // Domain
    case ViewDomain = 'domain.view';
    case CreateDomain = 'domain.create';
    case UpdateDomain = 'domain.update';
    case DeleteDomain = 'domain.delete';
    case ExportDomain = 'domain.export';

    // Provider
    case ViewProvider = 'provider.view';
    case CreateProvider = 'provider.create';
    case UpdateProvider = 'provider.update';
    case DeleteProvider = 'provider.delete';
    case ExportProvider = 'provider.export';

    // Client
    case ViewClient = 'client.view';
    case CreateClient = 'client.create';
    case UpdateClient = 'client.update';
    case DeleteClient = 'client.delete';
    case ExportClient = 'client.export';

    // Project
    case ViewProject = 'project.view';
    case CreateProject = 'project.create';
    case UpdateProject = 'project.update';
    case DeleteProject = 'project.delete';
    case ExportProject = 'project.export';

    // Users & access control
    case ViewUser = 'user.view';
    case CreateUser = 'user.create';
    case UpdateUser = 'user.update';
    case DeleteUser = 'user.delete';
    case ExportUser = 'user.export';
    case ManageRole = 'role.manage';

    // Cross-cutting
    case ViewActivityLog = 'activity_log.view';
    case ExportActivityLog = 'activity_log.export';

    /**
     * Reveal a decrypted credential (hosting/domain/FTP/registrar password).
     * Deliberately separate from `*.view` so read access never implies
     * secret access.
     */
    case ViewCredential = 'credential.view';

    case ManageSetting = 'setting.manage';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Human readable grouping, used by the Users screen to render the
     * permission matrix.
     */
    public function group(): string
    {
        return str_contains($this->value, '.')
            ? str_replace('_', ' ', ucfirst(strtok($this->value, '.') ?: 'general'))
            : 'General';
    }
}
