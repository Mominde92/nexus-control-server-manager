<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\ClientRepositoryInterface;
use App\Contracts\Services\ClientServiceInterface;
use App\Models\Client;
use App\Services\Concerns\NormalisesInput;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Business rules for the Client module.
 *
 * @extends BaseCrudService<Client>
 */
class ClientService extends BaseCrudService implements ClientServiceInterface
{
    use NormalisesInput;

    public function __construct(
        private readonly ClientRepositoryInterface $clients,
    ) {
        parent::__construct($clients);
    }

    public function findWithTrashed(int $id): Client
    {
        return $this->clients->findWithTrashedOrFail($id);
    }

    /**
     * A client is the top of the ownership chain, so removing one silently would
     * strand its projects, hostings and domains. Block the delete and name the
     * dependents.
     *
     * @param  Client  $model
     */
    public function delete(Model $model): bool
    {
        $counts = $this->clients->dependentCounts($model);
        $blockers = [];

        foreach (['projects' => 'project(s)', 'hostings' => 'hosting(s)', 'domains' => 'domain(s)'] as $key => $noun) {
            if ($counts[$key] > 0) {
                $blockers[] = $counts[$key].' '.$noun;
            }
        }

        if ($blockers !== []) {
            throw new RuntimeException(sprintf(
                'Cannot delete client "%s": %s still belong to it. Reassign or remove them first.',
                $model->name,
                implode(', ', $blockers),
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
        $data = $this->lowercaseKeys($data, ['email']);

        return $this->normaliseUrls($data, ['website']);
    }
}
