<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import {
    X,
    Banknote,
    CreditCard,
    Split,
    CheckCircle2,
    AlertCircle,
    Delete,
} from 'lucide-vue-next';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    initialMethod: {
        type: String,
        default: 'cash',
    },
    subtotal: {
        type: Number,
        default: 0,
    },
    discountTotal: {
        type: Number,
        default: 0,
    },
    taxAmount: {
        type: Number,
        default: 0,
    },
    grandTotal: {
        type: Number,
        default: 0,
    },
    totalUnits: {
        type: Number,
        default: 0,
    },
    customer: {
        type: Object,
        default: null,
    },
    loading: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['close', 'confirm']);

const method = ref('cash');
const cashInput = ref('');
const hasTypedCash = ref(false);
const splitCashAmount = ref('');
const splitCashTendered = ref('');
const paymentReference = ref('');

const round2 = (value) => {
    const n = Number(value || 0);
    if (!Number.isFinite(n) || n === 0) {
        return 0;
    }

    return Math.sign(n) * (Math.round(Math.abs(n) * 100 + 1e-9) / 100);
};

const formatMoney = (val) => `$${Number(val || 0).toFixed(2)}`;

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) {
            return;
        }
        method.value = props.initialMethod || 'cash';
        cashInput.value = round2(props.grandTotal).toFixed(2);
        hasTypedCash.value = false;
        const half = round2(props.grandTotal / 2).toFixed(2);
        splitCashAmount.value = half;
        splitCashTendered.value = half;
        paymentReference.value = '';
    },
);

watch(
    () => props.grandTotal,
    (nextTotal) => {
        if (!props.open) {
            return;
        }
        if (!hasTypedCash.value) {
            cashInput.value = round2(nextTotal).toFixed(2);
        }
        const half = round2(nextTotal / 2).toFixed(2);
        if (round2(splitCashAmount.value) >= round2(nextTotal)) {
            splitCashAmount.value = half;
            splitCashTendered.value = half;
        }
    },
);

const smartCashPresets = computed(() => {
    const total = Number(props.grandTotal || 0);
    const candidates = [
        total,
        Math.ceil(total / 5) * 5,
        Math.ceil(total / 10) * 10,
        20,
        50,
        100,
    ].filter((amt) => amt >= total && amt > 0);

    const unique = [];
    for (const val of candidates) {
        const rounded = Math.round(val * 100) / 100;
        if (!unique.some((u) => Math.abs(u - rounded) < 0.005)) {
            unique.push(rounded);
        }
    }
    return unique.slice(0, 5);
});

const tapNumpad = (key) => {
    if (key === 'clear') {
        cashInput.value = '';
        hasTypedCash.value = true;
        return;
    }

    if (key === 'back') {
        cashInput.value = String(cashInput.value).slice(0, -1);
        hasTypedCash.value = true;
        return;
    }

    const base = hasTypedCash.value ? String(cashInput.value) : '';
    hasTypedCash.value = true;

    if (key === '.' && base.includes('.')) {
        return;
    }

    if (base.includes('.')) {
        const decimals = base.split('.')[1] || '';
        if (decimals.length >= 2) {
            return;
        }
    }

    cashInput.value = key === '.' && base === '' ? '0.' : `${base}${key}`;
};

const selectCashPreset = (amount) => {
    cashInput.value = Number(amount).toFixed(2);
    hasTypedCash.value = false;
};

const splitCashPortion = computed(() => Math.max(0, round2(splitCashAmount.value)));

const splitTerminalPortion = computed(() =>
    Math.max(0, round2(props.grandTotal - splitCashPortion.value)),
);

const splitTenderInvalid = computed(
    () =>
        method.value === 'split' &&
        (splitCashPortion.value <= 0 || splitTerminalPortion.value <= 0),
);

const cashShortfall = computed(() => {
    if (method.value === 'cash') {
        const tendered = round2(cashInput.value);
        return Math.max(0, round2(props.grandTotal - tendered));
    }

    if (method.value === 'split') {
        const cashPortion = splitCashPortion.value;
        const tendered =
            splitCashTendered.value !== '' ? round2(splitCashTendered.value) : cashPortion;
        return Math.max(0, round2(cashPortion - tendered));
    }

    return 0;
});

const changeDue = computed(() => {
    if (method.value === 'cash') {
        const tendered = round2(cashInput.value);
        return Math.max(0, round2(tendered - props.grandTotal));
    }

    if (method.value === 'split') {
        const cashPortion = splitCashPortion.value;
        const tendered =
            splitCashTendered.value !== '' ? round2(splitCashTendered.value) : cashPortion;
        return Math.max(0, round2(tendered - cashPortion));
    }

    return 0;
});

