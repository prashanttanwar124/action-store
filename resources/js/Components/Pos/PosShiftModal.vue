<script setup>
import axios from 'axios';
import { computed, ref, watch } from 'vue';
import {
    X,
    Wallet,
    CheckCircle2,
    AlertCircle,
    AlertTriangle,
    CreditCard,
    Banknote,
    Printer,
} from 'lucide-vue-next';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    activeShift: {
        type: Object,
        default: null,
    },
    drawerConfig: {
        type: Object,
        default: () => ({ safe_drop_alert_threshold: 1000 }),
    },
});

const emit = defineEmits(['close', 'shift-opened', 'shift-closed', 'open-cash-movement']);

const round2 = (value) => {
    const n = Number(value || 0);
    if (!Number.isFinite(n) || n === 0) {
        return 0;
    }

    return Math.sign(n) * (Math.round(Math.abs(n) * 100 + 1e-9) / 100);
};

const openingCash = ref('200.00');
const countedCash = ref('');
const countedTerminal = ref('');
const notes = ref('');
const loading = ref(false);
const error = ref('');
const closedReport = ref(null);

const formatMoney = (val) => `$${Number(val || 0).toFixed(2)}`;

const safeDropThreshold = computed(() =>
    Math.max(1, Number(props.drawerConfig?.safe_drop_alert_threshold ?? 1000)),
);

const isHighDrawerCash = computed(() =>
    Boolean(
        props.activeShift &&
            (props.activeShift.safe_drop_recommended ||
                Number(props.activeShift.expected_cash || 0) >= safeDropThreshold.value),
    ),
);

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) {
            return;
        }
        error.value = '';
        closedReport.value = null;
        if (props.activeShift) {
            countedCash.value = Number(props.activeShift.expected_cash || 0).toFixed(2);
            countedTerminal.value = '';
            notes.value = props.activeShift.notes || '';
        } else {
            openingCash.value = '200.00';
            countedTerminal.value = '';
            notes.value = '';
        }
    },
);

watch(
    () => props.activeShift?.expected_cash,
    (nextExpected) => {
        if (props.activeShift && props.open) {
            countedCash.value = Number(nextExpected || 0).toFixed(2);
        }
    },
);

const currentExpectedCash = computed(() => round2(props.activeShift?.expected_cash || 0));
const currentNetCard = computed(() =>
    round2(
        Number(props.activeShift?.terminal_sales || 0) -
            Number(props.activeShift?.terminal_refunds || 0),
    ),
);
const currentNetCashSales = computed(() =>
    round2(
        Number(props.activeShift?.cash_sales || 0) -
            Number(props.activeShift?.cash_refunds || 0),
    ),
);
const currentNetShiftSales = computed(() =>
    round2(currentNetCashSales.value + currentNetCard.value),
);

const terminalVariance = computed(() => {
    if (!props.activeShift || countedTerminal.value === '') {
        return null;
    }

    return round2(Number(countedTerminal.value || 0) - currentNetCard.value);
});

const cashVariance = computed(() => {
    if (!props.activeShift || countedCash.value === '') {
        return 0;
    }
    return round2(Number(countedCash.value || 0) - currentExpectedCash.value);
});

const submitOpen = async () => {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await axios.post(route('pos.shifts.open'), {
            opening_cash: round2(openingCash.value),
            notes: notes.value || null,
        });
        emit('shift-opened', data.shift);
    } catch (err) {
        const errors = err.response?.data?.errors;
        error.value = errors ? Object.values(errors).flat()[0] : 'Could not open shift.';
    } finally {
        loading.value = false;
    }
};

const printZReport = () => {
    if (typeof window !== 'undefined' && typeof window.print === 'function') {
        window.print();
    }
};

