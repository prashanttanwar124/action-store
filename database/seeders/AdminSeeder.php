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
            'manage settings',
            'operate pos',
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
        $storeManagerRole->syncPermissions(['manage products', 'manage orders', 'manage users', 'view reports', 'manage settings', 'operate pos']);

        $orderHandlerRole = Role::firstOrCreate([
            'name' => 'Order Handler',
            'guard_name' => 'admin',
        ]);
        $orderHandlerRole->syncPermissions(['manage orders']);

        $cashierRole = Role::firstOrCreate([
            'name' => 'Cashier',
            'guard_name' => 'admin',
        ]);
        $cashierRole->syncPermissions(['operate pos']);

        // Default Super Admin User
        $superAdmin = Admin::firstOrCreate(
            ['email' => 'admin@masalamart.com'],
            [
                'name' => 'Priya Sharma (Head Chef & Owner)',
                'password' => Hash::make('password'),
                'pos_pin' => '1111',
            ]
        );
        if (! $superAdmin->hasPosPin()) {
            $superAdmin->update(['pos_pin' => '1111']);
        }
        $superAdmin->assignRole($superAdminRole);

        // Default Store Manager
        $storeManager = Admin::firstOrCreate(
            ['email' => 'manager@masalamart.com'],
            [
                'name' => 'Rohan Verma (Operations)',
                'password' => Hash::make('password'),
                'pos_pin' => '2222',
            ]
        );
        if (! $storeManager->hasPosPin()) {
            $storeManager->update(['pos_pin' => '2222']);
        }
        $storeManager->assignRole($storeManagerRole);
    }
}
