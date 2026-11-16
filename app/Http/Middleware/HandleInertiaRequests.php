<?php

namespace App\Http\Middleware;

use App\Models\StoreSetting;
use App\Support\PosRequest;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the root template that is loaded on the first page visit.
     */
    public function rootView(Request $request): string
    {
        if (PosRequest::matches($request)) {
            return 'pos';
        }

        return $request->is('admin*') ? 'admin' : 'app';
    }

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $isAdmin = $request->is('admin*');
        $isPos = PosRequest::matches($request);
        $usesAdminGuard = $isAdmin || $isPos;
        $admin = $usesAdminGuard ? $request->user('admin') : null;

        return [
            ...parent::share($request),
            'auth' => [
                'user' => fn () => ! $usesAdminGuard ? $request->user() : null,
                'admin' => fn () => $admin ? [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'email' => $admin->email,
                    'has_pos_pin' => $admin->hasPosPin(),
                    'roles' => $admin->getRoleNames(),
                    'permissions' => $admin->getAllPermissions()->pluck('name'),
                ] : null,
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
            'storeInfo' => fn () => StoreSetting::current(),
            'storeTimezone' => fn () => config('app.timezone', 'America/Toronto'),
        ];
    }
}
