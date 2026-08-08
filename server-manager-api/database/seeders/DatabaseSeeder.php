<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Permission as PermissionEnum;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionEnum::cases() as $ability) {
            Permission::findOrCreate($ability->value, 'web');
        }

        foreach (UserRole::cases() as $roleCase) {
            $role = Role::findOrCreate($roleCase->value, 'web');
            $role->syncPermissions($roleCase->permissions());
        }

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@servermanager.test'],
            [
                'name' => 'System Admin',
                'password' => 'password',
                'status' => UserStatus::Active->value,
                'email_verified_at' => now(),
            ],
        );

        $admin->syncRoles([UserRole::Admin->value]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
