<?php

namespace App\Models;

use Database\Factories\PosShiftFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class PosShift extends Model
{
    /** @use HasFactory<PosShiftFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'admin_id',
        'closed_by_admin_id',
        'status',
        'opening_cash',
        'cash_sales',
        'cash_deposits',
        'terminal_sales',
        'cash_refunds',
        'cash_withdrawals',
        'terminal_refunds',
        'counted_terminal',
        'terminal_difference',
        'expected_cash',
        'counted_cash',
        'cash_difference',
        'orders_count',
        'notes',
        'opened_at',
        'closed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opening_cash' => 'decimal:2',
            'cash_sales' => 'decimal:2',
            'cash_deposits' => 'decimal:2',
            'terminal_sales' => 'decimal:2',
            'cash_refunds' => 'decimal:2',
            'cash_withdrawals' => 'decimal:2',
            'terminal_refunds' => 'decimal:2',
            'counted_terminal' => 'decimal:2',
            'terminal_difference' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'counted_cash' => 'decimal:2',
            'cash_difference' => 'decimal:2',
            'orders_count' => 'integer',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Admin, $this>
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    /**
     * @return BelongsTo<Admin, $this>
     */
    public function closedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'closed_by_admin_id');
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'pos_shift_id');
    }

    /**
     * @return HasMany<PosCashMovement, $this>
     */
    public function cashMovements(): HasMany
    {
        return $this->hasMany(PosCashMovement::class, 'pos_shift_id')->orderByDesc('id');
    }

    /**
     * @param  Builder<PosShift>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', 'open');
    }

    /**
     * Get the currently open shift for the store register, if any.
     */
    public static function currentOpen(): ?self
    {
        return static::with(['admin:id,name,email', 'cashMovements.admin:id,name,email'])
            ->withCount([
                'orders as split_orders_count' => fn (Builder $q) => $q
                    ->where('payment_method', 'split')
                    ->where('status', '!=', 'pending'),
            ])
            ->open()
            ->latest('opened_at')
            ->first();
    }

    /**
     * Net cash sales recorded during this shift (gross cash sales less cash refunds).
     */
    public function netCashSales(): float
    {
        return round((float) $this->cash_sales - (float) $this->cash_refunds, 2);
    }

    /**
     * Net card terminal settlement for this shift (gross card sales less card refunds).
     */
    public function netTerminal(): float
    {
        return round((float) $this->terminal_sales - (float) $this->terminal_refunds, 2);
    }

    /**
     * Compute expected drawer cash from opening float, cash sales/deposits, and refunds/withdrawals.
     */
    private function calculateExpectedCash(
        float $cashSales,
        float $cashRefunds,
        float $cashDeposits,
        float $cashWithdrawals,
    ): float {
        return round(
            (float) $this->opening_cash + $cashSales + $cashDeposits - $cashRefunds - $cashWithdrawals,
            2,
        );
    }

    /**
     * Record a completed POS sale against this shift (call inside a DB transaction on a locked row).
     */
    public function recordSale(float $cashAmount, float $terminalAmount): void
    {
        $newCashSales = round((float) $this->cash_sales + max(0.0, $cashAmount), 2);
        $newTerminalSales = round((float) $this->terminal_sales + max(0.0, $terminalAmount), 2);
        $expectedCash = $this->calculateExpectedCash(
            $newCashSales,
            (float) $this->cash_refunds,
            (float) ($this->cash_deposits ?? 0),
            (float) ($this->cash_withdrawals ?? 0),
        );

        $this->update([
            'cash_sales' => $newCashSales,
            'terminal_sales' => $newTerminalSales,
            'expected_cash' => $expectedCash,
            'orders_count' => (int) $this->orders_count + 1,
        ]);
    }

    /**
     * Record a POS return/refund against this shift (call inside a DB transaction on a locked row).
     */
    public function recordRefund(float $cashRefund, float $terminalRefund): void
    {
        $newCashRefunds = round((float) $this->cash_refunds + max(0.0, $cashRefund), 2);
        $newTerminalRefunds = round((float) $this->terminal_refunds + max(0.0, $terminalRefund), 2);
        $expectedCash = $this->calculateExpectedCash(
            (float) $this->cash_sales,
            $newCashRefunds,
            (float) ($this->cash_deposits ?? 0),
            (float) ($this->cash_withdrawals ?? 0),
        );

        $this->update([
            'cash_refunds' => $newCashRefunds,
            'terminal_refunds' => $newTerminalRefunds,
            'expected_cash' => $expectedCash,
        ]);
    }

    /**
     * Record a cash drawer deposit (Pay-In / coins) or withdrawal (Safe Drop / payout) atomically.
     *
     * @throws ValidationException
     */
    public function recordCashMovement(
        Admin $cashier,
        string $type,
        float $amount,
        string $reason,
        ?string $notes = null,
    ): PosCashMovement {
        $cleanAmount = round($amount, 2);

        if ($cleanAmount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Cash drawer adjustment amount must be greater than $0.00.',
            ]);
        }

        if (! in_array($type, [PosCashMovement::TYPE_DEPOSIT, PosCashMovement::TYPE_WITHDRAWAL], true)) {
            throw ValidationException::withMessages([
                'type' => 'Invalid cash drawer adjustment type.',
            ]);
        }

        $currentExpected = round((float) $this->expected_cash, 2);

        if ($type === PosCashMovement::TYPE_WITHDRAWAL && $cleanAmount > ($currentExpected + 0.0001)) {
            throw ValidationException::withMessages([
                'amount' => sprintf(
                    'Insufficient cash in drawer ($%.2f expected) for a $%.2f withdrawal.',
                    $currentExpected,
                    $cleanAmount,
                ),
            ]);
        }

        $newDeposits = (float) ($this->cash_deposits ?? 0);
        $newWithdrawals = (float) ($this->cash_withdrawals ?? 0);

        if ($type === PosCashMovement::TYPE_DEPOSIT) {
            $newDeposits = round($newDeposits + $cleanAmount, 2);
        } else {
            $newWithdrawals = round($newWithdrawals + $cleanAmount, 2);
        }

        $expectedCash = max(
            0.0,
            $this->calculateExpectedCash(
                (float) $this->cash_sales,
                (float) $this->cash_refunds,
                $newDeposits,
                $newWithdrawals,
            ),
        );

        $this->update([
            'cash_deposits' => $newDeposits,
            'cash_withdrawals' => $newWithdrawals,
            'expected_cash' => $expectedCash,
        ]);

        return $this->cashMovements()->create([
            'admin_id' => $cashier->id,
            'type' => $type,
            'amount' => $cleanAmount,
            'reason' => trim($reason),
            'notes' => $notes !== null && trim($notes) !== '' ? trim($notes) : null,
            'balance_after' => $expectedCash,
        ]);
    }

    /**
     * Close the shift and compute drawer and optional terminal variances against expected totals.
     */
    public function closeShift(
        Admin $closedBy,
        float $countedCash,
        ?string $notes = null,
        ?float $countedTerminal = null,
    ): void {
        $expected = $this->calculateExpectedCash(
            (float) $this->cash_sales,
            (float) $this->cash_refunds,
            (float) ($this->cash_deposits ?? 0),
            (float) ($this->cash_withdrawals ?? 0),
        );
        $counted = round(max(0.0, $countedCash), 2);

        $netTerminal = $this->netTerminal();
        $cleanTerminal = $countedTerminal !== null ? round(max(0.0, $countedTerminal), 2) : null;
        $terminalDiff = $cleanTerminal !== null ? round($cleanTerminal - $netTerminal, 2) : null;

        $this->update([
            'status' => 'closed',
            'closed_by_admin_id' => $closedBy->id,
            'expected_cash' => $expected,
            'counted_cash' => $counted,
            'cash_difference' => round($counted - $expected, 2),
            'counted_terminal' => $cleanTerminal,
            'terminal_difference' => $terminalDiff,
            'notes' => $notes ?? $this->notes,
            'closed_at' => now(),
        ]);
    }
}
