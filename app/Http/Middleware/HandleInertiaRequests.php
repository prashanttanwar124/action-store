<?php

namespace App\Http\Middleware;

use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $user = ! $isAdmin ? $request->user() : null;

        if ($user instanceof User) {
            $user = clone $user;
            if (! $user->rewardPoints()->whereNull('order_id')->exists()) {
                $user->rewardPoints()->create([
                    'order_id' => null,
                    'points' => 100,
                    'description' => 'Welcome bonus',
                ]);
                $points = (int) $user->refreshRewardPointsBalance();
            } else {
                $dbPoints = DB::table('users')->where('id', $user->id)->value('reward_points');
                $points = $dbPoints !== null ? (int) $dbPoints : (int) $user->refreshRewardPointsBalance();
            }
            $user->setAttribute('reward_points', $points);
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                'admin' => $isAdmin && $request->user('admin') ? [
                    'id' => $request->user('admin')->id,
                    'name' => $request->user('admin')->name,
                    'email' => $request->user('admin')->email,
                    'roles' => $request->user('admin')->getRoleNames(),
                    'permissions' => $request->user('admin')->getAllPermissions()->pluck('name'),
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
