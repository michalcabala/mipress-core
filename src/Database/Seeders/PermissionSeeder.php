<?php

declare(strict_types=1);

namespace MiPress\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use MiPress\Core\Enums\UserRole;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    private const PERMISSIONS = [
        'entry.view',
        'entry.create',
        'entry.update',
        'entry.delete',
        'entry.publish',
        'collection.view',
        'collection.create',
        'collection.update',
        'collection.delete',
        'blueprint.view',
        'blueprint.create',
        'blueprint.update',
        'blueprint.delete',
        'media.view',
        'media.upload',
        'media.update',
        'media.delete',
        'global_set.view',
        'global_set.create',
        'global_set.update',
        'global_set.delete',
        'taxonomy.view',
        'taxonomy.create',
        'taxonomy.update',
        'taxonomy.delete',
    ];

    private const ROLE_PERMISSIONS = [
        UserRole::SuperAdmin->value => self::PERMISSIONS,
        UserRole::Admin->value => self::PERMISSIONS,
        UserRole::Editor->value => [
            'entry.view',
            'entry.create',
            'entry.update',
            'entry.delete',
            'entry.publish',
            'media.view',
            'media.upload',
            'media.update',
            'taxonomy.view',
            'taxonomy.create',
            'taxonomy.update',
        ],
        UserRole::Contributor->value => [
            'entry.view',
            'entry.create',
            'entry.update',
            'media.view',
            'media.upload',
            'taxonomy.view',
        ],
    ];

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        foreach (UserRole::cases() as $roleEnum) {
            $role = Role::firstOrCreate(['name' => $roleEnum->value, 'guard_name' => 'web']);
            $role->syncPermissions(self::ROLE_PERMISSIONS[$roleEnum->value] ?? []);
        }

        $this->command->info('MiPress permissions and roles seeded successfully.');
    }
}