const canSubmit = computed(() => {
    if (props.loading) {
        return false;
    }

    if (method.value === 'cash') {
        return round2(cashInput.value) + 0.0001 >= round2(props.grandTotal);
    }

    if (method.value === 'split') {
        const cash = splitCashPortion.value;
        const term = splitTerminalPortion.value;
        const tendered =
            splitCashTendered.value !== '' ? round2(splitCashTendered.value) : cash;
        return (
            cash > 0 &&
            term > 0 &&
            Math.abs(round2(cash + term) - round2(props.grandTotal)) <= 0.0001 &&
            tendered + 0.0001 >= cash
        );
    }

    return true;
});

const submitPayment = () => {
    if (!canSubmit.value) {
        return;
    }

    if (method.value === 'cash') {
        emit('confirm', {
            payment_method: 'cash',
            cash_tendered:
                cashInput.value !== '' ? round2(cashInput.value) : round2(props.grandTotal),
            payment_reference: null,
        });
        return;
    }

    if (method.value === 'split') {
        const cashAmt = splitCashPortion.value;
        const tendered =
            splitCashTendered.value !== '' && round2(splitCashTendered.value) >= cashAmt
                ? round2(splitCashTendered.value)
                : cashAmt;

        emit('confirm', {
            payment_method: 'split',
            cash_amount: cashAmt,
            terminal_amount: splitTerminalPortion.value,
            cash_tendered: tendered,
            payment_reference: paymentReference.value || null,
        });
        return;
    }

    emit('confirm', {
        payment_method: 'pos_terminal',
        payment_reference: paymentReference.value || null,
    });
};

const handleKeydown = (e) => {
    if (!props.open) {
        return;
    }
    if (e.key === 'Escape' && !props.loading) {
        emit('close');
    }
};

