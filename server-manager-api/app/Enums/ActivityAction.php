<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Auditable actions recorded in `activity_logs`.
 */
enum ActivityAction: string
{
    use HasOptions;

    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case Restored = 'restored';
    case ForceDeleted = 'force_deleted';

    case Login = 'login';
    case Logout = 'logout';
    case LoginFailed = 'login_failed';

    case PasswordResetRequested = 'password_reset_requested';
    case PasswordReset = 'password_reset';
    case PasswordChanged = 'password_changed';

    case Exported = 'exported';
    case CredentialViewed = 'credential_viewed';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'created',
            self::Updated => 'updated',
            self::Deleted => 'deleted',
            self::Restored => 'restored',
            self::ForceDeleted => 'permanently deleted',
            self::Login => 'logged in',
            self::Logout => 'logged out',
            self::LoginFailed => 'failed to log in',
            self::PasswordResetRequested => 'requested a password reset',
            self::PasswordReset => 'reset their password',
            self::PasswordChanged => 'changed their password',
            self::Exported => 'exported',
            self::CredentialViewed => 'revealed a credential for',
        };
    }

    public function variant(): string
    {
        return match ($this) {
            self::Created, self::Restored => 'success',
            self::Updated => 'info',
            self::Deleted, self::ForceDeleted, self::LoginFailed => 'destructive',
            self::CredentialViewed, self::PasswordResetRequested => 'warning',
            self::Login, self::Logout, self::Exported,
            self::PasswordReset, self::PasswordChanged => 'secondary',
        };
    }

    /**
     * Actions that describe a change to a specific record (as opposed to an
     * account-level event such as a login).
     */
    public function isRecordEvent(): bool
    {
        return in_array($this, [
            self::Created,
            self::Updated,
            self::Deleted,
            self::Restored,
            self::ForceDeleted,
            self::CredentialViewed,
        ], true);
    }
}
