<?php

declare(strict_types=1);

namespace MiPress\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use MiPress\Core\Enums\UserRole;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (UserRole::cases() as $role) {
            Role::firstOrCreate(['name' => $role->value, 'guard_name' => 'web']);
        }

        $this->command->info('MiPress roles created: '.implode(', ', array_column(UserRole::cases(), 'value')));
    }
}
