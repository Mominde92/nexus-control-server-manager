<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ActivityLoggerInterface;
use App\Enums\ActivityAction;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Throwable;

/**
 * Default audit trail writer.
 *
 * Writes synchronously (never queued) so an audit record cannot be lost if the
 * queue is down — but a logging failure is swallowed and reported rather than
 * bubbling up, because auditing must never break the operation it describes.
 */
final class ActivityLogger implements ActivityLoggerInterface
{
    private bool $enabled = true;

    /**
     * @param  array<string, mixed>  $properties
     */
    public function log(
        ActivityAction $action,
        ?Model $subject = null,
        ?string $description = null,
        array $properties = [],
    ): ActivityLog {
        /** @var User|null $user */
        $user = Auth::user();

        return $this->logAs(
            $action,
            $user?->getKey() !== null ? (int) $user->getKey() : null,
            $user?->name,
            $subject,
            $description,
            $properties,
        );
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    public function logAs(
        ActivityAction $action,
        ?int $userId,
        ?string $userName,
        ?Model $subject = null,
        ?string $description = null,
        array $properties = [],
    ): ActivityLog {
        $log = new ActivityLog([
            'user_id' => $userId,
            'user_name' => $userName,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'subject_label' => $this->resolveLabel($subject),
            'description' => $description,
            'properties' => $properties === [] ? null : $properties,
            'ip_address' => $this->clientIp(),
            'user_agent' => $this->userAgent(),
        ]);

        if (! $this->enabled) {
            // Return an unsaved instance so callers keep a uniform return type.
            return $log;
        }

        try {
            $log->save();
        } catch (Throwable $e) {
            report($e);
        }

        return $log;
    }

    public function withoutLogging(callable $callback): mixed
    {
        $previous = $this->enabled;
        $this->enabled = false;

        try {
            return $callback();
        } finally {
            $this->enabled = $previous;
        }
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    private function resolveLabel(?Model $subject): ?string
    {
        if ($subject === null) {
            return null;
        }

        if (method_exists($subject, 'activityLabel')) {
            /** @var string|null $label */
            $label = $subject->activityLabel();

            return $label;
        }

        foreach (['title', 'name', 'email'] as $candidate) {
            $value = $subject->getAttribute($candidate);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * Console commands and queued jobs have no request context.
     */
    private function clientIp(): ?string
    {
        return app()->runningInConsole() ? null : Request::ip();
    }

    private function userAgent(): ?string
    {
        if (app()->runningInConsole()) {
            return 'console';
        }

        $agent = Request::userAgent();

        // Column is TEXT, but keep pathological headers bounded.
        return $agent === null ? null : mb_substr($agent, 0, 1000);
    }
}
