<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

/**
 * A bearer credential issued to one server's monitoring agent.
 *
 * Token format: `sma_<tokenId>_<secret>`
 *
 * `tokenId` is a public, indexed lookup key; only the secret half is hashed.
 * That combination means ingest performs ONE indexed lookup and ONE constant-
 * time hash comparison — it never has to scan and hash against every credential
 * in the table, so authentication cost stays flat as servers are added.
 *
 * The secret is stored as a SHA-256 hash and returned to the caller exactly once
 * at issue time. It is deliberately NOT encrypted like the vendor passwords
 * elsewhere in this app: nobody ever needs to read an agent token back, so a
 * one-way hash is the stronger choice.
 */
class ServerAgentCredential extends Model
{
    public const TOKEN_PREFIX = 'sma';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'server_id',
        'name',
        'token_id',
        'token_hash',
        'token_hint',
        'expires_at',
        'rotated_from_id',
        'created_by',
    ];

    /**
     * The hash must never reach an API response, a log or an audit diff.
     *
     * @var list<string>
     */
    protected $hidden = ['token_hash'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Server, $this>
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function rotatedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'rotated_from_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // -----------------------------------------------------------------------
    // Token lifecycle
    // -----------------------------------------------------------------------

    /**
     * Hash a secret. SHA-256 (not bcrypt) is correct here: the secret is 40
     * chars of cryptographic randomness, not a human password, so it needs no
     * brute-force stretching — and ingest runs on every heartbeat, where bcrypt
     * would add tens of milliseconds per request.
     */
    public static function hashSecret(string $secret): string
    {
        return hash('sha256', $secret);
    }

    /**
     * Build an unsaved credential plus its one-time plaintext token.
     *
     * @return array{credential: self, token: string}
     */
    public static function issue(Server $server, ?string $name = null, ?int $createdBy = null): array
    {
        $tokenId = Str::lower(Str::random(16));
        $secret = Str::random(40);

        $ttlDays = Config::get('monitoring.agent.token_ttl_days');

        $credential = new self([
            'server_id' => $server->getKey(),
            'name' => $name,
            'token_id' => $tokenId,
            'token_hash' => self::hashSecret($secret),
            // Last 4 chars only — enough to identify, useless to an attacker.
            'token_hint' => Str::substr($secret, -4),
            'expires_at' => $ttlDays === null ? null : now()->addDays((int) $ttlDays),
            'created_by' => $createdBy,
        ]);

        return [
            'credential' => $credential,
            'token' => sprintf('%s_%s_%s', self::TOKEN_PREFIX, $tokenId, $secret),
        ];
    }

    /**
     * Split a presented token into its lookup id and secret.
     *
     * @return array{token_id: string, secret: string}|null
     */
    public static function parse(string $token): ?array
    {
        $parts = explode('_', trim($token));

        if (count($parts) !== 3 || $parts[0] !== self::TOKEN_PREFIX) {
            return null;
        }

        [, $tokenId, $secret] = $parts;

        if ($tokenId === '' || $secret === '') {
            return null;
        }

        return ['token_id' => $tokenId, 'secret' => $secret];
    }

    /**
     * Constant-time comparison, so a timing side channel cannot be used to
     * recover a valid token byte by byte.
     */
    public function matches(string $secret): bool
    {
        return hash_equals($this->token_hash, self::hashSecret($secret));
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExpired(): bool
    {
        $expires = $this->expires_at;

        return $expires instanceof CarbonInterface && $expires->isPast();
    }

    public function isUsable(): bool
    {
        return ! $this->isRevoked() && ! $this->isExpired();
    }

    public function revoke(?string $reason = null, ?int $userId = null): bool
    {
        if ($this->isRevoked()) {
            return false;
        }

        $this->revoked_at = now();
        $this->revoked_reason = $reason;
        $this->revoked_by = $userId;

        return $this->save();
    }

    /**
     * Record usage, but throttled: writing on every heartbeat would mean an
     * extra UPDATE per server per interval for information that only needs
     * to be approximately current.
     */
    public function touchUsage(?string $ip): void
    {
        $throttle = (int) Config::get('monitoring.agent.touch_last_used_seconds', 60);
        $last = $this->last_used_at;

        if ($last instanceof CarbonInterface && $last->diffInSeconds(now()) < $throttle) {
            return;
        }

        $this->forceFill([
            'last_used_at' => now(),
            'last_used_ip' => $ip,
        ])->saveQuietly();
    }

    // -----------------------------------------------------------------------
    // Scopes
    // -----------------------------------------------------------------------

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->whereNull($query->qualifyColumn('revoked_at'))
            ->where(function (Builder $inner) use ($query): void {
                $expires = $query->qualifyColumn('expires_at');
                $inner->whereNull($expires)->orWhere($expires, '>', now());
            });
    }
}
