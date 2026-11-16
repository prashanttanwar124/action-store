<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PosAuthController extends Controller
{
    /**
     * Display the touch-friendly POS terminal login view.
     */
    public function create(): Response|RedirectResponse
    {
        /** @var ?Admin $admin */
        $admin = Auth::guard('admin')->user();

        if ($admin && $admin->can('operate pos')) {
            return redirect()->route('pos.register');
        }

        return Inertia::render('Pos/Login');
    }

    /**
     * Authenticate a POS cashier or manager using the admin guard.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = 'pos-login:'.Str::transliterate(Str::lower($validated['email']).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => trans('auth.throttle', [
                    'seconds' => $seconds,
                    'minutes' => ceil($seconds / 60),
                ]),
            ]);
        }

        if (! Auth::guard('admin')->attempt([
            'email' => $validated['email'],
            'password' => $validated['password'],
        ], $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();

        if (! $admin->can('operate pos')) {
            Auth::guard('admin')->logout();
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'email' => 'Your staff account does not have POS register permission.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        return redirect()->intended(route('pos.register', absolute: false));
    }

    /**
     * Switch the active POS cashier (or unlock the register lock screen) via a 4-digit PIN.
     *
     * @throws ValidationException
     */
    public function switchCashier(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'admin_id' => ['required', 'integer', 'exists:admins,id'],
            'pin' => ['required', 'digits:4'],
        ]);

        $targetAdminId = (int) $validated['admin_id'];
        $throttleKey = "pos-pin:{$targetAdminId}|{$request->ip()}";

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'pin' => "Too many PIN attempts. Try again in {$seconds} seconds.",
            ]);
        }

        $targetAdmin = Admin::findOrFail($targetAdminId);

        if (! $targetAdmin->can('operate pos') || ! $targetAdmin->hasPosPin() || ! Hash::check($validated['pin'], (string) $targetAdmin->pos_pin)) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'pin' => 'Invalid 4-digit cashier PIN.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        Auth::guard('admin')->login($targetAdmin);
        $request->session()->regenerate();

        $cashierPayload = [
            'id' => $targetAdmin->id,
            'name' => $targetAdmin->name,
            'email' => $targetAdmin->email,
            'has_pos_pin' => $targetAdmin->hasPosPin(),
            'roles' => $targetAdmin->getRoleNames()->values()->all(),
        ];

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'cashier' => $cashierPayload,
            ]);
        }

        return back()->with('success', "Active cashier switched to {$targetAdmin->name}.");
    }

    /**
     * Log out of the POS terminal session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('pos.login');
    }
}
