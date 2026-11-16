<script setup>
import axios from 'axios';
import { ref } from 'vue';
import {
    Search,
    UserPlus,
    X,
    Sparkles,
    ScanBarcode,
    Scale,
    Minus,
    Plus,
    Trash2,
    Tag,
    StickyNote,
    PauseCircle,
    CreditCard,
    ArrowRight,
} from 'lucide-vue-next';
import { usePosStore } from '@/stores/pos';

const emit = defineEmits([
    'open-new-customer',
    'edit-line-weight',
    'hold-sale',
    'open-checkout',
    'notify',
]);

const pos = usePosStore();

const formatMoney = (val) => `$${Number(val || 0).toFixed(2)}`;

// Customer Instant Lookup
const customerQuery = ref('');
const customerResults = ref([]);
let searchTimer = null;

const onCustomerInput = () => {
    clearTimeout(searchTimer);
    const q = customerQuery.value.trim();

    if (q.length < 2) {
        customerResults.value = [];
        return;
    }

    searchTimer = setTimeout(async () => {
        try {
            const { data } = await axios.get(route('pos.customers.search'), {
                params: { q },
            });
            customerResults.value = data.customers || [];
        } catch {
            customerResults.value = [];
        }
    }, 180);
};

const selectCustomer = (cust) => {
    pos.attachCustomer(cust);
    customerQuery.value = '';
    customerResults.value = [];
    emit('notify', `Attached ${cust.name} (${cust.reward_points} pts)`);
};

// Inline Bulk Quantity Quick-Picker for a Cart Line
const editingQtyKey = ref(null);
const customQtyDraft = ref('');

const toggleQtyPicker = (line) => {
    if (editingQtyKey.value === line.key) {
        editingQtyKey.value = null;
        return;
    }
    editingQtyKey.value = line.key;
    customQtyDraft.value = String(line.quantity);
};

const applyBulkQty = (line, qty) => {
    pos.setLineQuantity(line.key, qty);
    editingQtyKey.value = null;
};

// Discount & Notes Drawers
const showDiscountDrawer = ref(false);
const showNotesDrawer = ref(false);
const draftDiscountType = ref('percent');
const draftDiscountValue = ref('');

const applyPresetPercent = (pct) => {
    draftDiscountType.value = 'percent';
    draftDiscountValue.value = String(pct);
    pos.setManualDiscount('percent', pct);
    showDiscountDrawer.value = false;
};

const applyCustomDiscount = () => {
    const val = Number(draftDiscountValue.value);
    if (!Number.isFinite(val) || val <= 0) {
        pos.clearManualDiscount();
    } else {
        pos.setManualDiscount(draftDiscountType.value, val);
    }
    showDiscountDrawer.value = false;
};

const customerInitials = (name) =>
    String(name || 'WC')
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();
</script>

