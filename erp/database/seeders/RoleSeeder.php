<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // Tenant management (superadmin only)
            'view_tenants',
            'create_tenants',
            'edit_tenants',
            'delete_tenants',

            // User management
            'view_users',
            'create_users',
            'edit_users',
            'delete_users',

            // General permissions (for future modules)
            'view_dashboard',
            'manage_settings',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create roles
        // Superadmin: can do everything, no tenant restriction
        $superadmin = Role::firstOrCreate(['name' => 'superadmin']);
        $superadmin->givePermissionTo(Permission::all());

        // Tenant Admin: can manage their own tenant
        $tenantAdmin = Role::firstOrCreate(['name' => 'tenant_admin']);
        $tenantAdmin->givePermissionTo([
            'view_users',
            'create_users',
            'edit_users',
            'delete_users',
            'view_dashboard',
            'manage_settings',
        ]);

        // Tenant Staff: limited access within tenant
        $tenantStaff = Role::firstOrCreate(['name' => 'tenant_staff']);
        $tenantStaff->givePermissionTo([
            'view_dashboard',
        ]);
    }
}
