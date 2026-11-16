<script setup>
import { computed } from 'vue';

const props = defineProps({
    order: {
        type: Object,
        required: true,
    },
    storeInfo: {
        type: Object,
        default: () => ({}),
    },
    printable: {
        type: Boolean,
        default: true,
    },
});

const formatMoney = (val) => `$${Number(val || 0).toFixed(2)}`;

const storeAddress = computed(() => {
    const parts = [
        props.storeInfo?.address,
        props.storeInfo?.city,
        [props.storeInfo?.state, props.storeInfo?.zip].filter(Boolean).join(' '),
    ].filter(Boolean);
    return parts.join(', ');
});

const paymentLabel = computed(() => {
    switch (props.order?.payment_method) {
        case 'cash':
            return 'Cash';
        case 'pos_terminal':
            return 'Card Terminal';
        case 'split':
            return 'Split (Cash + Card)';
        default:
            return props.order?.payment_method || 'Paid';
    }
});
</script>

<template>
    <div
        :id="printable ? 'printable-pos-receipt' : undefined"
        class="bg-white text-black font-mono text-xs leading-relaxed p-4 rounded-lg border border-zinc-200 max-w-[320px] mx-auto shadow-sm"
    >
        <!-- Store Header -->
        <div class="text-center border-b border-dashed border-zinc-400 pb-3 mb-3">
            <div class="text-sm font-bold uppercase tracking-wide">
                {{ storeInfo?.name || 'Masala Mart' }}
            </div>
            <div v-if="storeAddress" class="text-[11px] text-zinc-700 mt-0.5">
                {{ storeAddress }}
            </div>
            <div v-if="storeInfo?.phone" class="text-[11px] text-zinc-700">
                Tel: {{ storeInfo.phone }}
            </div>
        </div>

        <!-- Receipt Metadata -->
        <div class="space-y-0.5 text-[11px] border-b border-dashed border-zinc-400 pb-2.5 mb-2.5">
            <div class="flex justify-between gap-2">
                <span>Receipt:</span>
                <span class="font-bold">{{ order.order_number }}</span>
            </div>
            <div class="flex justify-between gap-2">
                <span>Date:</span>
                <span>{{ order.created_at_formatted || order.created_at }}</span>
            </div>
            <div class="flex justify-between gap-2">
                <span>Cashier:</span>
                <span>{{ order.cashier_name || 'Staff' }}</span>
            </div>
            <div class="flex justify-between gap-2">
                <span>Customer:</span>
                <span class="truncate max-w-[180px]">{{ order.customer_name || 'Walk-in Customer' }}</span>
            </div>
        </div>

        <!-- Line Items -->
        <div class="space-y-2 border-b border-dashed border-zinc-400 pb-3 mb-3">
            <div v-for="item in order.items" :key="item.id" class="text-[11px]">
                <div class="flex justify-between items-start gap-2">
                    <span class="font-semibold leading-snug">
                        {{ item.name }}
                        <span v-if="item.is_taxable" class="text-[10px] font-normal text-zinc-600">[T]</span>
                    </span>
                    <span class="font-semibold shrink-0 tabular-nums">
                        {{ formatMoney(item.total_price) }}
                    </span>
                </div>
                <div class="text-[10px] text-zinc-600 flex justify-between">
                    <span v-if="item.unit_type !== 'each' && item.weight">
                        {{ item.quantity > 1 ? `${item.quantity} × ` : '' }}{{ item.weight }} {{ item.unit_type }} @ {{ formatMoney(item.unit_price) }}/{{ item.unit_type }}
                    </span>
                    <span v-else>
                        {{ item.quantity }} × {{ formatMoney(item.unit_price) }}
                    </span>
                    <span v-if="item.refunded_quantity > 0" class="font-bold text-zinc-800">
                        Returned: {{ item.refunded_quantity }} (-{{ formatMoney(item.refunded_amount) }})
                    </span>
                </div>
            </div>
        </div>

        <!-- Totals -->
        <div class="space-y-1 text-[11px] border-b border-dashed border-zinc-400 pb-3 mb-3 tabular-nums">
            <div class="flex justify-between">
                <span>Subtotal</span>
                <span>{{ formatMoney(order.subtotal) }}</span>
            </div>
            <div v-if="Number(order.discount) > 0" class="flex justify-between">
                <span>Store Discount</span>
                <span>-{{ formatMoney(order.discount) }}</span>
            </div>
            <div v-if="Number(order.points_discount) > 0" class="flex justify-between">
                <span>Points Redeemed ({{ order.points_redeemed }} pts)</span>
                <span>-{{ formatMoney(order.points_discount) }}</span>
            </div>
            <div class="flex justify-between">
                <span>Sales Tax</span>
                <span>{{ formatMoney(order.tax) }}</span>
            </div>
            <div class="flex justify-between text-sm font-bold pt-1 border-t border-zinc-300">
                <span>TOTAL</span>
                <span>{{ formatMoney(order.total) }}</span>
            </div>
        </div>

        <!-- Tender Breakdown -->
        <div class="space-y-1 text-[11px] tabular-nums border-b border-dashed border-zinc-400 pb-3 mb-3">
            <div class="flex justify-between font-semibold">
                <span>Tender</span>
                <span>{{ paymentLabel }}</span>
            </div>
            <div v-if="Number(order.cash_amount) > 0" class="flex justify-between">
                <span>Cash Portion</span>
                <span>{{ formatMoney(order.cash_amount) }}</span>
            </div>
            <div v-if="Number(order.terminal_amount) > 0" class="flex justify-between">
                <span>Card Terminal</span>
                <span>{{ formatMoney(order.terminal_amount) }}</span>
            </div>
            <div v-if="order.cash_tendered !== null && order.cash_tendered !== undefined && Number(order.cash_amount) > 0" class="flex justify-between">
                <span>Cash Tendered</span>
                <span>{{ formatMoney(order.cash_tendered) }}</span>
            </div>
            <div v-if="Number(order.change_due) > 0" class="flex justify-between font-bold">
                <span>CHANGE DUE</span>
                <span>{{ formatMoney(order.change_due) }}</span>
            </div>
            <div v-if="order.payment_reference" class="flex justify-between text-[10px] text-zinc-600">
                <span>Terminal Ref</span>
                <span>{{ order.payment_reference }}</span>
            </div>
            <div v-if="Number(order.refunded_total) > 0" class="flex justify-between font-bold pt-1 border-t border-zinc-300">
                <span>REFUNDED</span>
                <span>-{{ formatMoney(order.refunded_total) }}</span>
            </div>
        </div>

        <!-- Loyalty Footer -->
        <div v-if="order.customer_id" class="text-[10px] text-center space-y-0.5 mb-3 pb-2 border-b border-dashed border-zinc-400">
            <div>Points Earned This Visit: <span class="font-bold">+{{ order.points_earned || 0 }} pts</span></div>
            <div v-if="order.customer_points_balance !== null && order.customer_points_balance !== undefined">
                Updated Balance: <span class="font-bold">{{ order.customer_points_balance }} pts</span>
            </div>
        </div>

        <div class="text-center text-[10px] text-zinc-600 space-y-0.5">
            <div class="font-semibold text-black">Thank you for shopping with us!</div>
            <div>Keep receipt for returns & exchanges</div>
        </div>
    </div>
</template>

