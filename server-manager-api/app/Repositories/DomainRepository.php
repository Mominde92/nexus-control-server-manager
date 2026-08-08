<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\DomainRepositoryInterface;
use App\Models\Domain;
use App\Repositories\Concerns\QueriesExpiringRecords;
use App\Support\QueryOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Domain persistence.
 *
 * @extends BaseRepository<Domain>
 */
class DomainRepository extends BaseRepository implements DomainRepositoryInterface
{
    use QueriesExpiringRecords;

    /**
     * @return class-string<Domain>
     */
    public function modelClass(): string
    {
        return Domain::class;
    }

    /**
     * @return array<int, string>
     */
    protected function defaultRelations(): array
    {
        return [
            'provider:id,name,url',
            'project:id,name',
            'client:id,name,company',
            'hosting:id,title',
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function filterableColumns(): array
    {
        return [
            'status',
            'registrar_status',
            'domain_type',
            'provider_id',
            'project_id',
            'client_id',
            'hosting_id',
            'auto_renewal',
            'whois_protection',
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function booleanFilters(): array
    {
        return ['auto_renewal', 'whois_protection'];
    }

    /**
     * @param  Builder<Domain>  $query
     */
    protected function applyFilters(Builder $query, QueryOptions $options): void
    {
        $user = Auth::user();
        if ($user !== null && ! $user->hasRole('admin')) {
            $query->where(fn (Builder $scope) => $scope
                ->whereDoesntHave('accessRules')
                ->orWhereHas('accessRules', fn (Builder $rule) => $rule->where('user_id', $user->id)->where('can_view', true)));
        }
        parent::applyFilters($query, $this->normaliseFilters($options));

        $this->applyRangeFilters($query, $options);
    }

    /**
     * Case-insensitive lookup. DNS treats `Example.COM` and `example.com` as the
     * same name, so a uniqueness probe must not depend on the column collation.
     */
    public function findByName(string $name): ?Domain
    {
        return $this->query()
            ->with($this->defaultRelations())
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])
            ->first();
    }
}
