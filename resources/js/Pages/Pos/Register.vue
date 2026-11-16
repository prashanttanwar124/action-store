<script setup>
import axios from 'axios';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import {
    X,
    Trash2,
    Printer,
    CheckCircle2,
    AlertTriangle,
} from 'lucide-vue-next';
import { usePosStore } from '@/stores/pos';
import { usePosAudio } from '@/composables/usePosAudio';
import PosHeader from '@/Components/Pos/PosHeader.vue';
import PosCatalogPanel from '@/Components/Pos/PosCatalogPanel.vue';
import PosCartPanel from '@/Components/Pos/PosCartPanel.vue';
import PosTenderModal from '@/Components/Pos/PosTenderModal.vue';
import PosWeightModal from '@/Components/Pos/PosWeightModal.vue';
import PosShiftModal from '@/Components/Pos/PosShiftModal.vue';
import PosCashMovementModal from '@/Components/Pos/PosCashMovementModal.vue';
import PosPinModal from '@/Components/Pos/PosPinModal.vue';
import PosReceipt from '@/Components/Pos/PosReceipt.vue';
import PosOrdersView from '@/Components/Pos/PosOrdersView.vue';

const props = defineProps({
    categories: {
        type: Array,
        default: () => [],
    },
    catalogSummary: {
        type: Object,
        default: () => ({ total_products: 0, weighed_products: 0 }),
    },
    currentShift: {
        type: Object,
        default: null,
    },
    cashiers: {
        type: Array,
        default: () => [],
    },
    loyaltyConfig: {
        type: Object,
        default: () => ({ points_per_block: 500, discount_per_block: 10 }),
    },
    drawerConfig: {
        type: Object,
        default: () => ({ safe_drop_alert_threshold: 1000 }),
    },
    recentOrders: {
        type: Array,
        default: () => [],
    },
    initialTab: {
        type: String,
        default: 'register',
    },
    initialOrderSearch: {
        type: String,
        default: '',
    },
});

const page = usePage();
const pos = usePosStore();
const { isSoundEnabled, toggleSound, playScanBeep, playSuccessChime, playErrorBuzz } =
    usePosAudio();

const storeInfo = computed(() => page.props.storeInfo || {});
const activeCashier = ref(
    page.props.auth?.admin
        ? { ...page.props.auth.admin }
        : { id: null, name: 'Cashier', email: '' },
);
const activeShift = ref(props.currentShift ? { ...props.currentShift } : null);
const activeTab = ref(props.initialTab === 'orders' ? 'orders' : 'register');

const catalogPanelRef = ref(null);
const ordersViewRef = ref(null);

pos.hydrateRegisterConfig(storeInfo.value?.tax_rate || 0, props.loyaltyConfig);

watch(
    () => storeInfo.value?.tax_rate,
    (nextTaxRate) => {
        pos.hydrateRegisterConfig(nextTaxRate || 0, props.loyaltyConfig);
    },
);

const formatMoney = (val) => `$${Number(val || 0).toFixed(2)}`;

// Non-blocking Status Toast
const toast = ref(null);
let toastTimer = null;
const notify = (message, tone = 'success') => {
    clearTimeout(toastTimer);
    toast.value = { message, tone };
    toastTimer = setTimeout(() => {
        toast.value = null;
    }, 2800);
};

// Modal State
const shiftModalOpen = ref(!props.currentShift);
const cashMovementModal = reactive({
    open: false,
    initialType: null,
});
const parkedModalOpen = ref(false);

const weightModal = reactive({
    open: false,
    product: null,
    editingLineKey: null,
    initialWeight: '',
});

const customItemModal = reactive({
    open: false,
    name: '',
    unit_price: '',
    quantity: 1,
    is_taxable: false,
    error: '',
});

const newCustomerModal = reactive({
    open: false,
    name: '',
    phone: '',
    email: '',
    loading: false,
    error: '',
});

const pinModal = reactive({
    open: false,
    locked: false,
});

const checkoutModal = reactive({
    open: false,
    initialMethod: 'cash',
    loading: false,
    error: '',
    idempotencyKey: '',
});

const generateIdempotencyKey = () => {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return `pos-${crypto.randomUUID()}`;
    }

    return `pos-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 12)}`;
};

