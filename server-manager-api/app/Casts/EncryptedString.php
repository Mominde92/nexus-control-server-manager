<?php

declare(strict_types=1);

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Two-way encryption cast for stored secrets (hosting/FTP/registrar passwords).
 *
 * Uses Crypt::encryptString() / Crypt::decryptString(), so the column holds an
 * AES-256-CBC payload authenticated with an HMAC derived from APP_KEY. Values
 * are therefore recoverable — unlike a password *hash* — because operators must
 * be able to read a vendor credential back out of the vault.
 *
 * @implements CastsAttributes<string|null, string|null>
 */
final class EncryptedString implements CastsAttributes
{
    /**
     * Decrypt on read.
     *
     * A failed decrypt must never take an endpoint down: if APP_KEY was rotated
     * without re-encrypting, or the column holds legacy plaintext, we log once
     * and surface null rather than throwing a 500 on every list request.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Crypt::decryptString((string) $value);
        } catch (DecryptException) {
            Log::warning('Unable to decrypt attribute; returning null.', [
                'model' => $model::class,
                'model_id' => $model->getKey(),
                'attribute' => $key,
            ]);

            return null;
        }
    }

    /**
     * Encrypt on write. Empty input is normalised to null so we never store a
     * ciphertext that decrypts to an empty string.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, string|null>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null || $value === '') {
            return [$key => null];
        }

        return [$key => Crypt::encryptString((string) $value)];
    }
}
