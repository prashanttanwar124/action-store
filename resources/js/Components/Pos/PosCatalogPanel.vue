<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import {
    Search,
    X,
    Plus,
    LayoutGrid,
    List,
    Scale,
    ScanBarcode,
    Package,
    ArrowLeft,
    ChevronRight,
    RefreshCw,
    Loader2,
    Layers,
    Wheat,
    Flame,
    Milk,
    Cookie,
    Sparkles,
    Apple,
    ShoppingBag,
    UtensilsCrossed,
} from 'lucide-vue-next';
import { usePosStore } from '@/stores/pos';

const props = defineProps({
    categories: {
        type: Array,
        default: () => [],
    },
    catalogSummary: {
        type: Object,
        default: () => ({
            total_products: 0,
            weighed_products: 0,
        }),
    },
    cartSummaryByProduct: {
        type: Object,
        default: () => new Map(),
    },
});

const emit = defineEmits([
    'select-product',
    'scan-enter',
    'open-custom-item',
]);

const pos = usePosStore();

const ICON_COMPONENTS = {
    Wheat,
    Flame,
    Milk,
    Cookie,
    Sparkles,
    Apple,
    ShoppingBag,
    UtensilsCrossed,
    Package,
};

const searchQuery = ref('');
const selectedCategory = ref(null); // null = Category Home Grid, 'all' = All Products, '<slug>' = Specific Category
const weighedOnly = ref(false);
const viewMode = ref(
    typeof window !== 'undefined'
        ? window.localStorage.getItem('masala_pos_catalog_view') || 'grid'
        : 'grid',
);

const searchInputEl = ref(null);
const brokenImages = reactive({});

const CATEGORY_TONES = {
    staples: 'bg-amber-50 border-amber-200/80 text-amber-700',
    grains: 'bg-amber-50 border-amber-200/80 text-amber-700',
    spices: 'bg-orange-50 border-orange-200/80 text-orange-700',
    dairy: 'bg-sky-50 border-sky-200/80 text-sky-700',
    snacks: 'bg-yellow-50 border-yellow-200/80 text-yellow-700',
    sweets: 'bg-rose-50 border-rose-200/80 text-rose-700',
    produce: 'bg-emerald-50 border-emerald-200/80 text-emerald-700',
    vegetables: 'bg-emerald-50 border-emerald-200/80 text-emerald-700',
    grocery: 'bg-indigo-50 border-indigo-200/80 text-indigo-700',
    'recipe-kits': 'bg-violet-50 border-violet-200/80 text-violet-700',
};

const categoryToneClass = (slug) =>
    CATEGORY_TONES[slug] || 'bg-zinc-100 border-zinc-200/80 text-zinc-700';

const loadedProductIds = ref([]);
const paginationMeta = ref({
    current_page: 1,
    last_page: 1,
    per_page: 48,
    total: 0,
    has_more: false,
});
const loading = ref(false);
const loadingMore = ref(false);
const fetchError = ref('');

let debounceTimer = null;
let activeAbortController = null;

const formatMoney = (val) => `$${Number(val || 0).toFixed(2)}`;

const totalProductsCount = computed(() => Number(props.catalogSummary?.total_products || 0));
const weighedCount = computed(() => Number(props.catalogSummary?.weighed_products || 0));

const isHomeCategoriesView = computed(
    () => selectedCategory.value === null && !weighedOnly.value && !searchQuery.value.trim(),
);

const activeCategoryObj = computed(() => {
    if (!selectedCategory.value || selectedCategory.value === 'all') {
        return null;
    }

    return props.categories.find((cat) => cat.slug === selectedCategory.value) || null;
});

const displayedProducts = computed(() => {
    // Subscribe to catalogRevision so live stock updates reflect immediately
    void pos.catalogRevision;
    return loadedProductIds.value
        .map((id) => pos.productsById[id])
        .filter(Boolean);
});

const resolveCategoryIcon = (iconName) => ICON_COMPONENTS[iconName] || Package;

const setViewMode = (mode) => {
    viewMode.value = mode;
    try {
        window.localStorage.setItem('masala_pos_catalog_view', mode);
    } catch {
        // Ignore storage errors
    }
};

const abortInFlight = () => {
    if (activeAbortController) {
        activeAbortController.abort();
        activeAbortController = null;
    }
};