watch(
    [() => pos.lines, () => pos.grandTotal, () => pos.effectivePointsRedeemed],
    () => {
        checkoutModal.idempotencyKey = '';
    },
    { deep: true },
);

const receiptModal = reactive({
    open: false,
    order: null,
});

const anyModalOpen = computed(
    () =>
        shiftModalOpen.value ||
        cashMovementModal.open ||
        parkedModalOpen.value ||
        weightModal.open ||
        customItemModal.open ||
        newCustomerModal.open ||
        pinModal.open ||
        checkoutModal.open ||
        receiptModal.open,
);

// Catalog & Barcode Actions
const selectCatalogProduct = (product) => {
    if (product.unit_type === 'lb' || product.unit_type === 'kg') {
        weightModal.product = product;
        weightModal.editingLineKey = null;
        weightModal.initialWeight = '';
        weightModal.open = true;
        return;
    }

    pos.addUnitProduct(product, 1);
    playScanBeep();
};

const openWeightEditorForLine = (line) => {
    weightModal.product = {
        id: line.product_id,
        name: line.name,
        price: line.unit_price,
        unit_type: line.unit_type,
        is_taxable: line.is_taxable,
    };
    weightModal.editingLineKey = line.key;
    weightModal.initialWeight = line.weight || '';
    weightModal.open = true;
};

const handleConfirmWeight = (weightValue) => {
    if (weightModal.editingLineKey) {
        pos.updateLineWeight(weightModal.editingLineKey, weightValue);
        notify(`Updated ${weightModal.product.name} to ${weightValue} ${weightModal.product.unit_type}`);
    } else if (weightModal.product) {
        pos.addWeighedProduct(weightModal.product, weightValue, 1);
        notify(`Added ${weightValue} ${weightModal.product.unit_type} of ${weightModal.product.name}`);
    }
    playScanBeep();
    weightModal.open = false;
};

const processScanCode = async (rawCode, { singleMatch = null, clearSearch = null } = {}) => {
    const code = String(rawCode || '').trim();
    if (!code) {
        return;
    }

    const exactMatch = await pos.resolveScanCode(code);
    if (exactMatch) {
        clearSearch?.();
        selectCatalogProduct(exactMatch);
        if (exactMatch.unit_type === 'each') {
            notify(`Scanned: ${exactMatch.name}`);
        }
        return;
    }

    if (singleMatch) {
        clearSearch?.();
        selectCatalogProduct(singleMatch);
        return;
    }

    playErrorBuzz();
    notify(`No barcode or PLU match for "${code}"`, 'error');
};

const handleCatalogScanEnter = ({ query, singleMatch, clearSearch }) => {
    processScanCode(query, { singleMatch, clearSearch });
};

// Custom Open-Price Item
const openCustomItemModal = () => {
    customItemModal.name = '';
    customItemModal.unit_price = '';
    customItemModal.quantity = 1;
    customItemModal.is_taxable = Number(storeInfo.value?.tax_rate || 0) > 0;
    customItemModal.error = '';
    customItemModal.open = true;
};

const submitCustomItem = () => {
    const price = Number(customItemModal.unit_price);
    if (!customItemModal.name.trim() || !Number.isFinite(price) || price <= 0) {
        customItemModal.error = 'Enter both a description and a price greater than $0.00.';
        return;
    }

    pos.addCustomItem({
        name: customItemModal.name,
        unit_price: price,
        quantity: customItemModal.quantity,
        is_taxable: customItemModal.is_taxable,
    });

    playScanBeep();
    customItemModal.open = false;
    notify(`Added custom item: ${customItemModal.name.trim()}`);
};

// Quick Customer Creation
const openNewCustomerModal = (prefillQuery = '') => {
    const q = String(prefillQuery || '').trim();
    const looksLikePhone = /^\+?[\d\s()-]{4,}$/.test(q);
    newCustomerModal.name = !looksLikePhone ? q : '';
    newCustomerModal.phone = looksLikePhone ? q : '';
    newCustomerModal.email = '';
    newCustomerModal.error = '';
    newCustomerModal.open = true;
};

