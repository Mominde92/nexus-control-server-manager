<?php

declare(strict_types=1);

namespace App\Http\Resources\Concerns;

/**
 * Serialises a labelled enum into the object shape the React UI consumes:
 *
 *   { "value": "on_hold", "label": "On Hold", "variant": "warning" }
 *
 * Sending the label and badge variant from the server means the frontend never
 * hardcodes a status string or its colour, so adding a status is a
 * backend-only change.
 */
trait FormatsEnums
{
    /**
     * @return array{value: string, label: string, variant: string}|null
     */
    protected function enumPayload(mixed $enum): ?array
    {
        if (! $enum instanceof \BackedEnum) {
            return null;
        }

        return [
            'value' => (string) $enum->value,
            'label' => method_exists($enum, 'label') ? $enum->label() : (string) $enum->value,
            'variant' => method_exists($enum, 'variant') ? $enum->variant() : 'secondary',
        ];
    }

    /**
     * Enum payload without a badge variant — used for roles, which render as
     * plain text rather than a status pill.
     *
     * @return array{value: string, label: string}|null
     */
    protected function labelPayload(mixed $enum): ?array
    {
        if (! $enum instanceof \BackedEnum) {
            return null;
        }

        return [
            'value' => (string) $enum->value,
            'label' => method_exists($enum, 'label') ? $enum->label() : (string) $enum->value,
        ];
    }

    /**
     * ISO-8601 for timestamps; null stays null so the client can distinguish
     * "never" from "epoch".
     */
    protected function isoDate(mixed $value): ?string
    {
        return $value instanceof \DateTimeInterface ? $value->format(\DateTimeInterface::ATOM) : null;
    }

    /**
     * Calendar dates carry no time component — sending one invites timezone
     * drift in the browser.
     */
    protected function plainDate(mixed $value): ?string
    {
        return $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : null;
    }
}
