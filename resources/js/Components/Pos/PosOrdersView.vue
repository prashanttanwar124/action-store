<script setup>
import axios from 'axios';
import { computed, reactive, ref, watch } from 'vue';
import {
    Search,
    RefreshCw,
    Printer,
    RotateCcw,
    PackageCheck,
    Trash2,
    Banknote,
    CreditCard,
    CheckCircle2,
    AlertCircle,
    X,
} from 'lucide-vue-next';

const props = defineProps({
    initialOrders: {
        type: Array,
        default: () => [],
    },
    initialSearch: {
        type: String,
        default: '',
    },
    hasOpenShift: {
        type: Boolean,
        default: false,
    },
    expectedDrawerCash: {
        type: Number,
        default: 0,
    },
});

const emit = defineEmits(['print-receipt', 'refund-completed', 'open-shift']);

const orders = ref([...props.initialOrders]);
const searchQuery = ref(props.initialSearch || '');
const channelFilter = ref('all');
const isLoading = ref(false);
const selectedOrderId = ref(props.initialOrders[0]?.id || null);

const refundMethod = ref('cash');
const refundReason = ref('Customer return');
const isSubmittingRefund = ref(false);
const refundError = ref('');
const refundSuccessMessage = ref('');

const returnDraft = reactive({});

const formatMoney = (val) => `$${Number(val || 0).toFixed(2)}`;

watch(
    () => props.initialOrders,
    (next) => {
        if (Array.isArray(next) && next.length > 0 && orders.value.length === 0) {
            orders.value = [...next];
            selectedOrderId.value = next[0]?.id || null;
        }
    },
);

const selectedOrder = computed(
    () => orders.value.find((order) => order.id === selectedOrderId.value) || null,
);

const summaryMetrics = computed(() => {
    const posOrders = orders.value.filter((o) => o.channel === 'pos');
    const netPosRevenue = posOrders.reduce(
        (sum, o) =>
            o.status === 'cancelled'
                ? sum
                : sum + Math.max(0, Number(o.total || 0) - Number(o.refunded_total || 0)),
        0,
    );
    const totalRefunded = orders.value.reduce((sum, o) => sum + Number(o.refunded_total || 0), 0);

    return {
        count: orders.value.length,
        posCount: posOrders.length,
        netPosRevenue,
        totalRefunded,
    };
});

const resetReturnDraft = (order) => {
    Object.keys(returnDraft).forEach((key) => delete returnDraft[key]);
    refundError.value = '';
    refundSuccessMessage.value = '';

    if (!order) {
        return;
    }

    refundMethod.value =
        order.payment_method === 'pos_terminal' || order.payment_method === 'stripe'
            ? 'pos_terminal'
            : 'cash';

    for (const item of order.items || []) {
        returnDraft[item.id] = {
            quantity: 0,
            restock: true,
        };
    }
};

watch(
    selectedOrder,
    (order) => {
        resetReturnDraft(order);
    },
    { immediate: true },
);

const fetchOrders = async () => {
    isLoading.value = true;
    refundError.value = '';

    try {
        const { data } = await axios.get(route('pos.orders.index'), {
            params: {
                q: searchQuery.value,
                channel: channelFilter.value,
            },
        });

        orders.value = data.orders || [];
        if (!orders.value.some((order) => order.id === selectedOrderId.value)) {
            selectedOrderId.value = orders.value[0]?.id || null;
        }
    } catch {
        refundError.value = 'Unable to load orders. Check connection and try again.';
    } finally {
        isLoading.value = false;
    }
};

let searchTimer = null;
const handleSearchInput = () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        fetchOrders();
    }, 200);
};

const selectChannel = (channel) => {
    channelFilter.value = channel;
    fetchOrders();
};

const adjustReturnQty = (item, delta) => {
    if (!returnDraft[item.id]) {
        returnDraft[item.id] = { quantity: 0, restock: true };
    }

    const maxQty = Math.max(
        0,
        Number(item.remaining_quantity ?? item.quantity - (item.refunded_quantity || 0)),
    );
    const next = Math.min(maxQty, Math.max(0, Number(returnDraft[item.id].quantity || 0) + delta));
    returnDraft[item.id].quantity = next;
};

