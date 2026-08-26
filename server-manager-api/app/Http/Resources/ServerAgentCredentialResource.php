<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsAssetFields;
use App\Models\ServerAgentCredential;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An agent credential's metadata.
 *
 * `token_hash` is NEVER serialised — not here, and not via the model, which also
 * marks it `$hidden`. `token_id` is safe to expose (it is a public lookup key,
 * useless without the secret) and `token_hint` is only the last four characters,
 * enough to tell two credentials apart and no help to an attacker.
 *
 * The plaintext token is returned exactly once, by the issue/rotate endpoints,
 * outside this resource.
 *
 * @mixin ServerAgentCredential
 */
class ServerAgentCredentialResource extends JsonResource
{
    use FormatsAssetFields;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        unset($request);

        /** @var ServerAgentCredential $credential */
        $credential = $this->resource;

        return [
            'id' => (int) $credential->getKey(),
            'server_id' => (int) $credential->server_id,
            'name' => $credential->name,
            'token_id' => $credential->token_id,
            'token_hint' => $credential->token_hint,

            'last_used_at' => $this->timestamp($credential->last_used_at),
            'last_used_ip' => $credential->last_used_ip,

            'expires_at' => $this->timestamp($credential->expires_at),
            'revoked_at' => $this->timestamp($credential->revoked_at),
            'revoked_reason' => $credential->revoked_reason,

            'is_usable' => $credential->isUsable(),
            'is_revoked' => $credential->isRevoked(),
            'is_expired' => $credential->isExpired(),

            'rotated_from_id' => $credential->rotated_from_id,

            'created_at' => $this->timestamp($credential->created_at),
        ];
    }
}
