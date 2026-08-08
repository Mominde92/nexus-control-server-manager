<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ActivityLoggerInterface;
use App\Contracts\Repositories\HostingRepositoryInterface;
use App\Contracts\Services\HostingServiceInterface;
use App\Enums\ActivityAction;
use App\Enums\HostingStatus;
use App\Models\Hosting;
use App\Services\Concerns\NormalisesAssetInput;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Hosting business rules.
 *
 * @extends BaseCrudService<Hosting>
 */
class HostingService extends BaseCrudService implements HostingServiceInterface
{
    use NormalisesAssetInput;

    /**
     * Default "expiring soon" window for the dashboard counters.
     */
    private const int DEFAULT_EXPIRY_WINDOW = 30;

    public function __construct(
        private readonly HostingRepositoryInterface $hostings,
        private readonly ActivityLoggerInterface $activity,
    ) {
        parent::__construct($hostings);
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
        return $this->normalise($data);
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
     * @param  Hosting  $model
     * @return array<string, mixed>
     */
    protected function prepareForUpdate(array $data, Model $model): array
    {
        unset($model);

        return $this->preserveExistingSecrets($this->prepare($data));
    }

    // -----------------------------------------------------------------------
    // Module operations
    // -----------------------------------------------------------------------

    /**
     * @return array<string, string|null>
     */
    public function revealCredentials(Hosting $hosting): array
    {
        $revealed = $hosting->revealCredentials();

        // Audit the reveal, never the secret: only the attribute names and
        // which of them actually held a value are recorded.
        $this->activity->log(
            ActivityAction::CredentialViewed,
            $hosting,
            'Revealed hosting credentials.',
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

    public function findTrashed(int $id): Hosting
    {
        /** @var Hosting $hosting */
        $hosting = $this->hostings->findWithTrashed($id);

        return $hosting;
    }

    /**
     * @return array{total: int, active: int, expiring: int, expired: int, by_status: array<string, int>}
     */
    public function statistics(): array
    {
        return [
            'total' => $this->hostings->totalCount(),
            'active' => $this->hostings->activeCount(),
            'expiring' => $this->hostings->expiringCount(self::DEFAULT_EXPIRY_WINDOW),
            'expired' => $this->hostings->expiredCount(),
            'by_status' => $this->hostings->countByStatus(),
        ];
    }

    /**
     * @return Collection<int, Hosting>
     */
    public function expiringWithin(int $days = self::DEFAULT_EXPIRY_WINDOW, int $limit = 0): Collection
    {
        return $this->hostings->expiringWithin($days, $limit);
    }

    // -----------------------------------------------------------------------
    // Status vocabulary for the shared normalisation rules
    // -----------------------------------------------------------------------

    protected function activeStatusValue(): string
    {
        return HostingStatus::Active->value;
    }

    protected function expiredStatusValue(): string
    {
        return HostingStatus::Expired->value;
    }

    /**
     * @return array<int, string>
     */
    protected function secretKeys(): array
    {
        return ['password', 'ftp_password'];
    }
}
