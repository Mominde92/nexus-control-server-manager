<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Concerns\LogsActivity;
use App\Notifications\ResetPasswordNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens;
    use HasFactory;
    use HasRoles;
    use LogsActivity;
    use Notifiable;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'job_title',
        'avatar_path',
        'status',
        'created_by',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Never surface a password hash or login telemetry in an audit diff.
     *
     * @var list<string>
     */
    protected array $activityIgnored = [
        'password',
        'last_login_at',
        'last_login_ip',
        'email_verified_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
        ];
    }

    // -----------------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------------

    /**
     * @return BelongsTo<self, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(self::class, 'created_by');
    }

    /**
     * @return HasMany<ActivityLog, $this>
     */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    // -----------------------------------------------------------------------
    // Role helpers
    // -----------------------------------------------------------------------

    /**
     * The user's single primary role. The app assigns exactly one role per
     * user, so this collapses Spatie's many-to-many into a scalar for the API.
     */
    public function primaryRole(): ?UserRole
    {
        $name = $this->roles->first()?->name;

        return $name === null ? null : UserRole::tryFrom($name);
    }

    public function isAdmin(): bool
    {
        return $this->primaryRole() === UserRole::Admin;
    }

    public function canAuthenticate(): bool
    {
        return $this->status instanceof UserStatus && $this->status->canAuthenticate();
    }

    /**
     * True when this user is allowed to administer $target — used to stop a
     * Manager from editing or deleting an Admin.
     */
    public function outranks(self $target): bool
    {
        $mine = $this->primaryRole();
        $theirs = $target->primaryRole();

        if ($mine === null || $theirs === null) {
            return false;
        }

        return $mine->outranks($theirs) || ($mine === UserRole::Admin && $this->isNot($target));
    }

    // -----------------------------------------------------------------------
    // Notifications
    // -----------------------------------------------------------------------

    /**
     * Send the reset link to the React SPA rather than a Blade route.
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordNotification($token, $this->getEmailForPasswordReset()));
    }
}
