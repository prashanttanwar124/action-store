<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PaymentAnalyticsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminAnalyticsController extends Controller
{
    public function __construct(public PaymentAnalyticsService $analytics) {}

    /**
     * Render the Payment & Revenue Analytics page.
     */
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'preset' => ['nullable', 'string', 'in:today,yesterday,7d,30d,this_month,last_month,custom'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'group_by' => ['nullable', 'string', 'in:day,week,month'],
        ]);

        return Inertia::render('Admin/Analytics/Index', [
            'report' => $this->analytics->buildReport($validated),
        ]);
    }

    /**
     * Export the filtered Payment, Time-Series, and Shift Closing report as UTF-8 CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'preset' => ['nullable', 'string', 'in:today,yesterday,7d,30d,this_month,last_month,custom'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'group_by' => ['nullable', 'string', 'in:day,week,month'],
        ]);

        $report = $this->analytics->buildReport($validated);
        $filters = $report['filters'];
        $summary = $report['summary'];
        $filename = sprintf(
            'masala-mart-payments-%s-to-%s-%s.csv',
            $filters['from'],
            $filters['to'],
            $filters['group_by'],
        );

        return response()->streamDownload(function () use ($report, $filters, $summary): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // UTF-8 BOM for Excel compatibility
            fwrite($handle, "\xEF\xBB\xBF");

            $safe = static function (mixed $value): string {
                $str = (string) $value;
                if ($str !== '' && preg_match('/^[=+\-@\t\r]/', $str) === 1) {
                    return "'".$str;
                }

                return $str;
            };
            $num = static fn (float|int $val): string => number_format((float) $val, 2, '.', '');

            fputcsv($handle, ['Masala Mart Payment & Reconciliation Export']);
            fputcsv($handle, ['From', $filters['from'], 'To', $filters['to'], 'Grouping', strtoupper($filters['group_by']), 'Timezone', $filters['timezone']]);
            fputcsv($handle, []);

            fputcsv($handle, ['Channel Summary', 'Gross Sales', 'Refunds', 'Net Revenue']);
            fputcsv($handle, ['POS Cash Drawer', $num($summary['pos_cash_gross']), $num($summary['pos_cash_refunds']), $num($summary['pos_cash_net'])]);
            fputcsv($handle, ['POS Card Terminal', $num($summary['pos_card_gross']), $num($summary['pos_card_refunds']), $num($summary['pos_card_net'])]);
            fputcsv($handle, ['Website Online (Stripe)', $num($summary['online_gross']), $num($summary['online_refunds']), $num($summary['online_net'])]);
            fputcsv($handle, ['Grand Total', $num($summary['total_gross']), $num($summary['total_refunds']), $num($summary['total_net'])]);
            fputcsv($handle, ['Tax Collected', $num($summary['tax_collected']), 'Delivery Fees', $num($summary['delivery_fees_collected']), 'Discounts / Points', $num($summary['discounts_total'])]);
            fputcsv($handle, []);

            fputcsv($handle, [
                'Period',
                'Start Date',
                'End Date',
                'Orders',
                'POS Cash Gross',
                'POS Cash Refunds',
                'POS Cash Net',
                'POS Card Gross',
                'POS Card Refunds',
                'POS Card Net',
                'Online Gross',
                'Online Refunds',
                'Online Net',
                'Total Net Revenue',
            ]);

            foreach ($report['series'] as $row) {
                fputcsv($handle, [
                    $safe($row['label']),
                    $row['start_date'],
                    $row['end_date'],
                    (int) $row['orders'],
                    $num($row['pos_cash_gross']),
                    $num($row['pos_cash_refunds']),
                    $num($row['pos_cash_net']),
                    $num($row['pos_card_gross']),
                    $num($row['pos_card_refunds']),
                    $num($row['pos_card_net']),
                    $num($row['online_gross']),
                    $num($row['online_refunds']),
                    $num($row['online_net']),
                    $num($row['total_net']),
                ]);
            }

            fputcsv($handle, []);
            fputcsv($handle, [
                'Shift ID',
                'Status',
                'Opened At',
                'Closed At',
                'Opened By',
                'Closed By',
                'Opening Float',
                'Cash Sales',
                'Deposits / Coins',
                'Cash Refunds',
                'Safe Drops / Withdrawals',
                'Expected Drawer Cash',
                'Counted Cash',
                'Cash Variance',
                'Card Sales',
                'Card Refunds',
                'Net Card Terminal',
                'Terminal Batch Total',
                'Terminal Variance',
            ]);

            foreach ($report['shifts'] as $shift) {
                fputcsv($handle, [
                    (int) $shift['id'],
                    $safe($shift['status']),
                    $shift['opened_at_formatted'] ?? '',
                    $shift['closed_at_formatted'] ?? '',
                    $safe($shift['opened_by']['name'] ?? 'Staff'),
                    $safe($shift['closed_by']['name'] ?? ''),
                    $num($shift['opening_cash']),
                    $num($shift['cash_sales']),
                    $num($shift['cash_deposits'] ?? 0),
                    $num($shift['cash_refunds']),
                    $num($shift['cash_withdrawals'] ?? 0),
                    $num($shift['expected_cash']),
                    $shift['counted_cash'] !== null ? $num($shift['counted_cash']) : '',
                    $shift['cash_difference'] !== null ? $num($shift['cash_difference']) : '',
                    $num($shift['terminal_sales']),
                    $num($shift['terminal_refunds']),
                    $num($shift['net_terminal']),
                    $shift['counted_terminal'] !== null ? $num($shift['counted_terminal']) : '',
                    $shift['terminal_difference'] !== null ? $num($shift['terminal_difference']) : '',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
