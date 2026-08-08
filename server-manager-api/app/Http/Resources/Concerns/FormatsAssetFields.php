<?php

declare(strict_types=1);

namespace App\Http\Resources\Concerns;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Serialisation helpers shared by the asset resources.
 *
 * The UI renders status badges straight from the API, so an enum is never sent
 * as a bare string: it travels as `{value, label, variant}`, which keeps the
 * label wording and badge colour in one place (the enum) instead of duplicated
 * in React.
 */
trait FormatsAssetFields
{
    /**
     * @param  mixed  $enum  A backed enum using the HasOptions concern.
     * @return array{value: string, label: string, variant: string}|null
     */
    protected function enumPayload(mixed $enum): ?array
    {
        if (! is_object($enum) || ! method_exists($enum, 'label') || ! method_exists($enum, 'variant')) {
            return null;
        }

        /** @var \App\Enums\HostingStatus $enum Any HasOptions enum; narrowed for the analyser only. */
        return [
            'value' => $enum->value,
            'label' => $enum->label(),
            'variant' => $enum->variant(),
        ];
    }

    /**
     * A minimal reference to a related record: enough for the UI to render a
     * label and link, without shipping the whole row.
     *
     * @param  array<int, string>  $extra  Additional columns to include.
     * @return array<string, mixed>|null
     */
    protected function reference(?Model $related, string $labelColumn = 'name', array $extra = []): ?array
    {
        if ($related === null) {
            return null;
        }

        $payload = [
            'id' => (int) $related->getKey(),
            $labelColumn => $related->getAttribute($labelColumn),
        ];

        foreach ($extra as $column) {
            $payload[$column] = $related->getAttribute($column);
        }

        return $payload;
    }

    /**
     * A date column as `Y-m-d` — a calendar date has no time zone, so sending
     * a timestamp would let the client shift it by a day.
     */
    protected function dateOnly(mixed $value): ?string
    {
        return $value instanceof CarbonInterface ? $value->toDateString() : null;
    }

    protected function timestamp(mixed $value): ?string
    {
        return $value instanceof CarbonInterface ? $value->toIso8601String() : null;
    }

    /**
     * True when a secret is on file. The value itself is only ever released by
     * the dedicated credential endpoint.
     */
    protected function hasSecret(Model $model, string $attribute): bool
    {
        $raw = $model->getRawOriginal($attribute);

        return is_string($raw) && $raw !== '';
    }
}