const submitNewCustomer = async () => {
    newCustomerModal.loading = true;
    newCustomerModal.error = '';

    try {
        const { data } = await axios.post(route('pos.customers.store'), {
            name: newCustomerModal.name,
            phone: newCustomerModal.phone,
            email: newCustomerModal.email || null,
        });

        pos.attachCustomer(data.customer);
        newCustomerModal.open = false;
        notify(`Created & attached ${data.customer.name}`);
    } catch (err) {
        const errors = err.response?.data?.errors;
        newCustomerModal.error = errors
            ? Object.values(errors).flat()[0]
            : 'Unable to save customer.';
    } finally {
        newCustomerModal.loading = false;
    }
};

// Hold / Park Cart
const handleHoldSale = () => {
    if (pos.parkCurrentSale()) {
        notify('Current basket parked on hold.');
    }
};

// Checkout Flow
const openCheckoutModal = (preferredMethod = 'cash') => {
    if (pos.lines.length === 0) {
        return;
    }

    if (!activeShift.value) {
        shiftModalOpen.value = true;
        playErrorBuzz();
        notify('Open a register shift before ringing a sale.', 'error');
        return;
    }

    checkoutModal.initialMethod = preferredMethod;
    checkoutModal.error = '';
    if (!checkoutModal.idempotencyKey) {
        checkoutModal.idempotencyKey = generateIdempotencyKey();
    }
    checkoutModal.open = true;
};

const submitCheckout = async (paymentPayload) => {
    checkoutModal.loading = true;
    checkoutModal.error = '';

    if (!checkoutModal.idempotencyKey) {
        checkoutModal.idempotencyKey = generateIdempotencyKey();
    }

    try {
        const { data } = await axios.post(
            route('pos.orders.store'),
            pos.buildOrderPayload({
                ...paymentPayload,
                idempotency_key: checkoutModal.idempotencyKey,
            }),
        );

        if (data.shift) {
            activeShift.value = data.shift;
        }

        if (data.product_stocks) {
            pos.applyStockUpdates(data.product_stocks);
        }

        ordersViewRef.value?.prependNewOrder(data.order);

        checkoutModal.open = false;
        checkoutModal.idempotencyKey = '';
        pos.clearSale();
        playSuccessChime();
        receiptModal.order = data.order;
        receiptModal.open = true;
    } catch (err) {
        playErrorBuzz();
        const errors = err.response?.data?.errors;

        if (errors?.total) {
            pos.invalidateCatalogCache();
            router.reload({
                only: ['storeInfo', 'categories', 'catalogSummary'],
                preserveState: true,
                preserveScroll: true,
            });
            try {
                const syncResult = await pos.syncCartProductPrices();
                if (syncResult.missingProductNames.length > 0) {
                    notify(
                        `Removed or unavailable item(s): ${syncResult.missingProductNames.join(', ')}`,
                        'error',
                    );
                } else if (syncResult.updatedCount > 0) {
                    notify('Cart prices refreshed to current store rates. Review and charge.', 'error');
                }
            } catch {
                // Keep validation message visible if network retry failed
            }
        }

        checkoutModal.error = errors
            ? Object.values(errors).flat()[0]
            : err.response?.data?.message || 'Unable to complete transaction.';
    } finally {
        checkoutModal.loading = false;
    }
};

// Shift & Cashier Callbacks
const openCashMovementModal = (preferredType = null) => {
    if (!activeShift.value) {
        shiftModalOpen.value = true;
        return;
    }
    shiftModalOpen.value = false;
    cashMovementModal.initialType = preferredType;
    cashMovementModal.open = true;
};

const handleShiftOpened = (shift) => {
    activeShift.value = shift;
    shiftModalOpen.value = false;
    notify(`Shift opened with ${formatMoney(shift.opening_cash)} float`);
};

const handleShiftUpdated = ({ shift, movement }) => {
    activeShift.value = shift;
    if (movement) {
        const verb = movement.type === 'deposit' ? 'Deposited' : 'Withdrew';
        notify(`${verb} ${formatMoney(movement.amount)} (${movement.reason})`);
    }
};

const handleShiftClosed = () => {
    activeShift.value = null;
    notify('Register shift reconciled and closed.');
};

const handleCashierSwitched = (cashier) => {
    activeCashier.value = cashier;
    pinModal.locked = false;
    pinModal.open = false;
    notify(`Active cashier: ${cashier.name}`);
};

