<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\EncryptedString;
use App\Enums\HostingStatus;
use App\Enums\ServerType;
use App\Enums\SslCertificate;
use App\Models\Concerns\HasCredentials;
use App\Models\Concerns\HasRecordAccess;
use App\Models\Concerns\HasExpiry;
use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\Searchable;
use App\Models\Concerns\TracksBlame;
use Database\Factories\HostingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Hosting extends Model
{
    /** @use HasFactory<HostingFactory> */
    use HasFactory;

    use HasCredentials;
    use HasRecordAccess;
    use HasExpiry;
    use LogsActivity;
    use Searchable;
    use SoftDeletes;
    use TracksBlame;

    public const SEARCHABLE = [
        'title',
        'provider_name',
        'ip_address',
        'server_location',
        'plan',
        'username',
    ];

    public const SEARCHABLE_RELATIONS = [
        'provider' => ['name'],
        'client' => ['name', 'company'],
        'project' => ['name'],
    ];

    public const SORTABLE = [
        'title',
        'status',
        'server_type',
        'purchase_date',
        'expiry_date',
        'price',
        'created_at',
        'updated_at',
        'id',
    ];

    /**
     * Encrypted secrets. Declared for HasCredentials so they are redacted from
     * audit diffs and only released through the reveal endpoint.
     *
     * @var list<string>
     */
    protected array $credentials = ['password', 'ftp_password'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'provider_id',
        'provider_name',
        'provider_url',
        'server_type',
        'server_location',
        'ip_address',
        'control_panel_url',
        'username',
        'password',
        'ftp_username',
        'ftp_password',
        'purchase_date',
        'expiry_date',
        'plan',
        'price',
        'currency',
        'project_id',
        'client_id',
        'status',
        'ssl_certificate',
        'expiry_notification',
        'notification_days_before',
        'description',
    ];

    /**
     * Secrets never appear in array/JSON output. The reveal endpoint reads them
     * explicitly via revealCredentials().
     *
     * @var list<string>
     */
    protected $hidden = ['password', 'ftp_password'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => HostingStatus::class,
            'server_type' => ServerType::class,
            'ssl_certificate' => SslCertificate::class,
            'password' => EncryptedString::class,
            'ftp_password' => EncryptedString::class,
            'purchase_date' => 'date',
            'expiry_date' => 'date',
            'expiry_notified_at' => 'datetime',
            'price' => 'decimal:2',
            'expiry_notification' => 'boolean',
            'notification_days_before' => 'integer',
        ];
    }

    // -----------------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------------

    /**
     * @return BelongsTo<Provider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return HasMany<Domain, $this>
     */
    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    // -----------------------------------------------------------------------
    // Derived attributes
    // -----------------------------------------------------------------------

    /**
     * The effective vendor label: the catalogued provider when linked,
     * otherwise the ad-hoc name captured on the record.
     */
    public function providerLabel(): ?string
    {
        return $this->provider?->name ?? $this->provider_name;
    }

    public function providerLink(): ?string
    {
        return $this->provider?->url ?? $this->provider_url;
    }

    // -----------------------------------------------------------------------
    // Scopes
    // -----------------------------------------------------------------------
    // Expiry scopes (expiringWithin, expired, dueForExpiryNotification) come
    // from the HasExpiry concern.

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', HostingStatus::Active);
    }
}
