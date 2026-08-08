<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\ProviderRepositoryInterface;
use App\Contracts\Services\ProviderServiceInterface;
use App\Models\Provider;
use App\Services\Concerns\NormalisesInput;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Business rules for the Provider module.
 *
 * @extends BaseCrudService<Provider>
 */
class ProviderService extends BaseCrudService implements ProviderServiceInterface
{
    use NormalisesInput;

    public function __construct(
        private readonly ProviderRepositoryInterface $providers,
    ) {
        parent::__construct($providers);
    }

    public function findWithTrashed(int $id): Provider
    {
        return $this->providers->findWithTrashedOrFail($id);
    }

    /**
     * @return array{by_type: array<string, int>, active: int, total: int}
     */
    public function statistics(): array
    {
        $byType = $this->providers->countByType();

        return [
            'by_type' => $byType,
            'active' => $this->providers->activeCount(),
            'total' => array_sum($byType),
        ];
    }

    /**
     * Hostings and domains carry a `provider_id`; deleting the provider out from
     * under them would orphan records the operator still relies on. Refuse, and
     * say exactly what is in the way so the caller knows what to reassign.
     *
     * @param  Provider  $model
     */
    public function delete(Model $model): bool
    {
        $counts = $this->providers->dependentCounts($model);
        $blockers = [];

        if ($counts['hostings'] > 0) {
            $blockers[] = $counts['hostings'].' hosting(s)';
        }

        if ($counts['domains'] > 0) {
            $blockers[] = $counts['domains'].' domain(s)';
        }

        if ($blockers !== []) {
            throw new RuntimeException(sprintf(
                'Cannot delete provider "%s": %s still reference it. Reassign or remove them first.',
                $model->name,
                implode(' and ', $blockers),
            ));
        }

        return parent::delete($model);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepare(array $data): array
    {
        $data = $this->trimAndNullify($data);
        $data = $this->lowercaseKeys($data, ['support_email']);

        return $this->normaliseUrls($data, ['url']);
    }
}
