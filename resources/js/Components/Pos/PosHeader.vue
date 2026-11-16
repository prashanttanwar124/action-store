<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import {
    ScanBarcode,
    History,
    PauseCircle,
    Wallet,
    Coins,
    AlertTriangle,
    Lock,
    LogOut,
    Volume2,
    VolumeX,
    LayoutDashboard,
    ChevronDown,
} from 'lucide-vue-next';

const props = defineProps({
    storeInfo: {
        type: Object,
        default: () => ({}),
    },
    activeTab: {
        type: String,
        default: 'register',
    },
    activeShift: {
        type: Object,
        default: null,
    },
    drawerConfig: {
        type: Object,
        default: () => ({ safe_drop_alert_threshold: 1000 }),
    },
    activeCashier: {
        type: Object,
        default: () => ({ name: 'Cashier' }),
    },
    heldCount: {
        type: Number,
        default: 0,
    },
    soundEnabled: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits([
    'update:activeTab',
    'open-held',
    'open-shift',
    'open-cash-movement',
    'open-pin-switch',
    'lock-register',
    'toggle-sound',
    'logout',
]);

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

const cashierInitials = computed(() => {
    const name = String(props.activeCashier?.name || 'CS').trim();
    const parts = name.split(/\s+/);
    if (parts.length >= 2) {
        return `${parts[0][0]}${parts[1][0]}`.toUpperCase();
    }
    return name.slice(0, 2).toUpperCase();
});

const liveTime = ref('');
let timer = null;

const refreshTime = () => {
    liveTime.value = new Date().toLocaleTimeString([], {
        hour: '2-digit',
        minute: '2-digit',
    });
};

onMounted(() => {
    refreshTime();
    timer = setInterval(refreshTime, 15000);
});

onBeforeUnmount(() => {
    clearInterval(timer);
});
</script>

<template>
    <header class="h-14 bg-zinc-950 text-zinc-100 px-3 sm:px-4 flex items-center justify-between gap-2 border-b border-zinc-800 shrink-0 select-none">
        <!-- Left: Brand + Primary Workspace Tabs -->
        <div class="flex items-center gap-3 min-w-0">
            <div class="flex items-center gap-2.5 shrink-0">
                <div class="w-8 h-8 rounded-lg bg-emerald-500/15 border border-emerald-500/30 flex items-center justify-center text-emerald-400">
                    <ScanBarcode class="w-4 h-4" />
                </div>
                <div class="hidden md:block leading-tight">
                    <div class="text-xs font-bold text-white tracking-tight truncate max-w-[165px]">
                        {{ storeInfo?.brand_name || storeInfo?.name || 'Masala Mart' }}
                    </div>
                    <div class="flex items-center gap-1.5 text-[10px] font-mono text-zinc-400 tabular-nums">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400" />
                        <span>POS · {{ liveTime }}</span>
                    </div>
                </div>
            </div>

            <!-- Workspace Switcher -->
            <nav class="inline-flex items-center rounded-lg bg-zinc-900 p-1 border border-zinc-800">
                <button
                    type="button"
                    @click="emit('update:activeTab', 'register')"
                    class="h-8 px-3 rounded-md text-xs font-semibold inline-flex items-center gap-1.5 transition-all cursor-pointer"
                    :class="
                        activeTab === 'register'
                            ? 'bg-white text-zinc-950 shadow-sm'
                            : 'text-zinc-400 hover:text-white'
                    "
                >
                    <ScanBarcode class="w-3.5 h-3.5" />
                    <span>Register</span>
                    <kbd
                        class="hidden xl:inline-block px-1 py-0.5 rounded text-[10px] font-mono"
                        :class="activeTab === 'register' ? 'bg-zinc-200 text-zinc-700' : 'bg-zinc-800 text-zinc-400'"
                    >
                        F1
                    </kbd>
                </button>

                <button
                    type="button"
                    @click="emit('update:activeTab', 'orders')"
                    class="h-8 px-3 rounded-md text-xs font-semibold inline-flex items-center gap-1.5 transition-all cursor-pointer"
                    :class="
                        activeTab === 'orders'
                            ? 'bg-white text-zinc-950 shadow-sm'
                            : 'text-zinc-400 hover:text-white'
                    "
                >
                    <History class="w-3.5 h-3.5" />
                    <span>Orders & Returns</span>
                    <kbd
                        class="hidden xl:inline-block px-1 py-0.5 rounded text-[10px] font-mono"
                        :class="activeTab === 'orders' ? 'bg-zinc-200 text-zinc-700' : 'bg-zinc-800 text-zinc-400'"
                    >
                        F2
                    </kbd>
                </button>
            </nav>
        </div>

        <!-- Right: Operational Controls -->
        <div class="flex items-center gap-1.5 sm:gap-2 shrink-0">
            <!-- Held Carts -->
            <button
                type="button"
                @click="emit('open-held')"
                class="h-9 px-2.5 sm:px-3 rounded-lg bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 text-xs font-semibold text-zinc-200 inline-flex items-center gap-1.5 transition-colors cursor-pointer"
                title="View Held Counter Carts (F4)"
            >
                <PauseCircle class="w-3.5 h-3.5 text-amber-400" />
                <span class="hidden lg:inline">Held</span>
                <span
                    class="px-1.5 py-0.5 rounded-full text-[10px] font-bold font-mono tabular-nums"
                    :class="heldCount > 0 ? 'bg-amber-400 text-zinc-950' : 'bg-zinc-800 text-zinc-400'"
                >
                    {{ heldCount }}
                </span>
            </button>

            <!-- Dedicated Cash Deposit / Safe Drop Button -->
            <button
                v-if="activeShift"
                type="button"
                @click="emit('open-cash-movement', isHighDrawerCash ? 'withdrawal' : null)"
                class="h-9 px-2.5 sm:px-3 rounded-lg text-xs font-semibold inline-flex items-center gap-1.5 border transition-colors cursor-pointer"
                :class="
                    isHighDrawerCash
                        ? 'bg-amber-500/25 hover:bg-amber-500/35 border-amber-400/70 text-amber-200 animate-pulse'
                        : 'bg-zinc-900 hover:bg-zinc-800 border-zinc-700 text-zinc-200'
                "
                :title="
                    isHighDrawerCash
                        ? `High cash in drawer (>= ${formatMoney(safeDropThreshold)}). Click to record a Safe Drop.`
                        : 'Deposit Coins / Safe Drop Withdrawal'
                "
            >
                <AlertTriangle v-if="isHighDrawerCash" class="w-3.5 h-3.5 shrink-0 text-amber-400" />
                <Coins v-else class="w-3.5 h-3.5 shrink-0 text-emerald-400" />
                <span class="hidden sm:inline">{{ isHighDrawerCash ? 'Safe Drop' : 'Cash In / Drop' }}</span>
            </button>

            <!-- Shift X-Report & Close Register Pill -->
            <button
                type="button"
                @click="emit('open-shift')"
                class="h-9 px-3 rounded-lg text-xs font-semibold inline-flex items-center gap-1.5 border transition-colors cursor-pointer"
                :class="
                    !activeShift
                        ? 'bg-amber-500/20 hover:bg-amber-500/30 border-amber-500/50 text-amber-300 animate-pulse'
                        : 'bg-emerald-950/60 hover:bg-emerald-900/70 border-emerald-700/70 text-emerald-300'
                "
                :title="activeShift ? 'View Shift Summary & Close Register (X / Z Report)' : 'Open Register Shift'"
            >
                <Wallet class="w-3.5 h-3.5 shrink-0" />
                <span v-if="activeShift" class="font-mono tabular-nums">
                    {{ formatMoney(activeShift.expected_cash) }}
                </span>
                <span v-else>Open Shift</span>
            </button>

            <!-- Scanner Sound Toggle -->
            <button
                type="button"
                @click="emit('toggle-sound')"
                class="hidden sm:inline-flex h-9 w-9 rounded-lg bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 items-center justify-center transition-colors cursor-pointer"
                :class="soundEnabled ? 'text-emerald-400' : 'text-zinc-500'"
                :title="soundEnabled ? 'Scanner beep enabled' : 'Scanner beep muted'"
            >
                <Volume2 v-if="soundEnabled" class="w-4 h-4" />
                <VolumeX v-else class="w-4 h-4" />
            </button>

            <!-- Active Cashier PIN Switcher -->
            <button
                type="button"
                @click="emit('open-pin-switch')"
                class="h-9 pl-1.5 pr-2.5 rounded-lg bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 inline-flex items-center gap-2 transition-colors cursor-pointer"
                title="Switch Active Cashier via 4-Digit PIN (F8)"
            >
                <span class="w-6 h-6 rounded-md bg-zinc-800 border border-zinc-700 text-[10px] font-bold font-mono text-emerald-400 flex items-center justify-center">
                    {{ cashierInitials }}
                </span>
                <span class="text-xs font-semibold text-zinc-200 max-w-[100px] truncate hidden sm:inline">
                    {{ activeCashier?.name?.split(' ')[0] || 'Cashier' }}
                </span>
                <ChevronDown class="w-3 h-3 text-zinc-500 hidden sm:inline" />
            </button>

            <!-- Lock Register Screen -->
            <button
                type="button"
                @click="emit('lock-register')"
                class="h-9 w-9 rounded-lg bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 inline-flex items-center justify-center text-zinc-300 hover:text-white transition-colors cursor-pointer"
                title="Lock Register Screen (F9)"
            >
                <Lock class="w-3.5 h-3.5" />
            </button>

            <!-- Backoffice Shortcut -->
            <a
                href="/admin/dashboard"
                class="hidden xl:inline-flex h-9 w-9 rounded-lg bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 items-center justify-center text-zinc-400 hover:text-white transition-colors"
                title="Open Admin Backoffice"
            >
                <LayoutDashboard class="w-3.5 h-3.5" />
            </a>

            <!-- Sign Out -->
            <button
                type="button"
                @click="emit('logout')"
                class="h-9 w-9 rounded-lg bg-zinc-900 hover:bg-rose-950 border border-zinc-800 hover:border-rose-800 inline-flex items-center justify-center text-zinc-400 hover:text-rose-300 transition-colors cursor-pointer"
                title="Sign Out of POS"
            >
                <LogOut class="w-3.5 h-3.5" />
            </button>
        </div>
    </header>
</template>