<template>
    <aside class="bg-white flex flex-col min-h-0 select-none">
        <!-- Customer & Loyalty Header -->
        <div class="p-3 border-b border-zinc-200 bg-zinc-50/70 shrink-0">
            <div v-if="!pos.customer" class="relative">
                <div class="flex items-center gap-2">
                    <div class="relative flex-1">
                        <Search class="w-3.5 h-3.5 text-zinc-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                        <input
                            v-model="customerQuery"
                            @input="onCustomerInput"
                            type="text"
                            placeholder="Walk-in Customer · Lookup phone or name..."
                            class="w-full h-9 pl-8 pr-8 rounded-lg border border-zinc-200 bg-white text-xs font-medium text-zinc-900"
                        />
                        <button
                            v-if="customerQuery"
                            type="button"
                            @click="
                                customerQuery = '';
                                customerResults = [];
                            "
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-zinc-700 cursor-pointer"
                        >
                            <X class="w-3.5 h-3.5" />
                        </button>
                    </div>
                    <button
                        type="button"
                        @click="emit('open-new-customer', customerQuery)"
                        class="h-9 px-2.5 rounded-lg border border-zinc-200 bg-white hover:bg-zinc-100 text-xs font-semibold text-zinc-800 inline-flex items-center gap-1 shadow-sm shrink-0 cursor-pointer"
                    >
                        <UserPlus class="w-3.5 h-3.5 text-emerald-600" />
                        <span>New</span>
                    </button>
                </div>

                <!-- Instant Customer Lookup Results -->
                <div
                    v-if="customerResults.length > 0"
                    class="absolute left-0 right-0 top-10 z-30 bg-white border border-zinc-200 rounded-xl shadow-xl divide-y divide-zinc-100 max-h-60 overflow-y-auto"
                >
                    <button
                        v-for="cust in customerResults"
                        :key="cust.id"
                        type="button"
                        @click="selectCustomer(cust)"
                        class="w-full px-3.5 py-2.5 text-left hover:bg-emerald-50/70 flex items-center justify-between gap-2 transition-colors cursor-pointer"
                    >
                        <div class="flex items-center gap-2.5 min-w-0">
                            <span class="w-7 h-7 rounded-full bg-zinc-900 text-white text-[10px] font-bold font-mono flex items-center justify-center shrink-0">
                                {{ customerInitials(cust.name) }}
                            </span>
                            <div class="min-w-0">
                                <div class="text-xs font-bold text-zinc-900 truncate">{{ cust.name }}</div>
                                <div class="text-[11px] font-mono text-zinc-500 truncate">
                                    {{ cust.phone || cust.email || 'Loyalty Member' }}
                                </div>
                            </div>
                        </div>

                        <span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-900 text-[11px] font-bold font-mono shrink-0 tabular-nums">
                            {{ cust.reward_points }} pts
                        </span>
                    </button>
                </div>
            </div>

            <!-- Attached Customer & Loyalty Redemption Card -->
            <div v-else class="space-y-2">
                <div class="flex items-center justify-between gap-2 bg-white border border-zinc-200 rounded-lg px-3 py-2 shadow-sm">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <span class="w-8 h-8 rounded-lg bg-zinc-900 text-emerald-400 text-xs font-bold font-mono flex items-center justify-center shrink-0">
                            {{ customerInitials(pos.customer.name) }}
                        </span>
                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5">
                                <span class="text-xs font-bold text-zinc-900 truncate">{{ pos.customer.name }}</span>
                                <span class="px-1.5 py-0.5 rounded bg-amber-100 text-amber-900 text-[10px] font-bold font-mono tabular-nums">
                                    {{ pos.customer.reward_points }} pts
                                </span>
                            </div>
                            <div class="flex items-center gap-2 text-[11px] text-zinc-500 font-mono">
                                <span>{{ pos.customer.phone || pos.customer.email || 'Member' }}</span>
                                <span v-if="pos.estimatedPointsEarned > 0" class="text-emerald-700 font-semibold">
                                    · +{{ pos.estimatedPointsEarned }} pts
                                </span>
                            </div>
                        </div>
                    </div>

                    <button
                        type="button"
                        @click="pos.detachCustomer()"
                        class="p-1.5 rounded-md text-zinc-400 hover:text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer"
                        title="Remove customer from ticket"
                    >
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <!-- 500 pts = $10.00 Loyalty Redemption Bar -->
                <div
                    v-if="pos.customer.reward_points >= pos.loyaltyConfig.points_per_block"
                    class="px-3 py-2 rounded-lg bg-amber-50/90 border border-amber-200 flex items-center justify-between gap-2 text-xs"
                >
                    <div class="flex items-center gap-1.5 text-amber-950 font-semibold min-w-0">
                        <Sparkles class="w-3.5 h-3.5 text-amber-600 shrink-0" />
                        <span class="truncate">
                            {{ pos.loyaltyConfig.points_per_block }} pts = ${{ pos.loyaltyConfig.discount_per_block }} off
                            <span
                                v-if="pos.maxCustomerRedeemableBlocks === 0 && pos.lines.length > 0"
                                class="text-[10px] font-normal text-amber-800 ml-1"
                            >
                                (Need ${{ pos.loyaltyConfig.discount_per_block }}+ subtotal)
                            </span>
                        </span>
                    </div>

                    <div class="inline-flex items-center gap-1.5">
                        <button
                            type="button"
                            :disabled="pos.effectivePointsBlocks <= 0"
                            @click="pos.setPointsBlocks(pos.effectivePointsBlocks - 1)"
                            class="w-7 h-7 rounded-md bg-white border border-amber-300 font-bold text-amber-950 hover:bg-amber-100 disabled:opacity-40 cursor-pointer"
                        >
                            −
                        </button>
                        <span class="font-mono text-xs font-bold text-amber-950 min-w-[58px] text-center tabular-nums">
                            {{ pos.effectivePointsRedeemed }} pts
                        </span>
                        <button
                            type="button"
                            :disabled="pos.effectivePointsBlocks >= pos.maxCustomerRedeemableBlocks"
                            @click="pos.setPointsBlocks(pos.effectivePointsBlocks + 1)"
                            class="w-7 h-7 rounded-md bg-white border border-amber-300 font-bold text-amber-950 hover:bg-amber-100 disabled:opacity-40 cursor-pointer"
                        >
                            +
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ticket Line Items -->
        <div class="flex-1 overflow-y-auto divide-y divide-zinc-100">
            <div
                v-if="pos.lines.length === 0"
                class="h-full flex flex-col items-center justify-center px-6 py-10 text-center"
            >
                <div class="w-12 h-12 rounded-xl bg-zinc-100 border border-zinc-200/80 flex items-center justify-center text-zinc-400 mb-3">
                    <ScanBarcode class="w-6 h-6" />
                </div>
                <p class="text-sm font-bold text-zinc-700">Ready to ring items</p>
                <p class="text-xs text-zinc-400 mt-1 max-w-[230px]">
                    Scan a product barcode, enter a 4-digit PLU, or tap any catalog tile on the left.
                </p>
            </div>

            <div
                v-for="line in pos.lines"
                :key="line.key"
                class="px-3.5 py-2.5 hover:bg-zinc-50/80 transition-colors"
            >
                <div class="flex items-center justify-between gap-2">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-1.5">
                            <span class="text-xs font-bold text-zinc-900 truncate">
                                {{ line.name }}
                            </span>
                            <span
                                v-if="line.is_taxable"
                                class="px-1 py-0.5 rounded bg-zinc-100 text-zinc-500 text-[9px] font-mono font-semibold shrink-0"
                            >
                                TAX
                            </span>
                        </div>

                        <div class="flex items-center gap-2 mt-1 text-[11px] text-zinc-500 tabular-nums">
                            <button
                                v-if="line.unit_type !== 'each'"
                                type="button"
                                @click="emit('edit-line-weight', line)"
                                class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-sky-50 hover:bg-sky-100 border border-sky-200/80 text-sky-800 font-mono text-[10px] font-semibold cursor-pointer"
                            >
                                <Scale class="w-3 h-3" />
                                <span>{{ line.weight }} {{ line.unit_type }} × {{ formatMoney(line.unit_price) }}</span>
                            </button>
                            <span v-else class="font-mono text-[11px] text-zinc-500">
                                {{ formatMoney(line.unit_price) }}
                                <span v-if="line.sku" class="text-zinc-400">· {{ line.sku }}</span>
                            </span>
                        </div>
                    </div>

                    <!-- Quantity Stepper + Line Price + Remove -->
                    <div class="flex items-center gap-2 shrink-0">
                        <div class="inline-flex items-center rounded-lg border border-zinc-200 bg-white shadow-sm">
                            <button
                                type="button"
                                @click="pos.decrementLine(line.key)"
                                class="w-7 h-7 flex items-center justify-center text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 rounded-l-lg cursor-pointer"
                            >
                                <Minus class="w-3 h-3" />
                            </button>
                            <button
                                type="button"
                                @click="toggleQtyPicker(line)"
                                class="min-w-[28px] h-7 px-1 text-center font-mono text-xs font-bold text-zinc-900 hover:bg-zinc-100 tabular-nums cursor-pointer"
                                title="Tap for bulk quantity"
                            >
                                {{ line.quantity }}
                            </button>
                            <button
                                type="button"
                                @click="pos.incrementLine(line.key)"
                                class="w-7 h-7 flex items-center justify-center text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 rounded-r-lg cursor-pointer"
                            >
                                <Plus class="w-3 h-3" />
                            </button>
                        </div>

                        <div class="w-[66px] text-right font-mono text-xs font-bold text-zinc-950 tabular-nums">
                            {{ formatMoney(pos.computeLineSubtotal(line)) }}
                        </div>

                        <button
                            type="button"
                            @click="pos.removeLine(line.key)"
                            class="p-1 rounded text-zinc-300 hover:text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer"
                            title="Remove item"
                        >
                            <Trash2 class="w-3.5 h-3.5" />
                        </button>
                    </div>
                </div>

                <!-- Expandable Bulk Quantity Bar -->
                <div
                    v-if="editingQtyKey === line.key"
                    class="mt-2 pt-2 border-t border-dashed border-zinc-200 flex items-center justify-between gap-1.5"
                >
                    <div class="flex items-center gap-1">
                        <button
                            v-for="presetQty in [1, 2, 3, 5, 10, 12]"
                            :key="presetQty"
                            type="button"
                            @click="applyBulkQty(line, presetQty)"
                            class="h-7 px-2 rounded border text-[11px] font-mono font-bold cursor-pointer"
                            :class="
                                line.quantity === presetQty
                                    ? 'bg-zinc-900 border-zinc-900 text-white'
                                    : 'bg-zinc-50 border-zinc-200 text-zinc-700 hover:bg-zinc-100'
                            "
                        >
                            ×{{ presetQty }}
                        </button>
                    </div>
                    <div class="flex items-center gap-1">
                        <input
                            v-model="customQtyDraft"
                            @keydown.enter.prevent="applyBulkQty(line, customQtyDraft)"
                            type="number"
                            min="1"
                            max="9999"
                            class="w-14 h-7 px-2 rounded border border-zinc-200 text-xs font-mono font-bold text-center"
                        />
                        <button
                            type="button"
                            @click="applyBulkQty(line, customQtyDraft)"
                            class="h-7 px-2 rounded bg-emerald-600 text-white text-[11px] font-semibold cursor-pointer"
                        >
                            Set
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sticky Register Summary & Charge Dock -->
        <div class="border-t border-zinc-200 bg-zinc-50/90 p-3.5 space-y-3 shrink-0">
            <!-- Quick Action Strip -->
            <div class="flex items-center justify-between gap-1.5">
                <div class="flex items-center gap-1.5">
                    <button
                        type="button"
                        @click="showDiscountDrawer = !showDiscountDrawer"
                        class="h-7 px-2.5 rounded-md border text-[11px] font-semibold inline-flex items-center gap-1 transition-colors cursor-pointer"
                        :class="
                            pos.manualDiscount > 0
                                ? 'bg-emerald-50 border-emerald-300 text-emerald-800'
                                : 'bg-white border-zinc-200 text-zinc-700 hover:bg-zinc-100'
                        "
                    >
                        <Tag class="w-3 h-3" />
                        <span>
                            {{ pos.manualDiscount > 0 ? `-${formatMoney(pos.manualDiscount)}` : 'Discount' }}
                        </span>
                    </button>

                    <button
                        type="button"
                        @click="showNotesDrawer = !showNotesDrawer"
                        class="h-7 px-2.5 rounded-md border text-[11px] font-semibold inline-flex items-center gap-1 transition-colors cursor-pointer"
                        :class="
                            pos.orderNotes
                                ? 'bg-amber-50 border-amber-300 text-amber-900'
                                : 'bg-white border-zinc-200 text-zinc-700 hover:bg-zinc-100'
                        "
                    >
                        <StickyNote class="w-3 h-3" />
                        <span>{{ pos.orderNotes ? 'Note Added' : 'Note' }}</span>
                    </button>
                </div>

                <div class="flex items-center gap-1.5">
                    <button
                        type="button"
                        :disabled="pos.lines.length === 0"
                        @click="emit('hold-sale')"
                        class="h-7 px-2.5 rounded-md border border-zinc-200 bg-white hover:bg-zinc-100 text-[11px] font-semibold text-zinc-700 inline-flex items-center gap-1 disabled:opacity-40 cursor-pointer"
                    >
                        <PauseCircle class="w-3 h-3 text-amber-600" />
                        <span>Hold</span>
                    </button>
                    <button
                        type="button"
                        :disabled="pos.lines.length === 0"
                        @click="pos.clearSale()"
                        class="h-7 px-2.5 rounded-md border border-zinc-200 bg-white hover:bg-rose-50 hover:border-rose-200 hover:text-rose-700 text-[11px] font-semibold text-zinc-600 disabled:opacity-40 cursor-pointer"
                    >
                        Clear
                    </button>
                </div>
            </div>

            <!-- Manual Discount Drawer -->
            <div v-if="showDiscountDrawer" class="p-2.5 rounded-lg bg-white border border-zinc-200 shadow-sm space-y-2">
                <div class="flex items-center justify-between gap-1.5">
                    <span class="text-[11px] font-semibold text-zinc-600">Quick % Off:</span>
                    <div class="flex items-center gap-1">
                        <button
                            v-for="pct in [5, 10, 15, 20]"
                            :key="pct"
                            type="button"
                            @click="applyPresetPercent(pct)"
                            class="h-6 px-2 rounded bg-zinc-100 hover:bg-zinc-200 font-mono text-[11px] font-bold text-zinc-800 cursor-pointer"
                        >
                            {{ pct }}%
                        </button>
                    </div>
                </div>

                <div class="flex items-center gap-1.5">
                    <div class="inline-flex rounded-md bg-zinc-100 p-0.5">
                        <button
                            type="button"
                            @click="draftDiscountType = 'percent'"
                            class="px-2 py-1 rounded text-xs font-bold cursor-pointer"
                            :class="draftDiscountType === 'percent' ? 'bg-zinc-900 text-white' : 'text-zinc-600'"
                        >
                            %
                        </button>
                        <button
                            type="button"
                            @click="draftDiscountType = 'fixed'"
                            class="px-2 py-1 rounded text-xs font-bold cursor-pointer"
                            :class="draftDiscountType === 'fixed' ? 'bg-zinc-900 text-white' : 'text-zinc-600'"
                        >
                            $
                        </button>
                    </div>
                    <input
                        v-model="draftDiscountValue"
                        @keydown.enter.prevent="applyCustomDiscount"
                        type="number"
                        step="0.01"
                        min="0"
                        placeholder="Custom value"
                        class="h-8 px-2.5 rounded-md border border-zinc-200 text-xs font-mono flex-1"
                    />
                    <button
                        type="button"
                        @click="applyCustomDiscount"
                        class="h-8 px-3 rounded-md bg-zinc-900 text-white text-xs font-semibold cursor-pointer"
                    >
                        Apply
                    </button>
                    <button
                        v-if="pos.manualDiscount > 0"
                        type="button"
                        @click="
                            pos.clearManualDiscount();
                            draftDiscountValue = '';
                            showDiscountDrawer = false;
                        "
                        class="h-8 px-2 text-xs font-semibold text-rose-600 cursor-pointer"
                    >
                        Remove
                    </button>
                </div>
            </div>

            <!-- Order Note Input -->
            <div v-if="showNotesDrawer">
                <input
                    v-model="pos.orderNotes"
                    type="text"
                    placeholder="Add receipt or kitchen note..."
                    class="w-full h-8 px-2.5 rounded-md border border-zinc-200 bg-white text-xs"
                />
            </div>

            <!-- Totals Ledger -->
            <div class="space-y-1 text-xs tabular-nums">
                <div class="flex justify-between text-zinc-500">
                    <span>Subtotal ({{ pos.totalUnits }} {{ pos.totalUnits === 1 ? 'item' : 'items' }})</span>
                    <span class="font-mono text-zinc-800">{{ formatMoney(pos.subtotal) }}</span>
                </div>
                <div v-if="pos.manualDiscount > 0" class="flex justify-between text-emerald-700 font-medium">
                    <span>Store Discount</span>
                    <span class="font-mono">-{{ formatMoney(pos.manualDiscount) }}</span>
                </div>
                <div v-if="pos.pointsDiscount > 0" class="flex justify-between text-amber-700 font-medium">
                    <span>Loyalty Redeemed ({{ pos.effectivePointsRedeemed }} pts)</span>
                    <span class="font-mono">-{{ formatMoney(pos.pointsDiscount) }}</span>
                </div>
                <div class="flex justify-between text-zinc-500">
                    <span>Sales Tax ({{ Number(pos.taxRate || 0).toFixed(2).replace(/\.00$/, '') }}%)</span>
                    <span class="font-mono text-zinc-800">{{ formatMoney(pos.taxAmount) }}</span>
                </div>
                <div class="flex justify-between items-baseline pt-2 border-t border-zinc-200/90">
                    <span class="text-sm font-bold text-zinc-900">Total</span>
                    <span class="text-2xl font-bold font-mono text-zinc-950 tracking-tight">
                        {{ formatMoney(pos.grandTotal) }}
                    </span>
                </div>
            </div>

            <!-- Primary Charge Buttons -->
            <div class="grid grid-cols-12 gap-2">
                <button
                    type="button"
                    :disabled="pos.lines.length === 0"
                    @click="emit('open-checkout', 'pos_terminal')"
                    class="col-span-4 h-12 rounded-xl border border-zinc-300 bg-white hover:bg-zinc-100 text-zinc-900 font-semibold text-xs inline-flex items-center justify-center gap-1.5 shadow-sm transition-colors disabled:opacity-40 cursor-pointer"
                    title="Quick External Card Terminal"
                >
                    <CreditCard class="w-4 h-4 text-zinc-700" />
                    <span>Card</span>
                </button>

                <button
                    type="button"
                    :disabled="pos.lines.length === 0"
                    @click="emit('open-checkout', 'cash')"
                    class="col-span-8 h-12 rounded-xl bg-emerald-600 hover:bg-emerald-500 active:bg-emerald-700 text-white font-bold text-sm px-4 flex items-center justify-between shadow-sm transition-colors disabled:opacity-40 cursor-pointer"
                >
                    <span>Charge</span>
                    <span class="inline-flex items-center gap-1.5 font-mono text-base tabular-nums">
                        {{ formatMoney(pos.grandTotal) }}
                        <ArrowRight class="w-4 h-4" />
                    </span>
                </button>
            </div>
        </div>
    </aside>
</template>
