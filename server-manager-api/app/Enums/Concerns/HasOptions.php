<?php

declare(strict_types=1);

namespace App\Enums\Concerns;

/**
 * Shared behaviour for every "labelled" backed enum in the application.
 *
 * Implementers provide `label()` and `variant()`; this trait derives the
 * validation list and the option payload the React UI renders in selects and
 * status badges — so the frontend never hardcodes a status string.
 */
trait HasOptions
{
    abstract public function label(): string;

    /**
     * Badge variant name understood by the UI (`success`, `warning`,
     * `destructive`, `info`, `secondary`, `default`).
     */
    abstract public function variant(): string;

    /**
     * All backing values — use directly in validation rules via Rule::in().
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return array<int, array{value: string, label: string, variant: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
                'variant' => $case->variant(),
            ],
            self::cases(),
        );
    }

    /**
     * Resolve a case from a nullable raw value without throwing.
     */
    public static function tryFromValue(?string $value): ?static
    {
        return $value === null ? null : self::tryFrom($value);
    }
}
