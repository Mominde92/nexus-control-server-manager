<?php

declare(strict_types=1);

namespace App\Services\Concerns;

use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;

/**
 * Input normalisation shared by the Hosting and Domain services.
 *
 * Both modules describe a purchased asset bought from a vendor: an optional
 * catalogued provider (or an ad-hoc name/url), a price in a currency, an expiry
 * date with a reminder window, and encrypted vendor credentials. The rules
 * below are therefore identical; only the status enum differs, which each
 * service supplies through `activeStatusValue()` / `expiredStatusValue()`.
 */
trait NormalisesAssetInput
{
    /**
     * Reminder windows outside this range are meaningless: 0 would notify never
     * (or always, depending on reading), and beyond a year the record has
     * usually been renewed already.
     */
    private const int MIN_NOTIFICATION_DAYS = 1;

    private const int MAX_NOTIFICATION_DAYS = 365;

    private const string DEFAULT_CURRENCY = 'USD';

    /**
     * The status value meaning "live", i.e. the one auto-derivation may replace.
     */
    abstract protected function activeStatusValue(): string;

    abstract protected function expiredStatusValue(): string;

    /**
     * Encrypted attributes that must survive an update which does not resend
     * them.
     *
     * @return array<int, string>
     */
    abstract protected function secretKeys(): array;

    /**
     * Rules applied to both create and update payloads.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalise(array $data): array
    {
        $data = $this->blanksToNull($data);
        $data = $this->resolveProvider($data);
        $data = $this->clampNotificationWindow($data);
        $data = $this->normaliseCurrency($data);

        return $this->deriveExpiredStatus($data);
    }

    /**
     * An HTML form submits a cleared input as `""`, which would otherwise be
     * stored as an empty string and break `whereNull` reads and `?->` chains.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function blanksToNull(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value) && trim($value) === '') {
                $data[$key] = null;

                continue;
            }

            if (is_string($value)) {
                $data[$key] = trim($value);
            }
        }

        return $data;
    }

    /**
     * The catalogued provider wins: when one is linked, the ad-hoc vendor
     * fields are cleared so the record cannot disagree with the catalogue.
     * When no provider is linked, the ad-hoc values are the only vendor
     * information there is, so they are preserved.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function resolveProvider(array $data): array
    {
        if (! array_key_exists('provider_id', $data) || $data['provider_id'] === null) {
            return $data;
        }

        $data['provider_id'] = (int) $data['provider_id'];
        $data['provider_name'] = null;
        $data['provider_url'] = null;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function clampNotificationWindow(array $data): array
    {
        if (! array_key_exists('notification_days_before', $data) || $data['notification_days_before'] === null) {
            return $data;
        }

        $days = (int) $data['notification_days_before'];

        $data['notification_days_before'] = max(
            self::MIN_NOTIFICATION_DAYS,
            min($days, self::MAX_NOTIFICATION_DAYS),
        );

        return $data;
    }

    /**
     * ISO-4217 codes are upper case; the column is a CHAR(3).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normaliseCurrency(array $data): array
    {
        if (! array_key_exists('currency', $data)) {
            return $data;
        }

        $currency = is_string($data['currency']) ? strtoupper(trim($data['currency'])) : '';

        $data['currency'] = $currency === '' ? self::DEFAULT_CURRENCY : $currency;

        return $data;
    }

    /**
     * A record whose expiry date has already passed is not "active", whatever
     * the form said. Only `active` is overridden — a deliberate `suspended` or
     * `pending` classification is left alone.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function deriveExpiredStatus(array $data): array
    {
        $expiry = $data['expiry_date'] ?? null;

        if (! is_string($expiry) || $expiry === '') {
            return $data;
        }

        try {
            $expiresAt = CarbonImmutable::parse($expiry)->startOfDay();
        } catch (InvalidFormatException) {
            // Validation already rejected malformed dates; be defensive anyway.
            return $data;
        }

        if (! $expiresAt->isBefore(CarbonImmutable::now()->startOfDay())) {
            return $data;
        }

        $status = $data['status'] ?? $this->activeStatusValue();

        if ($status === $this->activeStatusValue()) {
            $data['status'] = $this->expiredStatusValue();
        }

        return $data;
    }

    /**
     * Drop secret keys the client did not actually supply.
     *
     * Editing a hosting without retyping its password must not wipe the stored
     * ciphertext — and because EncryptedString::set() normalises `''` to null,
     * an empty string would do exactly that. Removing the key entirely leaves
     * the existing value untouched.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function preserveExistingSecrets(array $data): array
    {
        foreach ($this->secretKeys() as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            $value = $data[$key];

            if ($value === null || (is_string($value) && trim($value) === '')) {
                unset($data[$key]);
            }
        }

        return $data;
    }

    /**
     * Default the currency on insert only, so a partial update never silently
     * rewrites a record's currency.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function applyCreateDefaults(array $data): array
    {
        $data['currency'] ??= self::DEFAULT_CURRENCY;

        return $data;
    }
}
