<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { X, Scale, Check } from 'lucide-vue-next';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    product: {
        type: Object,
        default: null,
    },
    initialWeight: {
        type: [String, Number],
        default: '',
    },
});

const emit = defineEmits(['close', 'confirm']);

const weightStr = ref('');
const error = ref('');

const formatMoney = (val) => `$${Number(val || 0).toFixed(2)}`;

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            weightStr.value = props.initialWeight ? String(props.initialWeight) : '';
            error.value = '';
        }
    },
);

const unitLabel = computed(() => props.product?.unit_type || 'lb');

const livePrice = computed(
    () => Number(weightStr.value || 0) * Number(props.product?.price || 0),
);

const tapKey = (key) => {
    error.value = '';
    if (key === 'clear') {
        weightStr.value = '';
        return;
    }
    if (key === 'back') {
        weightStr.value = weightStr.value.slice(0, -1);
        return;
    }
    if (key === '.' && weightStr.value.includes('.')) {
        return;
    }
    if (weightStr.value.includes('.')) {
        const decimals = weightStr.value.split('.')[1] || '';
        if (decimals.length >= 3) {
            return;
        }
    }
    weightStr.value = key === '.' && weightStr.value === '' ? '0.' : `${weightStr.value}${key}`;
};

const submit = () => {
    const val = Number(weightStr.value);
    if (!Number.isFinite(val) || val <= 0) {
        error.value = `Enter scale weight in ${unitLabel.value} greater than 0.`;
        return;
    }
    emit('confirm', val);
};

const handleKeydown = (e) => {
    if (!props.open) {
        return;
    }
    if (e.key === 'Escape') {
        emit('close');
        return;
    }
    if (e.key === 'Enter') {
        e.preventDefault();
        submit();
        return;
    }
    if (e.key === 'Backspace') {
        tapKey('back');
        return;
    }
    if (/^[0-9.]$/.test(e.key)) {
        tapKey(e.key);
    }
};

onMounted(() => window.addEventListener('keydown', handleKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', handleKeydown));
</script>

<template>
    <div
        v-if="open && product"
        class="fixed inset-0 z-50 bg-zinc-950/60 backdrop-blur-sm flex items-center justify-center p-4"
        @click.self="emit('close')"
    >
        <div class="bg-white border border-zinc-200 rounded-xl shadow-2xl w-full max-w-sm p-5 space-y-4 select-none">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <span class="inline-flex items-center gap-1 text-[11px] font-mono uppercase font-bold text-sky-700">
                        <Scale class="w-3.5 h-3.5" />
                        Scale Weight
                    </span>
                    <h3 class="text-base font-bold text-zinc-950 mt-0.5">{{ product.name }}</h3>
                    <p class="text-xs font-mono text-zinc-500">
                        {{ formatMoney(product.price) }} / {{ unitLabel }}
                    </p>
                </div>
                <button
                    type="button"
                    @click="emit('close')"
                    class="w-8 h-8 rounded-lg text-zinc-400 hover:text-zinc-800 hover:bg-zinc-100 flex items-center justify-center cursor-pointer"
                >
                    <X class="w-4 h-4" />
                </button>
            </div>

            <!-- Digital Scale Readout -->
            <div class="p-3.5 rounded-xl bg-zinc-950 text-white flex items-baseline justify-between">
                <span class="text-xs font-mono text-zinc-400 uppercase">Net {{ unitLabel }}</span>
                <div class="text-right tabular-nums">
                    <div class="text-2xl font-bold font-mono">
                        {{ weightStr || '0.000' }} <span class="text-sm text-zinc-400">{{ unitLabel }}</span>
                    </div>
                    <div class="text-xs font-mono text-emerald-400 font-semibold">
                        {{ formatMoney(livePrice) }}
                    </div>
                </div>
            </div>

            <!-- Quick Produce Presets -->
            <div class="grid grid-cols-5 gap-1.5">
                <button
                    v-for="preset in ['0.25', '0.50', '1.00', '1.50', '2.00']"
                    :key="preset"
                    type="button"
                    @click="weightStr = preset"
                    class="h-8 rounded-lg border border-sky-200 bg-sky-50 hover:bg-sky-100 text-sky-900 font-mono text-[11px] font-bold cursor-pointer"
                >
                    {{ preset }}
                </button>
            </div>

            <!-- Touch Numpad -->
            <div class="grid grid-cols-3 gap-1.5">
                <button
                    v-for="key in ['7', '8', '9', '4', '5', '6', '1', '2', '3', '.', '0', 'back']"
                    :key="key"
                    type="button"
                    @click="tapKey(key)"
                    class="h-11 rounded-lg bg-zinc-100 hover:bg-zinc-200 active:bg-zinc-300 text-zinc-900 font-mono text-base font-bold cursor-pointer"
                >
                    {{ key === 'back' ? '⌫' : key }}
                </button>
            </div>

            <p v-if="error" class="text-xs font-medium text-rose-600">{{ error }}</p>

            <button
                type="button"
                @click="submit"
                class="w-full h-11 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-sm inline-flex items-center justify-center gap-1.5 shadow-sm cursor-pointer"
            >
                <Check class="w-4 h-4" />
                <span>Confirm {{ weightStr ? `${weightStr} ${unitLabel}` : 'Weight' }}</span>
            </button>
        </div>
    </div>
</template>

