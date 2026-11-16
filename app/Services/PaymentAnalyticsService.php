<?php

namespace App\Services;

use App\Http\Controllers\Pos\PosShiftController;
use App\Models\Order;
use App\Models\PosShift;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class PaymentAnalyticsService
{
    public const MAX_RANGE_DAYS = 730;

    /**
     * Build the full payment, revenue, and shift reconciliation analytics report.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function buildReport(array $input = []): array
    {
        $timezone = config('app.timezone', 'America/Toronto');
        $resolved = $this->resolveFilters($input, $timezone);

        $periodData = $this->aggregatePeriod(
            $resolved['start'],
            $resolved['end'],
            $resolved['group_by'],
            $timezone,
            includeDetails: true,
        );

        $previousDays = max(1, (int) $resolved['days_count']);
        $previousEnd = $resolved['start']->subSecond();
        $previousStart = $resolved['start']->subDays($previousDays)->startOfDay();

        $previousPeriod = $this->aggregatePeriod(
            $previousStart,
            $previousEnd,
            $resolved['group_by'],
            $timezone,
            includeDetails: false,
        );

        return [
            'filters' => [
                'preset' => $resolved['preset'],
                'from' => $resolved['from'],
                'to' => $resolved['to'],
                'group_by' => $resolved['group_by'],
                'auto_group_by' => $resolved['auto_group_by'],
                'days_count' => $resolved['days_count'],
                'timezone' => $timezone,
            ],
            'summary' => $periodData['summary'],
            'comparison' => $this->buildComparison(
                $periodData['summary'],
                $previousPeriod['summary'],
                $previousDays,
            ),
            'drawer_summary' => $periodData['drawer_summary'],
            'method_breakdown' => $periodData['method_breakdown'],
            'series' => $periodData['series'],
            'shifts' => $periodData['shifts'],
            'recent_transactions' => $periodData['recent_transactions'],
            'integrity' => $periodData['integrity'],
        ];
    }

    /**
     * Build a compact today-only snapshot for the Admin Dashboard.
     *
     * @return array<string, mixed>
     */
    public function todaySnapshot(): array
    {
        $timezone = config('app.timezone', 'America/Toronto');
        $now = CarbonImmutable::now($timezone);
        $start = $now->startOfDay();
        $end = $now->endOfDay();

        $period = $this->aggregatePeriod($start, $end, 'day', $timezone, includeDetails: false);
        $openShift = PosShift::currentOpen();

        return [
            'date_label' => $now->format('M j, Y'),
            'summary' => $period['summary'],
            'drawer_summary' => $period['drawer_summary'],
            'open_shift' => $openShift ? PosShiftController::formatShift($openShift) : null,
        ];
    }

    /**
     * Normalize filter presets, custom dates, and grouping granularity.
     *
     * @param  array<string, mixed>  $input
     * @return array{
     *     preset: string,
     *     from: string,
     *     to: string,
     *     group_by: string,
     *     auto_group_by: bool,
     *     days_count: int,
     *     start: CarbonImmutable,
     *     end: CarbonImmutable
     * }
     */
    public function resolveFilters(array $input, ?string $timezone = null): array
    {
        $timezone ??= config('app.timezone', 'America/Toronto');
        $now = CarbonImmutable::now($timezone);

        $allowedPresets = ['today', 'yesterday', '7d', '30d', 'this_month', 'last_month', 'custom'];
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
            [$start, $end] = $this->parseCustomRange(
                (string) $input['from'],
                (string) $input['to'],
                $timezone,
                $now,
            );
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
                default => [$now->subDays(29)->startOfDay(), $now->endOfDay()],
            };
        }

        $daysCount = (int) $start->startOfDay()->diffInDays($end->startOfDay()) + 1;
        if ($daysCount > self::MAX_RANGE_DAYS) {
            $start = $end->subDays(self::MAX_RANGE_DAYS - 1)->startOfDay();
            $daysCount = self::MAX_RANGE_DAYS;
        }

        $rawGroup = is_string($input['group_by'] ?? null) ? $input['group_by'] : '';
        $allowedGroups = ['day', 'week', 'month'];
        $autoGroup = ! in_array($rawGroup, $allowedGroups, true);

        if (! $autoGroup) {
            $groupBy = $rawGroup;
        } elseif ($daysCount <= 35) {
            $groupBy = 'day';
        } elseif ($daysCount <= 180) {
            $groupBy = 'week';
        } else {
            $groupBy = 'month';
        }

        return [
            'preset' => $preset,
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
            'group_by' => $groupBy,
            'auto_group_by' => $autoGroup,
            'days_count' => $daysCount,
            'start' => $start,
            'end' => $end,
        ];
    }

    /**
     * Parse and clamp custom From/To date strings.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function parseCustomRange(
        string $rawFrom,
        string $rawTo,
        string $timezone,
        CarbonImmutable $fallbackNow,
    ): array {
        try {
            $from = CarbonImmutable::parse($rawFrom, $timezone)->startOfDay();
            $to = CarbonImmutable::parse($rawTo, $timezone)->endOfDay();
        } catch (\Throwable) {
            return [$fallbackNow->subDays(29)->startOfDay(), $fallbackNow->endOfDay()];
        }

        if ($from->greaterThan($to)) {
            $tmp = $from->startOfDay();
            $from = $to->startOfDay();
            $to = $tmp->endOfDay();
        }

        return [$from, $to];
    }

    /**
     * Aggregate orders, shift cash/card reconciliation, drawer movements, and series buckets.
     *
     * @return array{
     *     summary: array<string, float|int>,
     *     drawer_summary: array<string, float|int>,
     *     method_breakdown: array<int, array<string, mixed>>,
     *     series: array<int, array<string, mixed>>,
     *     shifts: array<int, array<string, mixed>>,
     *     recent_transactions: array<int, array<string, mixed>>,
     *     integrity: array<string, mixed>
     * }
     */
    private function aggregatePeriod(
        CarbonImmutable $start,
        CarbonImmutable $end,
        string $groupBy,
        string $timezone,
        bool $includeDetails,
    ): array {
        $buckets = $includeDetails
            ? $this->buildBuckets($start, $end, $groupBy)
            : [];

        $orders = Order::query()
            ->select([
                'id',
                'order_number',
                'channel',
                'status',
                'customer_name',
                'payment_method',
                'cash_amount',
                'terminal_amount',
                'subtotal',
                'discount',
                'points_discount',
                'tax',
                'delivery_fee',
                'total',
                'refunded_total',
                'stripe_payment_id',
                'pos_shift_id',
                'created_at',
            ])
            ->whereBetween('created_at', [$start, $end])
            ->where('status', '!=', 'pending')
            ->where(function ($query): void {
                $query->where('status', '!=', 'cancelled')
                    ->orWhere('refunded_total', '>', 0);
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $shiftsQuery = PosShift::query()
            ->whereBetween('opened_at', [$start, $end])
            ->orderByDesc('opened_at');

        if ($includeDetails) {
            $shiftsQuery
                ->with(['admin:id,name,email', 'closedByAdmin:id,name,email', 'cashMovements.admin:id,name,email'])
                ->withCount([
                    'orders as split_orders_count' => fn ($query) => $query
                        ->where('payment_method', 'split')
                        ->where('status', '!=', 'pending'),
                ]);
        }

        /** @var Collection<int, PosShift> $shifts */
        $shifts = $shiftsQuery->get();

        $summary = [
            'pos_cash_gross' => 0.0,
            'pos_cash_refunds' => 0.0,
            'pos_cash_net' => 0.0,
            'pos_card_gross' => 0.0,
            'pos_card_refunds' => 0.0,
            'pos_card_net' => 0.0,
            'online_gross' => 0.0,
            'online_refunds' => 0.0,
            'online_net' => 0.0,
            'total_gross' => 0.0,
            'total_refunds' => 0.0,
            'total_net' => 0.0,
            'tax_collected' => 0.0,
            'delivery_fees_collected' => 0.0,
            'discounts_total' => 0.0,
            'total_orders' => 0,
            'pos_orders' => 0,
            'online_orders' => 0,
            'pos_cash_orders' => 0,
            'pos_card_orders' => 0,
            'pos_split_orders' => 0,
            'average_order_value' => 0.0,
        ];

        /** @var array<string, array{key: string, label: string, channel: string, orders: int, gross: float, refunds: float, net: float}> $methodStats */
        $methodStats = [];

        $unshiftedPosOrdersCount = 0;
        $mismatchedSplitOrdersCount = 0;
        $onlineMissingStripeIdCount = 0;
        $recentTransactions = [];

        foreach ($orders as $order) {
            $channel = $order->channel === 'pos' ? 'pos' : 'online';
            $total = round((float) $order->total, 2);
            $refunded = min($total, round((float) ($order->refunded_total ?? 0), 2));
            $netOrderAmount = max(0.0, round($total - $refunded, 2));
            $netRatio = $total > 0 ? ($netOrderAmount / $total) : 0.0;

            $summary['total_orders']++;
            $summary['tax_collected'] += (float) ($order->tax ?? 0) * $netRatio;
            $summary['delivery_fees_collected'] += (float) ($order->delivery_fee ?? 0) * $netRatio;
            $summary['discounts_total'] += ((float) ($order->discount ?? 0) + (float) ($order->points_discount ?? 0)) * $netRatio;

            $createdLocal = $order->created_at
                ? CarbonImmutable::instance($order->created_at)->timezone($timezone)
                : $start;
            $bucketKey = $includeDetails ? $this->bucketKeyForDate($createdLocal, $groupBy) : null;

            if ($channel === 'pos') {
                $summary['pos_orders']++;
                [$cashAmount, $terminalAmount] = $this->resolvePosSplitAmounts($order, $total);

                $summary['pos_cash_gross'] += $cashAmount;
                $summary['pos_card_gross'] += $terminalAmount;

                if ($order->pos_shift_id === null) {
                    $unshiftedPosOrdersCount++;

                    // Safety fallback when legacy/unshifted POS fixtures carry refunded_total
                    if ($refunded > 0) {
                        [$fallbackCashRefund, $fallbackCardRefund] = $this->allocateProportionalRefund(
                            $refunded,
                            $cashAmount,
                            $terminalAmount,
                            (string) $order->payment_method,
                        );
                        $summary['pos_cash_refunds'] += $fallbackCashRefund;
                        $summary['pos_card_refunds'] += $fallbackCardRefund;

                        if ($bucketKey !== null && isset($buckets[$bucketKey])) {
                            $buckets[$bucketKey]['pos_cash_refunds'] += $fallbackCashRefund;
                            $buckets[$bucketKey]['pos_card_refunds'] += $fallbackCardRefund;
                        }
                    }
                }

                if ($order->payment_method === 'split') {
                    $summary['pos_split_orders']++;
                    if (abs(($cashAmount + $terminalAmount) - $total) > 0.01) {
                        $mismatchedSplitOrdersCount++;
                    }
                } elseif ($order->payment_method === 'pos_terminal') {
                    $summary['pos_card_orders']++;
                } else {
                    $summary['pos_cash_orders']++;
                }

                if ($bucketKey !== null && isset($buckets[$bucketKey])) {
                    $buckets[$bucketKey]['orders']++;
                    $buckets[$bucketKey]['pos_orders']++;
                    $buckets[$bucketKey]['pos_cash_gross'] += $cashAmount;
                    $buckets[$bucketKey]['pos_card_gross'] += $terminalAmount;
                }

                $this->recordMethodStat($methodStats, 'pos', (string) ($order->payment_method ?: 'cash'), $total, $refunded);
            } else {
                $summary['online_orders']++;
                $summary['online_gross'] += $total;
                $summary['online_refunds'] += $refunded;

                if (empty($order->stripe_payment_id)) {
                    $onlineMissingStripeIdCount++;
                }

                if ($bucketKey !== null && isset($buckets[$bucketKey])) {
                    $buckets[$bucketKey]['orders']++;
                    $buckets[$bucketKey]['online_orders']++;
                    $buckets[$bucketKey]['online_gross'] += $total;
                    $buckets[$bucketKey]['online_refunds'] += $refunded;
                }

                $this->recordMethodStat($methodStats, 'online', (string) ($order->payment_method ?: 'card'), $total, $refunded);
            }

            if ($includeDetails && count($recentTransactions) < 50) {
                [$resolvedCash, $resolvedCard] = $channel === 'pos'
                    ? $this->resolvePosSplitAmounts($order, $total)
                    : [0.0, 0.0];

                $recentTransactions[] = [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'channel' => $channel,
                    'payment_method' => $order->payment_method ?: ($channel === 'pos' ? 'cash' : 'card'),
                    'payment_label' => $this->methodLabel($channel, (string) ($order->payment_method ?? '')),
                    'status' => $order->status,
                    'customer_name' => $order->customer_name ?: 'Walk-In Customer',
                    'cash_amount' => $resolvedCash,
                    'terminal_amount' => $resolvedCard,
                    'total' => $total,
                    'refunded_total' => $refunded,
                    'net_amount' => $netOrderAmount,
                    'tax' => round((float) ($order->tax ?? 0), 2),
                    'pos_shift_id' => $order->pos_shift_id,
                    'stripe_payment_id' => $order->stripe_payment_id,
                    'created_at' => $createdLocal->toIso8601String(),
                    'created_at_formatted' => $createdLocal->format('M j, Y g:i A'),
                ];
            }
        }

        $drawerSummary = [
            'shifts_count' => $shifts->count(),
            'shifts_open_count' => 0,
            'shifts_closed_count' => 0,
            'opening_cash_total' => 0.0,
            'cash_deposits_total' => 0.0,
            'cash_withdrawals_total' => 0.0,
            'cash_refunds_total' => 0.0,
            'terminal_refunds_total' => 0.0,
            'expected_cash_total' => 0.0,
            'counted_cash_total' => 0.0,
            'cash_variance_total' => 0.0,
            'terminal_batch_counted_total' => 0.0,
            'terminal_variance_total' => 0.0,
            'shifts_with_terminal_count' => 0,
        ];

        $drawerFormulaMismatchCount = 0;
        $formattedShifts = [];

        foreach ($shifts as $shift) {
            $cashRefunds = round((float) $shift->cash_refunds, 2);
            $terminalRefunds = round((float) $shift->terminal_refunds, 2);

            $summary['pos_cash_refunds'] += $cashRefunds;
            $summary['pos_card_refunds'] += $terminalRefunds;

            $openedLocal = $shift->opened_at
                ? CarbonImmutable::instance($shift->opened_at)->timezone($timezone)
                : $start;
            $bucketKey = $includeDetails ? $this->bucketKeyForDate($openedLocal, $groupBy) : null;

            if ($bucketKey !== null && isset($buckets[$bucketKey])) {
                $buckets[$bucketKey]['pos_cash_refunds'] += $cashRefunds;
                $buckets[$bucketKey]['pos_card_refunds'] += $terminalRefunds;
            }

            $openingCash = round((float) $shift->opening_cash, 2);
            $cashDeposits = round((float) ($shift->cash_deposits ?? 0), 2);
            $cashWithdrawals = round((float) ($shift->cash_withdrawals ?? 0), 2);
            $expectedCash = round((float) $shift->expected_cash, 2);

            $computedExpected = round(
                $openingCash + (float) $shift->cash_sales + $cashDeposits - $cashRefunds - $cashWithdrawals,
                2,
            );
            if (abs($computedExpected - $expectedCash) > 0.01) {
                $drawerFormulaMismatchCount++;
            }

            $drawerSummary['opening_cash_total'] += $openingCash;
            $drawerSummary['cash_deposits_total'] += $cashDeposits;
            $drawerSummary['cash_withdrawals_total'] += $cashWithdrawals;
            $drawerSummary['cash_refunds_total'] += $cashRefunds;
            $drawerSummary['terminal_refunds_total'] += $terminalRefunds;
            $drawerSummary['expected_cash_total'] += $expectedCash;

            if ($shift->status === 'closed') {
                $drawerSummary['shifts_closed_count']++;
                $drawerSummary['counted_cash_total'] += round((float) ($shift->counted_cash ?? 0), 2);
                $drawerSummary['cash_variance_total'] += round((float) ($shift->cash_difference ?? 0), 2);

                if ($shift->counted_terminal !== null) {
                    $drawerSummary['shifts_with_terminal_count']++;
                    $drawerSummary['terminal_batch_counted_total'] += round((float) $shift->counted_terminal, 2);
                    $drawerSummary['terminal_variance_total'] += round((float) ($shift->terminal_difference ?? 0), 2);
                }
            } else {
                $drawerSummary['shifts_open_count']++;
            }

            if ($includeDetails) {
                $formattedShifts[] = PosShiftController::formatShift($shift);
            }
        }

        // Round and derive all Net & Total metrics consistently
        $summary['pos_cash_gross'] = round($summary['pos_cash_gross'], 2);
        $summary['pos_cash_refunds'] = round($summary['pos_cash_refunds'], 2);
        $summary['pos_cash_net'] = round($summary['pos_cash_gross'] - $summary['pos_cash_refunds'], 2);

        $summary['pos_card_gross'] = round($summary['pos_card_gross'], 2);
        $summary['pos_card_refunds'] = round($summary['pos_card_refunds'], 2);
        $summary['pos_card_net'] = round($summary['pos_card_gross'] - $summary['pos_card_refunds'], 2);

        $summary['online_gross'] = round($summary['online_gross'], 2);
        $summary['online_refunds'] = round($summary['online_refunds'], 2);
        $summary['online_net'] = round($summary['online_gross'] - $summary['online_refunds'], 2);

        $summary['total_gross'] = round(
            $summary['pos_cash_gross'] + $summary['pos_card_gross'] + $summary['online_gross'],
            2,
        );
        $summary['total_refunds'] = round(
            $summary['pos_cash_refunds'] + $summary['pos_card_refunds'] + $summary['online_refunds'],
            2,
        );
        $summary['total_net'] = round($summary['total_gross'] - $summary['total_refunds'], 2);

        $summary['tax_collected'] = round($summary['tax_collected'], 2);
        $summary['delivery_fees_collected'] = round($summary['delivery_fees_collected'], 2);
        $summary['discounts_total'] = round($summary['discounts_total'], 2);
        $summary['average_order_value'] = $summary['total_orders'] > 0
            ? round($summary['total_net'] / $summary['total_orders'], 2)
            : 0.0;

        foreach ($drawerSummary as $key => $value) {
            if (is_float($value)) {
                $drawerSummary[$key] = round($value, 2);
            }
        }

        $series = [];
        if ($includeDetails) {
            foreach ($buckets as $bucket) {
                $posCashGross = round($bucket['pos_cash_gross'], 2);
                $posCashRefunds = round($bucket['pos_cash_refunds'], 2);
                $posCashNet = round($posCashGross - $posCashRefunds, 2);

                $posCardGross = round($bucket['pos_card_gross'], 2);
                $posCardRefunds = round($bucket['pos_card_refunds'], 2);
                $posCardNet = round($posCardGross - $posCardRefunds, 2);

                $onlineGross = round($bucket['online_gross'], 2);
                $onlineRefunds = round($bucket['online_refunds'], 2);
                $onlineNet = round($onlineGross - $onlineRefunds, 2);

                $totalGross = round($posCashGross + $posCardGross + $onlineGross, 2);
                $totalRefunds = round($posCashRefunds + $posCardRefunds + $onlineRefunds, 2);

                $series[] = [
                    'key' => $bucket['key'],
                    'label' => $bucket['label'],
                    'short_label' => $bucket['short_label'],
                    'start_date' => $bucket['start_date'],
                    'end_date' => $bucket['end_date'],
                    'orders' => $bucket['orders'],
                    'pos_orders' => $bucket['pos_orders'],
                    'online_orders' => $bucket['online_orders'],
                    'pos_cash_gross' => $posCashGross,
                    'pos_cash_refunds' => $posCashRefunds,
                    'pos_cash_net' => $posCashNet,
                    'pos_card_gross' => $posCardGross,
                    'pos_card_refunds' => $posCardRefunds,
                    'pos_card_net' => $posCardNet,
                    'online_gross' => $onlineGross,
                    'online_refunds' => $onlineRefunds,
                    'online_net' => $onlineNet,
                    'total_gross' => $totalGross,
                    'total_refunds' => $totalRefunds,
                    'total_net' => round($totalGross - $totalRefunds, 2),
                ];
            }
        }

        $methodList = array_values(array_map(function (array $row): array {
            $gross = round($row['gross'], 2);
            $refunds = round($row['refunds'], 2);

            return [
                'key' => $row['key'],
                'label' => $row['label'],
                'channel' => $row['channel'],
                'orders' => $row['orders'],
                'gross' => $gross,
                'refunds' => $refunds,
                'net' => round($gross - $refunds, 2),
            ];
        }, $methodStats));

        usort($methodList, fn (array $a, array $b): int => $b['gross'] <=> $a['gross']);

        return [
            'summary' => $summary,
            'drawer_summary' => $drawerSummary,
            'method_breakdown' => $methodList,
            'series' => $series,
            'shifts' => $formattedShifts,
            'recent_transactions' => $recentTransactions,
            'integrity' => [
                'drawer_formula_mismatches' => $drawerFormulaMismatchCount,
                'mismatched_split_orders' => $mismatchedSplitOrdersCount,
                'unshifted_pos_orders' => $unshiftedPosOrdersCount,
                'online_missing_stripe_id' => $onlineMissingStripeIdCount,
                'is_healthy' => $drawerFormulaMismatchCount === 0
                    && $mismatchedSplitOrdersCount === 0
                    && $unshiftedPosOrdersCount === 0,
            ],
        ];
    }

    /**
     * Build continuous time buckets covering [$start, $end] for day, week, or month grouping.
     *
     * @return array<string, array<string, mixed>>
     */
    private function buildBuckets(CarbonImmutable $start, CarbonImmutable $end, string $groupBy): array
    {
        $buckets = [];
        $multiYear = $start->year !== $end->year;

        if ($groupBy === 'month') {
            $cursor = $start->startOfMonth()->startOfDay();
            $limit = $end->startOfMonth()->startOfDay();

            while ($cursor->lessThanOrEqualTo($limit)) {
                $key = $cursor->format('Y-m');
                $buckets[$key] = $this->emptyBucket(
                    key: $key,
                    label: $cursor->format('F Y'),
                    shortLabel: $cursor->format('M y'),
                    startDate: $cursor->toDateString(),
                    endDate: $cursor->endOfMonth()->toDateString(),
                );
                $cursor = $cursor->addMonthNoOverflow();
            }

            return $buckets;
        }

        if ($groupBy === 'week') {
            $cursor = $start->startOfWeek(CarbonInterface::MONDAY)->startOfDay();
            $limit = $end->startOfWeek(CarbonInterface::MONDAY)->startOfDay();

            while ($cursor->lessThanOrEqualTo($limit)) {
                $weekEnd = $cursor->endOfWeek(CarbonInterface::SUNDAY);
                $endFmt = $cursor->month === $weekEnd->month
                    ? ($multiYear ? 'j, Y' : 'j')
                    : ($multiYear ? 'M j, Y' : 'M j');
                $key = $cursor->format('Y-m-d');

                $buckets[$key] = $this->emptyBucket(
                    key: $key,
                    label: $cursor->format('M j').' – '.$weekEnd->format($endFmt),
                    shortLabel: $cursor->format('M j'),
                    startDate: $cursor->toDateString(),
                    endDate: $weekEnd->toDateString(),
                );
                $cursor = $cursor->addWeek();
            }

            return $buckets;
        }

        $cursor = $start->startOfDay();
        $limit = $end->startOfDay();

        while ($cursor->lessThanOrEqualTo($limit)) {
            $key = $cursor->format('Y-m-d');
            $buckets[$key] = $this->emptyBucket(
                key: $key,
                label: $multiYear ? $cursor->format('M j, Y') : $cursor->format('M j'),
                shortLabel: $cursor->format('M j'),
                startDate: $key,
                endDate: $key,
            );
            $cursor = $cursor->addDay();
        }

        return $buckets;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyBucket(
        string $key,
        string $label,
        string $shortLabel,
        string $startDate,
        string $endDate,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'short_label' => $shortLabel,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'orders' => 0,
            'pos_orders' => 0,
            'online_orders' => 0,
            'pos_cash_gross' => 0.0,
            'pos_cash_refunds' => 0.0,
            'pos_card_gross' => 0.0,
            'pos_card_refunds' => 0.0,
            'online_gross' => 0.0,
            'online_refunds' => 0.0,
        ];
    }

    private function bucketKeyForDate(CarbonImmutable $date, string $groupBy): string
    {
        return match ($groupBy) {
            'month' => $date->format('Y-m'),
            'week' => $date->startOfWeek(CarbonInterface::MONDAY)->format('Y-m-d'),
            default => $date->format('Y-m-d'),
        };
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function resolvePosSplitAmounts(Order $order, float $total): array
    {
        $cashAmount = round((float) ($order->cash_amount ?? 0), 2);
        $terminalAmount = round((float) ($order->terminal_amount ?? 0), 2);

        if ($cashAmount <= 0 && $terminalAmount <= 0 && $total > 0) {
            if ($order->payment_method === 'pos_terminal') {
                return [0.0, $total];
            }

            return [$total, 0.0];
        }

        return [$cashAmount, $terminalAmount];
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function allocateProportionalRefund(
        float $refunded,
        float $cashAmount,
        float $terminalAmount,
        string $paymentMethod,
    ): array {
        $combined = $cashAmount + $terminalAmount;
        if ($combined > 0 && $cashAmount > 0 && $terminalAmount > 0) {
            $cashShare = round($refunded * ($cashAmount / $combined), 2);

            return [$cashShare, round($refunded - $cashShare, 2)];
        }

        if ($paymentMethod === 'pos_terminal' || ($terminalAmount > 0 && $cashAmount <= 0)) {
            return [0.0, $refunded];
        }

        return [$refunded, 0.0];
    }

    /**
     * @param  array<string, array{key: string, label: string, channel: string, orders: int, gross: float, refunds: float, net: float}>  $stats
     */
    private function recordMethodStat(
        array &$stats,
        string $channel,
        string $method,
        float $total,
        float $refunded,
    ): void {
        $key = "{$channel}:{$method}";
        if (! isset($stats[$key])) {
            $stats[$key] = [
                'key' => $key,
                'label' => $this->methodLabel($channel, $method),
                'channel' => $channel,
                'orders' => 0,
                'gross' => 0.0,
                'refunds' => 0.0,
                'net' => 0.0,
            ];
        }

        $stats[$key]['orders']++;
        $stats[$key]['gross'] += $total;
        $stats[$key]['refunds'] += $refunded;
    }

    private function methodLabel(string $channel, string $method): string
    {
        if ($channel === 'pos') {
            return match ($method) {
                'pos_terminal' => 'POS Card Terminal',
                'split' => 'POS Split (Cash + Card)',
                default => 'POS Cash',
            };
        }

        return match ($method) {
            'apple-pay' => 'Online Apple Pay (Stripe)',
            'google-pay' => 'Online Google Pay (Stripe)',
            'stripe' => 'Online Stripe Checkout',
            default => 'Online Card (Stripe)',
        };
    }

    /**
     * @param  array<string, float|int>  $current
     * @param  array<string, float|int>  $previous
     * @return array<string, mixed>
     */
    private function buildComparison(array $current, array $previous, int $previousDays): array
    {
        $deltaPct = static function (float|int $curr, float|int $prev): ?float {
            $prevFloat = (float) $prev;
            if ($prevFloat == 0.0) {
                return (float) $curr > 0.0 ? 100.0 : null;
            }

            return round((((float) $curr - $prevFloat) / abs($prevFloat)) * 100, 1);
        };

        return [
            'previous_days' => $previousDays,
            'total_net_pct' => $deltaPct($current['total_net'], $previous['total_net']),
            'pos_cash_net_pct' => $deltaPct($current['pos_cash_net'], $previous['pos_cash_net']),
            'pos_card_net_pct' => $deltaPct($current['pos_card_net'], $previous['pos_card_net']),
            'online_net_pct' => $deltaPct($current['online_net'], $previous['online_net']),
            'orders_pct' => $deltaPct($current['total_orders'], $previous['total_orders']),
        ];
    }
}
