<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Contracts\ActivityLoggerInterface;
use App\Enums\ActivityAction;

/**
 * Records create / update / delete / restore events for a model into the audit
 * trail, including a field-level diff with secrets redacted.
 *
 * Implementers may define:
 *   protected array $activityIgnored = ['updated_at'];  // extra skipped fields
 *   public function activityLabel(): string             // human name for the row
 */
trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::created(function ($model): void {
            $model->recordActivity(ActivityAction::Created, [
                'attributes' => $model->activityAttributes($model->getAttributes()),
            ]);
        });

        static::updated(function ($model): void {
            $changes = $model->activityDiff();

            // A no-op save (or one that only touched timestamps) is not news.
            if ($changes === []) {
                return;
            }

            $model->recordActivity(ActivityAction::Updated, ['changes' => $changes]);
        });

        static::deleted(function ($model): void {
            $action = method_exists($model, 'isForceDeleting') && $model->isForceDeleting()
                ? ActivityAction::ForceDeleted
                : ActivityAction::Deleted;

            $model->recordActivity($action);
        });

        // Only fires on models using SoftDeletes.
        if (method_exists(static::class, 'restored')) {
            static::restored(function ($model): void {
                $model->recordActivity(ActivityAction::Restored);
            });
        }
    }

    /**
     * Human-readable identifier for this record, shown in the activity feed.
     * Override when the display column is not `name` or `title`.
     */
    public function activityLabel(): ?string
    {
        foreach (['title', 'name', 'domain_name', 'email'] as $candidate) {
            $value = $this->getAttribute($candidate);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    protected function activityIgnoredAttributes(): array
    {
        /** @var array<int, string> $extra */
        $extra = property_exists($this, 'activityIgnored') ? $this->activityIgnored : [];

        return array_merge(['created_at', 'updated_at', 'updated_by', 'remember_token'], $extra);
    }

    /**
     * Changed fields as `field => [old, new]`, with secrets redacted and noise
     * columns removed.
     *
     * @return array<string, array{old: mixed, new: mixed}>
     */
    protected function activityDiff(): array
    {
        $ignored = $this->activityIgnoredAttributes();
        $original = $this->getRawOriginal();
        $diff = [];

        foreach ($this->getChanges() as $field => $newValue) {
            if (in_array($field, $ignored, true)) {
                continue;
            }

            $isSecret = method_exists($this, 'credentialAttributes')
                && in_array($field, $this->credentialAttributes(), true);

            // Never record secret values — only that they changed.
            $diff[$field] = $isSecret
                ? ['old' => '••••••••', 'new' => '••••••••']
                : ['old' => $original[$field] ?? null, 'new' => $newValue];
        }

        return $diff;
    }

    /**
     * Sanitise a full attribute array for storage in the log.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function activityAttributes(array $attributes): array
    {
        foreach ($this->activityIgnoredAttributes() as $field) {
            unset($attributes[$field]);
        }

        if (method_exists($this, 'redactCredentials')) {
            $attributes = $this->redactCredentials($attributes);
        }

        // Never log password hashes either.
        unset($attributes['password_hash']);

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    protected function recordActivity(ActivityAction $action, array $properties = []): void
    {
        app(ActivityLoggerInterface::class)->log($action, $this, null, $properties);
    }
}