const selectAllRemainingForReturn = () => {
    if (!selectedOrder.value) {
        return;
    }

    for (const item of selectedOrder.value.items || []) {
        const maxQty = Math.max(
            0,
            Number(item.remaining_quantity ?? item.quantity - (item.refunded_quantity || 0)),
        );
        returnDraft[item.id] = {
            quantity: maxQty,
            restock: returnDraft[item.id]?.restock ?? true,
        };
    }
};

const clearReturnSelection = () => {
    if (selectedOrder.value) {
        resetReturnDraft(selectedOrder.value);
    }
};

const selectedReturnLines = computed(() => {
    if (!selectedOrder.value) {
        return [];
    }

    return (selectedOrder.value.items || [])
        .map((item) => {
            const draft = returnDraft[item.id];
            const qty = Math.max(0, Number(draft?.quantity || 0));
            return {
                order_item_id: item.id,
                item,
                quantity: qty,
                restock: draft?.restock ?? true,
            };
        })
        .filter((entry) => entry.quantity > 0);
});

const estimatedRefundTotal = computed(() => {
    const order = selectedOrder.value;
    if (!order || selectedReturnLines.value.length === 0) {
        return 0;
    }

    const orderSubtotal = Number(order.subtotal || 0);
    const totalDiscount = Math.min(
        orderSubtotal,
        Number(order.discount || 0) + Number(order.points_discount || 0),
    );
    const discountRatio = orderSubtotal > 0 ? totalDiscount / orderSubtotal : 0;

    const totalRemainingUnitsBefore = (order.items || []).reduce(
        (sum, item) => sum + Math.max(0, Number(item.remaining_quantity ?? 0)),
        0,
    );
    const returningUnits = selectedReturnLines.value.reduce((sum, line) => sum + line.quantity, 0);

    if (totalRemainingUnitsBefore > 0 && returningUnits >= totalRemainingUnitsBefore) {
        return Number(order.refundable_balance || 0);
    }

    const sumLines = selectedReturnLines.value.reduce((sum, entry) => {
        const item = entry.item;
        const fullLineValue =
            Math.round(
                (Number(item.total_price || 0) * (1 - discountRatio) +
                    Number(item.tax_amount || 0)) *
                    100,
            ) / 100;
        const remainingItemValue = Math.max(
            0,
            Math.round((fullLineValue - Number(item.refunded_amount || 0)) * 100) / 100,
        );

        if (entry.quantity >= Number(item.remaining_quantity || 0)) {
            return sum + remainingItemValue;
        }

        const perUnit = fullLineValue / Math.max(1, Number(item.quantity || 1));
        return sum + Math.min(remainingItemValue, Math.round(perUnit * entry.quantity * 100) / 100);
    }, 0);

    return Math.min(Number(order.refundable_balance || 0), Math.round(sumLines * 100) / 100);
});

const canRefundInPos = computed(
    () => selectedOrder.value?.channel === 'pos' && selectedOrder.value?.status !== 'pending',
);

const cashDrawerInsufficient = computed(
    () =>
        props.hasOpenShift &&
        refundMethod.value === 'cash' &&
        estimatedRefundTotal.value > props.expectedDrawerCash + 0.0001,
);

const submitRefund = async () => {
    if (!selectedOrder.value || !canRefundInPos.value || selectedReturnLines.value.length === 0) {
        return;
    }

    if (!props.hasOpenShift) {
        refundError.value = 'Open a register shift before issuing a refund.';
        return;
    }

    if (cashDrawerInsufficient.value) {
        refundError.value = `Insufficient cash in drawer (${formatMoney(props.expectedDrawerCash)} expected) for a ${formatMoney(estimatedRefundTotal.value)} cash refund. Switch to Card Terminal.`;
        return;
    }

    isSubmittingRefund.value = true;
    refundError.value = '';
    refundSuccessMessage.value = '';

    try {
        const { data } = await axios.post(route('pos.orders.refund', selectedOrder.value.id), {
            items: selectedReturnLines.value.map((entry) => ({
                order_item_id: entry.order_item_id,
                quantity: entry.quantity,
                restock: entry.restock,
            })),
            refund_method: refundMethod.value,
            reason: refundReason.value,
        });

        const updatedOrder = data.order;
        orders.value = orders.value.map((existing) =>
            existing.id === updatedOrder.id ? updatedOrder : existing,
        );

        resetReturnDraft(updatedOrder);
        refundSuccessMessage.value = `Refunded ${formatMoney(data.refund_amount)} via ${refundMethod.value === 'cash' ? 'Cash Drawer' : 'Card Terminal'}.`;

        emit('refund-completed', data);
    } catch (error) {
        const errors = error.response?.data?.errors;
        const firstError = errors ? Object.values(errors).flat()[0] : null;
        refundError.value =
            firstError ||
            error.response?.data?.message ||
            'Refund failed. Please verify quantities.';
    } finally {
        isSubmittingRefund.value = false;
    }
};

