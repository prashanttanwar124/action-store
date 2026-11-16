<script setup>
import axios from 'axios';
import { computed, ref, watch } from 'vue';
import {
    X,
    Coins,
    ArrowDownLeft,
    ArrowUpRight,
    AlertTriangle,
    AlertCircle,
    CheckCircle2,
    Wallet,
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
    initialType: {
        type: String,
        default: null,
    },
});

const emit = defineEmits(['close', 'shift-updated']);

const round2 = (value) => {
    const n = Number(value || 0);
    if (!Number.isFinite(n) || n === 0) {
        return 0;
    }

    return Math.sign(n) * (Math.round(Math.abs(n) * 100 + 1e-9) / 100);
};

const formatMoney = (val) => `$${Number(val || 0).toFixed(2)}`;

const movementType = ref('deposit');
const movementAmount = ref('');
const movementReason = ref('Coins & Change Top-Up');
const movementNotes = ref('');
const loading = ref(false);
const error = ref('');
const movementSuccess = ref('');

const depositReasons = ['Coins & Change Top-Up', 'Register Float Top-Up', 'Petty Cash Return'];
const withdrawalReasons = ['Safe Drop / Bank Bag', 'Excess Cash Pickup', 'Vendor / Petty Payout'];

const safeDropThreshold = computed(() =>
    Math.max(1, Number(props.drawerConfig?.safe_drop_alert_threshold ?? 1000)),
);

const currentExpectedCash = computed(() => round2(props.activeShift?.expected_cash || 0));

const isHighDrawerCash = computed(() =>
    Boolean(
        props.activeShift &&
            (props.activeShift.safe_drop_recommended ||
                currentExpectedCash.value >= safeDropThreshold.value),
    ),
);

const selectMovementType = (nextType) => {
    movementType.value = nextType;
    error.value = '';
    movementSuccess.value = '';
    movementReason.value =
        nextType === 'deposit' ? depositReasons[0] : withdrawalReasons[0];
};

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) {
            return;
        }
        error.value = '';
        movementSuccess.value = '';
        movementAmount.value = '';
        movementNotes.value = '';

        const preferred =
            props.initialType === 'deposit' || props.initialType === 'withdrawal'
                ? props.initialType
                : isHighDrawerCash.value
                  ? 'withdrawal'
                  : 'deposit';
        selectMovementType(preferred);
    },
);

const parsedMovementAmount = computed(() => round2(movementAmount.value));

const withdrawalExceedsDrawer = computed(
    () =>
        movementType.value === 'withdrawal' &&
        parsedMovementAmount.value > currentExpectedCash.value + 0.0001,
);

const projectedDrawerBalance = computed(() => {
    const delta =
        movementType.value === 'deposit'
            ? parsedMovementAmount.value
            : -parsedMovementAmount.value;
    return round2(currentExpectedCash.value + delta);
});

const keepFloatDropAmount = computed(() => {
    const floatTarget =
        Number(props.activeShift?.opening_cash || 0) > 0
            ? round2(props.activeShift.opening_cash)
            : 200;
    return Math.max(0, round2(currentExpectedCash.value - floatTarget));
});

const canSubmitMovement = computed(
    () =>
        Boolean(props.activeShift) &&
        !loading.value &&
        parsedMovementAmount.value > 0 &&
        movementReason.value.trim().length >= 2 &&
        !withdrawalExceedsDrawer.value,
);

