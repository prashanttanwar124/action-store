<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PosCustomerController extends Controller
{
    /**
     * Search loyalty customers rapidly by phone number, name, or email at the counter.
     */
    public function search(Request $request): JsonResponse
    {
        $query = trim((string) $request->input('q', ''));

        if ($query === '') {
            return response()->json(['customers' => []]);
        }

        $digitsOnly = preg_replace('/\D+/', '', $query) ?? '';

        $customers = User::query()
            ->select(['id', 'name', 'email', 'phone', 'reward_points'])
            ->where(function ($q) use ($query, $digitsOnly): void {
                $q->where('phone', 'like', "%{$query}%")
                    ->orWhere('name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%");

                if (strlen($digitsOnly) >= 4 && $digitsOnly !== $query) {
                    $q->orWhereRaw(
                        "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(phone, ''), '-', ''), ' ', ''), '(', ''), ')', ''), '+', '') LIKE ?",
                        ["%{$digitsOnly}%"],
                    );
                }
            })
            ->orderByDesc('reward_points')
            ->orderBy('name')
            ->limit(10)
            ->get()
            ->map(fn (User $user): array => self::formatCustomer($user))
            ->values();

        return response()->json([
            'customers' => $customers,
        ]);
    }

    /**
     * Create a new loyalty customer inline at the POS register using name + phone (+ optional email).
     *
     * @throws ValidationException
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['nullable', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
        ]);

        $phone = trim($validated['phone']);
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (strlen($digits) < 7) {
            throw ValidationException::withMessages([
                'phone' => 'Please enter a valid customer phone number.',
            ]);
        }

        $existingByPhone = User::query()
            ->where('phone', $phone)
            ->orWhereRaw(
                "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(phone, ''), '-', ''), ' ', ''), '(', ''), ')', ''), '+', '') = ?",
                [$digits],
            )
            ->first();

        if ($existingByPhone) {
            throw ValidationException::withMessages([
                'phone' => "A customer ({$existingByPhone->name}) is already registered with this phone number.",
            ]);
        }

        $email = ! empty($validated['email'])
            ? trim($validated['email'])
            : 'walkin.'.$digits.'.'.Str::lower(Str::random(5)).'@pos.masalamart.local';

        $customer = User::create([
            'name' => trim($validated['name']),
            'phone' => $phone,
            'email' => $email,
            'password' => Hash::make(Str::random(24)),
        ]);

        return response()->json([
            'success' => true,
            'customer' => self::formatCustomer($customer),
        ], 201);
    }

    /**
     * Format a customer record with loyalty redemption metadata.
     *
     * @return array<string, mixed>
     */
    public static function formatCustomer(User $user): array
    {
        $pointsPerBlock = max(1, (int) config('pos.loyalty.points_per_block', 500));
        $discountPerBlock = (float) config('pos.loyalty.discount_per_block', 10.00);
        $points = max(0, (int) ($user->reward_points ?? 0));
        $redeemableBlocks = intdiv($points, $pointsPerBlock);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => str_ends_with((string) $user->email, '@pos.masalamart.local') ? null : $user->email,
            'phone' => $user->phone,
            'reward_points' => $points,
            'redeemable_blocks' => $redeemableBlocks,
            'redeemable_points' => $redeemableBlocks * $pointsPerBlock,
            'max_points_discount' => round($redeemableBlocks * $discountPerBlock, 2),
        ];
    }
}