const prependNewOrder = (order) => {
    orders.value = [order, ...orders.value.filter((item) => item.id !== order.id)].slice(0, 35);
    selectedOrderId.value = order.id;
};

defineExpose({
    prependNewOrder,
    fetchOrders,
});
</script>

<template>
    <div class="flex-1 grid grid-cols-1 lg:grid-cols-12 min-h-0 bg-zinc-100 select-none">
        <!-- Left Order Search & History List -->
        <div class="lg:col-span-5 xl:col-span-4 border-r border-zinc-200 bg-white flex flex-col min-h-0">
            <div class="p-3.5 border-b border-zinc-200 space-y-2.5 shrink-0">
                <div class="flex items-center gap-2">
                    <div class="relative flex-1">
                        <Search class="w-4 h-4 text-zinc-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                        <input
                            v-model="searchQuery"
                            @input="handleSearchInput"
                            @keydown.enter.prevent="fetchOrders"
                            type="text"
                            placeholder="Scan receipt #MM-... or customer phone"
                            class="w-full h-10 pl-9 pr-8 rounded-lg border border-zinc-200 bg-white text-xs font-medium text-zinc-900"
                        />
                        <button
                            v-if="searchQuery"
                            type="button"
                            @click="
                                searchQuery = '';
                                fetchOrders();
                            "
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-zinc-700 cursor-pointer"
                        >
                            <X class="w-3.5 h-3.5" />
                        </button>
                    </div>
                    <button
                        type="button"
                        @click="fetchOrders"
                        class="h-10 w-10 rounded-lg border border-zinc-200 bg-zinc-50 hover:bg-zinc-100 flex items-center justify-center text-zinc-700 cursor-pointer shrink-0"
                        title="Refresh orders"
                    >
                        <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': isLoading }" />
                    </button>
                </div>

                <div class="flex items-center justify-between gap-2">
                    <div class="inline-flex rounded-lg bg-zinc-100 p-0.5 border border-zinc-200">
                        <button
                            v-for="filter in [
                                { id: 'all', label: 'All' },
                                { id: 'pos', label: 'POS' },
                                { id: 'online', label: 'Online' },
                            ]"
                            :key="filter.id"
                            type="button"
                            @click="selectChannel(filter.id)"
                            class="px-3 py-1 rounded-md text-xs font-semibold transition-all cursor-pointer"
                            :class="
                                channelFilter === filter.id
                                    ? 'bg-white text-zinc-950 shadow-sm'
                                    : 'text-zinc-600 hover:text-zinc-900'
                            "
                        >
                            {{ filter.label }}
                        </button>
                    </div>

                    <div class="text-[11px] font-mono text-zinc-500 tabular-nums">
                        POS Net: <span class="font-bold text-zinc-900">{{ formatMoney(summaryMetrics.netPosRevenue) }}</span>
                    </div>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto divide-y divide-zinc-100">
                <div v-if="orders.length === 0" class="p-8 text-center text-xs text-zinc-500">
                    No matching orders found. Scan a receipt barcode or search by customer phone.
                </div>

                <button
                    v-for="order in orders"
                    :key="order.id"
                    type="button"
                    @click="selectedOrderId = order.id"
                    class="w-full p-3.5 text-left transition-colors cursor-pointer"
                    :class="
                        selectedOrderId === order.id
                            ? 'bg-emerald-50/70 border-l-4 border-l-emerald-600'
                            : 'hover:bg-zinc-50'
                    "
                >
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-mono text-xs font-bold text-zinc-950">
                            {{ order.order_number }}
                        </span>
                        <span class="font-mono text-sm font-bold text-zinc-950 tabular-nums">
                            {{ formatMoney(order.total) }}
                        </span>
                    </div>

                    <div class="flex items-center justify-between gap-2 mt-1">
                        <span class="text-xs text-zinc-600 truncate">
                            {{ order.customer_name }}
                            <template v-if="order.customer_phone"> · {{ order.customer_phone }}</template>
                        </span>
                        <div class="flex items-center gap-1 shrink-0">
                            <span
                                class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase font-mono"
                                :class="
                                    order.channel === 'pos'
                                        ? 'bg-emerald-100 text-emerald-800'
                                        : 'bg-sky-100 text-sky-800'
                                "
                            >
                                {{ order.channel === 'pos' ? 'POS' : 'WEB' }}
                            </span>
                            <span
                                v-if="Number(order.refunded_total) > 0 || order.status === 'cancelled'"
                                class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase font-mono bg-rose-100 text-rose-700"
                            >
                                {{ order.status === 'cancelled' ? 'Refunded' : `-${formatMoney(order.refunded_total)}` }}
                            </span>
                        </div>
                    </div>

                    <div class="text-[11px] text-zinc-400 mt-1">
                        {{ order.created_at_formatted }} · {{ order.items?.length || 0 }} lines
                    </div>
                </button>
            </div>
        </div>

        <!-- Right Order Inspection & Return Builder -->
        <div class="lg:col-span-7 xl:col-span-8 flex flex-col min-h-0 overflow-y-auto p-4 sm:p-6">
            <div v-if="!selectedOrder" class="flex-1 flex items-center justify-center text-sm text-zinc-500">
                Select an order from the list to reprint a receipt or issue a return.
            </div>

            <div v-else class="max-w-3xl w-full mx-auto space-y-4">
                <!-- Order Summary Card -->
                <div class="bg-white border border-zinc-200 rounded-xl p-4 sm:p-5 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3 pb-4 border-b border-zinc-100">
                        <div>
                            <div class="flex items-center gap-2">
                                <h2 class="text-lg font-bold font-mono text-zinc-950">
                                    {{ selectedOrder.order_number }}
                                </h2>
                                <span
                                    class="px-2 py-0.5 rounded-full text-[11px] font-semibold uppercase"
                                    :class="
                                        selectedOrder.status === 'cancelled'
                                            ? 'bg-rose-100 text-rose-700'
                                            : 'bg-emerald-100 text-emerald-800'
                                    "
                                >
                                    {{ selectedOrder.status }}
                                </span>
                            </div>
                            <p class="text-xs text-zinc-500 mt-1">
                                {{ selectedOrder.created_at_formatted }} · Cashier: {{ selectedOrder.cashier_name }} · Customer: {{ selectedOrder.customer_name }}
                                <span v-if="selectedOrder.customer_phone">({{ selectedOrder.customer_phone }})</span>
                            </p>
                        </div>

                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                @click="emit('print-receipt', selectedOrder)"
                                class="h-9 px-3.5 rounded-lg border border-zinc-200 bg-white hover:bg-zinc-50 text-xs font-semibold text-zinc-800 inline-flex items-center gap-1.5 shadow-sm cursor-pointer"
                            >
                                <Printer class="w-3.5 h-3.5" />
                                <span>Reprint Receipt</span>
                            </button>
                            <button
                                v-if="canRefundInPos && selectedOrder.refundable_balance > 0 && selectedOrder.status !== 'cancelled'"
                                type="button"
                                @click="selectAllRemainingForReturn"
                                class="h-9 px-3.5 rounded-lg border border-zinc-200 bg-zinc-50 hover:bg-zinc-100 text-xs font-semibold text-zinc-700 inline-flex items-center gap-1.5 cursor-pointer"
                            >
                                <RotateCcw class="w-3.5 h-3.5" />
                                <span>Full Return</span>
                            </button>
                        </div>
                    </div>

                    <!-- Financial Pills -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-4 text-xs">
                        <div class="p-2.5 rounded-lg bg-zinc-50 border border-zinc-100">
                            <div class="text-zinc-500">Order Total</div>
                            <div class="text-sm font-bold font-mono text-zinc-950 mt-0.5 tabular-nums">
                                {{ formatMoney(selectedOrder.total) }}
                            </div>
                        </div>
                        <div class="p-2.5 rounded-lg bg-zinc-50 border border-zinc-100">
                            <div class="text-zinc-500">Sales Tax</div>
                            <div class="text-sm font-bold font-mono text-zinc-950 mt-0.5 tabular-nums">
                                {{ formatMoney(selectedOrder.tax) }}
                            </div>
                        </div>
                        <div class="p-2.5 rounded-lg bg-zinc-50 border border-zinc-100">
                            <div class="text-zinc-500">Original Tender</div>
                            <div class="text-sm font-semibold text-zinc-950 mt-0.5 capitalize">
                                {{ selectedOrder.payment_method === 'pos_terminal' ? 'Card Terminal' : selectedOrder.payment_method }}
                            </div>
                        </div>
                        <div class="p-2.5 rounded-lg bg-zinc-50 border border-zinc-100">
                            <div class="text-zinc-500">Refundable Balance</div>
                            <div class="text-sm font-bold font-mono text-emerald-700 mt-0.5 tabular-nums">
                                {{ formatMoney(selectedOrder.refundable_balance) }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Feedback Banners -->
                <div
                    v-if="refundSuccessMessage"
                    class="p-3.5 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold flex items-center gap-2"
                >
                    <CheckCircle2 class="w-4 h-4 shrink-0 text-emerald-600" />
                    <span>{{ refundSuccessMessage }}</span>
                </div>

                <div
                    v-if="selectedOrder && !canRefundInPos"
                    class="p-3.5 rounded-lg bg-sky-50 border border-sky-200 text-sky-900 text-xs font-medium flex items-center gap-2"
                >
                    <AlertCircle class="w-4 h-4 shrink-0 text-sky-600" />
                    <span v-if="selectedOrder.channel !== 'pos'">
                        Online orders are view-only at the counter. Process Stripe or delivery refunds from <span class="font-bold">Admin Orders</span>.
                    </span>
                    <span v-else>
                        Unpaid pending orders cannot be refunded at the counter.
                    </span>
                </div>

                <div
                    v-if="refundError"
                    class="p-3.5 rounded-lg bg-rose-50 border border-rose-200 text-rose-700 text-xs font-semibold flex items-center justify-between gap-2"
                >
                    <div class="flex items-center gap-2">
                        <AlertCircle class="w-4 h-4 shrink-0 text-rose-600" />
                        <span>{{ refundError }}</span>
                    </div>
                    <button
                        v-if="!hasOpenShift"
                        type="button"
                        @click="emit('open-shift')"
                        class="px-2.5 py-1 rounded bg-rose-600 text-white text-[11px] font-semibold cursor-pointer shrink-0"
                    >
                        Open Shift
                    </button>
                </div>

                <!-- Items & Per-Item Restock vs Waste Controls -->
                <div class="bg-white border border-zinc-200 rounded-xl overflow-hidden shadow-sm">
                    <div class="px-4 py-3 border-b border-zinc-200 bg-zinc-50 flex items-center justify-between">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-zinc-700">
                            Items & Return Disposition
                        </h3>
                        <button
                            v-if="selectedReturnLines.length > 0"
                            type="button"
                            @click="clearReturnSelection"
                            class="text-xs font-semibold text-zinc-500 hover:text-zinc-900 cursor-pointer"
                        >
                            Clear selection
                        </button>
                    </div>

                    <div class="divide-y divide-zinc-100">
                        <div
                            v-for="item in selectedOrder.items"
                            :key="item.id"
                            class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-bold text-zinc-950">{{ item.name }}</span>
                                    <span
                                        v-if="item.is_taxable"
                                        class="px-1.5 py-0.5 rounded bg-zinc-100 text-zinc-600 text-[10px] font-mono font-semibold"
                                    >
                                        TAX
                                    </span>
                                </div>
                                <div class="text-xs text-zinc-500 mt-0.5 tabular-nums">
                                    <template v-if="item.unit_type !== 'each' && item.weight">
                                        {{ item.quantity }} × {{ item.weight }} {{ item.unit_type }} @ {{ formatMoney(item.unit_price) }}/{{ item.unit_type }}
                                    </template>
                                    <template v-else>
                                        {{ item.quantity }} × {{ formatMoney(item.unit_price) }}
                                    </template>
                                    · Line: <span class="font-semibold text-zinc-800">{{ formatMoney(item.total_price) }}</span>
                                    <span v-if="item.tax_amount > 0"> (+{{ formatMoney(item.tax_amount) }} tax)</span>
                                </div>
                                <div v-if="item.refunded_quantity > 0" class="text-[11px] text-rose-600 font-medium mt-1">
                                    Returned {{ item.refunded_quantity }} of {{ item.quantity }} (-{{ formatMoney(item.refunded_amount) }})
                                    <span v-if="item.refund_reason" class="text-zinc-500"> · {{ item.refund_reason }}</span>
                                </div>
                            </div>

                            <!-- Return Quantity Stepper + Restock/Waste Toggle -->
                            <div
                                v-if="canRefundInPos && item.remaining_quantity > 0 && selectedOrder.status !== 'cancelled'"
                                class="flex items-center gap-2.5 shrink-0"
                            >
                                <div
                                    v-if="(returnDraft[item.id]?.quantity || 0) > 0 && item.product_id"
                                    class="inline-flex rounded-lg border border-zinc-200 p-0.5 bg-zinc-100"
                                >
                                    <button
                                        type="button"
                                        @click="returnDraft[item.id].restock = true"
                                        class="px-2.5 py-1 rounded-md text-[11px] font-semibold inline-flex items-center gap-1 transition-all cursor-pointer"
                                        :class="
                                            returnDraft[item.id]?.restock
                                                ? 'bg-emerald-600 text-white shadow-sm'
                                                : 'text-zinc-600 hover:text-zinc-900'
                                        "
                                    >
                                        <PackageCheck class="w-3.5 h-3.5" />
                                        <span>Restock</span>
                                    </button>
                                    <button
                                        type="button"
                                        @click="returnDraft[item.id].restock = false"
                                        class="px-2.5 py-1 rounded-md text-[11px] font-semibold inline-flex items-center gap-1 transition-all cursor-pointer"
                                        :class="
                                            !returnDraft[item.id]?.restock
                                                ? 'bg-amber-600 text-white shadow-sm'
                                                : 'text-zinc-600 hover:text-zinc-900'
                                        "
                                    >
                                        <Trash2 class="w-3.5 h-3.5" />
                                        <span>Waste</span>
                                    </button>
                                </div>

                                <div class="inline-flex items-center rounded-lg border border-zinc-200 bg-white shadow-sm">
                                    <button
                                        type="button"
                                        @click="adjustReturnQty(item, -1)"
                                        class="w-8 h-8 flex items-center justify-center text-sm font-bold text-zinc-700 hover:bg-zinc-100 rounded-l-lg cursor-pointer"
                                    >
                                        −
                                    </button>
                                    <span class="w-9 text-center font-mono text-xs font-bold text-zinc-950 tabular-nums">
                                        {{ returnDraft[item.id]?.quantity || 0 }}
                                    </span>
                                    <button
                                        type="button"
                                        @click="adjustReturnQty(item, 1)"
                                        class="w-8 h-8 flex items-center justify-center text-sm font-bold text-zinc-700 hover:bg-zinc-100 rounded-r-lg cursor-pointer"
                                    >
                                        +
                                    </button>
                                </div>
                            </div>

                            <span v-else-if="item.remaining_quantity <= 0 || selectedOrder.status === 'cancelled'" class="text-xs font-semibold text-zinc-400">
                                {{ selectedOrder.status === 'cancelled' && item.refunded_quantity <= 0 ? 'Cancelled' : 'Fully Returned' }}
                            </span>
                            <span v-else class="text-[11px] font-mono font-medium text-sky-700">
                                Online Item
                            </span>
                        </div>
                    </div>

                    <!-- Light Crisp Refund Execution Panel (Zero Dark Input Focus Collisions!) -->
                    <div
                        v-if="canRefundInPos && selectedReturnLines.length > 0"
                        class="p-4 bg-zinc-50 border-t border-zinc-200 space-y-3"
                    >
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <!-- Refund Tender Method -->
                            <div class="inline-flex rounded-lg bg-zinc-200/70 p-1">
                                <button
                                    type="button"
                                    @click="refundMethod = 'cash'"
                                    class="px-3 py-1.5 rounded-md text-xs font-semibold inline-flex items-center gap-1.5 transition-all cursor-pointer"
                                    :class="
                                        refundMethod === 'cash'
                                            ? 'bg-white text-zinc-950 shadow-sm'
                                            : 'text-zinc-600 hover:text-zinc-900'
                                    "
                                >
                                    <Banknote class="w-3.5 h-3.5 text-emerald-600" />
                                    <span>Cash Drawer</span>
                                </button>
                                <button
                                    type="button"
                                    @click="refundMethod = 'pos_terminal'"
                                    class="px-3 py-1.5 rounded-md text-xs font-semibold inline-flex items-center gap-1.5 transition-all cursor-pointer"
                                    :class="
                                        refundMethod === 'pos_terminal'
                                            ? 'bg-white text-zinc-950 shadow-sm'
                                            : 'text-zinc-600 hover:text-zinc-900'
                                    "
                                >
                                    <CreditCard class="w-3.5 h-3.5 text-sky-600" />
                                    <span>Card Terminal</span>
                                </button>
                            </div>

                            <!-- Quick Reason Presets -->
                            <div class="flex flex-wrap items-center gap-1.5">
                                <button
                                    v-for="preset in ['Customer return', 'Defective / Spoiled', 'Wrong item']"
                                    :key="preset"
                                    type="button"
                                    @click="refundReason = preset"
                                    class="px-2.5 py-1 rounded-md border text-[11px] font-semibold cursor-pointer"
                                    :class="
                                        refundReason === preset
                                            ? 'bg-zinc-900 border-zinc-900 text-white'
                                            : 'bg-white border-zinc-200 text-zinc-600 hover:bg-zinc-100'
                                    "
                                >
                                    {{ preset }}
                                </button>
                            </div>
                        </div>

                        <div
                            v-if="!hasOpenShift || cashDrawerInsufficient"
                            class="px-3 py-2 rounded-lg bg-amber-50 border border-amber-200 text-amber-900 text-xs font-medium flex items-center justify-between gap-2"
                        >
                            <span>
                                <template v-if="!hasOpenShift">
                                    No register shift is open. Open a shift to record Cash or Card Terminal refunds.
                                </template>
                                <template v-else>
                                    Drawer has {{ formatMoney(expectedDrawerCash) }} expected cash — switch to <span class="font-bold">Card Terminal</span> or add cash float.
                                </template>
                            </span>
                            <button
                                v-if="!hasOpenShift"
                                type="button"
                                @click="emit('open-shift')"
                                class="px-2.5 py-1 rounded bg-amber-700 hover:bg-amber-800 text-white text-[11px] font-semibold cursor-pointer shrink-0"
                            >
                                Open Shift
                            </button>
                        </div>

                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-1">
                            <input
                                v-model="refundReason"
                                type="text"
                                placeholder="Return reason..."
                                class="h-10 px-3 rounded-lg border border-zinc-200 bg-white text-xs text-zinc-900 sm:w-72"
                            />

                            <div class="flex items-center justify-between sm:justify-end gap-4">
                                <div class="text-right">
                                    <div class="text-[11px] font-medium text-zinc-500">Refund Total</div>
                                    <div class="text-xl font-bold font-mono text-rose-600 tabular-nums">
                                        -{{ formatMoney(estimatedRefundTotal) }}
                                    </div>
                                </div>

                                <button
                                    type="button"
                                    :disabled="isSubmittingRefund || !hasOpenShift || cashDrawerInsufficient"
                                    @click="submitRefund"
                                    class="h-11 px-5 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs inline-flex items-center gap-2 shadow-sm cursor-pointer disabled:opacity-50"
                                >
                                    <RotateCcw class="w-4 h-4" />
                                    <span>{{ isSubmittingRefund ? 'Processing...' : 'Issue Refund' }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
