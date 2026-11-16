<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class AdminRoleController extends Controller
{
    /**
     * Create a new role with permissions for admin guard.
     */
    public function storeRole(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50', 'unique:roles,name,NULL,id,guard_name,admin'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        DB::transaction(function () use ($validated) {
            $role = Role::create([
                'name' => $validated['name'],
                'guard_name' => 'admin',
            ]);

            if (! empty($validated['permissions'])) {
                $role->syncPermissions($validated['permissions']);
            }
        });

        return back()->with('success', 'Role created successfully.');
    }

    /**
     * Update roles for an admin.
     */
    public function syncAdminRoles(Request $request, Admin $admin): RedirectResponse
    {
        $validated = $request->validate([
            'roles' => ['required', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        $admin->syncRoles($validated['roles']);

        return back()->with('success', 'Admin roles updated successfully.');
    }

    /**
     * Set or clear a 4-digit POS quick-switch PIN for an admin staff member.
     */
    public function updateAdminPin(Request $request, Admin $admin): RedirectResponse
    {
        $validated = $request->validate([
            'pos_pin' => ['nullable', 'digits:4'],
        ]);

        $admin->update([
            'pos_pin' => ! empty($validated['pos_pin']) ? $validated['pos_pin'] : null,
        ]);

        $message = ! empty($validated['pos_pin'])
            ? "POS 4-digit PIN updated for {$admin->name}."
            : "POS PIN cleared for {$admin->name}.";

        return back()->with('success', $message);
    }
}