const submitClose = async () => {
    if (!props.activeShift) {
        return;
    }
    loading.value = true;
    error.value = '';

    try {
        const { data } = await axios.post(route('pos.shifts.close', props.activeShift.id), {
            counted_cash: round2(countedCash.value),
            counted_terminal:
                countedTerminal.value !== '' ? round2(countedTerminal.value) : null,
            notes: notes.value || null,
        });
        closedReport.value = data.shift;
        emit('shift-closed', data.shift);
    } catch (err) {
        const errors = err.response?.data?.errors;
        error.value = errors ? Object.values(errors).flat()[0] : 'Could not close shift.';
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <div
        v-if="open"
        class="fixed inset-0 z-50 bg-zinc-950/60 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto"
        @click.self="!loading && emit('close')"
    >
        <div
            class="bg-white border border-zinc-200 rounded-2xl shadow-2xl w-full max-h-[92vh] overflow-y-auto p-5 space-y-4 my-auto transition-all"
            :class="closedReport || activeShift ? 'max-w-2xl' : 'max-w-lg'"
        >
            <div class="flex items-start justify-between gap-2">
                <div>
                    <span class="inline-flex items-center gap-1 text-[11px] font-mono uppercase font-bold text-emerald-700">
                        <Wallet class="w-3.5 h-3.5" />
                        Register Settlement &amp; Shift Control
                    </span>
                    <h3 class="text-base font-bold text-zinc-950 mt-0.5">
                        {{
                            closedReport
                                ? `Shift #${closedReport.id} Z-Report Summary`
                                : activeShift
                                  ? `Shift #${activeShift.id} · Cash Drawer, Card Terminal & Closing`
                                  : 'Open Register Shift'
                        }}
                    </h3>
                </div>
                <button
                    type="button"
                    @click="emit('close')"
                    class="w-8 h-8 rounded-lg text-zinc-400 hover:text-zinc-800 hover:bg-zinc-100 flex items-center justify-center cursor-pointer"
                >
                    <X class="w-4 h-4" />
                </button>
            </div>

            <!-- Post-Close Z-Report -->
            <div v-if="closedReport" class="space-y-3.5">
                <!-- Banner & Combined Totals Strip -->
                <div class="p-3.5 rounded-xl bg-emerald-950 text-white space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2 font-bold text-sm">
                            <CheckCircle2 class="w-4 h-4 text-emerald-400 shrink-0" />
                            <span>Shift Reconciled &amp; Closed</span>
                        </div>
                        <div class="text-[11px] text-emerald-200 font-mono">
                            {{ closedReport.opened_at_formatted }} → {{ closedReport.closed_at_formatted || 'Just now' }}
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-2 pt-1 border-t border-emerald-800/80 tabular-nums">
                        <div>
                            <div class="text-[10px] uppercase tracking-wider text-emerald-300 font-semibold">Gross POS Sales</div>
                            <div class="text-sm font-mono font-bold mt-0.5">
                                {{
                                    formatMoney(
                                        closedReport.gross_total_sales ??
                                            Number(closedReport.cash_sales || 0) + Number(closedReport.terminal_sales || 0),
                                    )
                                }}
                            </div>
                        </div>
                        <div>
                            <div class="text-[10px] uppercase tracking-wider text-emerald-300 font-semibold">Total Refunds</div>
                            <div class="text-sm font-mono font-bold text-rose-300 mt-0.5">
                                −{{
                                    formatMoney(
                                        closedReport.total_refunds ??
                                            Number(closedReport.cash_refunds || 0) + Number(closedReport.terminal_refunds || 0),
                                    )
                                }}
                            </div>
                        </div>
                        <div>
                            <div class="text-[10px] uppercase tracking-wider text-emerald-300 font-semibold">Combined Net Sales</div>
                            <div class="text-base font-mono font-bold text-emerald-300 mt-0.5">
                                {{
                                    formatMoney(
                                        closedReport.net_total_sales ??
                                            Number(closedReport.cash_sales || 0) -
                                                Number(closedReport.cash_refunds || 0) +
                                                (Number(closedReport.terminal_sales || 0) - Number(closedReport.terminal_refunds || 0)),
                                    )
                                }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Two-Column Z-Report Breakdown: Cash Drawer + Card Terminal -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs tabular-nums">
                    <!-- Left: Cash Drawer Settlement -->
                    <div class="p-3.5 rounded-xl bg-zinc-50 border border-zinc-200 space-y-1.5">
                        <div class="flex items-center justify-between pb-1.5 border-b border-zinc-200">
                            <span class="font-bold text-zinc-900 inline-flex items-center gap-1.5">
                                <Banknote class="w-3.5 h-3.5 text-emerald-600" />
                                Cash Drawer Reconciliation
                            </span>
                            <span class="text-[10px] font-mono text-zinc-500">Till #{{ closedReport.id }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-zinc-600">Opening Float</span>
                            <span class="font-mono font-semibold text-zinc-800">{{ formatMoney(closedReport.opening_cash) }}</span>
                        </div>
                        <div class="flex justify-between text-emerald-700">
                            <span>+ Cash Sales</span>
                            <span class="font-mono font-semibold">{{ formatMoney(closedReport.cash_sales) }}</span>
                        </div>
                        <div v-if="Number(closedReport.cash_deposits || 0) > 0" class="flex justify-between text-emerald-700">
                            <span>+ Deposits / Coins</span>
                            <span class="font-mono font-semibold">{{ formatMoney(closedReport.cash_deposits) }}</span>
                        </div>
                        <div class="flex justify-between text-rose-600">
                            <span>− Cash Refunds</span>
                            <span class="font-mono font-semibold">{{ formatMoney(closedReport.cash_refunds) }}</span>
                        </div>
                        <div v-if="Number(closedReport.cash_withdrawals || 0) > 0" class="flex justify-between text-amber-700">
                            <span>− Withdrawals / Safe Drops</span>
                            <span class="font-mono font-semibold">{{ formatMoney(closedReport.cash_withdrawals) }}</span>
                        </div>
                        <div class="flex justify-between pt-1.5 border-t border-zinc-200">
                            <span class="text-zinc-700 font-semibold">Expected Drawer Cash</span>
                            <span class="font-mono font-bold text-zinc-950">{{ formatMoney(closedReport.expected_cash) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-zinc-700 font-semibold">Counted Drawer Cash</span>
                            <span class="font-mono font-bold text-zinc-950">{{ formatMoney(closedReport.counted_cash) }}</span>
                        </div>
                        <div class="flex justify-between pt-1.5 border-t border-zinc-200 font-bold">
                            <span>Drawer Variance</span>
                            <span
                                class="font-mono"
                                :class="
                                    Math.abs(Number(closedReport.cash_difference || 0)) < 0.005
                                        ? 'text-emerald-700'
                                        : Number(closedReport.cash_difference) < 0
                                          ? 'text-rose-600'
                                          : 'text-amber-700'
                                "
                            >
                                {{ Number(closedReport.cash_difference) >= 0 ? '+' : '' }}{{ formatMoney(closedReport.cash_difference) }}
                            </span>
                        </div>
                    </div>

                    <!-- Right: Card Terminal Settlement -->
                    <div class="p-3.5 rounded-xl bg-indigo-50/50 border border-indigo-200/80 space-y-1.5 flex flex-col justify-between">
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between pb-1.5 border-b border-indigo-200/70">
                                <span class="font-bold text-indigo-950 inline-flex items-center gap-1.5">
                                    <CreditCard class="w-3.5 h-3.5 text-indigo-600" />
                                    Card Terminal Settlement
                                </span>
                                <span
                                    v-if="Number(closedReport.split_orders_count || 0) > 0"
                                    class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-indigo-100 text-indigo-800 font-semibold"
                                >
                                    {{ closedReport.split_orders_count }} split
                                </span>
                            </div>
                            <div class="flex justify-between text-indigo-900">
                                <span>+ Card Terminal Sales</span>
                                <span class="font-mono font-semibold">{{ formatMoney(closedReport.terminal_sales) }}</span>
                            </div>
                            <div class="flex justify-between text-rose-600">
                                <span>− Card Refunds</span>
                                <span class="font-mono font-semibold">{{ formatMoney(closedReport.terminal_refunds) }}</span>
                            </div>
                            <div class="flex justify-between pt-1.5 border-t border-indigo-200/80 text-sm font-bold text-indigo-950">
                                <span>Net Card Terminal</span>
                                <span class="font-mono">
                                    {{
                                        formatMoney(
                                            closedReport.net_terminal ??
                                                Number(closedReport.terminal_sales || 0) - Number(closedReport.terminal_refunds || 0),
                                        )
                                    }}
                                </span>
                            </div>
                            <template v-if="closedReport.counted_terminal !== null && closedReport.counted_terminal !== undefined">
                                <div class="flex justify-between pt-1">
                                    <span class="text-indigo-800 font-semibold">Terminal Batch Slip</span>
                                    <span class="font-mono font-bold text-indigo-950">{{ formatMoney(closedReport.counted_terminal) }}</span>
                                </div>
                                <div class="flex justify-between font-bold">
                                    <span class="text-indigo-900">Batch Variance</span>
                                    <span
                                        class="font-mono"
                                        :class="
                                            Math.abs(Number(closedReport.terminal_difference || 0)) < 0.005
                                                ? 'text-emerald-700'
                                                : 'text-rose-600'
                                        "
                                    >
                                        {{ Number(closedReport.terminal_difference) >= 0 ? '+' : '' }}{{ formatMoney(closedReport.terminal_difference) }}
                                    </span>
                                </div>
                            </template>
                            <p v-else class="text-[11px] text-indigo-700/80 pt-1">
                                Includes card-only orders and card portions of split payments.
                            </p>
                        </div>

                        <div class="pt-2 mt-2 border-t border-indigo-200/70 flex items-center justify-between text-[11px] text-indigo-800">
                            <span>Orders processed</span>
                            <span class="font-mono font-bold">{{ closedReport.orders_count }} orders</span>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="printZReport"
                        class="h-10 px-4 rounded-lg border border-zinc-200 bg-white hover:bg-zinc-50 text-zinc-800 text-xs font-semibold inline-flex items-center justify-center gap-1.5 cursor-pointer"
                    >
                        <Printer class="w-3.5 h-3.5" />
                        <span>Print Z-Report</span>
                    </button>
                    <button
                        type="button"
                        @click="emit('close')"
                        class="flex-1 h-10 rounded-lg bg-zinc-900 hover:bg-zinc-800 text-white text-xs font-semibold cursor-pointer"
                    >
                        Done
                    </button>
                </div>
            </div>

            <!-- Open Shift Form -->
            <div v-else-if="!activeShift" class="space-y-3.5">
                <p class="text-xs text-zinc-500">
                    Verify the starting cash float in your register drawer before ringing counter sales.
                </p>

                <div class="grid grid-cols-4 gap-1.5">
                    <button
                        v-for="preset in ['100.00', '150.00', '200.00', '300.00']"
                        :key="preset"
                        type="button"
                        @click="openingCash = preset"
                        class="h-9 rounded-lg border text-xs font-mono font-bold cursor-pointer transition-colors"
                        :class="
                            Number(openingCash) === Number(preset)
                                ? 'bg-zinc-900 border-zinc-900 text-white'
                                : 'bg-zinc-50 border-zinc-200 text-zinc-800 hover:bg-zinc-100'
                        "
                    >
                        ${{ preset.replace('.00', '') }}
                    </button>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-zinc-700 mb-1">Starting Cash Float ($)</label>
                    <input
                        v-model="openingCash"
                        @keydown.enter.prevent="submitOpen"
                        type="number"
                        step="0.01"
                        min="0"
                        class="w-full h-11 px-3 rounded-lg border border-zinc-200 bg-white font-mono text-base font-bold tabular-nums"
                    />
                </div>

                <div>
                    <label class="block text-xs font-semibold text-zinc-700 mb-1">Shift Note (Optional)</label>
                    <input
                        v-model="notes"
                        type="text"
                        placeholder="e.g. Morning drawer verified"
                        class="w-full h-9 px-3 rounded-lg border border-zinc-200 bg-white text-xs"
                    />
                </div>

                <div v-if="error" class="p-2.5 rounded-lg bg-rose-50 border border-rose-200 text-xs text-rose-700 flex items-center gap-2">
                    <AlertCircle class="w-4 h-4 shrink-0" />
                    <span>{{ error }}</span>
                </div>

                <button
                    type="button"
                    :disabled="loading"
                    @click="submitOpen"
                    class="w-full h-11 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-sm shadow-sm cursor-pointer disabled:opacity-50"
                >
                    {{ loading ? 'Opening Shift...' : 'Open Register Shift' }}
                </button>
            </div>

            <!-- Active Shift X-Report + Close Shift -->
            <div v-else class="space-y-3.5">
                <!-- Optional High Cash Notice with 1-Click Jump to Dedicated Deposit/Safe Drop Modal -->
                <div
                    v-if="isHighDrawerCash"
                    class="p-3 rounded-xl bg-amber-50 border border-amber-300 text-amber-950 text-xs flex items-center justify-between gap-3"
                >
                    <div class="flex items-start gap-2.5">
                        <AlertTriangle class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
                        <div>
                            <div class="font-bold">Safe Drop Recommended</div>
                            <p class="text-amber-800 mt-0.5">
                                Drawer has <span class="font-mono font-bold">{{ formatMoney(activeShift.expected_cash) }}</span>
                                (above {{ formatMoney(safeDropThreshold) }} alert limit).
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="emit('open-cash-movement', 'withdrawal')"
                        class="px-2.5 py-1.5 rounded-lg bg-amber-600 hover:bg-amber-500 text-white text-[11px] font-bold shrink-0 cursor-pointer"
                    >
                        Safe Drop
                    </button>
                </div>

                <!-- Live Shift Summary Strip -->
                <div class="grid grid-cols-3 gap-2 text-xs tabular-nums">
                    <div class="p-2.5 rounded-xl bg-zinc-900 text-white">
                        <div class="text-[10px] text-zinc-400 uppercase font-semibold">Net Shift Sales</div>
                        <div class="text-sm font-mono font-bold mt-0.5">{{ formatMoney(currentNetShiftSales) }}</div>
                        <div class="text-[10px] text-zinc-400 font-mono mt-0.5">{{ activeShift.orders_count }} orders</div>
                    </div>
                    <div class="p-2.5 rounded-xl bg-emerald-50 border border-emerald-200">
                        <div class="text-[10px] text-emerald-700 uppercase font-semibold">Net Cash Sales</div>
                        <div class="text-sm font-mono font-bold text-emerald-950 mt-0.5">{{ formatMoney(currentNetCashSales) }}</div>
                        <div class="text-[10px] text-emerald-700 font-mono mt-0.5">Till: {{ formatMoney(activeShift.expected_cash) }}</div>
                    </div>
                    <div class="p-2.5 rounded-xl bg-indigo-50 border border-indigo-200">
                        <div class="text-[10px] text-indigo-700 uppercase font-semibold">Net Card Terminal</div>
                        <div class="text-sm font-mono font-bold text-indigo-950 mt-0.5">{{ formatMoney(currentNetCard) }}</div>
                        <div class="text-[10px] text-indigo-700 font-mono mt-0.5">
                            Gross {{ formatMoney(activeShift.terminal_sales) }}
                            <template v-if="Number(activeShift.split_orders_count || 0) > 0">
                                · {{ activeShift.split_orders_count }} split
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Two-Column X-Report Breakdown: Drawer Cash + Card Terminal -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs tabular-nums">
                    <!-- Cash Drawer X-Report -->
                    <div class="p-3.5 rounded-xl bg-zinc-50 border border-zinc-200 space-y-1.5">
                        <div class="flex justify-between text-[11px] text-zinc-500 pb-1 border-b border-zinc-200/80">
                            <span class="font-bold text-zinc-800 inline-flex items-center gap-1">
                                <Banknote class="w-3.5 h-3.5 text-emerald-600" />
                                Cash Drawer
                            </span>
                            <span>Opened {{ activeShift.opened_at_formatted }}</span>
                        </div>
                        <div class="flex justify-between pt-0.5">
                            <span class="text-zinc-600">Opening Float</span>
                            <span class="font-mono font-semibold">{{ formatMoney(activeShift.opening_cash) }}</span>
                        </div>
                        <div class="flex justify-between text-emerald-700">
                            <span>+ Cash Sales</span>
                            <span class="font-mono font-semibold">{{ formatMoney(activeShift.cash_sales) }}</span>
                        </div>
                        <div
                            v-if="Number(activeShift.cash_deposits || 0) > 0"
                            class="flex justify-between text-emerald-700"
                        >
                            <span>+ Deposits (Coins / Float)</span>
                            <span class="font-mono font-semibold">{{ formatMoney(activeShift.cash_deposits) }}</span>
                        </div>
                        <div class="flex justify-between text-rose-600">
                            <span>− Cash Refunds</span>
                            <span class="font-mono font-semibold">{{ formatMoney(activeShift.cash_refunds) }}</span>
                        </div>
                        <div
                            v-if="Number(activeShift.cash_withdrawals || 0) > 0"
                            class="flex justify-between text-amber-700"
                        >
                            <span>− Withdrawals / Safe Drops</span>
                            <span class="font-mono font-semibold">{{ formatMoney(activeShift.cash_withdrawals) }}</span>
                        </div>
                        <div class="flex justify-between pt-1.5 border-t border-zinc-200 text-sm font-bold text-zinc-950">
                            <span>Expected Drawer Cash</span>
                            <span class="font-mono">{{ formatMoney(activeShift.expected_cash) }}</span>
                        </div>
                    </div>

                    <!-- Card Terminal X-Report -->
                    <div class="p-3.5 rounded-xl bg-indigo-50/50 border border-indigo-200/80 space-y-1.5 flex flex-col justify-between">
                        <div class="space-y-1.5">
                            <div class="flex justify-between text-[11px] pb-1 border-b border-indigo-200/70">
                                <span class="font-bold text-indigo-950 inline-flex items-center gap-1">
                                    <CreditCard class="w-3.5 h-3.5 text-indigo-600" />
                                    Card Terminal
                                </span>
                                <span class="font-mono text-indigo-700">External Reader</span>
                            </div>
                            <div class="flex justify-between pt-0.5 text-indigo-900">
                                <span>+ Card Terminal Sales</span>
                                <span class="font-mono font-semibold">{{ formatMoney(activeShift.terminal_sales) }}</span>
                            </div>
                            <div class="flex justify-between text-rose-600">
                                <span>− Card Refunds</span>
                                <span class="font-mono font-semibold">{{ formatMoney(activeShift.terminal_refunds) }}</span>
                            </div>
                            <div class="flex justify-between pt-1.5 border-t border-indigo-200/80 text-sm font-bold text-indigo-950">
                                <span>Net Card Settlement</span>
                                <span class="font-mono">{{ formatMoney(currentNetCard) }}</span>
                            </div>
                        </div>
                        <p class="text-[11px] text-indigo-800/80 pt-2">
                            Includes card tap/chip payments and card portions of split-tender orders.
                        </p>
                    </div>
                </div>

                <!-- Close & Reconcile Inputs -->
                <div class="space-y-3 pt-1 border-t border-zinc-200">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-zinc-600">Close &amp; Reconcile Register</span>
                        <button
                            type="button"
                            @click="emit('open-cash-movement')"
                            class="text-[11px] font-semibold text-amber-700 hover:text-amber-800 cursor-pointer"
                        >
                            Need a Deposit or Safe Drop? →
                        </button>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <!-- Counted Drawer Cash -->
                        <div class="p-3 rounded-xl border border-zinc-200 bg-zinc-50/70 space-y-2">
                            <div class="flex items-center justify-between gap-1">
                                <label class="text-xs font-bold text-zinc-800">Physical Counted Cash ($)</label>
                                <span
                                    class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold tabular-nums"
                                    :class="
                                        Math.abs(cashVariance) < 0.005
                                            ? 'bg-emerald-100 text-emerald-800'
                                            : cashVariance < 0
                                              ? 'bg-rose-100 text-rose-700'
                                              : 'bg-amber-100 text-amber-800'
                                    "
                                >
                                    {{
                                        Math.abs(cashVariance) < 0.005
                                            ? 'Balanced ($0.00)'
                                            : `${cashVariance > 0 ? '+' : ''}${formatMoney(cashVariance)}`
                                    }}
                                </span>
                            </div>
                            <input
                                v-model="countedCash"
                                type="number"
                                step="0.01"
                                min="0"
                                class="w-full h-11 px-3 rounded-lg border border-zinc-200 bg-white font-mono text-base font-bold tabular-nums"
                            />
                            <div class="flex items-center justify-between text-[11px] text-zinc-500">
                                <span>Expected: <strong class="font-mono text-zinc-800">{{ formatMoney(activeShift.expected_cash) }}</strong></span>
                                <button
                                    type="button"
                                    @click="countedCash = Number(activeShift.expected_cash || 0).toFixed(2)"
                                    class="text-emerald-700 hover:text-emerald-800 font-semibold cursor-pointer"
                                >
                                    Match Expected
                                </button>
                            </div>
                        </div>

                        <!-- Optional Card Terminal Batch Total Check -->
                        <div class="p-3 rounded-xl border border-indigo-200/90 bg-indigo-50/40 space-y-2">
                            <div class="flex items-center justify-between gap-1">
                                <label class="text-xs font-bold text-indigo-950">Card Terminal Batch ($ Opt.)</label>
                                <span
                                    v-if="terminalVariance === null"
                                    class="px-2 py-0.5 rounded-full text-[10px] font-mono font-semibold bg-indigo-100 text-indigo-700"
                                >
                                    Optional
                                </span>
                                <span
                                    v-else
                                    class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold tabular-nums"
                                    :class="
                                        Math.abs(terminalVariance) < 0.005
                                            ? 'bg-emerald-100 text-emerald-800'
                                            : 'bg-rose-100 text-rose-700'
                                    "
                                >
                                    {{
                                        Math.abs(terminalVariance) < 0.005
                                            ? 'Batch Matched'
                                            : `${terminalVariance > 0 ? '+' : ''}${formatMoney(terminalVariance)}`
                                    }}
                                </span>
                            </div>
                            <input
                                v-model="countedTerminal"
                                type="number"
                                step="0.01"
                                min="0"
                                :placeholder="currentNetCard.toFixed(2)"
                                class="w-full h-11 px-3 rounded-lg border border-indigo-200 bg-white font-mono text-base font-bold tabular-nums"
                            />
                            <div class="flex items-center justify-between text-[11px] text-indigo-700">
                                <span>Net Card: <strong class="font-mono text-indigo-950">{{ formatMoney(currentNetCard) }}</strong></span>
                                <button
                                    type="button"
                                    @click="countedTerminal = currentNetCard.toFixed(2)"
                                    class="text-indigo-700 hover:text-indigo-900 font-semibold cursor-pointer"
                                >
                                    Match ({{ formatMoney(currentNetCard) }})
                                </button>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-zinc-700 mb-1">Closing Note (Optional)</label>
                        <input
                            v-model="notes"
                            type="text"
                            placeholder="End-of-shift drawer or terminal batch notes"
                            class="w-full h-9 px-3 rounded-lg border border-zinc-200 bg-white text-xs"
                        />
                    </div>

                    <div v-if="error" class="p-2.5 rounded-lg bg-rose-50 border border-rose-200 text-xs text-rose-700 flex items-center gap-2">
                        <AlertCircle class="w-4 h-4 shrink-0" />
                        <span>{{ error }}</span>
                    </div>

                    <button
                        type="button"
                        :disabled="loading"
                        @click="submitClose"
                        class="w-full h-11 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-white font-bold text-sm cursor-pointer disabled:opacity-50"
                    >
                        {{ loading ? 'Closing Shift...' : 'Close & Reconcile Shift' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