const submitMovement = async () => {
    if (!props.activeShift || !canSubmitMovement.value) {
        return;
    }

    loading.value = true;
    error.value = '';
    movementSuccess.value = '';

    try {
        const { data } = await axios.post(
            route('pos.shifts.movements.store', props.activeShift.id),
            {
                type: movementType.value,
                amount: parsedMovementAmount.value,
                reason: movementReason.value.trim(),
                notes: movementNotes.value.trim() || null,
            },
        );

        movementAmount.value = '';
        movementNotes.value = '';
        const verb = movementType.value === 'deposit' ? 'Deposited' : 'Withdrew';
        movementSuccess.value = `${verb} ${formatMoney(data.movement.amount)} · New drawer balance ${formatMoney(data.shift.expected_cash)}`;
        emit('shift-updated', data);
    } catch (err) {
        const errors = err.response?.data?.errors;
        error.value = errors
            ? Object.values(errors).flat()[0]
            : err.response?.data?.message || 'Unable to record drawer adjustment.';
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <div
        v-if="open && activeShift"
        class="fixed inset-0 z-50 bg-zinc-950/60 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto"
        @click.self="!loading && emit('close')"
    >
        <div class="bg-white border border-zinc-200 rounded-2xl shadow-2xl w-full max-w-lg max-h-[92vh] overflow-y-auto p-5 space-y-4 my-auto">
            <!-- Header -->
            <div class="flex items-start justify-between gap-3">
                <div>
                    <span class="inline-flex items-center gap-1.5 text-[11px] font-mono uppercase font-bold text-amber-700">
                        <Coins class="w-3.5 h-3.5" />
                        Cash Drawer Adjustments · Shift #{{ activeShift.id }}
                    </span>
                    <h3 class="text-base font-bold text-zinc-950 mt-0.5">
                        Deposit Coins / Safe Drop Withdrawal
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

            <!-- Current Expected Cash Strip -->
            <div class="p-3 rounded-xl bg-zinc-900 text-white flex items-center justify-between gap-3 tabular-nums">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-zinc-800 border border-zinc-700 flex items-center justify-center text-emerald-400">
                        <Wallet class="w-4 h-4" />
                    </div>
                    <div>
                        <div class="text-[10px] font-mono uppercase text-zinc-400">Current Expected Drawer</div>
                        <div class="text-base font-mono font-bold text-white">{{ formatMoney(activeShift.expected_cash) }}</div>
                    </div>
                </div>
                <div class="text-right text-[11px] font-mono">
                    <div class="text-emerald-400">Deposits: +{{ formatMoney(activeShift.cash_deposits) }}</div>
                    <div class="text-amber-300">Drops: −{{ formatMoney(activeShift.cash_withdrawals) }}</div>
                </div>
            </div>

            <!-- High Cash Safe Drop Warning -->
            <div
                v-if="isHighDrawerCash"
                class="p-3 rounded-xl bg-amber-50 border border-amber-300 text-amber-950 text-xs flex items-start gap-2.5"
            >
                <AlertTriangle class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
                <div>
                    <div class="font-bold">Safe Drop Recommended</div>
                    <p class="text-amber-800 mt-0.5">
                        Drawer has <span class="font-mono font-bold">{{ formatMoney(activeShift.expected_cash) }}</span>
                        (above {{ formatMoney(safeDropThreshold) }} alert limit). Drop excess cash into the safe.
                    </p>
                </div>
            </div>

            <!-- Mode Selector: Deposit vs Safe Drop -->
            <div class="grid grid-cols-2 gap-2">
                <button
                    type="button"
                    @click="selectMovementType('deposit')"
                    class="h-11 rounded-xl border text-xs font-bold inline-flex items-center justify-center gap-1.5 transition-colors cursor-pointer"
                    :class="
                        movementType === 'deposit'
                            ? 'bg-emerald-600 border-emerald-600 text-white shadow-sm'
                            : 'bg-zinc-50 border-zinc-200 text-zinc-700 hover:bg-zinc-100'
                    "
                >
                    <ArrowDownLeft class="w-4 h-4" />
                    <span>Deposit / Add Coins</span>
                </button>
                <button
                    type="button"
                    @click="selectMovementType('withdrawal')"
                    class="h-11 rounded-xl border text-xs font-bold inline-flex items-center justify-center gap-1.5 transition-colors cursor-pointer"
                    :class="
                        movementType === 'withdrawal'
                            ? 'bg-amber-600 border-amber-600 text-white shadow-sm'
                            : 'bg-zinc-50 border-zinc-200 text-zinc-700 hover:bg-zinc-100'
                    "
                >
                    <ArrowUpRight class="w-4 h-4" />
                    <span>Withdraw / Safe Drop</span>
                </button>
            </div>

            <!-- Quick Amount Presets -->
            <div class="flex flex-wrap gap-1.5">
                <template v-if="movementType === 'deposit'">
                    <button
                        v-for="amt in [20, 50, 100, 200]"
                        :key="amt"
                        type="button"
                        @click="movementAmount = amt.toFixed(2)"
                        class="flex-1 min-w-[60px] h-9 px-2 rounded-lg border border-zinc-200 bg-zinc-50 hover:bg-zinc-100 text-xs font-mono font-bold text-zinc-800 cursor-pointer"
                    >
                        +${{ amt }}
                    </button>
                </template>
                <template v-else>
                    <button
                        v-for="amt in [100, 200, 500]"
                        :key="amt"
                        type="button"
                        :disabled="amt > currentExpectedCash + 0.0001"
                        @click="movementAmount = amt.toFixed(2)"
                        class="flex-1 min-w-[56px] h-9 px-2 rounded-lg border border-zinc-200 bg-zinc-50 hover:bg-zinc-100 text-xs font-mono font-bold text-zinc-800 disabled:opacity-40 cursor-pointer"
                    >
                        −${{ amt }}
                    </button>
                    <button
                        v-if="keepFloatDropAmount > 0"
                        type="button"
                        @click="movementAmount = keepFloatDropAmount.toFixed(2)"
                        class="h-9 px-2.5 rounded-lg border border-amber-300 bg-amber-50 hover:bg-amber-100 text-[11px] font-bold text-amber-900 cursor-pointer"
                    >
                        Keep {{ formatMoney(Number(activeShift.opening_cash || 0) > 0 ? activeShift.opening_cash : 200) }} Float
                    </button>
                </template>
            </div>

            <!-- Reason Presets -->
            <div class="flex flex-wrap gap-1.5">
                <button
                    v-for="preset in movementType === 'deposit' ? depositReasons : withdrawalReasons"
                    :key="preset"
                    type="button"
                    @click="movementReason = preset"
                    class="px-2.5 py-1 rounded-md border text-[11px] font-semibold cursor-pointer"
                    :class="
                        movementReason === preset
                            ? 'bg-zinc-900 border-zinc-900 text-white'
                            : 'bg-white border-zinc-200 text-zinc-600 hover:bg-zinc-100'
                    "
                >
                    {{ preset }}
                </button>
            </div>

            <!-- Inputs -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                <div>
                    <label class="block text-xs font-semibold text-zinc-700 mb-1">
                        {{ movementType === 'deposit' ? 'Deposit Amount ($)' : 'Withdraw Amount ($)' }}
                    </label>
                    <input
                        v-model="movementAmount"
                        @keydown.enter.prevent="submitMovement"
                        type="number"
                        step="0.01"
                        min="0.01"
                        placeholder="0.00"
                        class="w-full h-11 px-3 rounded-lg border border-zinc-200 bg-white font-mono text-base font-bold tabular-nums"
                    />
                </div>
                <div>
                    <label class="block text-xs font-semibold text-zinc-700 mb-1">Reason *</label>
                    <input
                        v-model="movementReason"
                        @keydown.enter.prevent="submitMovement"
                        type="text"
                        placeholder="Reason for adjustment..."
                        class="w-full h-11 px-3 rounded-lg border border-zinc-200 bg-white text-xs font-medium"
                    />
                </div>
            </div>

            <div>
                <input
                    v-model="movementNotes"
                    @keydown.enter.prevent="submitMovement"
                    type="text"
                    placeholder="Optional note (e.g. Drop bag #4, quarters & $1s roll)..."
                    class="w-full h-9 px-3 rounded-lg border border-zinc-200 bg-white text-xs"
                />
            </div>

            <!-- Preview / Guard Bar -->
            <div
                v-if="parsedMovementAmount > 0"
                class="px-3 py-2 rounded-lg border text-xs flex items-center justify-between tabular-nums"
                :class="
                    withdrawalExceedsDrawer
                        ? 'bg-rose-50 border-rose-200 text-rose-800'
                        : 'bg-zinc-50 border-zinc-200 text-zinc-700'
                "
            >
                <span v-if="withdrawalExceedsDrawer" class="font-semibold">
                    Exceeds drawer cash ({{ formatMoney(currentExpectedCash) }} available)
                </span>
                <span v-else>Resulting Expected Drawer Balance</span>
                <span v-if="!withdrawalExceedsDrawer" class="font-mono font-bold text-zinc-950">
                    {{ formatMoney(projectedDrawerBalance) }}
                </span>
            </div>

            <div v-if="movementSuccess" class="p-2.5 rounded-lg bg-emerald-50 border border-emerald-200 text-xs text-emerald-800 font-semibold flex items-center gap-2">
                <CheckCircle2 class="w-4 h-4 shrink-0 text-emerald-600" />
                <span>{{ movementSuccess }}</span>
            </div>

            <div v-if="error" class="p-2.5 rounded-lg bg-rose-50 border border-rose-200 text-xs text-rose-700 flex items-center gap-2">
                <AlertCircle class="w-4 h-4 shrink-0" />
                <span>{{ error }}</span>
            </div>

            <div class="flex items-center gap-2">
                <button
                    type="button"
                    @click="emit('close')"
                    class="h-11 px-4 rounded-xl border border-zinc-200 bg-white hover:bg-zinc-50 text-zinc-700 font-semibold text-xs cursor-pointer"
                >
                    Close
                </button>
                <button
                    type="button"
                    :disabled="!canSubmitMovement"
                    @click="submitMovement"
                    class="flex-1 h-11 rounded-xl text-white font-bold text-xs cursor-pointer disabled:opacity-45 shadow-sm transition-colors"
                    :class="
                        movementType === 'deposit'
                            ? 'bg-emerald-600 hover:bg-emerald-500'
                            : 'bg-amber-600 hover:bg-amber-500'
                    "
                >
                    {{
                        loading
                            ? 'Saving...'
                            : movementType === 'deposit'
                              ? `Record Deposit (+${formatMoney(parsedMovementAmount)})`
                              : `Record Safe Drop / Withdrawal (−${formatMoney(parsedMovementAmount)})`
                    }}
                </button>
            </div>

            <!-- Shift Cash Movement Log -->
            <div v-if="activeShift.movements && activeShift.movements.length > 0" class="pt-3 border-t border-zinc-200 space-y-1.5">
                <div class="text-[11px] font-bold uppercase tracking-wider text-zinc-500">
                    Shift Cash Movement Log ({{ activeShift.movements.length }})
                </div>
                <div class="max-h-40 overflow-y-auto divide-y divide-zinc-100 rounded-xl border border-zinc-200 bg-zinc-50/70">
                    <div
                        v-for="entry in activeShift.movements"
                        :key="entry.id"
                        class="px-3 py-2 flex items-center justify-between gap-2 text-xs"
                    >
                        <div class="min-w-0">
                            <div class="font-semibold text-zinc-900 truncate">
                                {{ entry.reason }}
                            </div>
                            <div class="text-[10px] text-zinc-500 truncate">
                                {{ entry.cashier_name }} · {{ entry.created_at_formatted }}
                                <template v-if="entry.notes"> · {{ entry.notes }}</template>
                            </div>
                        </div>
                        <div class="text-right shrink-0 font-mono tabular-nums">
                            <div
                                class="font-bold"
                                :class="entry.type === 'deposit' ? 'text-emerald-700' : 'text-amber-700'"
                            >
                                {{ entry.type === 'deposit' ? '+' : '−' }}{{ formatMoney(entry.amount) }}
                            </div>
                            <div class="text-[10px] text-zinc-400">
                                Bal: {{ formatMoney(entry.balance_after) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

