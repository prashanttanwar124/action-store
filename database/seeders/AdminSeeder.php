<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Admin Permissions
        $permissions = [
            'manage products',
            'manage orders',
            'manage users',
            'manage roles',
            'view reports',
        ];

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'admin',
            ]);
        }

        // Roles
        $superAdminRole = Role::firstOrCreate([
            'name' => 'Super Admin',
            'guard_name' => 'admin',
        ]);
        $superAdminRole->syncPermissions(Permission::where('guard_name', 'admin')->get());

        $storeManagerRole = Role::firstOrCreate([
            'name' => 'Store Manager',
            'guard_name' => 'admin',
        ]);
        $storeManagerRole->syncPermissions(['manage products', 'manage orders', 'view reports']);

        $orderHandlerRole = Role::firstOrCreate([
            'name' => 'Order Handler',
            'guard_name' => 'admin',
        ]);
        $orderHandlerRole->syncPermissions(['manage orders']);

        // Default Super Admin User
        $superAdmin = Admin::firstOrCreate(
            ['email' => 'admin@masalamart.com'],
            [
                'name' => 'Priya Sharma (Head Chef & Owner)',
                'password' => Hash::make('password'),
            ]
        );
        $superAdmin->assignRole($superAdminRole);

        // Default Store Manager
        $storeManager = Admin::firstOrCreate(
            ['email' => 'manager@masalamart.com'],
            [
                'name' => 'Rohan Verma (Operations)',
                'password' => Hash::make('password'),
            ]
        );
        $storeManager->assignRole($storeManagerRole);
    }
}
