<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AdminDashboardController extends Controller
{
    /**
     * Display the admin dashboard.
     */
    public function index(Request $request): Response
    {
        $currentAdmin = $request->user('admin');

        $stats = [
            'total_products' => Product::count(),
            'total_users' => User::count(),
            'total_admins' => Admin::count(),
            'total_roles' => Role::where('guard_name', 'admin')->count(),
            'total_permissions' => Permission::where('guard_name', 'admin')->count(),
        ];

        $admins = Admin::with('roles')->latest()->take(10)->get()->map(function (Admin $admin) {
            return [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'roles' => $admin->getRoleNames(),
                'created_at' => $admin->created_at?->format('M d, Y'),
            ];
        });

        $roles = Role::where('guard_name', 'admin')->with('permissions')->get()->map(function (Role $role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name'),
                'users_count' => Admin::role($role->name, 'admin')->count(),
            ];
        });

        $permissions = Permission::where('guard_name', 'admin')->pluck('name');

        return Inertia::render('Admin/Dashboard', [
            'stats' => $stats,
            'admins' => $admins,
            'roles' => $roles,
            'availablePermissions' => $permissions,
        ]);
    }
}
