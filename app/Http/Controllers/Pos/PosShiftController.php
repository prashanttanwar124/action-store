<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\StoreShiftCashMovementRequest;
use App\Models\Admin;
use App\Models\PosCashMovement;
use App\Models\PosShift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PosShiftController extends Controller
{
    /**
     * Return the currently open register shift, if any.
     */
    public function current(): JsonResponse
    {
        $shift = PosShift::currentOpen();

        return response()->json([
            'shift' => $shift ? $this->formatShift($shift) : null,
        ]);
    }

    /**
     * Open a new cash register shift with a starting float.
     *
     * @throws ValidationException
     */
    public function open(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'opening_cash' => ['required', 'numeric', 'min:0', 'max:100000'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var Admin $cashier */
        $cashier = $request->user('admin');
        $openingCash = round((float) $validated['opening_cash'], 2);

        $shift = DB::transaction(function () use ($cashier, $openingCash, $validated): PosShift {
            $existingOpen = PosShift::open()->lockForUpdate()->first();
            if ($existingOpen) {
                throw ValidationException::withMessages([
                    'opening_cash' => 'A register shift is already open.',
                ]);
            }

            return PosShift::create([
                'admin_id' => $cashier->id,
                'status' => 'open',
                'opening_cash' => $openingCash,
                'cash_sales' => 0.00,
                'cash_deposits' => 0.00,
                'terminal_sales' => 0.00,
                'cash_refunds' => 0.00,
                'cash_withdrawals' => 0.00,
                'terminal_refunds' => 0.00,
                'expected_cash' => $openingCash,
                'orders_count' => 0,
                'notes' => $validated['notes'] ?? null,
                'opened_at' => now(),
            ]);
        });

        $shift->load(['admin:id,name,email', 'cashMovements.admin:id,name,email']);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'shift' => $this->formatShift($shift),
            ], 201);
        }

        return back()->with('success', 'Register shift opened.');
    }

    /**
     * Record a mid-shift cash drawer deposit (Pay-In / coins) or withdrawal (Safe Drop / payout).
     *
     * @throws ValidationException
     */
    public function storeMovement(StoreShiftCashMovementRequest $request, PosShift $shift): JsonResponse|RedirectResponse
    {
        $validated = $request->validated();

        /** @var Admin $cashier */
        $cashier = $request->user('admin');

        $result = DB::transaction(function () use ($shift, $cashier, $validated): array {
            $lockedShift = PosShift::whereKey($shift->id)->lockForUpdate()->firstOrFail();

            if ($lockedShift->status !== 'open') {
                throw ValidationException::withMessages([
                    'shift' => 'Cash drawer adjustments require an open register shift.',
                ]);
            }

            $movement = $lockedShift->recordCashMovement(
                $cashier,
                (string) $validated['type'],
                (float) $validated['amount'],
                (string) $validated['reason'],
                $validated['notes'] ?? null,
            );

            $lockedShift->load([
                'admin:id,name,email',
                'closedByAdmin:id,name,email',
                'cashMovements.admin:id,name,email',
            ]);
            $movement->load('admin:id,name,email');

            return [
                'shift' => $lockedShift,
                'movement' => $movement,
            ];
        });

        /** @var PosCashMovement $movement */
        $movement = $result['movement'];

        /** @var PosShift $updatedShift */
        $updatedShift = $result['shift'];

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'shift' => self::formatShift($updatedShift),
                'movement' => self::formatMovement($movement),
            ], 201);
        }

        return back()->with('success', 'Cash drawer adjustment recorded.');
    }

    /**
     * Close an open register shift and record physical cash drawer variance.
     *
     * @throws ValidationException
     */
    public function close(Request $request, PosShift $shift): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'counted_cash' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'counted_terminal' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var Admin $cashier */
        $cashier = $request->user('admin');

        $countedTerminal = isset($validated['counted_terminal']) && $validated['counted_terminal'] !== ''
            ? (float) $validated['counted_terminal']
            : null;

        $closedShift = DB::transaction(function () use ($shift, $cashier, $validated, $countedTerminal): PosShift {
            $lockedShift = PosShift::whereKey($shift->id)->lockForUpdate()->firstOrFail();

            if ($lockedShift->status !== 'open') {
                throw ValidationException::withMessages([
                    'counted_cash' => 'This register shift is already closed.',
                ]);
            }

            $lockedShift->closeShift(
                $cashier,
                (float) $validated['counted_cash'],
                $validated['notes'] ?? null,
                $countedTerminal,
            );

            return PosShift::with([
                'admin:id,name,email',
                'closedByAdmin:id,name,email',
                'cashMovements.admin:id,name,email',
            ])->withCount([
                'orders as split_orders_count' => fn ($q) => $q
                    ->where('payment_method', 'split')
                    ->where('status', '!=', 'pending'),
            ])->find($lockedShift->id) ?? $lockedShift;
        });

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'shift' => $this->formatShift($closedShift),
            ]);
        }

        return back()->with('success', 'Register shift closed.');
    }

    /**
     * Serialize a PosShift model for frontend display.
     *
     * @return array<string, mixed>
     */
    public static function formatShift(PosShift $shift): array
    {
        $safeDropThreshold = max(0.01, (float) config('pos.drawer.safe_drop_alert_threshold', 1000.00));

        $splitOrdersCount = array_key_exists('split_orders_count', $shift->getAttributes())
            ? (int) $shift->getAttribute('split_orders_count')
            : $shift->orders()
                ->where('payment_method', 'split')
                ->where('status', '!=', 'pending')
                ->count();

        $netCashSales = $shift->netCashSales();
        $netTerminal = $shift->netTerminal();

        $timezone = config('app.timezone', 'America/Toronto');
        $openedLocal = $shift->opened_at?->copy()->timezone($timezone);
        $closedLocal = $shift->closed_at?->copy()->timezone($timezone);
        $endForDuration = $closedLocal ?? now($timezone);
        $durationLabel = null;

        if ($openedLocal) {
            $totalMinutes = max(0, (int) $openedLocal->diffInMinutes($endForDuration));
            $hours = intdiv($totalMinutes, 60);
            $minutes = $totalMinutes % 60;
            $durationLabel = $hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes}m";
        }

        $movements = $shift->relationLoaded('cashMovements')
            ? $shift->cashMovements
                ->map(static fn (PosCashMovement $movement): array => self::formatMovement($movement))
                ->values()
                ->all()
            : [];

        $depositsCount = $shift->relationLoaded('cashMovements')
            ? $shift->cashMovements->where('type', PosCashMovement::TYPE_DEPOSIT)->count()
            : 0;
        $withdrawalsCount = $shift->relationLoaded('cashMovements')
            ? $shift->cashMovements->where('type', PosCashMovement::TYPE_WITHDRAWAL)->count()
            : 0;

        return [
            'id' => $shift->id,
            'status' => $shift->status,
            'opening_cash' => (float) $shift->opening_cash,
            'cash_sales' => (float) $shift->cash_sales,
            'cash_deposits' => (float) ($shift->cash_deposits ?? 0),
            'terminal_sales' => (float) $shift->terminal_sales,
            'cash_refunds' => (float) $shift->cash_refunds,
            'cash_withdrawals' => (float) ($shift->cash_withdrawals ?? 0),
            'terminal_refunds' => (float) $shift->terminal_refunds,
            'net_cash_sales' => $netCashSales,
            'net_terminal' => $netTerminal,
            'gross_total_sales' => round((float) $shift->cash_sales + (float) $shift->terminal_sales, 2),
            'total_refunds' => round((float) $shift->cash_refunds + (float) $shift->terminal_refunds, 2),
            'net_total_sales' => round($netCashSales + $netTerminal, 2),
            'counted_terminal' => $shift->counted_terminal !== null ? (float) $shift->counted_terminal : null,
            'terminal_difference' => $shift->terminal_difference !== null ? (float) $shift->terminal_difference : null,
            'expected_cash' => (float) $shift->expected_cash,
            'safe_drop_recommended' => (float) $shift->expected_cash >= $safeDropThreshold,
            'counted_cash' => $shift->counted_cash !== null ? (float) $shift->counted_cash : null,
            'cash_difference' => $shift->cash_difference !== null ? (float) $shift->cash_difference : null,
            'orders_count' => (int) $shift->orders_count,
            'split_orders_count' => $splitOrdersCount,
            'deposits_count' => $depositsCount,
            'withdrawals_count' => $withdrawalsCount,
            'movements_count' => count($movements),
            'notes' => $shift->notes,
            'opened_by' => $shift->admin ? [
                'id' => $shift->admin->id,
                'name' => $shift->admin->name,
            ] : null,
            'closed_by' => $shift->relationLoaded('closedByAdmin') && $shift->closedByAdmin ? [
                'id' => $shift->closedByAdmin->id,
                'name' => $shift->closedByAdmin->name,
            ] : null,
            'movements' => $movements,
            'opened_at' => $openedLocal?->toIso8601String(),
            'opened_at_formatted' => $openedLocal?->format('M j, g:i A'),
            'opened_date_label' => $openedLocal?->format('M j, Y'),
            'opened_time_label' => $openedLocal?->format('g:i A'),
            'closed_at' => $closedLocal?->toIso8601String(),
            'closed_at_formatted' => $closedLocal?->format('M j, g:i A'),
            'closed_date_label' => $closedLocal?->format('M j, Y'),
            'closed_time_label' => $closedLocal?->format('g:i A'),
            'duration_label' => $durationLabel,
        ];
    }

    /**
     * Serialize an individual PosCashMovement ledger entry.
     *
     * @return array<string, mixed>
     */
    public static function formatMovement(PosCashMovement $movement): array
    {
        $timezone = config('app.timezone', 'America/Toronto');
        $createdLocal = $movement->created_at?->copy()->timezone($timezone);

        return [
            'id' => $movement->id,
            'type' => $movement->type,
            'amount' => (float) $movement->amount,
            'reason' => $movement->reason,
            'notes' => $movement->notes,
            'balance_after' => (float) $movement->balance_after,
            'cashier_name' => $movement->admin?->name ?? 'Staff',
            'created_at' => $createdLocal?->toIso8601String(),
            'created_at_formatted' => $createdLocal?->format('g:i A'),
            'created_date_time' => $createdLocal?->format('M j, Y g:i A'),
        ];
    }
}
