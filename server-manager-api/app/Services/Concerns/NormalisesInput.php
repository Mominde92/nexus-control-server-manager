<?php

declare(strict_types=1);

namespace App\Services\Concerns;

/**
 * Input hygiene shared by every module service.
 *
 * HTML forms submit empty strings, not nulls, and users paste values with stray
 * whitespace or a bare domain where a URL is expected. Normalising here — in the
 * service, not the Resource — means the database only ever holds canonical
 * values, so equality checks and audit diffs stay meaningful.
 */
trait NormalisesInput
{
    /**
     * Trim every string and collapse empty strings to null.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function trimAndNullify(array $data): array
    {
        foreach ($data as $key => $value) {
            if (! is_string($value)) {
                continue;
            }

            $trimmed = trim($value);
            $data[$key] = $trimmed === '' ? null : $trimmed;
        }

        return $data;
    }

    /**
     * Lowercase the given keys — emails are case-insensitive, and storing them
     * mixed-case defeats the unique index.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    protected function lowercaseKeys(array $data, array $keys): array
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data) && is_string($data[$key])) {
                $data[$key] = mb_strtolower($data[$key]);
            }
        }

        return $data;
    }

    /**
     * Prefix a bare domain with `https://` so the stored value is a usable link.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    protected function normaliseUrls(array $data, array $keys): array
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $data) || ! is_string($data[$key]) || $data[$key] === '') {
                continue;
            }

            $data[$key] = $this->normaliseUrl($data[$key]);
        }

        return $data;
    }

    protected function normaliseUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '' || preg_match('~^[a-z][a-z0-9+.-]*://~i', $url) === 1) {
            return $url;
        }

        // Protocol-relative (`//example.com`) is still a scheme-less URL.
        return 'https://'.ltrim($url, '/');
    }
}