const handleRefundCompleted = (data) => {
    if (data.shift) {
        activeShift.value = data.shift;
    }
    if (data.product_stocks) {
        pos.applyStockUpdates(data.product_stocks);
    }
    if (data.customer && pos.customer?.id === data.customer.id) {
        pos.attachCustomer(data.customer);
    }
    playSuccessChime();
    notify(`Refunded ${formatMoney(data.refund_amount)} on ${data.order.order_number}`);
};

const openReceiptPreview = (order) => {
    receiptModal.order = order;
    receiptModal.open = true;
};

const printReceipt = () => {
    window.print();
};

const logoutPos = () => {
    router.post(route('pos.logout'));
};

// Global Hardware Barcode Scanner + Pro Keyboard Shortcuts
let scannerBuffer = '';
let lastKeystrokeAt = 0;

const handleGlobalKeydown = (event) => {
    if (receiptModal.open) {
        if (event.key === 'Enter' || event.key === 'Escape') {
            event.preventDefault();
            receiptModal.open = false;
            return;
        }
        if (event.key.toLowerCase() === 'p' && !event.metaKey && !event.ctrlKey) {
            event.preventDefault();
            printReceipt();
            return;
        }
    }

    if (anyModalOpen.value) {
        return;
    }

    // Function key shortcuts (work anywhere on the terminal)
    if (event.key === 'F1') {
        event.preventDefault();
        activeTab.value = 'register';
        return;
    }
    if (event.key === 'F2') {
        event.preventDefault();
        activeTab.value = 'orders';
        return;
    }
    if (event.key === 'F4' && activeTab.value === 'register') {
        event.preventDefault();
        if (pos.lines.length > 0) {
            handleHoldSale();
        } else {
            parkedModalOpen.value = true;
        }
        return;
    }
    if (event.key === 'F6' && activeTab.value === 'register') {
        event.preventDefault();
        openCustomItemModal();
        return;
    }
    if (event.key === 'F8') {
        event.preventDefault();
        pinModal.locked = false;
        pinModal.open = true;
        return;
    }
    if (event.key === 'F9') {
        event.preventDefault();
        pinModal.locked = true;
        pinModal.open = true;
        return;
    }
    if (event.key === 'F12' && activeTab.value === 'register' && pos.lines.length > 0) {
        event.preventDefault();
        openCheckoutModal('cash');
        return;
    }

    const activeTag = document.activeElement?.tagName?.toLowerCase();
    if (activeTag === 'input' || activeTag === 'textarea' || activeTag === 'select') {
        return;
    }

    if (event.key === '/' && activeTab.value === 'register') {
        event.preventDefault();
        catalogPanelRef.value?.focusSearch();
        return;
    }

    if (event.key === 'Escape' && activeTab.value === 'register') {
        if (!catalogPanelRef.value?.isHomeCategoriesView) {
            event.preventDefault();
            catalogPanelRef.value?.resetToCategories();
            return;
        }
    }

    if (activeTab.value !== 'register') {
        return;
    }

    // Hardware USB/Bluetooth Barcode Scanner Burst Detector
    const now = Date.now();
    if (event.key === 'Enter') {
        if (scannerBuffer.length >= 3 && now - lastKeystrokeAt < 120) {
            event.preventDefault();
            const scannedCode = scannerBuffer;
            scannerBuffer = '';
            processScanCode(scannedCode);
        } else {
            scannerBuffer = '';
        }
        return;
    }

    if (event.key.length === 1 && !event.ctrlKey && !event.metaKey && !event.altKey) {
        if (now - lastKeystrokeAt > 80) {
            scannerBuffer = '';
        }
        scannerBuffer += event.key;
        lastKeystrokeAt = now;
    }
};

