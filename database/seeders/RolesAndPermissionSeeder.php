<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $permissions = [
          'manage_modules',
          'manage_users',
          'manage_settings',
          'manage_data_migrate',
          'view_logs',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin']);
        $superAdminRole->givePermissionTo(Permission::all());

        $adminRole = Role::firstOrCreate(['name' => 'Admin']);
        $adminRole->givePermissionTo(['manage_modules', 'manage_data_migrate', 'view_logs']);

        $superAdminUser = User::firstOrCreate(
            ['email' => 'superadmin@example.com'],
            ['name' => 'Super Admin', 'password' => Hash::make('password'), 'role' => 'Super Admin', 'status' => 'Active']
        );
        $superAdminUser->assignRole($superAdminRole);
        
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Admin', 'password' => Hash::make('password'), 'role' => 'Admin', 'status' => 'Active']
        );
        $adminUser->assignRole($adminRole);
    }
}
