<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $roleUserCounts = DB::table('model_has_roles')
            ->where('model_type', Admin::class)
            ->groupBy('role_id')
            ->selectRaw('role_id, count(*) as count')
            ->pluck('count', 'role_id');

        $roles = Role::where('guard_name', 'admin')->with('permissions')->get()->map(function (Role $role) use ($roleUserCounts) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name'),
                'users_count' => (int) ($roleUserCounts->get($role->id) ?? 0),
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
