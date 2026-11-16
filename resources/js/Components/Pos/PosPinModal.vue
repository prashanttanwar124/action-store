<script setup>
import axios from 'axios';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { X, Lock, UserCheck, LogOut } from 'lucide-vue-next';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    locked: {
        type: Boolean,
        default: false,
    },
    cashiers: {
        type: Array,
        default: () => [],
    },
    activeCashierId: {
        type: Number,
        default: null,
    },
});

const emit = defineEmits(['close', 'switched', 'logout']);

const selectedAdminId = ref(null);
const pin = ref('');
const loading = ref(false);
const error = ref('');

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            selectedAdminId.value = props.activeCashierId || props.cashiers[0]?.id || null;
            pin.value = '';
            error.value = '';
        }
    },
);

const selectStaff = (staff) => {
    selectedAdminId.value = staff.id;
    pin.value = '';
    error.value = '';
};

const submitSwitch = async () => {
    if (pin.value.length !== 4 || !selectedAdminId.value || loading.value) {
        return;
    }

    loading.value = true;
    error.value = '';

    try {
        const { data } = await axios.post(route('pos.switch-cashier'), {
            admin_id: selectedAdminId.value,
            pin: pin.value,
        });
        pin.value = '';
        emit('switched', data.cashier);
    } catch (err) {
        pin.value = '';
        const errors = err.response?.data?.errors;
        error.value = errors ? Object.values(errors).flat()[0] : 'Invalid 4-digit cashier PIN.';
    } finally {
        loading.value = false;
    }
};

const tapDigit = (key) => {
    if (loading.value) {
        return;
    }
    error.value = '';
    if (key === 'clear') {
        pin.value = '';
        return;
    }
    if (key === 'back') {
        pin.value = pin.value.slice(0, -1);
        return;
    }
    if (pin.value.length < 4) {
        pin.value = `${pin.value}${key}`;
        if (pin.value.length === 4) {
            submitSwitch();
        }
    }
};

const handleKeydown = (e) => {
    if (!props.open) {
        return;
    }
    if (e.key === 'Escape' && !props.locked) {
        emit('close');
        return;
    }
    if (e.key === 'Backspace') {
        tapDigit('back');
        return;
    }
    if (/^[0-9]$/.test(e.key)) {
        tapDigit(e.key);
    }
};

onMounted(() => window.addEventListener('keydown', handleKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', handleKeydown));

const initials = (name) =>
    String(name || 'ST')
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((p) => p[0])
        .join('')
        .toUpperCase();
</script>

<template>
    <div
        v-if="open"
        class="fixed inset-0 z-50 bg-zinc-950/75 backdrop-blur-sm flex items-center justify-center p-4 select-none"
        @click.self="!locked && emit('close')"
    >
        <div class="bg-white border border-zinc-200 rounded-xl shadow-2xl w-full max-w-sm p-5 space-y-4">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <span class="inline-flex items-center gap-1 text-[11px] font-mono uppercase font-bold text-emerald-700">
                        <Lock v-if="locked" class="w-3.5 h-3.5" />
                        <UserCheck v-else class="w-3.5 h-3.5" />
                        {{ locked ? 'Terminal Locked' : 'Switch Active Cashier' }}
                    </span>
                    <h3 class="text-base font-bold text-zinc-950 mt-0.5">
                        Select Staff & Enter 4-Digit PIN
                    </h3>
                </div>

                <button
                    v-if="!locked"
                    type="button"
                    @click="emit('close')"
                    class="w-8 h-8 rounded-lg text-zinc-400 hover:text-zinc-800 hover:bg-zinc-100 flex items-center justify-center cursor-pointer"
                >
                    <X class="w-4 h-4" />
                </button>
            </div>

            <!-- Staff Avatar Selector Cards -->
            <div class="space-y-1.5 max-h-40 overflow-y-auto">
                <button
                    v-for="staff in cashiers"
                    :key="staff.id"
                    type="button"
                    @click="selectStaff(staff)"
                    class="w-full p-2.5 rounded-lg border flex items-center justify-between gap-2 text-left transition-all cursor-pointer"
                    :class="
                        selectedAdminId === staff.id
                            ? 'border-zinc-900 bg-zinc-900 text-white shadow-sm'
                            : 'border-zinc-200 bg-zinc-50 hover:bg-zinc-100 text-zinc-800'
                    "
                >
                    <div class="flex items-center gap-2.5 min-w-0">
                        <span
                            class="w-8 h-8 rounded-md text-xs font-bold font-mono flex items-center justify-center shrink-0"
                            :class="selectedAdminId === staff.id ? 'bg-zinc-800 text-emerald-400' : 'bg-white border border-zinc-200 text-zinc-700'"
                        >
                            {{ initials(staff.name) }}
                        </span>
                        <div class="min-w-0">
                            <div class="text-xs font-bold truncate">{{ staff.name }}</div>
                            <div
                                class="text-[10px] font-mono truncate"
                                :class="selectedAdminId === staff.id ? 'text-zinc-400' : 'text-zinc-500'"
                            >
                                {{ staff.roles?.[0] || 'Staff' }}
                            </div>
                        </div>
                    </div>

                    <span
                        class="px-2 py-0.5 rounded text-[10px] font-mono font-semibold shrink-0"
                        :class="
                            !staff.has_pos_pin
                                ? 'bg-amber-100 text-amber-800'
                                : selectedAdminId === staff.id
                                  ? 'bg-emerald-500/20 text-emerald-300'
                                  : 'bg-zinc-200 text-zinc-600'
                        "
                    >
                        {{ staff.has_pos_pin ? 'PIN Ready' : 'No PIN' }}
                    </span>
                </button>
            </div>

            <!-- 4-Dot PIN Readout -->
            <div class="py-2 flex flex-col items-center gap-2">
                <div class="flex items-center gap-3.5">
                    <span
                        v-for="idx in [0, 1, 2, 3]"
                        :key="idx"
                        class="w-4 h-4 rounded-full border-2 transition-all"
                        :class="
                            pin.length > idx
                                ? 'bg-emerald-600 border-emerald-600 scale-110'
                                : 'border-zinc-300 bg-zinc-100'
                        "
                    />
                </div>
                <p v-if="error" class="text-xs font-medium text-rose-600 text-center">
                    {{ error }}
                </p>
            </div>

            <!-- PIN Pad -->
            <div class="grid grid-cols-3 gap-1.5">
                <button
                    v-for="digit in ['1', '2', '3', '4', '5', '6', '7', '8', '9', 'clear', '0', 'back']"
                    :key="digit"
                    type="button"
                    :disabled="loading"
                    @click="tapDigit(digit)"
                    class="h-12 rounded-lg bg-zinc-100 hover:bg-zinc-200 active:bg-zinc-300 text-zinc-950 font-mono text-base font-bold transition-colors cursor-pointer disabled:opacity-40"
                >
                    {{ digit === 'clear' ? 'CLR' : digit === 'back' ? '⌫' : digit }}
                </button>
            </div>

            <div v-if="locked" class="pt-2 border-t border-zinc-100 text-center">
                <button
                    type="button"
                    @click="emit('logout')"
                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-zinc-500 hover:text-rose-600 cursor-pointer"
                >
                    <LogOut class="w-3.5 h-3.5" />
                    <span>Sign out of POS terminal</span>
                </button>
            </div>
        </div>
    </div>
</template>

