<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Pos\PosShiftController;
use App\Models\Admin;
use App\Models\Order;
use App\Models\PosCashMovement;
use App\Models\PosShift;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminTillController extends Controller
{
    /**
     * Render the POS Till & Shift Audit page with open/close logs, amounts entered, and drawer adjustments.
     */
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'preset' => ['nullable', 'string', 'in:today,yesterday,7d,30d,this_month,last_month,all,custom'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', 'string', 'in:all,open,closed,variance'],
            'admin_id' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $timezone = config('app.timezone', 'America/Toronto');
        $resolved = $this->resolveFilters($validated, $timezone);
        $baseQuery = $this->buildFilteredQuery($resolved);

        $summary = $this->summarizeTills((clone $baseQuery)->get());

        $tills = $baseQuery
            ->with([
                'admin:id,name,email',
                'closedByAdmin:id,name,email',
                'cashMovements.admin:id,name,email',
            ])
            ->withCount([
                'orders as split_orders_count' => fn (Builder $q) => $q
                    ->where('payment_method', 'split')
                    ->where('status', '!=', 'pending'),
            ])
            ->orderByDesc('opened_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (PosShift $shift): array => PosShiftController::formatShift($shift));

        $openShift = PosShift::currentOpen();

        $staffList = Admin::query()
            ->select(['id', 'name', 'email'])
            ->orderBy('name')
            ->get()
            ->map(fn (Admin $admin): array => [
                'id' => $admin->id,
                'name' => $admin->name,
            ])
            ->values();

        return Inertia::render('Admin/Tills/Index', [
            'filters' => [
                'preset' => $resolved['preset'],
                'from' => $resolved['from'],
                'to' => $resolved['to'],
                'status' => $resolved['status'],
                'admin_id' => $resolved['admin_id'],
                'search' => $resolved['search'],
                'timezone' => $timezone,
            ],
            'summary' => $summary,
            'openShift' => $openShift ? PosShiftController::formatShift($openShift) : null,
            'tills' => $tills,
            'staffList' => $staffList,
        ]);
    }

    /**
     * Return full chronological activity timeline and orders for a specific till shift.
     */
    public function show(PosShift $shift): JsonResponse
    {
        $timezone = config('app.timezone', 'America/Toronto');

        $shift->load([
            'admin:id,name,email',
            'closedByAdmin:id,name,email',
            'cashMovements' => fn ($query) => $query->reorder('created_at')->orderBy('id')->with('admin:id,name,email'),
        ])->loadCount([
            'orders as split_orders_count' => fn (Builder $q) => $q
                ->where('payment_method', 'split')
                ->where('status', '!=', 'pending'),
        ]);

        $orders = $shift->orders()
            ->select([
                'id',
                'order_number',
                'status',
                'customer_name',
                'payment_method',
                'cash_amount',
                'terminal_amount',
                'cash_tendered',
                'change_due',
                'total',
                'refunded_total',
                'created_at',
            ])
            ->where('status', '!=', 'pending')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $timeline = $this->buildTimeline($shift, $timezone);

        $formattedOrders = $orders->map(function (Order $order) use ($timezone): array {
            $createdLocal = $order->created_at?->copy()->timezone($timezone);
            $total = round((float) $order->total, 2);
            $refunded = min($total, round((float) ($order->refunded_total ?? 0), 2));

            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'customer_name' => $order->customer_name ?: 'Walk-in Customer',
                'payment_method' => $order->payment_method,
                'cash_amount' => (float) ($order->cash_amount ?? 0),
                'terminal_amount' => (float) ($order->terminal_amount ?? 0),
                'cash_tendered' => $order->cash_tendered !== null ? (float) $order->cash_tendered : null,
                'change_due' => $order->change_due !== null ? (float) $order->change_due : null,
                'total' => $total,
                'refunded_total' => $refunded,
                'net_amount' => round(max(0.0, $total - $refunded), 2),
                'time_label' => $createdLocal?->format('g:i A'),
                'date_time_label' => $createdLocal?->format('M j, g:i A'),
            ];
        })->values()->all();

        return response()->json([
            'till' => PosShiftController::formatShift($shift),
            'timeline' => $timeline,
            'orders' => $formattedOrders,
        ]);
    }

    /**
     * Allow an authorized manager/super admin to close an open till shift safely from Admin.
     *
     * @throws ValidationException
     */
    public function forceClose(Request $request, PosShift $shift): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'counted_cash' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'counted_terminal' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var Admin $manager */
        $manager = $request->user('admin');
        $countedTerminal = isset($validated['counted_terminal']) && $validated['counted_terminal'] !== ''
            ? (float) $validated['counted_terminal']
            : null;

        $note = trim((string) ($validated['notes'] ?? ''));
        $closingNote = $note !== ''
            ? "[Closed via Admin by {$manager->name}] {$note}"
            : "[Closed via Admin by {$manager->name}]";

        $closedShift = DB::transaction(function () use ($shift, $manager, $validated, $countedTerminal, $closingNote): PosShift {
            $lockedShift = PosShift::whereKey($shift->id)->lockForUpdate()->firstOrFail();

            if ($lockedShift->status !== 'open') {
                throw ValidationException::withMessages([
                    'counted_cash' => 'This till shift is already closed.',
                ]);
            }

            $lockedShift->closeShift(
                $manager,
                (float) $validated['counted_cash'],
                $closingNote,
                $countedTerminal,
            );

            return PosShift::with([
                'admin:id,name,email',
                'closedByAdmin:id,name,email',
                'cashMovements.admin:id,name,email',
            ])->withCount([
                'orders as split_orders_count' => fn (Builder $q) => $q
                    ->where('payment_method', 'split')
                    ->where('status', '!=', 'pending'),
            ])->find($lockedShift->id) ?? $lockedShift;
        });

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'till' => PosShiftController::formatShift($closedShift),
            ]);
        }

        return back()->with('success', "Till #{$closedShift->id} closed and reconciled.");
    }

    /**
     * Export filtered till audit records (with opening/closing timestamps & staff entries) as CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'preset' => ['nullable', 'string', 'in:today,yesterday,7d,30d,this_month,last_month,all,custom'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', 'string', 'in:all,open,closed,variance'],
            'admin_id' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $timezone = config('app.timezone', 'America/Toronto');
        $resolved = $this->resolveFilters($validated, $timezone);

        $shifts = $this->buildFilteredQuery($resolved)
            ->with(['admin:id,name', 'closedByAdmin:id,name'])
            ->orderByDesc('opened_at')
            ->orderByDesc('id')
            ->get();

        $filename = sprintf('masala-mart-tills-%s-to-%s.csv', $resolved['from'], $resolved['to']);

        return response()->streamDownload(function () use ($shifts, $resolved, $timezone): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");

            $safe = static function (mixed $value): string {
                $str = (string) $value;
                if ($str !== '' && preg_match('/^[=+\-@\t\r]/', $str) === 1) {
                    return "'".$str;
                }

                return $str;
            };
            $num = static fn (float|int $val): string => number_format((float) $val, 2, '.', '');

            fputcsv($handle, ['Masala Mart POS Till & Shift Audit Log']);
            fputcsv($handle, ['From', $resolved['from'], 'To', $resolved['to'], 'Status Filter', strtoupper($resolved['status']), 'Timezone', $timezone]);
            fputcsv($handle, []);

            fputcsv($handle, [
                'Till #',
                'Status',
                'Opened Date',
                'Opened Time',
                'Opened By',
                'Opening Float Entered',
                'Cash Sales',
                'Coins / Deposits',
                'Cash Refunds',
                'Safe Drops / Withdrawals',
                'Expected Drawer Cash',
                'Closed Date',
                'Closed Time',
                'Closed By',
                'Closing Counted Cash Entered',
                'Cash Variance',
                'Card Terminal Sales',
                'Card Refunds',
                'Net Card Terminal',
                'Terminal Batch Slip Entered',
                'Terminal Variance',
                'Orders Count',
                'Notes',
            ]);

            foreach ($shifts as $shift) {
                $openedLocal = $shift->opened_at?->copy()->timezone($timezone);
                $closedLocal = $shift->closed_at?->copy()->timezone($timezone);

                fputcsv($handle, [
                    (int) $shift->id,
                    $safe($shift->status),
                    $openedLocal?->format('Y-m-d') ?? '',
                    $openedLocal?->format('g:i A') ?? '',
                    $safe($shift->admin?->name ?? 'Staff'),
                    $num($shift->opening_cash),
                    $num($shift->cash_sales),
                    $num($shift->cash_deposits ?? 0),
                    $num($shift->cash_refunds),
                    $num($shift->cash_withdrawals ?? 0),
                    $num($shift->expected_cash),
                    $closedLocal?->format('Y-m-d') ?? '',
                    $closedLocal?->format('g:i A') ?? '',
                    $safe($shift->closedByAdmin?->name ?? ''),
                    $shift->counted_cash !== null ? $num($shift->counted_cash) : '',
                    $shift->cash_difference !== null ? $num($shift->cash_difference) : '',
                    $num($shift->terminal_sales),
                    $num($shift->terminal_refunds),
                    $num($shift->netTerminal()),
                    $shift->counted_terminal !== null ? $num($shift->counted_terminal) : '',
                    $shift->terminal_difference !== null ? $num($shift->terminal_difference) : '',
                    (int) $shift->orders_count,
                    $safe($shift->notes ?? ''),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     preset: string,
     *     from: string,
     *     to: string,
     *     status: string,
     *     admin_id: ?int,
     *     search: string,
     *     start: CarbonImmutable,
     *     end: CarbonImmutable
     * }
     */
    private function resolveFilters(array $input, string $timezone): array
    {
        $now = CarbonImmutable::now($timezone);
        $allowedPresets = ['today', 'yesterday', '7d', '30d', 'this_month', 'last_month', 'all', 'custom'];
        $rawPreset = is_string($input['preset'] ?? null) ? $input['preset'] : '';
        $hasExplicitDates = ! empty($input['from']) && ! empty($input['to']);

        if ($rawPreset !== '' && in_array($rawPreset, $allowedPresets, true)) {
            $preset = $rawPreset;
        } elseif ($hasExplicitDates) {
            $preset = 'custom';
        } else {
            $preset = '30d';
        }

        if ($preset === 'custom' && $hasExplicitDates) {
            try {
                $start = CarbonImmutable::parse((string) $input['from'], $timezone)->startOfDay();
                $end = CarbonImmutable::parse((string) $input['to'], $timezone)->endOfDay();
                if ($start->greaterThan($end)) {
                    $tmp = $start->startOfDay();
                    $start = $end->startOfDay();
                    $end = $tmp->endOfDay();
                }
            } catch (\Throwable) {
                $start = $now->subDays(29)->startOfDay();
                $end = $now->endOfDay();
            }
        } else {
            [$start, $end] = match ($preset) {
                'today' => [$now->startOfDay(), $now->endOfDay()],
                'yesterday' => [$now->subDay()->startOfDay(), $now->subDay()->endOfDay()],
                '7d' => [$now->subDays(6)->startOfDay(), $now->endOfDay()],
                'this_month' => [$now->startOfMonth()->startOfDay(), $now->endOfDay()],
                'last_month' => [
                    $now->subMonthNoOverflow()->startOfMonth()->startOfDay(),
                    $now->subMonthNoOverflow()->endOfMonth()->endOfDay(),
                ],
                'all' => [$now->subYears(5)->startOfDay(), $now->endOfDay()],
                default => [$now->subDays(29)->startOfDay(), $now->endOfDay()],
            };
        }

        return [
            'preset' => $preset,
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
            'status' => is_string($input['status'] ?? null) ? $input['status'] : 'all',
            'admin_id' => ! empty($input['admin_id']) ? (int) $input['admin_id'] : null,
            'search' => trim((string) ($input['search'] ?? '')),
            'start' => $start,
            'end' => $end,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<PosShift>
     */
    private function buildFilteredQuery(array $filters): Builder
    {
        $query = PosShift::query()->whereBetween('opened_at', [$filters['start'], $filters['end']]);

        if ($filters['status'] === 'open') {
            $query->where('status', 'open');
        } elseif ($filters['status'] === 'closed') {
            $query->where('status', 'closed');
        } elseif ($filters['status'] === 'variance') {
            $query->where('status', 'closed')->where(function (Builder $q): void {
                $q->where('cash_difference', '>=', 0.01)
                    ->orWhere('cash_difference', '<=', -0.01)
                    ->orWhere('terminal_difference', '>=', 0.01)
                    ->orWhere('terminal_difference', '<=', -0.01);
            });
        }

        if (! empty($filters['admin_id'])) {
            $adminId = (int) $filters['admin_id'];
            $query->where(function (Builder $q) use ($adminId): void {
                $q->where('admin_id', $adminId)->orWhere('closed_by_admin_id', $adminId);
            });
        }

        if ($filters['search'] !== '') {
            $search = $filters['search'];
            $numericId = preg_replace('/[^0-9]/', '', $search);

            $query->where(function (Builder $q) use ($search, $numericId): void {
                $q->where('notes', 'like', "%{$search}%")
                    ->orWhereHas('admin', fn (Builder $aq) => $aq->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('closedByAdmin', fn (Builder $cq) => $cq->where('name', 'like', "%{$search}%"));

                if ($numericId !== '') {
                    $q->orWhere('id', (int) $numericId);
                }
            });
        }

        return $query;
    }

    /**
     * @param  Collection<int, PosShift>  $shifts
     * @return array<string, float|int>
     */
    private function summarizeTills(Collection $shifts): array
    {
        $openCount = 0;
        $closedCount = 0;
        $varianceCount = 0;
        $openingTotal = 0.0;
        $depositsTotal = 0.0;
        $withdrawalsTotal = 0.0;
        $expectedTotal = 0.0;
        $countedCashTotal = 0.0;
        $cashVarianceTotal = 0.0;
        $netTerminalTotal = 0.0;
        $terminalVarianceTotal = 0.0;
        $batchCheckedCount = 0;

        foreach ($shifts as $shift) {
            $openingTotal += (float) $shift->opening_cash;
            $depositsTotal += (float) ($shift->cash_deposits ?? 0);
            $withdrawalsTotal += (float) ($shift->cash_withdrawals ?? 0);
            $expectedTotal += (float) $shift->expected_cash;
            $netTerminalTotal += $shift->netTerminal();

            if ($shift->status === 'closed') {
                $closedCount++;
                $countedCashTotal += (float) ($shift->counted_cash ?? 0);
                $cashDiff = (float) ($shift->cash_difference ?? 0);
                $cashVarianceTotal += $cashDiff;

                $termDiff = $shift->terminal_difference !== null ? (float) $shift->terminal_difference : 0.0;
                if ($shift->counted_terminal !== null) {
                    $batchCheckedCount++;
                    $terminalVarianceTotal += $termDiff;
                }

                if (abs($cashDiff) >= 0.01 || ($shift->counted_terminal !== null && abs($termDiff) >= 0.01)) {
                    $varianceCount++;
                }
            } else {
                $openCount++;
            }
        }

        return [
            'total_tills' => $shifts->count(),
            'open_tills' => $openCount,
            'closed_tills' => $closedCount,
            'variance_tills' => $varianceCount,
            'opening_cash_total' => round($openingTotal, 2),
            'cash_deposits_total' => round($depositsTotal, 2),
            'cash_withdrawals_total' => round($withdrawalsTotal, 2),
            'expected_cash_total' => round($expectedTotal, 2),
            'counted_cash_total' => round($countedCashTotal, 2),
            'cash_variance_total' => round($cashVarianceTotal, 2),
            'net_terminal_total' => round($netTerminalTotal, 2),
            'terminal_variance_total' => round($terminalVarianceTotal, 2),
            'batch_checked_tills' => $batchCheckedCount,
        ];
    }

    /**
     * Build chronological event timeline for Till Open -> Mid-Shift Cash Movements -> Till Close.
     *
     * @return list<array<string, mixed>>
     */
    private function buildTimeline(PosShift $shift, string $timezone): array
    {
        $events = [];
        $openedLocal = $shift->opened_at?->copy()->timezone($timezone);

        $events[] = [
            'id' => "open-{$shift->id}",
            'kind' => 'opened',
            'actor_name' => $shift->admin?->name ?? 'Staff',
            'title' => 'Till Opened · Starting Float Entered',
            'subtitle' => $shift->notes && $shift->status === 'open' ? $shift->notes : 'Opening drawer cash verified',
            'amount' => round((float) $shift->opening_cash, 2),
            'balance_after' => round((float) $shift->opening_cash, 2),
            'variance' => null,
            'timestamp' => $openedLocal?->toIso8601String(),
            'time_formatted' => $openedLocal?->format('g:i A'),
            'date_time_formatted' => $openedLocal?->format('M j, Y g:i A'),
        ];

        $chronologicalMovements = $shift->cashMovements
            ->sortBy([['created_at', 'asc'], ['id', 'asc']]);

        foreach ($chronologicalMovements as $movement) {
            /** @var PosCashMovement $movement */
            $mvLocal = $movement->created_at?->copy()->timezone($timezone);
            $isDeposit = $movement->type === PosCashMovement::TYPE_DEPOSIT;

            $events[] = [
                'id' => "mv-{$movement->id}",
                'kind' => $movement->type,
                'actor_name' => $movement->admin?->name ?? 'Staff',
                'title' => ($isDeposit ? 'Cash Deposit / Coins Added' : 'Safe Drop / Cash Withdrawal')." · {$movement->reason}",
                'subtitle' => $movement->notes,
                'amount' => round((float) $movement->amount, 2),
                'balance_after' => round((float) $movement->balance_after, 2),
                'variance' => null,
                'timestamp' => $mvLocal?->toIso8601String(),
                'time_formatted' => $mvLocal?->format('g:i A'),
                'date_time_formatted' => $mvLocal?->format('M j, Y g:i A'),
            ];
        }

        if ($shift->status === 'closed') {
            $closedLocal = $shift->closed_at?->copy()->timezone($timezone);
            $events[] = [
                'id' => "close-{$shift->id}",
                'kind' => 'closed',
                'actor_name' => $shift->closedByAdmin?->name ?? ($shift->admin?->name ?? 'Staff'),
                'title' => 'Till Closed · Physical Cash Count Entered',
                'subtitle' => $shift->counted_terminal !== null
                    ? sprintf('Card Batch Slip entered: $%0.2f', (float) $shift->counted_terminal)
                    : ($shift->notes ?: 'End-of-shift reconciliation complete'),
                'amount' => round((float) ($shift->counted_cash ?? 0), 2),
                'balance_after' => round((float) $shift->expected_cash, 2),
                'variance' => $shift->cash_difference !== null ? round((float) $shift->cash_difference, 2) : null,
                'terminal_counted' => $shift->counted_terminal !== null ? round((float) $shift->counted_terminal, 2) : null,
                'terminal_variance' => $shift->terminal_difference !== null ? round((float) $shift->terminal_difference, 2) : null,
                'timestamp' => $closedLocal?->toIso8601String(),
                'time_formatted' => $closedLocal?->format('g:i A'),
                'date_time_formatted' => $closedLocal?->format('M j, Y g:i A'),
            ];
        }

        return $events;
    }
}
