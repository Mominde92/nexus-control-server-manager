<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ActivityLoggerInterface;
use App\Contracts\Repositories\DomainRepositoryInterface;
use App\Contracts\Services\DomainServiceInterface;
use App\Enums\ActivityAction;
use App\Enums\DomainStatus;
use App\Models\Domain;
use App\Services\Concerns\NormalisesAssetInput;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Domain business rules.
 *
 * @extends BaseCrudService<Domain>
 */
class DomainService extends BaseCrudService implements DomainServiceInterface
{
    use NormalisesAssetInput;

    private const int DEFAULT_EXPIRY_WINDOW = 30;

    public function __construct(
        private readonly DomainRepositoryInterface $domains,
        private readonly ActivityLoggerInterface $activity,
    ) {
        parent::__construct($domains);
    }

    // -----------------------------------------------------------------------
    // Lifecycle hooks
    // -----------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepare(array $data): array
    {
        return $this->normaliseName($this->normalise($data));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareForCreate(array $data): array
    {
        return $this->applyCreateDefaults($this->prepare($data));
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  Domain  $model
     * @return array<string, mixed>
     */
    protected function prepareForUpdate(array $data, Model $model): array
    {
        unset($model);

        return $this->preserveExistingSecrets($this->prepare($data));
    }

    /**
     * Domain names are case-insensitive in DNS; store them in one canonical
     * form so lookups, uniqueness and the UI all agree.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normaliseName(array $data): array
    {
        if (! array_key_exists('name', $data) || ! is_string($data['name'])) {
            return $data;
        }

        $data['name'] = mb_strtolower(trim($data['name']));

        return $data;
    }

    // -----------------------------------------------------------------------
    // Module operations
    // -----------------------------------------------------------------------

    /**
     * @return array<string, string|null>
     */
    public function revealCredentials(Domain $domain): array
    {
        $revealed = $domain->revealCredentials();

        $this->activity->log(
            ActivityAction::CredentialViewed,
            $domain,
            'Revealed domain credentials.',
            [
                'attributes' => array_keys($revealed),
                'present' => array_keys(array_filter(
                    $revealed,
                    static fn (?string $value): bool => $value !== null && $value !== '',
                )),
            ],
        );

        return $revealed;
    }

    public function findTrashed(int $id): Domain
    {
        /** @var Domain $domain */
        $domain = $this->domains->findWithTrashed($id);

        return $domain;
    }

    /**
     * @return array{total: int, active: int, expiring: int, expired: int, by_status: array<string, int>}
     */
    public function statistics(): array
    {
        return [
            'total' => $this->domains->totalCount(),
            'active' => $this->domains->activeCount(),
            'expiring' => $this->domains->expiringCount(self::DEFAULT_EXPIRY_WINDOW),
            'expired' => $this->domains->expiredCount(),
            'by_status' => $this->domains->countByStatus(),
        ];
    }

    /**
     * @return Collection<int, Domain>
     */
    public function expiringWithin(int $days = self::DEFAULT_EXPIRY_WINDOW, int $limit = 0): Collection
    {
        return $this->domains->expiringWithin($days, $limit);
    }

    public function findByName(string $name): ?Domain
    {
        return $this->domains->findByName($name);
    }

    // -----------------------------------------------------------------------
    // Status vocabulary for the shared normalisation rules
    // -----------------------------------------------------------------------

    protected function activeStatusValue(): string
    {
        return DomainStatus::Active->value;
    }

    protected function expiredStatusValue(): string
    {
        return DomainStatus::Expired->value;
    }

    /**
     * @return array<int, string>
     */
    protected function secretKeys(): array
    {
        return ['password', 'registrar_password'];
    }
}