onMounted(() => window.addEventListener('keydown', handleKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', handleKeydown));
</script>

<template>
    <div
        v-if="open"
        class="fixed inset-0 z-50 bg-zinc-950/60 backdrop-blur-sm flex items-center justify-center p-4"
        @click.self="!loading && emit('close')"
    >
        <div class="bg-white border border-zinc-200 rounded-xl shadow-2xl w-full max-w-2xl overflow-hidden grid grid-cols-1 md:grid-cols-12">
            <!-- Left Order Summary Rail -->
            <div class="md:col-span-5 bg-zinc-50 border-b md:border-b-0 md:border-r border-zinc-200 p-5 flex flex-col justify-between">
                <div class="space-y-4">
                    <div>
                        <span class="text-[11px] font-mono uppercase tracking-wider font-semibold text-zinc-500">
                            Amount Due
                        </span>
                        <div class="text-3xl font-bold font-mono text-zinc-950 tracking-tight tabular-nums mt-0.5">
                            {{ formatMoney(grandTotal) }}
                        </div>
                    </div>

                    <div class="space-y-1.5 pt-3 border-t border-zinc-200/80 text-xs tabular-nums">
                        <div class="flex justify-between text-zinc-500">
                            <span>Items ({{ totalUnits }})</span>
                            <span class="font-mono text-zinc-800">{{ formatMoney(subtotal) }}</span>
                        </div>
                        <div v-if="discountTotal > 0" class="flex justify-between text-emerald-700 font-medium">
                            <span>Discounts & Loyalty</span>
                            <span class="font-mono">-{{ formatMoney(discountTotal) }}</span>
                        </div>
                        <div class="flex justify-between text-zinc-500">
                            <span>Sales Tax</span>
                            <span class="font-mono text-zinc-800">{{ formatMoney(taxAmount) }}</span>
                        </div>
                    </div>

                    <div v-if="customer" class="p-2.5 rounded-lg bg-white border border-zinc-200 text-xs">
                        <div class="font-bold text-zinc-900 truncate">{{ customer.name }}</div>
                        <div class="text-[11px] font-mono text-zinc-500">
                            Balance: {{ customer.reward_points }} pts
                        </div>
                    </div>
                </div>

                <!-- Dynamic Change Due / Terminal Callout -->
                <div class="mt-5 pt-4 border-t border-zinc-200">
                    <div
                        v-if="method === 'cash' || method === 'split'"
                        class="p-3 rounded-lg border"
                        :class="
                            cashShortfall > 0
                                ? 'bg-amber-50 border-amber-200 text-amber-900'
                                : 'bg-emerald-50 border-emerald-200 text-emerald-900'
                        "
                    >
                        <div class="text-[10px] font-mono uppercase font-bold tracking-wider">
                            {{ cashShortfall > 0 ? 'Remaining Cash Needed' : 'Change Due to Customer' }}
                        </div>
                        <div class="text-2xl font-bold font-mono tabular-nums mt-0.5">
                            {{ formatMoney(cashShortfall > 0 ? cashShortfall : changeDue) }}
                        </div>
                    </div>

                    <div v-else class="p-3 rounded-lg bg-zinc-900 text-white">
                        <div class="text-[10px] font-mono uppercase text-zinc-400">External Card Terminal</div>
                        <div class="text-xl font-bold font-mono text-emerald-400 tabular-nums mt-0.5">
                            {{ formatMoney(grandTotal) }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Interactive Tender Controls -->
            <div class="md:col-span-7 p-5 space-y-4">
                <div class="flex items-center justify-between gap-2">
                    <!-- Tender Tabs -->
                    <div class="inline-flex rounded-lg bg-zinc-100 p-1 border border-zinc-200 flex-1">
                        <button
                            type="button"
                            @click="method = 'cash'"
                            class="flex-1 h-8 rounded-md text-xs font-semibold inline-flex items-center justify-center gap-1.5 transition-all cursor-pointer"
                            :class="
                                method === 'cash'
                                    ? 'bg-white text-zinc-950 shadow-sm'
                                    : 'text-zinc-600 hover:text-zinc-900'
                            "
                        >
                            <Banknote class="w-3.5 h-3.5" />
                            <span>Cash</span>
                        </button>
                        <button
                            type="button"
                            @click="method = 'pos_terminal'"
                            class="flex-1 h-8 rounded-md text-xs font-semibold inline-flex items-center justify-center gap-1.5 transition-all cursor-pointer"
                            :class="
                                method === 'pos_terminal'
                                    ? 'bg-white text-zinc-950 shadow-sm'
                                    : 'text-zinc-600 hover:text-zinc-900'
                            "
                        >
                            <CreditCard class="w-3.5 h-3.5" />
                            <span>Card</span>
                        </button>
                        <button
                            type="button"
                            @click="method = 'split'"
                            class="flex-1 h-8 rounded-md text-xs font-semibold inline-flex items-center justify-center gap-1.5 transition-all cursor-pointer"
                            :class="
                                method === 'split'
                                    ? 'bg-white text-zinc-950 shadow-sm'
                                    : 'text-zinc-600 hover:text-zinc-900'
                            "
                        >
                            <Split class="w-3.5 h-3.5" />
                            <span>Split</span>
                        </button>
                    </div>

                    <button
                        type="button"
                        :disabled="loading"
                        @click="emit('close')"
                        class="w-8 h-8 rounded-lg text-zinc-400 hover:text-zinc-800 hover:bg-zinc-100 flex items-center justify-center cursor-pointer"
                    >
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <!-- TAB 1: Cash Tender + Quick Bills + Numpad -->
                <div v-if="method === 'cash'" class="space-y-3">
                    <!-- Quick Bill Presets -->
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="(amt, idx) in smartCashPresets"
                            :key="amt"
                            type="button"
                            @click="selectCashPreset(amt)"
                            class="flex-1 min-w-[60px] h-9 px-2 rounded-lg border text-xs font-mono font-bold transition-colors cursor-pointer tabular-nums"
                            :class="
                                Math.abs(Number(cashInput || 0) - amt) < 0.005
                                    ? 'bg-zinc-900 border-zinc-900 text-white'
                                    : 'bg-zinc-50 border-zinc-200 text-zinc-800 hover:bg-zinc-100'
                            "
                        >
                            {{ idx === 0 ? 'Exact' : `$${amt % 1 === 0 ? amt : amt.toFixed(2)}` }}
                        </button>
                    </div>

                    <!-- Cash Tendered Input -->
                    <div class="relative">
                        <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-sm font-mono font-bold text-zinc-400">$</span>
                        <input
                            v-model="cashInput"
                            @input="hasTypedCash = true"
                            @keydown.enter.prevent="submitPayment"
                            type="number"
                            step="0.01"
                            min="0"
                            class="w-full h-11 pl-8 pr-16 rounded-lg border border-zinc-200 bg-white font-mono text-lg font-bold text-zinc-950 tabular-nums"
                        />
                        <button
                            type="button"
                            @click="tapNumpad('clear')"
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 px-2 py-0.5 rounded bg-zinc-100 hover:bg-zinc-200 text-[10px] font-mono font-bold text-zinc-600 cursor-pointer"
                        >
                            CLR
                        </button>
                    </div>

                    <!-- Touch Numpad -->
                    <div class="grid grid-cols-3 gap-1.5">
                        <button
                            v-for="digit in ['7', '8', '9', '4', '5', '6', '1', '2', '3', '00', '0', '.']"
                            :key="digit"
                            type="button"
                            @click="tapNumpad(digit)"
                            class="h-10 rounded-lg bg-zinc-100 hover:bg-zinc-200 active:bg-zinc-300 text-zinc-900 font-mono text-sm font-bold transition-colors cursor-pointer"
                        >
                            {{ digit }}
                        </button>
                    </div>
                </div>

                <!-- TAB 2: External Card Terminal -->
                <div v-else-if="method === 'pos_terminal'" class="space-y-4 py-2">
                    <div class="p-4 rounded-xl bg-zinc-50 border border-zinc-200 text-center space-y-1.5">
                        <CreditCard class="w-7 h-7 text-emerald-600 mx-auto" />
                        <div class="text-sm font-bold text-zinc-900">
                            Collect {{ formatMoney(grandTotal) }} on External Terminal
                        </div>
                        <p class="text-xs text-zinc-500">
                            Have customer tap, insert, or swipe card on counter terminal, then confirm approval below.
                        </p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-zinc-700 mb-1">
                            Terminal Auth / Slip Reference (Optional)
                        </label>
                        <input
                            v-model="paymentReference"
                            @keydown.enter.prevent="submitPayment"
                            type="text"
                            placeholder="e.g. AUTH-4829"
                            class="w-full h-10 px-3 rounded-lg border border-zinc-200 bg-white text-xs font-mono"
                        />
                    </div>
                </div>

                <!-- TAB 3: Split Tender (Cash + External Card Terminal) -->
                <div v-else class="space-y-3 py-1">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-zinc-700 mb-1">
                                1. Cash Portion ($)
                            </label>
                            <input
                                v-model="splitCashAmount"
                                @input="splitCashTendered = splitCashAmount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                :max="grandTotal"
                                class="w-full h-10 px-3 rounded-lg border border-zinc-200 bg-white font-mono text-sm font-bold tabular-nums"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-zinc-700 mb-1">
                                2. Card Terminal ($)
                            </label>
                            <div class="h-10 px-3 rounded-lg bg-zinc-900 text-emerald-400 font-mono text-sm font-bold flex items-center tabular-nums">
                                {{ formatMoney(splitTerminalPortion) }}
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-zinc-700 mb-1">
                                Cash Handed by Customer ($)
                            </label>
                            <input
                                v-model="splitCashTendered"
                                type="number"
                                step="0.01"
                                min="0"
                                class="w-full h-9 px-3 rounded-lg border border-zinc-200 bg-white font-mono text-xs font-semibold tabular-nums"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-zinc-700 mb-1">
                                Card Auth Ref (Optional)
                            </label>
                            <input
                                v-model="paymentReference"
                                type="text"
                                placeholder="AUTH-#"
                                class="w-full h-9 px-3 rounded-lg border border-zinc-200 bg-white font-mono text-xs"
                            />
                        </div>
                    </div>

                    <p
                        v-if="splitTenderInvalid"
                        class="text-[11px] font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-2.5 py-1.5"
                    >
                        Both Cash and Card Terminal portions must exceed $0.00 (use Cash or Card tab for 100%).
                    </p>
                </div>

                <!-- Validation Error -->
                <div
                    v-if="error"
                    class="p-2.5 rounded-lg bg-rose-50 border border-rose-200 text-xs font-medium text-rose-700 flex items-center gap-2"
                >
                    <AlertCircle class="w-4 h-4 text-rose-600 shrink-0" />
                    <span>{{ error }}</span>
                </div>

                <!-- Finalize Button -->
                <button
                    type="button"
                    :disabled="!canSubmit"
                    @click="submitPayment"
                    class="w-full h-12 rounded-xl bg-emerald-600 hover:bg-emerald-500 active:bg-emerald-700 text-white font-bold text-sm flex items-center justify-center gap-2 shadow-sm transition-colors disabled:opacity-40 cursor-pointer"
                >
                    <CheckCircle2 class="w-4 h-4" />
                    <span>
                        {{
                            loading
                                ? 'Recording Sale...'
                                : method === 'pos_terminal'
                                  ? `Confirm Card Approved · ${formatMoney(grandTotal)}`
                                  : `Complete Sale · ${formatMoney(grandTotal)}`
                        }}
                    </span>
                </button>
            </div>
        </div>
    </div>
</template>

