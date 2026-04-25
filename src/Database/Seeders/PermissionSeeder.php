<?php

declare(strict_types=1);

namespace MiPress\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use MiPress\Core\Enums\UserRole;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    private const LEGACY_PERMISSIONS = [
        'global_set.view',
        'global_set.create',
        'global_set.update',
        'global_set.delete',
    ];

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
        'settings.manage',
        'taxonomy.view',
        'taxonomy.create',
        'taxonomy.update',
        'taxonomy.delete',
        'form.view',
        'form.create',
        'form.update',
        'form.delete',
        'form_submission.view',
        'form_submission.update',
        'form_submission.delete',
        'social_account.view',
        'social_account.create',
        'social_account.update',
        'social_account.delete',
        'social_feed.view',
        'social_feed.create',
        'social_feed.update',
        'social_feed.delete',
        'seo_robots.manage',
        'seo_sitemap.manage',
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
            'form.view',
            'form.create',
            'form.update',
            'form.delete',
            'form_submission.view',
            'form_submission.update',
            'social_account.view',
            'social_account.create',
            'social_account.update',
            'social_feed.view',
            'social_feed.create',
            'social_feed.update',
        ],
        UserRole::Contributor->value => [
            'entry.view',
            'entry.create',
            'entry.update',
            'media.view',
            'media.upload',
        ],
    ];

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->purgeLegacyPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        foreach (UserRole::cases() as $roleEnum) {
            $role = Role::firstOrCreate(['name' => $roleEnum->value, 'guard_name' => 'web']);
            $role->syncPermissions(self::ROLE_PERMISSIONS[$roleEnum->value] ?? []);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->command->info('MiPress permissions and roles seeded successfully.');
    }

    private function purgeLegacyPermissions(): void
    {
        $tableNames = config('permission.table_names');
        $permissionsTable = $tableNames['permissions'] ?? 'permissions';
        $roleHasPermissionsTable = $tableNames['role_has_permissions'] ?? 'role_has_permissions';
        $modelHasPermissionsTable = $tableNames['model_has_permissions'] ?? 'model_has_permissions';

        $legacyPermissionIds = DB::table($permissionsTable)
            ->whereIn('name', self::LEGACY_PERMISSIONS)
            ->pluck('id');

        if ($legacyPermissionIds->isEmpty()) {
            return;
        }

        DB::table($roleHasPermissionsTable)
            ->whereIn('permission_id', $legacyPermissionIds)
            ->delete();

        DB::table($modelHasPermissionsTable)
            ->whereIn('permission_id', $legacyPermissionIds)
            ->delete();

        DB::table($permissionsTable)
            ->whereIn('id', $legacyPermissionIds)
            ->delete();
    }
}