onMounted(() => window.addEventListener('keydown', handleGlobalKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', handleGlobalKeydown));
</script>

<template>
    <Head :title="activeTab === 'orders' ? 'Orders & Returns' : 'Counter Register'" />

    <div class="h-screen flex flex-col bg-zinc-100 text-zinc-900 overflow-hidden">
        <!-- Top Command Header -->
        <PosHeader
            :store-info="storeInfo"
            v-model:active-tab="activeTab"
            :active-shift="activeShift"
            :drawer-config="drawerConfig"
            :active-cashier="activeCashier"
            :held-count="pos.parkedSales.length"
            :sound-enabled="isSoundEnabled"
            @open-held="parkedModalOpen = true"
            @open-shift="shiftModalOpen = true"
            @open-cash-movement="openCashMovementModal"
            @open-pin-switch="
                pinModal.locked = false;
                pinModal.open = true;
            "
            @lock-register="
                pinModal.locked = true;
                pinModal.open = true;
            "
            @toggle-sound="toggleSound"
            @logout="logoutPos"
        />

        <!-- Floating Status Toast -->
        <div
            v-if="toast"
            class="fixed bottom-5 left-5 z-50 px-4 py-2.5 rounded-xl shadow-lg text-xs font-semibold flex items-center gap-2 pointer-events-none"
            :class="
                toast.tone === 'error'
                    ? 'bg-rose-600 text-white'
                    : 'bg-zinc-900 text-emerald-300 border border-zinc-700'
            "
        >
            <CheckCircle2 v-if="toast.tone !== 'error'" class="w-4 h-4 text-emerald-400" />
            <AlertTriangle v-else class="w-4 h-4 text-white" />
            <span>{{ toast.message }}</span>
        </div>

        <!-- Workspace 1: Counter Register -->
        <main
            v-show="activeTab === 'register'"
            class="flex-1 grid grid-cols-1 lg:grid-cols-12 min-h-0"
        >
            <PosCatalogPanel
                ref="catalogPanelRef"
                class="lg:col-span-7 xl:col-span-8"
                :categories="categories"
                :catalog-summary="catalogSummary"
                :cart-summary-by-product="pos.cartSummaryByProduct"
                @select-product="selectCatalogProduct"
                @scan-enter="handleCatalogScanEnter"
                @open-custom-item="openCustomItemModal"
            />

            <PosCartPanel
                class="lg:col-span-5 xl:col-span-4"
                @open-new-customer="openNewCustomerModal"
                @edit-line-weight="openWeightEditorForLine"
                @hold-sale="handleHoldSale"
                @open-checkout="openCheckoutModal"
                @notify="notify"
            />
        </main>

        <!-- Workspace 2: Counter Orders & Returns -->
        <PosOrdersView
            v-show="activeTab === 'orders'"
            ref="ordersViewRef"
            :initial-orders="recentOrders"
            :initial-search="initialOrderSearch"
            :has-open-shift="Boolean(activeShift)"
            :expected-drawer-cash="Number(activeShift?.expected_cash ?? 0)"
            @print-receipt="openReceiptPreview"
            @refund-completed="handleRefundCompleted"
            @open-shift="shiftModalOpen = true"
        />

        <!-- Dedicated Modals -->
        <PosWeightModal
            :open="weightModal.open"
            :product="weightModal.product"
            :initial-weight="weightModal.initialWeight"
            @close="weightModal.open = false"
            @confirm="handleConfirmWeight"
        />

        <PosShiftModal
            :open="shiftModalOpen"
            :active-shift="activeShift"
            :drawer-config="drawerConfig"
            @close="shiftModalOpen = false"
            @shift-opened="handleShiftOpened"
            @shift-closed="handleShiftClosed"
            @open-cash-movement="openCashMovementModal"
        />

        <PosCashMovementModal
            :open="cashMovementModal.open"
            :active-shift="activeShift"
            :drawer-config="drawerConfig"
            :initial-type="cashMovementModal.initialType"
            @close="cashMovementModal.open = false"
            @shift-updated="handleShiftUpdated"
        />

        <PosPinModal
            :open="pinModal.open"
            :locked="pinModal.locked"
            :cashiers="cashiers"
            :active-cashier-id="activeCashier?.id"
            @close="pinModal.open = false"
            @switched="handleCashierSwitched"
            @logout="logoutPos"
        />

        <PosTenderModal
            :open="checkoutModal.open"
            :initial-method="checkoutModal.initialMethod"
            :subtotal="pos.subtotal"
            :discount-total="pos.totalDiscount"
            :tax-amount="pos.taxAmount"
            :grand-total="pos.grandTotal"
            :total-units="pos.totalUnits"
            :customer="pos.customer"
            :loading="checkoutModal.loading"
            :error="checkoutModal.error"
            @close="checkoutModal.open = false"
            @confirm="submitCheckout"
        />

        <!-- Custom Open-Price Item Modal -->
        <div
            v-if="customItemModal.open"
            class="fixed inset-0 z-50 bg-zinc-950/60 backdrop-blur-sm flex items-center justify-center p-4"
            @click.self="customItemModal.open = false"
        >
            <div class="bg-white border border-zinc-200 rounded-xl shadow-2xl w-full max-w-sm p-5 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-bold text-zinc-950">Open-Price Custom Item</h3>
                    <button
                        type="button"
                        @click="customItemModal.open = false"
                        class="w-8 h-8 rounded-lg text-zinc-400 hover:text-zinc-800 hover:bg-zinc-100 flex items-center justify-center cursor-pointer"
                    >
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-zinc-700 mb-1">Description</label>
                        <input
                            v-model="customItemModal.name"
                            @keydown.enter.prevent="submitCustomItem"
                            type="text"
                            placeholder="e.g. Fresh Samosa Platter / Loose Mithai"
                            class="w-full h-10 px-3 rounded-lg border border-zinc-200 bg-white text-sm"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-zinc-700 mb-1">Unit Price ($)</label>
                            <input
                                v-model="customItemModal.unit_price"
                                @keydown.enter.prevent="submitCustomItem"
                                type="number"
                                step="0.01"
                                min="0.01"
                                placeholder="0.00"
                                class="w-full h-10 px-3 rounded-lg border border-zinc-200 bg-white text-sm font-mono font-bold tabular-nums"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-zinc-700 mb-1">Quantity</label>
                            <input
                                v-model="customItemModal.quantity"
                                @keydown.enter.prevent="submitCustomItem"
                                type="number"
                                min="1"
                                class="w-full h-10 px-3 rounded-lg border border-zinc-200 bg-white text-sm font-mono font-bold tabular-nums"
                            />
                        </div>
                    </div>

                    <label class="inline-flex items-center gap-2 pt-1 cursor-pointer select-none">
                        <input
                            v-model="customItemModal.is_taxable"
                            type="checkbox"
                            class="w-4 h-4 rounded border-zinc-300 text-zinc-900"
                        />
                        <span class="text-xs font-medium text-zinc-700">Apply store sales tax</span>
                    </label>
                </div>

                <p v-if="customItemModal.error" class="text-xs font-medium text-rose-600">
                    {{ customItemModal.error }}
                </p>

                <button
                    type="button"
                    @click="submitCustomItem"
                    class="w-full h-11 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-white font-bold text-sm cursor-pointer"
                >
                    Add Custom Item
                </button>
            </div>
        </div>

        <!-- New Loyalty Customer Modal -->
        <div
            v-if="newCustomerModal.open"
            class="fixed inset-0 z-50 bg-zinc-950/60 backdrop-blur-sm flex items-center justify-center p-4"
            @click.self="newCustomerModal.open = false"
        >
            <div class="bg-white border border-zinc-200 rounded-xl shadow-2xl w-full max-w-sm p-5 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-[11px] font-mono uppercase font-bold text-emerald-700">
                            100 Welcome Bonus Pts
                        </span>
                        <h3 class="text-base font-bold text-zinc-950">New Loyalty Customer</h3>
                    </div>
                    <button
                        type="button"
                        @click="newCustomerModal.open = false"
                        class="w-8 h-8 rounded-lg text-zinc-400 hover:text-zinc-800 hover:bg-zinc-100 flex items-center justify-center cursor-pointer"
                    >
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-zinc-700 mb-1">Full Name *</label>
                        <input
                            v-model="newCustomerModal.name"
                            type="text"
                            placeholder="Priya Patel"
                            class="w-full h-10 px-3 rounded-lg border border-zinc-200 bg-white text-sm"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-zinc-700 mb-1">Phone Number *</label>
                        <input
                            v-model="newCustomerModal.phone"
                            @keydown.enter.prevent="submitNewCustomer"
                            type="tel"
                            placeholder="(555) 234-5678"
                            class="w-full h-10 px-3 rounded-lg border border-zinc-200 bg-white text-sm font-mono"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-zinc-700 mb-1">Email (Optional)</label>
                        <input
                            v-model="newCustomerModal.email"
                            @keydown.enter.prevent="submitNewCustomer"
                            type="email"
                            placeholder="customer@example.com"
                            class="w-full h-10 px-3 rounded-lg border border-zinc-200 bg-white text-sm"
                        />
                    </div>
                </div>

                <p v-if="newCustomerModal.error" class="text-xs font-medium text-rose-600">
                    {{ newCustomerModal.error }}
                </p>

                <button
                    type="button"
                    :disabled="newCustomerModal.loading"
                    @click="submitNewCustomer"
                    class="w-full h-11 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-sm shadow-sm disabled:opacity-50 cursor-pointer"
                >
                    {{ newCustomerModal.loading ? 'Saving...' : 'Create & Attach Customer' }}
                </button>
            </div>
        </div>

        <!-- Held Counter Carts Modal -->
        <div
            v-if="parkedModalOpen"
            class="fixed inset-0 z-50 bg-zinc-950/60 backdrop-blur-sm flex items-center justify-center p-4"
            @click.self="parkedModalOpen = false"
        >
            <div class="bg-white border border-zinc-200 rounded-xl shadow-2xl w-full max-w-md p-5 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-bold text-zinc-950">Held Counter Baskets</h3>
                    <button
                        type="button"
                        @click="parkedModalOpen = false"
                        class="w-8 h-8 rounded-lg text-zinc-400 hover:text-zinc-800 hover:bg-zinc-100 flex items-center justify-center cursor-pointer"
                    >
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <div v-if="pos.parkedSales.length === 0" class="py-8 text-center text-xs text-zinc-500">
                    No held baskets. Press <kbd class="px-1.5 py-0.5 rounded bg-zinc-100 border border-zinc-200 font-mono">F4</kbd> or tap "Hold" to park an active basket.
                </div>

                <div v-else class="divide-y divide-zinc-100 max-h-80 overflow-y-auto">
                    <div
                        v-for="held in pos.parkedSales"
                        :key="held.id"
                        class="py-3 flex items-center justify-between gap-3"
                    >
                        <div>
                            <div class="text-sm font-bold text-zinc-900">{{ held.label }}</div>
                            <div class="text-xs font-mono text-zinc-500 tabular-nums">
                                {{ held.itemCount }} items · {{ formatMoney(held.totalPreview) }}
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                @click="
                                    pos.resumeParkedSale(held.id);
                                    parkedModalOpen = false;
                                "
                                class="h-8 px-3 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold cursor-pointer"
                            >
                                Resume
                            </button>
                            <button
                                type="button"
                                @click="pos.deleteParkedSale(held.id)"
                                class="p-1.5 rounded text-zinc-400 hover:text-rose-600 hover:bg-rose-50 cursor-pointer"
                            >
                                <Trash2 class="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Post-Sale / Reprint 80mm Receipt Modal -->
        <div
            v-if="receiptModal.open && receiptModal.order"
            class="fixed inset-0 z-50 bg-zinc-950/65 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto"
            @click.self="receiptModal.open = false"
        >
            <div class="bg-zinc-100 border border-zinc-200 rounded-xl w-full max-w-md p-5 shadow-2xl space-y-4 my-auto">
                <div
                    v-if="Number(receiptModal.order.change_due) > 0"
                    class="p-3.5 rounded-xl bg-emerald-600 text-white flex items-center justify-between shadow-sm"
                >
                    <div>
                        <div class="text-[10px] font-mono uppercase tracking-wider text-emerald-100 font-bold">
                            Cash Drawer
                        </div>
                        <div class="text-xs font-bold">Change Due to Customer</div>
                    </div>
                    <span class="text-2xl font-bold font-mono tabular-nums">
                        {{ formatMoney(receiptModal.order.change_due) }}
                    </span>
                </div>

                <div class="max-h-[58vh] overflow-y-auto">
                    <PosReceipt
                        :order="receiptModal.order"
                        :store-info="storeInfo"
                        :printable="true"
                    />
                </div>

                <div class="grid grid-cols-2 gap-2.5">
                    <button
                        type="button"
                        @click="printReceipt"
                        class="h-11 rounded-xl border border-zinc-300 bg-white hover:bg-zinc-50 text-zinc-900 font-semibold text-xs inline-flex items-center justify-center gap-2 shadow-sm cursor-pointer"
                    >
                        <Printer class="w-4 h-4" />
                        <span>Print Receipt (P)</span>
                    </button>
                    <button
                        type="button"
                        @click="receiptModal.open = false"
                        class="h-11 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-white font-bold text-xs shadow-sm cursor-pointer"
                    >
                        Next Sale (Enter)
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
