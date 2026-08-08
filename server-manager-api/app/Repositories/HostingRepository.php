<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\HostingRepositoryInterface;
use App\Models\Hosting;
use App\Repositories\Concerns\QueriesExpiringRecords;
use App\Support\QueryOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Hosting persistence.
 *
 * @extends BaseRepository<Hosting>
 */
class HostingRepository extends BaseRepository implements HostingRepositoryInterface
{
    use QueriesExpiringRecords;

    /**
     * @return class-string<Hosting>
     */
    public function modelClass(): string
    {
        return Hosting::class;
    }

    /**
     * Column-limited eager loads: the list screen only renders the vendor and
     * owner labels, so there is no reason to hydrate whole rows.
     *
     * @return array<int, string>
     */
    protected function defaultRelations(): array
    {
        return [
            'provider:id,name,url',
            'project:id,name',
            'client:id,name,company',
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function filterableColumns(): array
    {
        return [
            'status',
            'server_type',
            'provider_id',
            'project_id',
            'client_id',
            'ssl_certificate',
        ];
    }

    /**
     * @param  Builder<Hosting>  $query
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
}
