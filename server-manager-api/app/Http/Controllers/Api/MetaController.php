<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\DomainStatus;
use App\Enums\DomainType;
use App\Enums\HostingStatus;
use App\Enums\ProjectStatus;
use App\Enums\ProviderStatus;
use App\Enums\ProviderType;
use App\Enums\RegistrarStatus;
use App\Enums\ServerType;
use App\Enums\SslCertificate;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

final class MetaController extends Controller
{
    public function options(): JsonResponse
    {
        $roles = Role::query()->where('guard_name', 'web')->orderBy('name')->get()->map(static fn (Role $role): array => [
            'value' => $role->name, 'label' => ucwords(str_replace(['-', '_'], ' ', $role->name)), 'variant' => 'secondary',
        ])->all();

        return ApiResponse::success([
            'hosting_statuses' => HostingStatus::options(),
            'domain_statuses' => DomainStatus::options(),
            'registrar_statuses' => RegistrarStatus::options(),
            'domain_types' => DomainType::options(),
            'server_types' => ServerType::options(),
            'ssl_certificates' => SslCertificate::options(),
            'provider_types' => ProviderType::options(),
            'provider_statuses' => ProviderStatus::options(),
            'project_statuses' => ProjectStatus::options(),
            'user_statuses' => UserStatus::options(),
            'roles' => $roles,
            'permissions' => Permission::query()->where('guard_name', 'web')->orderBy('name')->get()->map(static fn (Permission $permission): array => ['value'=>$permission->name,'label'=>$permission->name,'variant'=>'secondary'])->all(),
            'currencies' => array_map(static fn (string $value): array => ['value' => $value, 'label' => $value, 'variant' => 'secondary'], ['USD', 'EUR', 'GBP', 'AED']),
            'expiry_window_days' => array_map(static fn (int $value): array => ['value' => (string) $value, 'label' => "{$value} days", 'variant' => 'secondary'], [7, 14, 30, 60, 90]),
        ]);
    }
}
