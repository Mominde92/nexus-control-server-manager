<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\EncryptedString;
use App\Enums\DomainStatus;
use App\Enums\DomainType;
use App\Enums\RegistrarStatus;
use App\Models\Concerns\HasCredentials;
use App\Models\Concerns\HasRecordAccess;
use App\Models\Concerns\HasExpiry;
use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\Searchable;
use App\Models\Concerns\TracksBlame;
use Database\Factories\DomainFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Domain extends Model
{
    /** @use HasFactory<DomainFactory> */
    use HasFactory;

    use HasCredentials;
    use HasRecordAccess;
    use HasExpiry;
    use LogsActivity;
    use Searchable;
    use SoftDeletes;
    use TracksBlame;

    public const SEARCHABLE = [
        'name',
        'provider_name',
        'plan',
        'username',
        'registrar_username',
    ];

    public const SEARCHABLE_RELATIONS = [
        'provider' => ['name'],
        'client' => ['name', 'company'],
        'project' => ['name'],
        'hosting' => ['title'],
    ];

    public const SORTABLE = [
        'name',
        'status',
        'registrar_status',
        'domain_type',
        'purchase_date',
        'expiry_date',
        'price',
        'created_at',
        'updated_at',
        'id',
    ];

    /**
     * @var list<string>
     */
    protected array $credentials = ['password', 'registrar_password'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'provider_id',
        'provider_name',
        'provider_url',
        'domain_type',
        'hosting_id',
        'username',
        'password',
        'purchase_date',
        'expiry_date',
        'price',
        'currency',
        'plan',
        'registrar_url',
        'registrar_username',
        'registrar_password',
        'registrar_status',
        'project_id',
        'client_id',
        'status',
        'auto_renewal',
        'whois_protection',
        'expiry_notification',
        'notification_days_before',
        'description',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = ['password', 'registrar_password'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DomainStatus::class,
            'domain_type' => DomainType::class,
            'registrar_status' => RegistrarStatus::class,
            'password' => EncryptedString::class,
            'registrar_password' => EncryptedString::class,
            'purchase_date' => 'date',
            'expiry_date' => 'date',
            'expiry_notified_at' => 'datetime',
            'price' => 'decimal:2',
            'auto_renewal' => 'boolean',
            'whois_protection' => 'boolean',
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
     * @return BelongsTo<Hosting, $this>
     */
    public function hosting(): BelongsTo
    {
        return $this->belongsTo(Hosting::class);
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

    // -----------------------------------------------------------------------
    // Derived attributes
    // -----------------------------------------------------------------------

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
    // Expiry scopes come from the HasExpiry concern.

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', DomainStatus::Active);
    }
}