const loadProducts = async ({ page = 1, append = false, force = false } = {}) => {
    if (isHomeCategoriesView.value) {
        abortInFlight();
        loading.value = false;
        loadingMore.value = false;
        fetchError.value = '';
        return;
    }

    abortInFlight();
    const controller = new AbortController();
    activeAbortController = controller;

    if (append) {
        loadingMore.value = true;
    } else {
        loading.value = true;
    }
    fetchError.value = '';

    try {
        const effectiveCategory =
            searchQuery.value.trim() && selectedCategory.value === null
                ? 'all'
                : selectedCategory.value || 'all';

        const result = await pos.fetchCatalogPage({
            category: effectiveCategory,
            weighed: weighedOnly.value,
            q: searchQuery.value.trim(),
            page,
            perPage: 48,
            force,
            signal: controller.signal,
        });

        if (controller.signal.aborted) {
            return;
        }

        const incomingIds = result.products.map((item) => item.id);

        if (append) {
            const seen = new Set(loadedProductIds.value);
            const merged = [...loadedProductIds.value];
            for (const id of incomingIds) {
                if (!seen.has(id)) {
                    seen.add(id);
                    merged.push(id);
                }
            }
            loadedProductIds.value = merged;
        } else {
            loadedProductIds.value = incomingIds;
        }

        paginationMeta.value = result.meta;
    } catch (err) {
        if (err?.name === 'CanceledError' || err?.code === 'ERR_CANCELED' || controller.signal.aborted) {
            return;
        }
        fetchError.value = 'Unable to load catalog items. Check network connection.';
    } finally {
        if (activeAbortController === controller) {
            activeAbortController = null;
        }
        loading.value = false;
        loadingMore.value = false;
    }
};

const openCategory = (slug) => {
    selectedCategory.value = slug;
};

const resetToCategories = () => {
    clearTimeout(debounceTimer);
    abortInFlight();
    searchQuery.value = '';
    selectedCategory.value = null;
    weighedOnly.value = false;
    loadedProductIds.value = [];
    fetchError.value = '';
};

const toggleWeighedFilter = () => {
    weighedOnly.value = !weighedOnly.value;
    if (weighedOnly.value && selectedCategory.value === null) {
        selectedCategory.value = 'all';
    }
};

const refreshCurrentCatalog = () => {
    pos.invalidateCatalogCache();
    if (!isHomeCategoriesView.value) {
        loadProducts({ page: 1, append: false, force: true });
    }
};

const loadNextPage = () => {
    if (loading.value || loadingMore.value || !paginationMeta.value.has_more) {
        return;
    }

    loadProducts({
        page: Number(paginationMeta.value.current_page || 1) + 1,
        append: true,
    });
};

watch([selectedCategory, weighedOnly], () => {
    clearTimeout(debounceTimer);
    if (isHomeCategoriesView.value) {
        loadedProductIds.value = [];
        return;
    }
    loadProducts({ page: 1, append: false });
});

watch(searchQuery, (nextVal) => {
    clearTimeout(debounceTimer);

    if (!nextVal.trim()) {
        if (isHomeCategoriesView.value) {
            abortInFlight();
            loadedProductIds.value = [];
            loading.value = false;
            return;
        }
        loadProducts({ page: 1, append: false });
        return;
    }

    debounceTimer = setTimeout(() => {
        loadProducts({ page: 1, append: false });
    }, 180);
});

const getInCartBadge = (product) => {
    const entry = props.cartSummaryByProduct?.get?.(product.id);
    if (!entry) {
        return null;
    }

    if (entry.unit_type !== 'each' && entry.weight > 0) {
        return `${entry.weight} ${entry.unit_type}`;
    }

    return `${entry.quantity} in cart`;
};

const handleEnter = () => {
    const raw = searchQuery.value.trim();
    if (!raw) {
        return;
    }

    clearTimeout(debounceTimer);
    abortInFlight();

    emit('scan-enter', {
        query: raw,
        singleMatch:
            !loading.value && displayedProducts.value.length === 1
                ? displayedProducts.value[0]
                : null,
        clearSearch: () => {
            searchQuery.value = '';
        },
    });
};

const focusSearch = () => {
    searchInputEl.value?.focus();
    searchInputEl.value?.select?.();
};

const productInitials = (name) =>
    String(name || 'PR')
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((w) => w[0])
        .join('')
        .toUpperCase();

onBeforeUnmount(() => {
    clearTimeout(debounceTimer);
    abortInFlight();
});

