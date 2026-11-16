<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PosShift;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PosOrderService
{
    /**
     * Relations eager-loaded on returned POS orders.
     *
     * @var list<string>
     */
    private const ORDER_RELATIONS = [
        'items',
        'admin:id,name,email',
        'user:id,name,email,phone,reward_points',
        'posShift.admin:id,name,email',
        'posShift.cashMovements.admin:id,name,email',
    ];

    /**
     * Create and finalize an in-store POS sale atomically inside a database transaction.
     *
     * @param  array<string, mixed>  $validated
     *
     * @throws ValidationException
     */
    public function createOrder(Admin $cashier, array $validated): Order
    {
        $idempotencyKey = trim((string) ($validated['idempotency_key'] ?? ''));
        $idempotencyKey = $idempotencyKey !== '' ? $idempotencyKey : null;

        try {
            return DB::transaction(function () use ($cashier, $validated, $idempotencyKey): Order {
                if ($idempotencyKey !== null) {
                    $existingOrder = Order::with(self::ORDER_RELATIONS)
                        ->where('idempotency_key', $idempotencyKey)
                        ->lockForUpdate()
                        ->first();

                    if ($existingOrder) {
                        return $existingOrder;
                    }
                }

                $shift = PosShift::open()->latest('opened_at')->lockForUpdate()->first();

                if (! $shift) {
                    throw ValidationException::withMessages([
                        'shift' => 'Please open a register shift before ringing sales.',
                    ]);
                }

                $rawItems = (array) ($validated['items'] ?? []);
                $productIds = collect($rawItems)
                    ->pluck('product_id')
                    ->filter(fn ($id): bool => $id !== null && $id !== '')
                    ->map(fn ($id): int => (int) $id)
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();

                $lockedProducts = $productIds !== []
                    ? Product::whereIn('id', $productIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id')
                    : collect();

                $preparedItems = [];
                $stockDeductionsByProduct = [];
                $subtotal = 0.0;

                foreach ($rawItems as $rawItem) {
                    $quantity = max(1, (int) ($rawItem['quantity'] ?? 1));
                    $productId = ! empty($rawItem['product_id']) ? (int) $rawItem['product_id'] : null;

                    if ($productId !== null) {
                        /** @var ?Product $product */
                        $product = $lockedProducts->get($productId);

                        if (! $product) {
                            throw ValidationException::withMessages([
                                'items' => 'One of the scanned products is no longer available.',
                            ]);
                        }

                        $unitType = in_array($product->unit_type, ['each', 'lb', 'kg'], true)
                            ? (string) $product->unit_type
                            : 'each';
                        $unitPrice = round((float) $product->price, 2);
                        $isTaxable = (bool) $product->is_taxable;

                        if ($unitType !== 'each') {
                            $weight = isset($rawItem['weight']) ? round((float) $rawItem['weight'], 3) : 0.0;

                            if ($weight <= 0) {
                                throw ValidationException::withMessages([
                                    'items' => "Enter a valid scale weight ({$unitType}) for '{$product->name}'.",
                                ]);
                            }

                            $lineSubtotal = round($unitPrice * $weight * $quantity, 2);
                            $stockUnits = max(1, (int) round($weight * $quantity));
                            $formattedWeight = rtrim(rtrim(number_format($weight, 3, '.', ''), '0'), '.');
                            $sizeLabel = "{$formattedWeight} {$unitType}";
                        } else {
                            $weight = null;
                            $lineSubtotal = round($unitPrice * $quantity, 2);
                            $stockUnits = $quantity;
                            $sizeLabel = $product->size_main ?: 'Each';
                        }

                        $stockDeductionsByProduct[$product->id] = ($stockDeductionsByProduct[$product->id] ?? 0) + $stockUnits;

                        $preparedItems[] = [
                            'product_id' => $product->id,
                            'name' => $product->name,
                            'size' => $sizeLabel,
                            'unit_type' => $unitType,
                            'unit_price' => $unitPrice,
                            'quantity' => $quantity,
                            'weight' => $weight,
                            'total_price' => $lineSubtotal,
                            'is_taxable' => $isTaxable,
                            'tax_amount' => 0.00,
                            'image' => $product->image,
                        ];
                    } else {
                        $customName = trim((string) ($rawItem['name'] ?? ''));
                        $unitPrice = round((float) ($rawItem['unit_price'] ?? 0), 2);

                        if ($customName === '' || $unitPrice <= 0) {
                            throw ValidationException::withMessages([
                                'items' => 'Custom items require both a name and a positive price.',
                            ]);
                        }

                        $lineSubtotal = round($unitPrice * $quantity, 2);

                        $preparedItems[] = [
                            'product_id' => null,
                            'name' => $customName,
                            'size' => 'Custom Item',
                            'unit_type' => 'each',
                            'unit_price' => $unitPrice,
                            'quantity' => $quantity,
                            'weight' => null,
                            'total_price' => $lineSubtotal,
                            'is_taxable' => (bool) ($rawItem['is_taxable'] ?? false),
                            'tax_amount' => 0.00,
                            'image' => null,
                        ];
                    }

                    $subtotal = round($subtotal + $lineSubtotal, 2);
                }

                $discountType = (string) ($validated['discount_type'] ?? 'none');
                $discountValue = max(0.0, (float) ($validated['discount_value'] ?? 0));

                if ($discountType === 'percent') {
                    if ($discountValue > 100) {
                        throw ValidationException::withMessages([
                            'discount_value' => 'Percentage discount cannot exceed 100%.',
                        ]);
                    }
                    $manualDiscount = round($subtotal * ($discountValue / 100.0), 2);
                } elseif ($discountType === 'fixed') {
                    $manualDiscount = min($subtotal, round($discountValue, 2));
                } else {
                    $manualDiscount = 0.0;
                }

                $customer = null;
                if (! empty($validated['customer_id'])) {
                    $customer = User::whereKey((int) $validated['customer_id'])->lockForUpdate()->first();
                }

                $pointsToRedeem = max(0, (int) ($validated['points_to_redeem'] ?? 0));
                $pointsDiscount = 0.0;

                if ($pointsToRedeem > 0) {
                    if (! $customer) {
                        throw ValidationException::withMessages([
                            'points_to_redeem' => 'Attach a loyalty customer before redeeming reward points.',
                        ]);
                    }

                    $pointsPerBlock = max(1, (int) config('pos.loyalty.points_per_block', 500));
                    $discountPerBlock = max(0.01, (float) config('pos.loyalty.discount_per_block', 10.00));

                    if ($pointsToRedeem % $pointsPerBlock !== 0) {
                        throw ValidationException::withMessages([
                            'points_to_redeem' => "Reward points must be redeemed in increments of {$pointsPerBlock}.",
                        ]);
                    }

                    if ($pointsToRedeem > (int) $customer->reward_points) {
                        throw ValidationException::withMessages([
                            'points_to_redeem' => 'Customer does not have enough reward points for this redemption.',
                        ]);
                    }

                    $remainingSubtotalAfterManual = max(0.0, round($subtotal - $manualDiscount, 2));
                    $requestedBlocks = intdiv($pointsToRedeem, $pointsPerBlock);
                    $maxUsefulBlocks = $remainingSubtotalAfterManual >= $discountPerBlock
                        ? (int) floor(($remainingSubtotalAfterManual + 0.0001) / $discountPerBlock)
                        : 0;
                    $effectiveBlocks = min($requestedBlocks, $maxUsefulBlocks);

                    $pointsToRedeem = $effectiveBlocks * $pointsPerBlock;
                    $pointsDiscount = round($effectiveBlocks * $discountPerBlock, 2);
                }

                $storeSettings = StoreSetting::current();
                $taxRate = max(0.0, (float) ($storeSettings->tax_rate ?? 0));
                $totalDiscount = min($subtotal, round($manualDiscount + $pointsDiscount, 2));
                $discountRatio = $subtotal > 0 ? ($totalDiscount / $subtotal) : 0.0;

                foreach ($preparedItems as $index => $preparedItem) {
                    if ($preparedItem['is_taxable'] && $taxRate > 0) {
                        $discountedLineBase = max(0.0, (float) $preparedItem['total_price'] * (1.0 - $discountRatio));
                        $preparedItems[$index]['tax_amount'] = round($discountedLineBase * ($taxRate / 100.0), 2);
                    }
                }

                $tax = round(array_sum(array_column($preparedItems, 'tax_amount')), 2);
                $total = max(0.0, round($subtotal - $manualDiscount - $pointsDiscount + $tax, 2));

                if (
                    array_key_exists('expected_total', $validated)
                    && $validated['expected_total'] !== null
                    && $validated['expected_total'] !== ''
                ) {
                    $expectedTotal = round((float) $validated['expected_total'], 2);

                    if (abs($expectedTotal - $total) > 0.0001) {
                        throw ValidationException::withMessages([
                            'total' => sprintf(
                                'Store prices or tax changed while ringing up (screen total $%.2f vs current $%.2f). Review the updated cart total before charging.',
                                $expectedTotal,
                                $total,
                            ),
                        ]);
                    }
                }

                $paymentMethod = (string) $validated['payment_method'];

                if ($paymentMethod === 'cash') {
                    $cashAmount = $total;
                    $terminalAmount = 0.00;
                    $cashTendered = array_key_exists('cash_tendered', $validated) && $validated['cash_tendered'] !== null && $validated['cash_tendered'] !== ''
                        ? round((float) $validated['cash_tendered'], 2)
                        : $total;

                    if ($cashTendered + 0.0001 < $cashAmount) {
                        throw ValidationException::withMessages([
                            'cash_tendered' => 'Cash tendered must be at least the order total.',
                        ]);
                    }

                    $changeDue = max(0.0, round($cashTendered - $cashAmount, 2));
                } elseif ($paymentMethod === 'pos_terminal') {
                    $cashAmount = 0.00;
                    $terminalAmount = $total;
                    $cashTendered = null;
                    $changeDue = 0.00;
                } else {
                    $cashAmount = round((float) ($validated['cash_amount'] ?? 0), 2);
                    $terminalAmount = round((float) ($validated['terminal_amount'] ?? 0), 2);

                    if ($cashAmount <= 0 || $terminalAmount <= 0) {
                        throw ValidationException::withMessages([
                            'payment_method' => 'Split tender requires both a positive cash amount and terminal amount.',
                        ]);
                    }

                    if (abs(round($cashAmount + $terminalAmount, 2) - $total) > 0.0001) {
                        throw ValidationException::withMessages([
                            'payment_method' => sprintf(
                                'Split amounts ($%.2f cash + $%.2f terminal) must equal the order total ($%.2f).',
                                $cashAmount,
                                $terminalAmount,
                                $total,
                            ),
                        ]);
                    }

                    $cashTendered = array_key_exists('cash_tendered', $validated) && $validated['cash_tendered'] !== null && $validated['cash_tendered'] !== ''
                        ? round((float) $validated['cash_tendered'], 2)
                        : $cashAmount;

                    if ($cashTendered + 0.0001 < $cashAmount) {
                        throw ValidationException::withMessages([
                            'cash_tendered' => 'Cash tendered must cover at least the cash portion of the split payment.',
                        ]);
                    }

                    $changeDue = max(0.0, round($cashTendered - $cashAmount, 2));
                }

                foreach ($stockDeductionsByProduct as $productId => $unitsToDeduct) {
                    /** @var Product $lockedProduct */
                    $lockedProduct = $lockedProducts->get($productId);
                    $lockedProduct->decrementStock($unitsToDeduct, allowNegative: true);
                }

                $customerName = $customer?->name ?? (trim((string) ($validated['customer_name'] ?? '')) ?: 'Walk-in Customer');
                $customerEmail = $customer && ! str_ends_with((string) $customer->email, '@pos.masalamart.local')
                    ? $customer->email
                    : null;
                $customerPhone = $customer?->phone ?? (trim((string) ($validated['customer_phone'] ?? '')) ?: null);

                $order = Order::create([
                    'idempotency_key' => $idempotencyKey,
                    'channel' => 'pos',
                    'user_id' => $customer?->id,
                    'admin_id' => $cashier->id,
                    'pos_shift_id' => $shift->id,
                    'customer_name' => $customerName,
                    'customer_email' => $customerEmail,
                    'customer_phone' => $customerPhone,
                    'subtotal' => $subtotal,
                    'discount' => $manualDiscount,
                    'tax' => $tax,
                    'delivery_fee' => 0.00,
                    'total' => $total,
                    'refunded_total' => 0.00,
                    'points_redeemed' => $pointsToRedeem,
                    'points_discount' => $pointsDiscount,
                    'payment_method' => $paymentMethod,
                    'cash_amount' => $cashAmount,
                    'terminal_amount' => $terminalAmount,
                    'cash_tendered' => $cashTendered,
                    'change_due' => $changeDue,
                    'payment_reference' => ! empty($validated['payment_reference']) ? trim((string) $validated['payment_reference']) : null,
                    'fulfillment_type' => 'In-Store POS',
                    'pickup_slot' => 'Counter Sale',
                    'pickup_location' => $storeSettings->name ?: 'In-Store Register',
                    'status' => 'completed',
                    'notes' => ! empty($validated['notes']) ? trim((string) $validated['notes']) : null,
                    'reserved_stock' => $stockDeductionsByProduct,
                ]);

                foreach ($preparedItems as $preparedItem) {
                    $order->items()->create([
                        ...$preparedItem,
                        'refunded_quantity' => 0,
                        'restocked_quantity' => 0,
                        'refunded_amount' => 0.00,
                        'is_subscribed' => false,
                        'is_packed' => true,
                    ]);
                }

                $shift->recordSale($cashAmount, $terminalAmount);

                if ($customer) {
                    $customer->refresh();
                }

                return $order->load(self::ORDER_RELATIONS);
            });
        } catch (UniqueConstraintViolationException $e) {
            if ($idempotencyKey !== null) {
                $existingOrder = Order::with(self::ORDER_RELATIONS)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existingOrder) {
                    return $existingOrder;
                }
            }

            throw $e;
        }
    }

    /**
     * Process a partial or full POS return, optionally restocking returned items or marking them as waste.
     *
     * @param  array<string, mixed>  $validated
     * @return array{order: Order, shift: ?PosShift, refund_amount: float, restocked_product_ids: list<int>}
     *
     * @throws ValidationException
     */
    public function refundOrderItems(Admin $cashier, Order $order, array $validated): array
    {
        return DB::transaction(function () use ($order, $validated): array {
            $lockedOrder = Order::with('items')->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (($lockedOrder->channel ?? 'online') !== 'pos') {
                throw ValidationException::withMessages([
                    'order' => 'Online orders must be refunded from Admin Orders so Stripe and shipping totals stay reconciled.',
                ]);
            }

            if ($lockedOrder->status === 'pending') {
                throw ValidationException::withMessages([
                    'order' => 'Unpaid pending orders cannot be refunded.',
                ]);
            }

            $activeUnitsBefore = $lockedOrder->items->sum(
                fn (OrderItem $i): int => max(0, (int) $i->quantity - (int) ($i->refunded_quantity ?? 0)),
            );

            if ($lockedOrder->status === 'cancelled' || $activeUnitsBefore <= 0) {
                throw ValidationException::withMessages([
                    'order' => 'This order has already been cancelled or fully refunded.',
                ]);
            }

            $refundMethod = (string) $validated['refund_method'];
            $reasonBase = trim((string) ($validated['reason'] ?? 'Customer return')) ?: 'Customer return';

            $openShift = PosShift::open()->latest('opened_at')->lockForUpdate()->first();

            if (! $openShift) {
                throw ValidationException::withMessages([
                    'shift' => 'Please open a register shift before issuing a refund.',
                ]);
            }

            $itemsById = $lockedOrder->items->keyBy('id');
            $requestedLines = [];
            $productIdsToLock = [];
            $totalUnitsReturning = 0;

            foreach ((array) $validated['items'] as $line) {
                $itemId = (int) ($line['order_item_id'] ?? 0);
                $qty = (int) ($line['quantity'] ?? 0);
                $restock = (bool) ($line['restock'] ?? true);

                /** @var ?OrderItem $orderItem */
                $orderItem = $itemsById->get($itemId);

                if (! $orderItem) {
                    throw ValidationException::withMessages([
                        'items' => 'Selected item does not belong to this order.',
                    ]);
                }

                if (isset($requestedLines[$itemId])) {
                    throw ValidationException::withMessages([
                        'items' => "Duplicate return entry for '{$orderItem->name}'.",
                    ]);
                }

                $alreadyRefundedQty = (int) ($orderItem->refunded_quantity ?? 0);
                $remainingQty = max(0, (int) $orderItem->quantity - $alreadyRefundedQty);

                if ($qty < 1 || $qty > $remainingQty) {
                    throw ValidationException::withMessages([
                        'items' => "Cannot return {$qty} of '{$orderItem->name}' (only {$remainingQty} refundable).",
                    ]);
                }

                if ($restock && $orderItem->product_id !== null) {
                    $productIdsToLock[] = (int) $orderItem->product_id;
                }

                $requestedLines[$itemId] = [
                    'item' => $orderItem,
                    'quantity' => $qty,
                    'restock' => $restock,
                    'already_refunded_qty' => $alreadyRefundedQty,
                    'remaining_qty' => $remainingQty,
                ];
                $totalUnitsReturning += $qty;
            }

            $productIdsToLock = array_values(array_unique($productIdsToLock));
            sort($productIdsToLock);

            $lockedProducts = $productIdsToLock !== []
                ? Product::whereIn('id', $productIdsToLock)->orderBy('id')->lockForUpdate()->get()->keyBy('id')
                : collect();

            $orderSubtotal = (float) $lockedOrder->subtotal;
            $orderTotalDiscount = min(
                $orderSubtotal,
                (float) ($lockedOrder->discount ?? 0) + (float) ($lockedOrder->points_discount ?? 0),
            );
            $discountRatio = $orderSubtotal > 0 ? ($orderTotalDiscount / $orderSubtotal) : 0.0;

            $remainingOrderBalance = max(
                0.0,
                round((float) $lockedOrder->total - (float) ($lockedOrder->refunded_total ?? 0), 2),
            );
            $willRefundEntireOrder = ($activeUnitsBefore - $totalUnitsReturning) <= 0;

            $lineRefundAmounts = [];
            $summedLineRefunds = 0.0;

            foreach ($requestedLines as $itemId => $entry) {
                /** @var OrderItem $orderItem */
                $orderItem = $entry['item'];
                $qtyToRefund = $entry['quantity'];
                $remainingQty = $entry['remaining_qty'];

                $lineFullNetPlusTax = round(
                    (float) $orderItem->total_price * (1.0 - $discountRatio) + (float) ($orderItem->tax_amount ?? 0),
                    2,
                );
                $alreadyRefundedAmount = (float) ($orderItem->refunded_amount ?? 0);
                $remainingItemBalance = max(0.0, round($lineFullNetPlusTax - $alreadyRefundedAmount, 2));

                if ($qtyToRefund >= $remainingQty) {
                    $lineRefund = $remainingItemBalance;
                } else {
                    $perUnitValue = $lineFullNetPlusTax / max(1, (int) $orderItem->quantity);
                    $lineRefund = min($remainingItemBalance, round($perUnitValue * $qtyToRefund, 2));
                }

                $lineRefundAmounts[$itemId] = $lineRefund;
                $summedLineRefunds = round($summedLineRefunds + $lineRefund, 2);
            }

            $totalRefundAmount = $willRefundEntireOrder
                ? $remainingOrderBalance
                : min($remainingOrderBalance, $summedLineRefunds);

            if ($refundMethod === 'cash' && $totalRefundAmount > ((float) $openShift->expected_cash + 0.0001)) {
                throw ValidationException::withMessages([
                    'refund_method' => sprintf(
                        'Insufficient cash in drawer ($%.2f expected) for a $%.2f cash refund. Refund via Card Terminal instead.',
                        (float) $openShift->expected_cash,
                        $totalRefundAmount,
                    ),
                ]);
            }

            if ($willRefundEntireOrder && $lineRefundAmounts !== []) {
                $drift = round($totalRefundAmount - $summedLineRefunds, 2);
                if ($drift > 0.0001) {
                    $lastItemId = array_key_last($lineRefundAmounts);
                    $lineRefundAmounts[$lastItemId] = round($lineRefundAmounts[$lastItemId] + $drift, 2);
                } elseif ($drift < -0.0001) {
                    $toSubtract = round(abs($drift), 2);
                    foreach (array_reverse(array_keys($lineRefundAmounts)) as $itemId) {
                        if ($toSubtract <= 0.0001) {
                            break;
                        }
                        $deduct = min($lineRefundAmounts[$itemId], $toSubtract);
                        $lineRefundAmounts[$itemId] = round($lineRefundAmounts[$itemId] - $deduct, 2);
                        $toSubtract = round($toSubtract - $deduct, 2);
                    }
                }
            }

            $reservedStock = is_array($lockedOrder->reserved_stock) ? $lockedOrder->reserved_stock : [];
            $restockedProductIds = [];

            foreach ($requestedLines as $itemId => $entry) {
                /** @var OrderItem $orderItem */
                $orderItem = $entry['item'];
                $qtyToRefund = $entry['quantity'];
                $restock = $entry['restock'];
                $lineRefund = $lineRefundAmounts[$itemId];

                $unitsRestocked = 0;

                if ($restock && $orderItem->product_id !== null) {
                    /** @var ?Product $product */
                    $product = $lockedProducts->get((int) $orderItem->product_id);

                    if ($product) {
                        $unitsRestocked = in_array($orderItem->unit_type, ['lb', 'kg'], true) && (float) ($orderItem->weight ?? 0) > 0
                            ? max(1, (int) round((float) $orderItem->weight * $qtyToRefund))
                            : $qtyToRefund;

                        $product->incrementStock($unitsRestocked);
                        $restockedProductIds[] = $product->id;

                        if (isset($reservedStock[$product->id])) {
                            $reservedStock[$product->id] = max(0, (int) $reservedStock[$product->id] - $unitsRestocked);
                        }
                    }
                }

                $dispositionTag = $restock ? 'Restocked' : 'Damaged/Waste';
                $newRefundedQty = $entry['already_refunded_qty'] + $qtyToRefund;

                $orderItem->update([
                    'refunded_quantity' => $newRefundedQty,
                    'restocked_quantity' => (int) ($orderItem->restocked_quantity ?? 0) + $unitsRestocked,
                    'refunded_amount' => round((float) ($orderItem->refunded_amount ?? 0) + $lineRefund, 2),
                    'refund_reason' => "{$reasonBase} ({$dispositionTag})",
                ]);
            }

            $newOrderRefundedTotal = $willRefundEntireOrder
                ? (float) $lockedOrder->total
                : round((float) ($lockedOrder->refunded_total ?? 0) + $totalRefundAmount, 2);

            $refundSummaryNote = sprintf(
                '[POS Refund $%.2f via %s: %s]',
                $totalRefundAmount,
                $refundMethod === 'cash' ? 'Cash' : 'Terminal',
                $reasonBase,
            );

            $lockedOrder->update([
                'refunded_total' => $newOrderRefundedTotal,
                'status' => $willRefundEntireOrder ? 'cancelled' : $lockedOrder->status,
                'reserved_stock' => $reservedStock,
                'notes' => trim(($lockedOrder->notes ?? '').' '.$refundSummaryNote),
            ]);

            if ($openShift && $totalRefundAmount > 0) {
                $cashRefund = $refundMethod === 'cash' ? $totalRefundAmount : 0.0;
                $terminalRefund = $refundMethod === 'pos_terminal' ? $totalRefundAmount : 0.0;
                $openShift->recordRefund($cashRefund, $terminalRefund);
                $openShift->load(['admin:id,name,email', 'cashMovements.admin:id,name,email']);
            }

            return [
                'order' => $lockedOrder->fresh(['items', 'admin:id,name,email', 'user:id,name,email,phone,reward_points']) ?? $lockedOrder,
                'shift' => $openShift,
                'refund_amount' => $totalRefundAmount,
                'restocked_product_ids' => array_values(array_unique($restockedProductIds)),
            ];
        });
    }

    /**
     * Format an Order with its items and cashier metadata for POS Register / Receipt / Return views.
     *
     * @return array<string, mixed>
     */
    public static function formatOrder(Order $order): array
    {
        $order->loadMissing(['items', 'admin:id,name,email', 'user:id,name,email,phone,reward_points']);

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'channel' => $order->channel ?? 'online',
            'status' => $order->status,
            'cashier_name' => $order->admin?->name ?? 'Online Storefront',
            'customer_id' => $order->user_id,
            'customer_name' => $order->customer_name ?: 'Walk-in Customer',
            'customer_email' => $order->customer_email,
            'customer_phone' => $order->customer_phone,
            'customer_points_balance' => $order->user ? (int) $order->user->reward_points : null,
            'subtotal' => (float) $order->subtotal,
            'discount' => (float) ($order->discount ?? 0),
            'points_redeemed' => (int) ($order->points_redeemed ?? 0),
            'points_discount' => (float) ($order->points_discount ?? 0),
            'tax' => (float) ($order->tax ?? 0),
            'delivery_fee' => (float) ($order->delivery_fee ?? 0),
            'total' => (float) $order->total,
            'refunded_total' => (float) ($order->refunded_total ?? 0),
            'refundable_balance' => max(0.0, round((float) $order->total - (float) ($order->refunded_total ?? 0), 2)),
            'points_earned' => (int) ($order->points_earned ?? 0),
            'payment_method' => $order->payment_method,
            'cash_amount' => (float) ($order->cash_amount ?? 0),
            'terminal_amount' => (float) ($order->terminal_amount ?? 0),
            'cash_tendered' => $order->cash_tendered !== null ? (float) $order->cash_tendered : null,
            'change_due' => (float) ($order->change_due ?? 0),
            'payment_reference' => $order->payment_reference,
            'fulfillment_type' => $order->fulfillment_type,
            'notes' => $order->notes,
            'created_at' => $order->created_at?->toIso8601String(),
            'created_at_formatted' => $order->created_at?->format('M j, Y · g:i A'),
            'items' => $order->items->map(static function (OrderItem $item): array {
                $qty = (int) $item->quantity;
                $refundedQty = (int) ($item->refunded_quantity ?? 0);

                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'name' => $item->name,
                    'size' => $item->size,
                    'unit_type' => $item->unit_type ?? 'each',
                    'weight' => $item->weight !== null ? (float) $item->weight : null,
                    'unit_price' => (float) $item->unit_price,
                    'quantity' => $qty,
                    'refunded_quantity' => $refundedQty,
                    'remaining_quantity' => max(0, $qty - $refundedQty),
                    'restocked_quantity' => (int) ($item->restocked_quantity ?? 0),
                    'total_price' => (float) $item->total_price,
                    'is_taxable' => (bool) $item->is_taxable,
                    'tax_amount' => (float) ($item->tax_amount ?? 0),
                    'refunded_amount' => (float) ($item->refunded_amount ?? 0),
                    'refund_reason' => $item->refund_reason,
                    'image' => $item->image,
                ];
            })->values()->all(),
        ];
    }
}