defineExpose({
    focusSearch,
    resetToCategories,
    isHomeCategoriesView,
});
</script>

<template>
    <section class="flex flex-col min-h-0 bg-zinc-100/80 border-r border-zinc-200">
        <!-- Omni Search / Barcode Bar + Toolbar -->
        <div class="p-3 bg-white border-b border-zinc-200 flex flex-wrap sm:flex-nowrap items-center gap-2 shrink-0">
            <div class="relative flex-1 min-w-[200px]">
                <Loader2
                    v-if="loading && searchQuery.trim()"
                    class="w-4 h-4 text-emerald-600 animate-spin absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none"
                />
                <ScanBarcode
                    v-else
                    class="w-4 h-4 text-zinc-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none"
                />
                <input
                    ref="searchInputEl"
                    v-model="searchQuery"
                    @keydown.enter.prevent="handleEnter"
                    type="text"
                    placeholder="Scan barcode, type PLU (4066), or search any item..."
                    class="w-full h-10 pl-10 pr-14 rounded-lg border border-zinc-200 bg-white text-sm font-medium text-zinc-900"
                />
                <button
                    v-if="searchQuery"
                    type="button"
                    @click="searchQuery = ''"
                    class="absolute right-2.5 top-1/2 -translate-y-1/2 p-1 rounded text-zinc-400 hover:text-zinc-800 cursor-pointer"
                >
                    <X class="w-4 h-4" />
                </button>
                <kbd
                    v-else
                    class="hidden sm:inline-block absolute right-3 top-1/2 -translate-y-1/2 px-1.5 py-0.5 rounded bg-zinc-100 border border-zinc-200 text-[10px] font-mono text-zinc-400 pointer-events-none"
                >
                    /
                </kbd>
            </div>

            <!-- Weighed Produce Quick Toggle -->
            <button
                v-if="weighedCount > 0"
                type="button"
                @click="toggleWeighedFilter"
                class="h-10 px-3 rounded-lg border text-xs font-semibold inline-flex items-center gap-1.5 shrink-0 transition-colors cursor-pointer"
                :class="
                    weighedOnly
                        ? 'bg-sky-600 border-sky-600 text-white shadow-sm'
                        : 'bg-white border-zinc-200 text-zinc-700 hover:bg-zinc-50'
                "
                title="Filter Loose / Scale Weighed Produce"
            >
                <Scale class="w-3.5 h-3.5" />
                <span class="hidden xl:inline">Scale Items</span>
                <span
                    class="px-1.5 py-0.5 rounded text-[10px] font-mono font-bold"
                    :class="weighedOnly ? 'bg-sky-700 text-white' : 'bg-zinc-100 text-zinc-600'"
                >
                    {{ weighedCount }}
                </span>
            </button>

            <!-- Grid / Compact List Switcher (shown when browsing products) -->
            <div
                v-if="!isHomeCategoriesView"
                class="hidden sm:inline-flex items-center rounded-lg border border-zinc-200 bg-zinc-50 p-0.5 shrink-0"
            >
                <button
                    type="button"
                    @click="setViewMode('grid')"
                    class="w-8 h-8 rounded-md flex items-center justify-center transition-colors cursor-pointer"
                    :class="viewMode === 'grid' ? 'bg-white text-zinc-900 shadow-sm' : 'text-zinc-400 hover:text-zinc-700'"
                    title="Touch Grid View"
                >
                    <LayoutGrid class="w-4 h-4" />
                </button>
                <button
                    type="button"
                    @click="setViewMode('list')"
                    class="w-8 h-8 rounded-md flex items-center justify-center transition-colors cursor-pointer"
                    :class="viewMode === 'list' ? 'bg-white text-zinc-900 shadow-sm' : 'text-zinc-400 hover:text-zinc-700'"
                    title="Compact Cashier List"
                >
                    <List class="w-4 h-4" />
                </button>
            </div>

            <!-- Sync / Refresh Catalog Cache -->
            <button
                v-if="!isHomeCategoriesView"
                type="button"
                @click="refreshCurrentCatalog"
                :disabled="loading"
                class="h-10 w-10 rounded-lg border border-zinc-200 bg-white hover:bg-zinc-50 text-zinc-600 flex items-center justify-center shrink-0 transition-colors cursor-pointer disabled:opacity-50"
                title="Refresh Products & Stock"
            >
                <RefreshCw class="w-4 h-4" :class="loading ? 'animate-spin' : ''" />
            </button>

            <!-- Open-Price Custom Item -->
            <button
                type="button"
                @click="emit('open-custom-item')"
                class="h-10 px-3.5 rounded-lg bg-zinc-900 hover:bg-zinc-800 text-white text-xs font-semibold inline-flex items-center gap-1.5 shrink-0 shadow-sm transition-colors cursor-pointer"
                title="Add Open-Price Custom Item (F6)"
            >
                <Plus class="w-4 h-4" />
                <span>Custom</span>
            </button>
        </div>

        <!-- Drill-Down Breadcrumb + Quick Department Switcher Rail -->
        <div
            v-if="!isHomeCategoriesView"
            class="px-3 py-2 bg-white border-b border-zinc-200 flex items-center gap-1.5 overflow-x-auto no-scrollbar shrink-0"
        >
            <button
                type="button"
                @click="resetToCategories"
                class="h-8 px-2.5 rounded-md bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 text-emerald-800 text-xs font-bold whitespace-nowrap inline-flex items-center gap-1.5 shrink-0 transition-colors cursor-pointer"
                title="Back to Department Tiles (Esc)"
            >
                <ArrowLeft class="w-3.5 h-3.5" />
                <span>Categories</span>
            </button>

            <div class="h-4 w-px bg-zinc-200 mx-0.5 shrink-0" />

            <button
                type="button"
                @click="selectedCategory = 'all'"
                class="h-8 px-3 rounded-md text-xs font-semibold whitespace-nowrap inline-flex items-center gap-1.5 transition-all cursor-pointer"
                :class="
                    selectedCategory === 'all' || (selectedCategory === null && searchQuery.trim())
                        ? 'bg-zinc-900 text-white shadow-sm'
                        : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200/80 hover:text-zinc-900'
                "
            >
                <span>All Items</span>
                <span
                    class="px-1.5 py-0.5 rounded text-[10px] font-mono"
                    :class="
                        selectedCategory === 'all' || (selectedCategory === null && searchQuery.trim())
                            ? 'bg-zinc-700 text-zinc-100'
                            : 'bg-white text-zinc-500'
                    "
                >
                    {{ totalProductsCount }}
                </span>
            </button>

            <button
                v-for="cat in categories"
                :key="cat.slug"
                type="button"
                @click="selectedCategory = cat.slug"
                class="h-8 px-3 rounded-md text-xs font-semibold whitespace-nowrap inline-flex items-center gap-1.5 transition-all cursor-pointer"
                :class="
                    selectedCategory === cat.slug
                        ? 'bg-zinc-900 text-white shadow-sm'
                        : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200/80 hover:text-zinc-900'
                "
            >
                <span>{{ cat.name }}</span>
                <span
                    class="px-1.5 py-0.5 rounded text-[10px] font-mono"
                    :class="selectedCategory === cat.slug ? 'bg-zinc-700 text-zinc-100' : 'bg-white text-zinc-500'"
                >
                    {{ cat.products_count ?? 0 }}
                </span>
            </button>
        </div>

        <!-- Main Scrollable Canvas -->
        <div class="flex-1 overflow-y-auto p-3">
            <!-- ========================================================= -->
            <!-- VIEW 1: POS REGISTER HOME — TOUCH DEPARTMENT / CATEGORY GRID -->
            <!-- ========================================================= -->
            <div v-if="isHomeCategoriesView" class="space-y-3">
                <div class="flex items-center justify-between px-0.5">
                    <div>
                        <h2 class="text-xs font-bold uppercase tracking-wider text-zinc-500">
                            Store Departments
                        </h2>
                        <p class="text-xs text-zinc-500 mt-0.5">
                            Select a category to load items on demand, or scan any barcode / PLU directly.
                        </p>
                    </div>
                    <span class="px-2.5 py-1 rounded-full bg-white border border-zinc-200 text-[11px] font-mono font-semibold text-zinc-600 shadow-sm">
                        {{ categories.length }} depts · {{ totalProductsCount }} SKUs
                    </span>
                </div>

                <div
                    v-if="categories.length === 0 && totalProductsCount === 0"
                    class="h-56 bg-white border border-dashed border-zinc-300 rounded-xl flex flex-col items-center justify-center p-6 text-center"
                >
                    <Package class="w-8 h-8 text-zinc-300 mb-2" />
                    <p class="text-sm font-semibold text-zinc-800">No catalog categories yet</p>
                    <p class="text-xs text-zinc-500 mt-0.5 max-w-xs">
                        Add products in Admin or ring an open-price custom item below.
                    </p>
                    <button
                        type="button"
                        @click="emit('open-custom-item')"
                        class="mt-4 h-9 px-3.5 rounded-lg bg-zinc-900 hover:bg-zinc-800 text-xs font-semibold text-white cursor-pointer"
                    >
                        + Ring Custom Item
                    </button>
                </div>

                <div v-else class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-3">
                    <!-- Category Department Tiles -->
                    <button
                        v-for="cat in categories"
                        :key="cat.slug"
                        type="button"
                        @click="openCategory(cat.slug)"
                        class="group bg-white hover:bg-zinc-50/60 rounded-xl border border-zinc-200/90 hover:border-emerald-500 p-3.5 text-left flex flex-col justify-between min-h-[124px] shadow-sm hover:shadow-md transition-all active:scale-[0.98] cursor-pointer"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div
                                class="w-11 h-11 rounded-xl border shrink-0 flex items-center justify-center transition-transform group-hover:scale-105"
                                :class="categoryToneClass(cat.slug)"
                            >
                                <component
                                    :is="resolveCategoryIcon(cat.icon)"
                                    class="w-5 h-5"
                                />
                            </div>

                            <span class="px-2 py-0.5 rounded-full bg-zinc-100 group-hover:bg-emerald-50 text-zinc-700 group-hover:text-emerald-800 font-mono text-[11px] font-bold tabular-nums transition-colors">
                                {{ cat.products_count ?? 0 }} items
                            </span>
                        </div>

                        <div class="mt-3 flex items-end justify-between gap-2">
                            <div class="min-w-0">
                                <div class="text-sm font-bold text-zinc-900 group-hover:text-emerald-800 truncate transition-colors">
                                    {{ cat.name }}
                                </div>
                                <div
                                    v-if="cat.hindi_title"
                                    class="text-[11px] font-medium text-zinc-400 truncate mt-0.5"
                                >
                                    {{ cat.hindi_title }}
                                </div>
                            </div>
                            <ChevronRight class="w-4 h-4 text-zinc-300 group-hover:text-emerald-600 group-hover:translate-x-0.5 shrink-0 transition-all" />
                        </div>
                    </button>

                    <!-- Dedicated Loose / Scale Produce Tile -->
                    <button
                        v-if="weighedCount > 0"
                        type="button"
                        @click="
                            weighedOnly = true;
                            selectedCategory = 'all';
                        "
                        class="group bg-sky-50/70 hover:bg-sky-50 rounded-xl border border-sky-200 hover:border-sky-500 p-3.5 text-left flex flex-col justify-between min-h-[124px] shadow-sm hover:shadow-md transition-all active:scale-[0.98] cursor-pointer"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div class="w-12 h-12 rounded-xl bg-sky-100 border border-sky-200/80 flex items-center justify-center shrink-0">
                                <Scale class="w-5 h-5 text-sky-700" />
                            </div>
                            <span class="px-2 py-0.5 rounded-full bg-sky-100 text-sky-800 font-mono text-[11px] font-bold tabular-nums">
                                {{ weighedCount }} lb/kg
                            </span>
                        </div>

                        <div class="mt-3 flex items-end justify-between gap-2">
                            <div class="min-w-0">
                                <div class="text-sm font-bold text-sky-950 truncate">
                                    Scale &amp; Loose Produce
                                </div>
                                <div class="text-[11px] font-medium text-sky-700/80 truncate mt-0.5">
                                    Weighed at counter (lb / kg)
                                </div>
                            </div>
                            <ChevronRight class="w-4 h-4 text-sky-400 group-hover:text-sky-700 group-hover:translate-x-0.5 shrink-0 transition-all" />
                        </div>
                    </button>

                    <!-- Browse All Catalog Tile -->
                    <button
                        v-if="totalProductsCount > 0"
                        type="button"
                        @click="openCategory('all')"
                        class="group bg-zinc-900 hover:bg-zinc-800 rounded-xl border border-zinc-800 p-3.5 text-left flex flex-col justify-between min-h-[124px] shadow-sm hover:shadow-md transition-all active:scale-[0.98] cursor-pointer"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div class="w-12 h-12 rounded-xl bg-zinc-800 border border-zinc-700 flex items-center justify-center shrink-0">
                                <Layers class="w-5 h-5 text-emerald-400" />
                            </div>
                            <span class="px-2 py-0.5 rounded-full bg-zinc-800 text-zinc-200 font-mono text-[11px] font-bold tabular-nums">
                                {{ totalProductsCount }} total
                            </span>
                        </div>

                        <div class="mt-3 flex items-end justify-between gap-2">
                            <div class="min-w-0">
                                <div class="text-sm font-bold text-white truncate">
                                    All Catalog (A–Z)
                                </div>
                                <div class="text-[11px] font-medium text-zinc-400 truncate mt-0.5">
                                    Paginated full store lookup
                                </div>
                            </div>
                            <ChevronRight class="w-4 h-4 text-zinc-500 group-hover:text-emerald-400 group-hover:translate-x-0.5 shrink-0 transition-all" />
                        </div>
                    </button>
                </div>
            </div>

            <!-- ========================================================= -->
            <!-- VIEW 2: ON-DEMAND API PRODUCT RESULTS (CATEGORY OR SEARCH) -->
            <!-- ========================================================= -->
            <div v-else class="space-y-3">
                <!-- Context Header Banner -->
                <div class="flex items-center justify-between px-0.5 text-xs text-zinc-500">
                    <div class="font-medium truncate">
                        <template v-if="searchQuery.trim()">
                            Search results for <span class="font-bold text-zinc-800">"{{ searchQuery.trim() }}"</span>
                            <span v-if="activeCategoryObj"> in {{ activeCategoryObj.name }}</span>
                        </template>
                        <template v-else-if="weighedOnly && !activeCategoryObj">
                            Showing <span class="font-bold text-sky-800">Scale &amp; Weighed Produce</span>
                        </template>
                        <template v-else-if="activeCategoryObj">
                            Department: <span class="font-bold text-zinc-800">{{ activeCategoryObj.name }}</span>
                        </template>
                        <template v-else>
                            Browsing <span class="font-bold text-zinc-800">All Catalog Items</span>
                        </template>
                    </div>
                    <span v-if="!loading" class="font-mono text-[11px] text-zinc-500 shrink-0">
                        {{ displayedProducts.length }} of {{ paginationMeta.total }} shown
                    </span>
                </div>

                <!-- Error Banner -->
                <div
                    v-if="fetchError"
                    class="p-4 rounded-xl bg-rose-50 border border-rose-200 flex items-center justify-between gap-3"
                >
                    <p class="text-xs font-semibold text-rose-800">{{ fetchError }}</p>
                    <button
                        type="button"
                        @click="loadProducts({ page: 1, force: true })"
                        class="h-8 px-3 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold cursor-pointer"
                    >
                        Retry
                    </button>
                </div>

                <!-- Fast Skeleton Loader on Initial API Fetch -->
                <div
                    v-else-if="loading && displayedProducts.length === 0"
                    class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-2.5"
                >
                    <div
                        v-for="n in 10"
                        :key="n"
                        class="h-36 rounded-xl bg-white border border-zinc-200/80 p-3 flex flex-col justify-between animate-pulse"
                    >
                        <div class="flex items-start gap-2.5">
                            <div class="w-12 h-12 rounded-lg bg-zinc-200 shrink-0" />
                            <div class="flex-1 space-y-2 py-1">
                                <div class="h-3 bg-zinc-200 rounded w-4/5" />
                                <div class="h-2.5 bg-zinc-100 rounded w-1/2" />
                            </div>
                        </div>
                        <div class="pt-2 border-t border-zinc-100 flex items-center justify-between">
                            <div class="h-4 bg-zinc-200 rounded w-16" />
                            <div class="h-4 bg-zinc-100 rounded w-10" />
                        </div>
                    </div>
                </div>

                <!-- Empty Search / Category Fallback -->
                <div
                    v-else-if="!loading && displayedProducts.length === 0"
                    class="h-60 bg-white border border-dashed border-zinc-300 rounded-xl flex flex-col items-center justify-center p-6 text-center"
                >
                    <Search class="w-8 h-8 text-zinc-300 mb-2" />
                    <p class="text-sm font-semibold text-zinc-800">
                        No matching products found
                    </p>
                    <p class="text-xs text-zinc-500 mt-0.5 max-w-xs">
                        <template v-if="searchQuery.trim()">
                            Nothing matched "{{ searchQuery.trim() }}". Return to Categories or ring an open-price custom item.
                        </template>
                        <template v-else>
                            No items are assigned to this filter yet.
                        </template>
                    </p>
                    <div class="flex items-center gap-2 mt-4">
                        <button
                            type="button"
                            @click="resetToCategories"
                            class="h-9 px-3.5 rounded-lg border border-zinc-200 bg-white hover:bg-zinc-50 text-xs font-semibold text-zinc-700 cursor-pointer"
                        >
                            ← All Categories
                        </button>
                        <button
                            type="button"
                            @click="emit('open-custom-item')"
                            class="h-9 px-3.5 rounded-lg bg-zinc-900 hover:bg-zinc-800 text-xs font-semibold text-white cursor-pointer"
                        >
                            + Ring Custom Item
                        </button>
                    </div>
                </div>

                <!-- MODE A: Touch Product Grid -->
                <div
                    v-else-if="viewMode === 'grid'"
                    class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-2.5"
                >
                    <button
                        v-for="product in displayedProducts"
                        :key="product.id"
                        type="button"
                        @click="emit('select-product', product)"
                        class="group relative bg-white rounded-xl border p-2.5 text-left flex flex-col justify-between transition-all active:scale-[0.98] shadow-sm hover:shadow-md cursor-pointer"
                        :class="
                            getInCartBadge(product)
                                ? 'border-emerald-500 ring-1 ring-emerald-500/30'
                                : 'border-zinc-200/90 hover:border-zinc-400'
                        "
                    >
                        <!-- Active In-Cart Counter Pill -->
                        <span
                            v-if="getInCartBadge(product)"
                            class="absolute -top-2 right-2 z-10 px-2 py-0.5 rounded-full bg-emerald-600 text-white text-[10px] font-bold font-mono shadow-sm tabular-nums"
                        >
                            {{ getInCartBadge(product) }}
                        </span>

                        <div>
                            <!-- Thumbnail + Meta Row -->
                            <div class="flex items-start gap-2.5">
                                <div class="w-12 h-12 rounded-lg bg-zinc-100 border border-zinc-200/70 overflow-hidden shrink-0 flex items-center justify-center">
                                    <img
                                        v-if="product.image && !brokenImages[product.id]"
                                        :src="product.image"
                                        :alt="product.name"
                                        loading="lazy"
                                        decoding="async"
                                        @error="brokenImages[product.id] = true"
                                        class="w-full h-full object-cover group-hover:scale-105 transition-transform"
                                    />
                                    <span v-else class="text-xs font-bold font-mono text-zinc-400">
                                        {{ productInitials(product.name) }}
                                    </span>
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="text-xs font-bold text-zinc-900 leading-snug line-clamp-2 group-hover:text-emerald-700 transition-colors">
                                        {{ product.name }}
                                    </div>
                                    <div class="text-[11px] text-zinc-400 truncate mt-0.5">
                                        {{ product.size_main || 'Each' }}
                                    </div>
                                </div>
                            </div>

                            <!-- SKU / PLU + Stock Status Row -->
                            <div class="flex items-center justify-between gap-1.5 mt-2.5">
                                <span
                                    v-if="product.sku"
                                    class="px-1.5 py-0.5 rounded bg-zinc-100 text-zinc-700 font-mono text-[10px] font-semibold truncate"
                                >
                                    {{ /^\d+$/.test(String(product.sku)) ? `PLU ${product.sku}` : product.sku }}
                                </span>
                                <span v-else class="text-[10px] font-medium text-zinc-400 truncate">
                                    {{ product.category_title || 'Catalog' }}
                                </span>

                                <span
                                    class="px-1.5 py-0.5 rounded text-[10px] font-mono font-semibold tabular-nums"
                                    :class="
                                        product.stock < 0
                                            ? 'bg-amber-100 text-amber-800'
                                            : product.stock === 0
                                              ? 'bg-zinc-100 text-zinc-500'
                                              : product.stock <= 5
                                                ? 'bg-amber-50 text-amber-700'
                                                : 'text-zinc-500'
                                    "
                                >
                                    {{
                                        product.stock < 0
                                            ? `Audit ${product.stock}`
                                            : product.stock === 0
                                              ? '0 stock'
                                              : `${product.stock} stk`
                                    }}
                                </span>
                            </div>
                        </div>

                        <!-- Price Footer -->
                        <div class="mt-2.5 pt-2 border-t border-zinc-100 flex items-center justify-between gap-1">
                            <div class="font-mono text-sm font-bold text-zinc-950 tabular-nums">
                                {{ formatMoney(product.price) }}
                                <span v-if="product.unit_type !== 'each'" class="text-[11px] font-medium text-sky-700">
                                    /{{ product.unit_type }}
                                </span>
                            </div>

                            <div class="flex items-center gap-1">
                                <span
                                    v-if="product.unit_type !== 'each'"
                                    class="px-1.5 py-0.5 rounded bg-sky-50 border border-sky-200/80 text-sky-700 text-[10px] font-semibold inline-flex items-center gap-0.5"
                                >
                                    <Scale class="w-2.5 h-2.5" />
                                    {{ product.unit_type }}
                                </span>
                                <span
                                    v-if="product.is_taxable"
                                    class="px-1.5 py-0.5 rounded bg-zinc-100 text-zinc-600 text-[10px] font-mono font-semibold"
                                >
                                    TAX
                                </span>
                            </div>
                        </div>
                    </button>
                </div>

                <!-- MODE B: High-Density Cashier List -->
                <div v-else class="bg-white border border-zinc-200 rounded-xl overflow-hidden shadow-sm divide-y divide-zinc-100">
                    <button
                        v-for="product in displayedProducts"
                        :key="product.id"
                        type="button"
                        @click="emit('select-product', product)"
                        class="w-full px-3.5 py-2.5 hover:bg-emerald-50/50 active:bg-emerald-50 flex items-center justify-between gap-3 text-left transition-colors cursor-pointer"
                    >
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-9 h-9 rounded-md bg-zinc-100 border border-zinc-200/70 overflow-hidden shrink-0 flex items-center justify-center">
                                <img
                                    v-if="product.image && !brokenImages[product.id]"
                                    :src="product.image"
                                    :alt="product.name"
                                    loading="lazy"
                                    decoding="async"
                                    @error="brokenImages[product.id] = true"
                                    class="w-full h-full object-cover"
                                />
                                <Package v-else class="w-4 h-4 text-zinc-400" />
                            </div>

                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-bold text-zinc-900 truncate">{{ product.name }}</span>
                                    <span
                                        v-if="product.sku"
                                        class="px-1.5 py-0.5 rounded bg-zinc-100 text-zinc-600 font-mono text-[10px] font-semibold"
                                    >
                                        {{ product.sku }}
                                    </span>
                                    <span
                                        v-if="product.is_taxable"
                                        class="px-1.5 py-0.5 rounded bg-zinc-100 text-zinc-500 font-mono text-[10px]"
                                    >
                                        +TAX
                                    </span>
                                </div>
                                <div class="text-[11px] text-zinc-500">
                                    {{ product.size_main || 'Standard' }} · Stock: {{ product.stock }}
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 shrink-0">
                            <span
                                v-if="getInCartBadge(product)"
                                class="px-2 py-0.5 rounded-full bg-emerald-600 text-white text-[10px] font-bold font-mono tabular-nums"
                            >
                                {{ getInCartBadge(product) }}
                            </span>
                            <span class="font-mono text-sm font-bold text-zinc-950 tabular-nums w-20 text-right">
                                {{ formatMoney(product.price) }}<span v-if="product.unit_type !== 'each'" class="text-[11px] text-sky-700">/{{ product.unit_type }}</span>
                            </span>
                        </div>
                    </button>
                </div>

                <!-- Pagination Load More Footer -->
                <div
                    v-if="paginationMeta.has_more && displayedProducts.length > 0"
                    class="pt-2 pb-1 flex justify-center"
                >
                    <button
                        type="button"
                        @click="loadNextPage"
                        :disabled="loadingMore"
                        class="h-10 px-5 rounded-xl border border-zinc-300 bg-white hover:bg-zinc-50 text-xs font-bold text-zinc-800 shadow-sm inline-flex items-center gap-2 transition-colors cursor-pointer disabled:opacity-50"
                    >
                        <Loader2 v-if="loadingMore" class="w-3.5 h-3.5 animate-spin text-emerald-600" />
                        <span>
                            {{
                                loadingMore
                                    ? 'Loading more items...'
                                    : `Load More (${paginationMeta.total - displayedProducts.length} remaining)`
                            }}
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </section>
</template>
