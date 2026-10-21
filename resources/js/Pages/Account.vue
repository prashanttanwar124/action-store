<script setup>
import { ref, computed, watch, watchEffect } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import StoreLayout from '../Layouts/StoreLayout.vue';
import { useStore } from '../stores/cart';
import { 
  ShoppingBag,
  Sparkles, 
  RefreshCw, 
  RotateCcw, 
  ChevronRight, 
  ChevronDown,
  ChevronUp,
  CheckCircle2, 
  LogOut,
  Plus,
  Minus,
  Award,
  Flame,
  UtensilsCrossed,
  ShieldCheck,
  MapPin,
  Receipt,
  Gift,
  Settings
} from 'lucide-vue-next';

const props = defineProps({
  dbOrders: {
    type: Array,
    default: () => [],
  },
  ordersPagination: {
    type: Object,
    default: () => ({
      current_page: 1,
      last_page: 1,
      per_page: 10,
      total: 0,
      has_more: false,
      next_page_url: null,
    }),
  },
  activeSection: {
    type: String,
    default: 'account',
  },
  activeOrders: {
    type: Array,
    default: () => [],
  },
  rewardLedger: {
    type: Array,
    default: () => [],
  },
  buyAgainItems: {
    type: Array,
    default: () => [],
  },
  popularEssentials: {
    type: Array,
    default: () => [],
  },
});

const store = useStore();
const page = usePage();
const reorderedNotification = ref(false);
const reorderedItemCount = ref(0);
const loadingMoreOrders = ref(false);
const accumulatedOrders = ref([...(props.dbOrders || [])]);
const showAllLedger = ref(false);
const selectedCategory = ref('all');

// Sync buyAgainItems & popularEssentials into Pinia store catalog so stock & cart steppers stay reactive
watchEffect(() => {
  const catalogItems = [
    ...(Array.isArray(props.buyAgainItems) ? props.buyAgainItems : []),
    ...(Array.isArray(props.popularEssentials) ? props.popularEssentials : []),
  ];
  const regularProducts = catalogItems.filter((i) => !i.isRecipeKit && i.type !== 'recipe-kit');
  const kitItems = catalogItems.filter((i) => i.isRecipeKit || i.type === 'recipe-kit');

  if (regularProducts.length > 0) {
    store.setProducts(regularProducts);
  }
  if (kitItems.length > 0) {
    store.setRecipeKits(
      kitItems.map((k) => ({
        ...k,
        id: k.recipe_kit_id || (typeof k.id === 'string' && k.id.startsWith('kit-') ? Number(k.id.slice(4)) || k.id : k.id),
      }))
    );
  }
});

watch(
  () => props.dbOrders,
  (newOrders) => {
    const incoming = newOrders || [];
    if ((props.ordersPagination?.current_page || 1) <= 1) {
      accumulatedOrders.value = [...incoming];
      return;
    }

    const existingIds = new Set(accumulatedOrders.value.map((o) => o.id));
    incoming.forEach((order) => {
      if (!existingIds.has(order.id)) {
        accumulatedOrders.value.push(order);
      }
    });
  }
);

const isReorderMode = computed(() => props.activeSection === 'reorder');
const storeInfo = computed(() => page.props.storeInfo || {});
const authUser = computed(() => page.props.auth?.user);
const userName = computed(() => authUser.value ? authUser.value.name.split(' ')[0] : 'Guest');

const displayOrders = computed(() => {
  if (!authUser.value) {
    return [];
  }
  return accumulatedOrders.value;
});

const totalOrdersCount = computed(() => {
  return props.ordersPagination?.total ?? displayOrders.value.length;
});

const hasMoreOrders = computed(() => {
  return Boolean(props.ordersPagination?.has_more && props.ordersPagination?.next_page_url);
});

function loadMoreOrders() {
  if (!hasMoreOrders.value || loadingMoreOrders.value) {
    return;
  }

  loadingMoreOrders.value = true;
  router.get(
    props.ordersPagination.next_page_url,
    {},
    {
      preserveState: true,
      preserveScroll: true,
      only: ['dbOrders', 'ordersPagination'],
      onFinish: () => {
        loadingMoreOrders.value = false;
      },
    }
  );
}

// Dynamic Reward Points Math
const userPoints = computed(() => {
  if (!authUser.value) {
    return 0;
  }
  if (typeof authUser.value.reward_points === 'number') {
    return authUser.value.reward_points;
  }
  return store.rewardPoints || 0;
});

const REWARD_MILESTONE = 500;

const rewardTier = computed(() => {
  const pts = userPoints.value;
  if (pts >= 500) return 'Gold Member';
  if (pts >= 200) return 'Silver Member';
  return 'Member';
});

const pointsIntoCurrentCycle = computed(() => {
  return userPoints.value % REWARD_MILESTONE;
});

const pointsToNextReward = computed(() => {
  return REWARD_MILESTONE - pointsIntoCurrentCycle.value;
});

const rewardProgressPercent = computed(() => {
  if (userPoints.value > 0 && pointsIntoCurrentCycle.value === 0) {
    return 100;
  }
  return Math.min(100, Math.max(4, Math.round((pointsIntoCurrentCycle.value / REWARD_MILESTONE) * 100)));
});

const filledRewardSegments = computed(() => {
  if (userPoints.value > 0 && pointsIntoCurrentCycle.value === 0) {
    return 10;
  }
  return Math.max(1, Math.min(10, Math.round((pointsIntoCurrentCycle.value / REWARD_MILESTONE) * 10)));
});

const visibleLedgerEntries = computed(() => {
  const list = props.rewardLedger || [];
  return showAllLedger.value ? list : list.slice(0, 4);
});

// Buy It Again Categories & Filtering
const availableCategories = computed(() => {
  const items = props.buyAgainItems || [];
  const map = new Map();
  items.forEach((item) => {
    const key = item.isRecipeKit ? 'recipe-kit' : (item.category || 'staples');
    const label = item.isRecipeKit ? 'Recipe Kits' : (item.category_title || key);
    if (!map.has(key)) {
      map.set(key, { id: key, label, count: 0 });
    }
    map.get(key).count += 1;
  });
  return Array.from(map.values());
});

const filteredBuyAgainItems = computed(() => {
  const items = props.buyAgainItems || [];
  if (selectedCategory.value === 'all') {
    return items;
  }
  return items.filter((item) => {
    const key = item.isRecipeKit ? 'recipe-kit' : (item.category || 'staples');
    return key === selectedCategory.value;
  });
});

function itemDetailHref(item) {
  if (item.isRecipeKit || item.type === 'recipe-kit') {
    return item.slug ? `/recipe-kits/${item.slug}` : '/recipe-kits';
  }
  return item.slug ? `/products/${item.slug}` : '/';
}

function getItemCartQty(item) {
  return store.getQuantity(item, item.isRecipeKit ? 'recipe-kit' : 'product');
}

function isItemOutOfStock(item) {
  if (item.isRecipeKit && item.is_active === false) {
    return true;
  }
  if (typeof item.stock === 'number' && item.stock <= 0) {
    return true;
  }
  return store.isOutOfStock(item);
}

function handleAddSingleItem(item) {
  store.addToCart({ ...item, quantity: 1 }, 1);
}

function handleDecrementSingleItem(item) {
  store.removeFromCart(item, item.isRecipeKit ? 'recipe-kit' : 'product');
}

function handleAddAllEssentials() {
  const list = filteredBuyAgainItems.value.filter((i) => !isItemOutOfStock(i));
  if (list.length === 0) return;

  let addedCount = 0;
  list.forEach((item) => {
    if (store.addToCart({ ...item, quantity: 1 }, 1)) {
      addedCount += 1;
    }
  });

  if (addedCount > 0) {
    reorderedItemCount.value = addedCount;
    reorderedNotification.value = true;
    setTimeout(() => {
      reorderedNotification.value = false;
    }, 2500);
  }
}

function handleReorderOrder(order) {
  if (!order || !Array.isArray(order.items) || order.items.length === 0) {
    return;
  }

  let addedCount = 0;
  order.items.forEach(item => {
    if (store.addToCart(item, item.quantity || 1)) {
      addedCount += 1;
    }
  });

  if (addedCount > 0) {
    reorderedItemCount.value = addedCount;
    reorderedNotification.value = true;
    setTimeout(() => {
      reorderedNotification.value = false;
    }, 2500);
  }
}

function isOrderFullyRefunded(order) {
  const total = Number(order?.total || 0);
  const refunded = Number(order?.refunded_total || 0);
  return refunded > 0 && total - refunded <= 0;
}

function displayOrderTotal(order) {
  if (isOrderFullyRefunded(order)) {
    return Number(order?.total || 0);
  }
  return Math.max(0, Number(order?.total || 0) - Number(order?.refunded_total || 0));
}

function refundedItemsForOrder(order) {
  if (!Array.isArray(order?.items)) return [];
  return order.items.filter((i) => Number(i?.refunded_quantity || 0) > 0);
}

function isOrderItemFullyRefunded(item) {
  return Number(item?.refunded_quantity || 0) >= Number(item?.quantity || 1);
}
</script>

<template>
  <Head :title="isReorderMode ? `Buy It Again — ${storeInfo.brand_name}` : `Customer Dashboard — ${storeInfo.brand_name}`" />

  <StoreLayout :showHeader="true" :showFooter="true" :showBottomNav="true" :showCartBar="true">
    <div class="max-w-6xl mx-auto space-y-6 pb-24">

      <!-- Toast Feedback -->
      <Transition
        enter-active-class="transform transition ease-out duration-200"
        enter-from-class="translate-y-2 opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transform transition ease-in duration-150"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="translate-y-2 opacity-0"
      >
        <div 
          v-if="reorderedNotification"
          class="fixed top-20 left-4 right-4 sm:left-auto sm:right-6 sm:w-96 z-50 bg-[#1a1a1a] text-white p-4 rounded-lg shadow-2xl border border-[#a47a3c]/40 flex items-center justify-between text-xs font-semibold"
        >
          <div class="flex items-center gap-2.5">
            <CheckCircle2 class="w-4 h-4 text-[#e4b97a] shrink-0" />
            <span>Reordered {{ reorderedItemCount }} item{{ reorderedItemCount > 1 ? 's' : '' }} into your cart!</span>
          </div>
          <Link href="/cart" class="text-[#e4b97a] hover:underline font-bold inline-flex items-center gap-1 shrink-0 ml-3">
            <span>View Cart</span>
            <ChevronRight class="w-3.5 h-3.5" />
          </Link>
        </div>
      </Transition>

      <!-- =================================================================== -->
      <!-- PAGE HEADER CARD (Clean, No Duplicate Tab Switcher)                 -->
      <!-- =================================================================== -->
      <div class="bg-white border border-[#dfd6c8] rounded-xl p-4 sm:p-6 shadow-2xs">
        <div class="flex items-center justify-between gap-4">

          <!-- Left: User Identity & Title -->
          <div class="flex items-start sm:items-center gap-3.5 min-w-0">
            <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-xl bg-[#f4efe6] border border-[#dfd6c8] flex items-center justify-center text-[#1d1d1f] font-serif font-semibold text-xl sm:text-2xl shrink-0 shadow-2xs">
              <RefreshCw v-if="isReorderMode" class="w-5 h-5 sm:w-6 sm:h-6 text-[#8a6b32]" />
              <span v-else>{{ authUser ? authUser.name.charAt(0).toUpperCase() : 'G' }}</span>
            </div>

            <div class="min-w-0 space-y-1">
              <div class="flex flex-wrap items-center gap-2">
                <span class="text-[10px] font-mono uppercase bg-[#1a1a1a] text-white px-2 py-0.5 rounded-md font-bold tracking-wider">
                  {{ isReorderMode ? 'Pantry Restock' : 'Customer Account' }}
                </span>
                <span class="font-devanagari text-[11px] text-[#7a5620] bg-[#f5eee2] px-2 py-0.5 rounded-md border border-[#e0d9cc] font-medium">
                  {{ isReorderMode ? 'फिर से खरीदें' : 'ग्राहक खाता' }}
                </span>
                <span
                  v-if="authUser && !isReorderMode"
                  class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-800 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-md"
                >
                  <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                  {{ rewardTier }}
                </span>
              </div>

              <h1 class="text-xl sm:text-2xl md:text-3xl font-serif font-medium text-[#1d1d1f] tracking-tight leading-tight">
                <template v-if="isReorderMode">
                  Buy It Again
                </template>
                <template v-else>
                  {{ authUser ? `Hi, ${userName}` : `Welcome to ${storeInfo.brand_name}` }}
                </template>
              </h1>

              <p class="text-xs text-[#6e6e73] font-normal truncate">
                <template v-if="isReorderMode">
                  {{ authUser ? `Restock your most-ordered ${storeInfo.brand_name} groceries & recipe kits in one tap.` : 'Quickly add popular weekly Indian grocery essentials to your pickup cart.' }}
                </template>
                <template v-else-if="authUser">
                  {{ authUser.email }} · {{ totalOrdersCount }} Pickup Order{{ totalOrdersCount === 1 ? '' : 's' }} · {{ userPoints.toLocaleString() }} Points
                </template>
                <template v-else>
                  Guest Preview · Sign in to track live pickup orders and earn reward points.
                </template>
              </p>
            </div>
          </div>

          <!-- Right Quick Action (Desktop) -->
          <div v-if="authUser && !isReorderMode" class="hidden sm:flex items-center gap-2 shrink-0">
            <Link
              href="/profile"
              class="px-3.5 py-2 rounded-lg border border-[#dfd6c8] bg-[#faf7f2] hover:bg-[#f0e9dd] text-xs font-semibold text-[#1d1d1f] inline-flex items-center gap-1.5 transition-colors"
            >
              <Settings class="w-3.5 h-3.5 text-[#8a6b32]" />
              <span>Edit Profile</span>
            </Link>
            <Link
              href="/logout"
              method="post"
              as="button"
              class="px-3.5 py-2 rounded-lg border border-rose-200 bg-rose-50 hover:bg-rose-100 text-xs font-semibold text-rose-700 inline-flex items-center gap-1.5 transition-colors cursor-pointer"
            >
              <LogOut class="w-3.5 h-3.5" />
              <span>Sign Out</span>
            </Link>
          </div>

          <button
            v-else-if="isReorderMode && buyAgainItems.length > 0"
            type="button"
            @click="handleAddAllEssentials"
            class="hidden sm:inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-lg bg-[#a47a3c] hover:bg-[#8a6b32] text-white text-xs font-semibold transition-colors cursor-pointer shadow-xs shrink-0"
          >
            <Plus class="w-4 h-4 stroke-[2.5]" />
            <span>Add All Essentials</span>
          </button>

        </div>

        <!-- Guest Sign-In Prompt Strip (Only when not logged in) -->
        <div
          v-if="!authUser"
          class="mt-4 pt-4 border-t border-[#e0d9cc]/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3"
        >
          <div class="flex items-center gap-2.5 text-xs text-[#6e6e73]">
            <Sparkles class="w-4 h-4 text-[#a47a3c] shrink-0" />
            <span>
              <strong class="text-[#1d1d1f]">Sign in to your account</strong> to earn 1 point per $1 spent, save your personal pantry, and track pickup orders live.
            </span>
          </div>
          <div class="flex items-center gap-2 shrink-0">
            <Link
              href="/login"
              class="px-4 py-2 bg-[#1a1a1a] hover:bg-black text-white text-xs font-semibold rounded-md transition-colors shadow-xs"
            >
              Sign In
            </Link>
            <Link
              href="/register"
              class="px-4 py-2 bg-[#f5eee2] hover:bg-[#ebe1cf] text-[#1d1d1f] border border-[#dfd6c8] text-xs font-semibold rounded-md transition-colors"
            >
              Create Account
            </Link>
          </div>
        </div>
      </div>

      <!-- =================================================================== -->
      <!-- MODE A: /reorder — BUY IT AGAIN (FULL-WIDTH PANTRY RESTOCK)         -->
      <!-- =================================================================== -->
      <template v-if="isReorderMode">

        <!-- SECTION R1: Frequent Essentials Grid (Authenticated user with past items) -->
        <div v-if="buyAgainItems.length > 0" class="space-y-4">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-2 overflow-x-auto no-scrollbar">
              <button
                type="button"
                @click="selectedCategory = 'all'"
                class="px-3.5 py-1.5 rounded-lg text-xs font-semibold shrink-0 transition-colors cursor-pointer"
                :class="selectedCategory === 'all' ? 'bg-[#1a1a1a] text-white shadow-2xs' : 'bg-white text-[#6e6e73] hover:text-[#1d1d1f] border border-[#dfd6c8]'"
              >
                All Essentials ({{ buyAgainItems.length }})
              </button>
              <button
                v-for="cat in availableCategories"
                :key="cat.id"
                type="button"
                @click="selectedCategory = cat.id"
                class="px-3.5 py-1.5 rounded-lg text-xs font-semibold shrink-0 transition-colors cursor-pointer inline-flex items-center gap-1.5"
                :class="selectedCategory === cat.id ? 'bg-[#1a1a1a] text-white shadow-2xs' : 'bg-white text-[#6e6e73] hover:text-[#1d1d1f] border border-[#dfd6c8]'"
              >
                <UtensilsCrossed v-if="cat.id === 'recipe-kit'" class="w-3 h-3 text-[#a47a3c]" />
                <span>{{ cat.label }} ({{ cat.count }})</span>
              </button>
            </div>

            <button
              type="button"
              @click="handleAddAllEssentials"
              class="sm:hidden inline-flex items-center justify-center gap-1.5 px-3.5 py-2 rounded-lg bg-[#a47a3c] hover:bg-[#8a6b32] text-white text-xs font-semibold transition-colors cursor-pointer shadow-xs"
            >
              <Plus class="w-3.5 h-3.5 stroke-[2.5]" />
              <span>Add All</span>
            </button>
          </div>

          <!-- 2 / 3 / 4 Column Storefront Product Grid -->
          <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3.5 sm:gap-4">
            <div
              v-for="item in filteredBuyAgainItems"
              :key="item.id"
              class="group rounded-xl border border-[#dfd6c8] bg-white overflow-hidden flex flex-col justify-between shadow-2xs hover:shadow-md hover:border-[#c5b7a0] transition-all"
            >
              <div>
                <!-- Square Product Thumbnail with Badges -->
                <Link
                  :href="itemDetailHref(item)"
                  class="block relative aspect-square bg-[#f4efe6] overflow-hidden border-b border-[#ece5d8]"
                >
                  <img
                    :src="item.image || '/images/products/atta.jpg'"
                    :alt="item.name"
                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                    :class="isItemOutOfStock(item) ? 'opacity-40 grayscale' : ''"
                  />
                  <!-- Frequency Badge Top-Left -->
                  <span class="absolute top-2.5 left-2.5 inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold bg-[#1a1a1a]/90 text-white backdrop-blur-xs shadow-xs">
                    <Flame class="w-2.5 h-2.5 text-[#e4b97a]" />
                    Ordered {{ item.times_ordered }}×
                  </span>
                  <!-- Recipe Kit Badge Top-Right -->
                  <span
                    v-if="item.isRecipeKit"
                    class="absolute top-2.5 right-2.5 px-2 py-0.5 rounded-md text-[10px] font-bold bg-[#a47a3c] text-white shadow-xs"
                  >
                    Kit
                  </span>
                </Link>

                <!-- Item Info -->
                <div class="p-3.5 space-y-1">
                  <div class="flex items-center justify-between gap-1 text-[10.5px]">
                    <span class="text-[#8a6b32] font-semibold uppercase tracking-wider truncate">
                      {{ item.category_title || 'Pantry' }}
                    </span>
                    <span
                      class="font-medium shrink-0"
                      :class="isItemOutOfStock(item) ? 'text-rose-600' : 'text-emerald-700'"
                    >
                      {{ isItemOutOfStock(item) ? 'Out of stock' : 'In stock' }}
                    </span>
                  </div>

                  <Link
                    :href="itemDetailHref(item)"
                    class="block font-bold text-xs sm:text-sm text-[#1d1d1f] group-hover:text-[#a47a3c] transition-colors line-clamp-2 leading-snug min-h-[2.5rem]"
                  >
                    {{ item.name }}
                  </Link>

                  <div class="text-[11px] text-[#6e6e73] truncate">
                    <span v-if="item.size">{{ item.size }}</span>
                    <span v-if="item.size && item.last_ordered_human"> · </span>
                    <span v-if="item.last_ordered_human">{{ item.last_ordered_human }}</span>
                  </div>
                </div>
              </div>

              <!-- Price + Action Footer -->
              <div class="px-3.5 pb-3.5 pt-2 border-t border-[#f3efe7] flex items-center justify-between gap-2">
                <span class="font-serif font-semibold text-base sm:text-lg text-[#1d1d1f]">
                  ${{ Number(item.price || 0).toFixed(2) }}
                </span>

                <button
                  v-if="isItemOutOfStock(item)"
                  type="button"
                  disabled
                  class="h-8 px-3 rounded-md bg-stone-100 text-stone-400 border border-stone-200 text-[11px] font-semibold cursor-not-allowed"
                >
                  Sold Out
                </button>

                <div
                  v-else-if="getItemCartQty(item) > 0"
                  class="inline-flex items-center bg-[#1a1a1a] text-white rounded-md h-8 px-1 shadow-xs"
                >
                  <button
                    type="button"
                    @click="handleDecrementSingleItem(item)"
                    class="w-6 h-6 flex items-center justify-center hover:bg-white/10 rounded-md cursor-pointer"
                    aria-label="Decrease quantity"
                  >
                    <Minus class="w-3 h-3 stroke-[2.5]" />
                  </button>
                  <span class="px-2 text-xs font-bold min-w-[20px] text-center">
                    {{ getItemCartQty(item) }}
                  </span>
                  <button
                    type="button"
                    @click="handleAddSingleItem(item)"
                    class="w-6 h-6 flex items-center justify-center hover:bg-white/10 rounded-md cursor-pointer"
                    aria-label="Increase quantity"
                  >
                    <Plus class="w-3 h-3 stroke-[2.5]" />
                  </button>
                </div>

                <button
                  v-else
                  type="button"
                  @click="handleAddSingleItem(item)"
                  class="h-8 px-3.5 rounded-md bg-[#1a1a1a] hover:bg-black text-white text-xs font-semibold inline-flex items-center gap-1 transition-colors cursor-pointer shadow-xs active:scale-[0.98]"
                >
                  <Plus class="w-3.5 h-3.5 stroke-[2.5]" />
                  <span>Add</span>
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- SECTION R2: Popular Weekly Staples Fallback (Guests or new customers) -->
        <div v-else-if="popularEssentials.length > 0" class="space-y-4">
          <div class="flex items-center justify-between border-b border-[#dfd6c8] pb-2.5">
            <div>
              <div class="flex items-center gap-2">
                <h2 class="text-lg sm:text-xl font-serif font-medium text-[#1d1d1f] tracking-tight">
                  Popular Weekly Staples
                </h2>
                <span class="font-devanagari text-xs text-[#7a5620] bg-[#f5eee2] px-2 py-0.5 rounded-md border border-[#e0d9cc]">
                  लोकप्रिय सामान
                </span>
              </div>
              <p class="text-xs text-[#6e6e73] mt-0.5">
                {{ authUser ? 'Once you complete your first pickup order, your personal frequent items will appear here.' : 'Most reordered daily essentials at ' + storeInfo.brand_name }}
              </p>
            </div>
          </div>

          <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3.5 sm:gap-4">
            <div
              v-for="item in popularEssentials"
              :key="item.id"
              class="group rounded-xl border border-[#dfd6c8] bg-white overflow-hidden flex flex-col justify-between shadow-2xs hover:shadow-md transition-all"
            >
              <div>
                <Link
                  :href="itemDetailHref(item)"
                  class="block relative aspect-square bg-[#f4efe6] overflow-hidden border-b border-[#ece5d8]"
                >
                  <img
                    :src="item.image || '/images/products/atta.jpg'"
                    :alt="item.name"
                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                  />
                  <span class="absolute top-2.5 left-2.5 px-2 py-0.5 rounded-md text-[10px] font-bold bg-white/90 text-[#7a5620] border border-[#dfd6c8] shadow-2xs">
                    Store Favorite
                  </span>
                </Link>

                <div class="p-3.5 space-y-1">
                  <span class="text-[10px] font-semibold uppercase tracking-wider text-[#a47a3c]">
                    {{ item.category_title || 'Essential' }}
                  </span>
                  <Link
                    :href="itemDetailHref(item)"
                    class="block font-bold text-xs sm:text-sm text-[#1d1d1f] group-hover:text-[#a47a3c] line-clamp-2 leading-snug min-h-[2.5rem]"
                  >
                    {{ item.name }}
                  </Link>
                  <div v-if="item.size" class="text-[11px] text-[#6e6e73]">{{ item.size }}</div>
                </div>
              </div>

              <div class="px-3.5 pb-3.5 pt-2 border-t border-[#f3efe7] flex items-center justify-between">
                <span class="font-serif font-semibold text-base sm:text-lg text-[#1d1d1f]">
                  ${{ Number(item.price || 0).toFixed(2) }}
                </span>

                <div
                  v-if="getItemCartQty(item) > 0"
                  class="inline-flex items-center bg-[#1a1a1a] text-white rounded-md h-8 px-1 shadow-xs"
                >
                  <button
                    type="button"
                    @click="handleDecrementSingleItem(item)"
                    class="w-6 h-6 flex items-center justify-center hover:bg-white/10 rounded-md cursor-pointer"
                  >
                    <Minus class="w-3 h-3 stroke-[2.5]" />
                  </button>
                  <span class="px-2 text-xs font-bold min-w-[20px] text-center">
                    {{ getItemCartQty(item) }}
                  </span>
                  <button
                    type="button"
                    @click="handleAddSingleItem(item)"
                    class="w-6 h-6 flex items-center justify-center hover:bg-white/10 rounded-md cursor-pointer"
                  >
                    <Plus class="w-3 h-3 stroke-[2.5]" />
                  </button>
                </div>

                <button
                  v-else
                  type="button"
                  @click="handleAddSingleItem(item)"
                  class="h-8 px-3.5 rounded-md bg-[#1a1a1a] hover:bg-black text-white text-xs font-semibold inline-flex items-center gap-1 transition-colors cursor-pointer shadow-xs"
                >
                  <Plus class="w-3.5 h-3.5 stroke-[2.5]" />
                  <span>Add</span>
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- SECTION R3: Reorder Full Past Baskets (2-Column Grid on Desktop) -->
        <div v-if="displayOrders.length > 0" class="space-y-4 pt-2">
          <div class="flex items-center justify-between border-b border-[#dfd6c8] pb-2.5">
            <div class="flex items-center gap-2">
              <h2 class="text-lg sm:text-xl font-serif font-medium text-[#1d1d1f] tracking-tight">
                Reorder Full Past Baskets
              </h2>
              <span class="text-xs font-normal text-[#6e6e73]">
                ({{ displayOrders.length }} of {{ totalOrdersCount }})
              </span>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div
              v-for="order in displayOrders"
              :key="order.id"
              class="rounded-xl border border-[#dfd6c8] bg-white p-4 sm:p-5 flex flex-col justify-between gap-4 shadow-2xs hover:border-[#c5b7a0] transition-colors"
            >
              <div class="space-y-2.5">
                <div class="flex items-center justify-between gap-2">
                  <div class="flex items-center gap-2">
                    <span class="font-bold text-sm text-[#1d1d1f]">{{ order.date }}</span>
                    <span class="text-xs text-[#6e6e73] font-mono">{{ order.id }}</span>
                  </div>
                  <span class="font-serif font-semibold text-base text-[#1d1d1f]">
                    ${{ displayOrderTotal(order).toFixed(2) }}
                  </span>
                </div>

                <p class="text-xs text-[#6e6e73] line-clamp-1">
                  {{ order.summary }}
                </p>

                <div v-if="order.items && order.items.length" class="flex items-center gap-2 pt-1 overflow-x-auto no-scrollbar">
                  <div
                    v-for="item in order.items.slice(0, 6)"
                    :key="item.id"
                    class="w-10 h-10 rounded-lg bg-[#f4efe6] overflow-hidden shrink-0 border border-[#dfd6c8]"
                    :title="`${item.quantity}× ${item.name}`"
                  >
                    <img :src="item.image || '/images/products/atta.jpg'" :alt="item.name" class="w-full h-full object-cover" />
                  </div>
                </div>
              </div>

              <div class="flex items-center gap-2 pt-3 border-t border-[#f3efe7]">
                <Link
                  :href="'/orders/' + encodeURIComponent(order.id)"
                  class="px-3.5 h-10 rounded-lg border border-[#dfd6c8] bg-[#faf7f2] hover:bg-[#f0e9dd] text-[#1d1d1f] text-xs font-semibold inline-flex items-center justify-center gap-1.5 transition-colors"
                >
                  <Receipt class="w-3.5 h-3.5 text-[#8a6b32]" />
                  <span>Receipt</span>
                </Link>
                <button
                  type="button"
                  @click="handleReorderOrder(order)"
                  class="flex-1 h-10 bg-[#1a1a1a] hover:bg-black text-white font-semibold text-xs rounded-lg transition-colors flex items-center justify-center gap-2 cursor-pointer shadow-xs"
                >
                  <RotateCcw class="w-3.5 h-3.5 stroke-[2.2]" />
                  <span>Reorder Basket ({{ order.itemCount }})</span>
                </button>
              </div>
            </div>
          </div>

          <div v-if="hasMoreOrders" class="pt-1 max-w-md mx-auto">
            <button
              type="button"
              :disabled="loadingMoreOrders"
              @click="loadMoreOrders"
              class="w-full h-11 rounded-lg border border-[#dfd6c8] bg-white hover:bg-[#f4efe6] disabled:opacity-60 text-[#1d1d1f] font-semibold text-xs transition-colors flex items-center justify-center gap-2 cursor-pointer shadow-2xs"
            >
              <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': loadingMoreOrders }" />
              <span>{{ loadingMoreOrders ? 'Loading...' : 'Load More Past Baskets' }}</span>
            </button>
          </div>
        </div>
      </template>

      <!-- =================================================================== -->
      <!-- MODE B: /account — 2-COLUMN CUSTOMER DASHBOARD & REWARDS            -->
      <!-- =================================================================== -->
      <template v-else>
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8 items-start">

          <!-- =============================================================== -->
          <!-- LEFT COLUMN (lg:col-span-5): REWARDS CARD, LEDGER & PROFILE     -->
          <!-- =============================================================== -->
          <div class="lg:col-span-5 space-y-6">

            <!-- CARD 1: Masala Mart Rewards & Loyalty Card -->
            <div class="rounded-xl overflow-hidden border border-[#dfd6c8] bg-white shadow-xs">
              <!-- Dark Luxury Top Panel -->
              <div class="bg-[#181512] text-white p-5 sm:p-6 space-y-4">
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-2">
                    <Gift class="w-4 h-4 text-[#e4b97a]" />
                    <span class="text-[11px] font-bold uppercase tracking-widest text-[#e4b97a]">
                      {{ storeInfo.brand_name }} Rewards
                    </span>
                  </div>
                  <span class="text-[11px] font-semibold text-[#e4b97a] bg-[#a47a3c]/20 border border-[#a47a3c]/40 px-2.5 py-0.5 rounded-md inline-flex items-center gap-1">
                    <Award class="w-3 h-3" />
                    <span>{{ rewardTier }}</span>
                  </span>
                </div>

                <div class="flex items-baseline justify-between pt-1">
                  <div>
                    <div class="flex items-baseline gap-2">
                      <span class="text-4xl sm:text-5xl font-serif font-medium tracking-tight text-white leading-none">
                        {{ userPoints.toLocaleString() }}
                      </span>
                      <span class="text-xs text-stone-400 font-semibold uppercase tracking-wider">Points</span>
                    </div>
                    <p class="text-xs text-stone-400 mt-1.5">
                      {{ pointsToNextReward }} pts away from your next <strong class="text-[#e4b97a]">$10 reward</strong>
                    </p>
                  </div>

                  <div class="text-right bg-white/5 border border-white/10 rounded-lg px-3 py-2">
                    <span class="block text-[9.5px] uppercase tracking-wider text-stone-400">Earning Rate</span>
                    <span class="font-mono font-bold text-xs text-[#e4b97a]">1 pt = $1 spent</span>
                  </div>
                </div>

                <!-- 10-Segment Progress Bar -->
                <div class="space-y-1.5 pt-1">
                  <div class="grid grid-cols-10 gap-1.5">
                    <div 
                      v-for="i in 10" 
                      :key="i"
                      :class="[
                        'h-2 rounded-sm transition-colors',
                        i <= filledRewardSegments ? 'bg-[#d4a153]' : 'bg-stone-800'
                      ]"
                    ></div>
                  </div>
                  <div class="flex justify-between text-[11px] text-stone-400">
                    <span>{{ pointsIntoCurrentCycle }} / {{ REWARD_MILESTONE }} pts cycle</span>
                    <span>{{ rewardProgressPercent }}%</span>
                  </div>
                </div>
              </div>

              <!-- Light Bottom Panel: Points Activity Ledger -->
              <div class="p-4 sm:p-5 bg-white space-y-3">
                <div class="flex items-center justify-between">
                  <span class="text-xs font-bold uppercase tracking-wider text-[#1d1d1f]">
                    Points Activity History
                  </span>
                  <span class="text-[11px] text-[#6e6e73]">
                    {{ rewardLedger.length }} transaction{{ rewardLedger.length === 1 ? '' : 's' }}
                  </span>
                </div>

                <div v-if="rewardLedger.length > 0" class="divide-y divide-[#f0eae0]">
                  <div
                    v-for="entry in visibleLedgerEntries"
                    :key="entry.id"
                    class="py-2.5 first:pt-1 last:pb-0 flex items-center justify-between gap-3 text-xs"
                  >
                    <div class="min-w-0">
                      <div class="font-semibold text-[#1d1d1f] truncate">
                        {{ entry.description }}
                      </div>
                      <div class="text-[11px] text-[#6e6e73] mt-0.5">
                        {{ entry.date }}
                        <span v-if="entry.order_number" class="font-mono text-[#8a6b32]"> · {{ entry.order_number }}</span>
                      </div>
                    </div>
                    <span
                      class="font-mono font-bold text-xs px-2 py-0.5 rounded-md border shrink-0"
                      :class="entry.points >= 0
                        ? 'bg-emerald-50 text-emerald-800 border-emerald-200'
                        : 'bg-rose-50 text-rose-800 border-rose-200'"
                    >
                      {{ entry.points >= 0 ? '+' + entry.points : entry.points }} pts
                    </span>
                  </div>

                  <button
                    v-if="rewardLedger.length > 4"
                    type="button"
                    @click="showAllLedger = !showAllLedger"
                    class="w-full pt-3 text-xs font-semibold text-[#8a6b32] hover:text-[#1d1d1f] flex items-center justify-center gap-1 cursor-pointer"
                  >
                    <span>{{ showAllLedger ? 'Show Less' : `View All ${rewardLedger.length} Entries` }}</span>
                    <ChevronUp v-if="showAllLedger" class="w-3.5 h-3.5" />
                    <ChevronDown v-else class="w-3.5 h-3.5" />
                  </button>
                </div>

                <div v-else class="py-3 text-xs text-[#6e6e73] text-center bg-[#faf7f2] rounded-lg border border-[#ece5d8]">
                  {{ authUser ? 'Complete a pickup order to start earning reward points.' : 'Sign in to view your reward points ledger.' }}
                </div>
              </div>
            </div>

            <!-- CARD 2: Account & Security Management -->
            <div class="rounded-xl border border-[#dfd6c8] bg-white p-5 space-y-4 shadow-2xs">
              <div class="flex items-center justify-between border-b border-[#f0eae0] pb-3">
                <div class="flex items-center gap-2">
                  <ShieldCheck class="w-4 h-4 text-[#8a6b32]" />
                  <h2 class="text-sm font-bold text-[#1d1d1f]">Account &amp; Security</h2>
                </div>
                <span 
                  v-if="authUser" 
                  class="text-[10.5px] font-semibold text-emerald-800 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-md"
                >
                  Active Session
                </span>
              </div>

              <div class="space-y-2.5 text-xs">
                <div class="flex items-center justify-between py-1">
                  <span class="text-[#6e6e73]">Customer Name</span>
                  <span class="font-semibold text-[#1d1d1f]">{{ authUser ? authUser.name : 'Guest Customer' }}</span>
                </div>
                <div class="flex items-center justify-between py-1 border-t border-[#f5f0e6]">
                  <span class="text-[#6e6e73]">Email Address</span>
                  <span class="font-semibold text-[#1d1d1f] truncate max-w-[200px]">{{ authUser ? authUser.email : 'Not signed in' }}</span>
                </div>
                <div class="flex items-center justify-between py-1 border-t border-[#f5f0e6]">
                  <span class="text-[#6e6e73]">Default Store</span>
                  <span class="font-semibold text-[#1d1d1f] inline-flex items-center gap-1">
                    <MapPin class="w-3 h-3 text-[#a47a3c]" />
                    {{ storeInfo.name || storeInfo.brand_name }}
                  </span>
                </div>
              </div>

              <div class="pt-2 flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
                <template v-if="authUser">
                  <Link
                    href="/profile"
                    class="flex-1 py-2.5 px-3 bg-[#f4efe6] hover:bg-[#ebe3d5] text-[#1d1d1f] text-xs font-semibold rounded-lg transition-colors border border-[#dfd6c8] inline-flex items-center justify-center gap-1.5"
                  >
                    <Settings class="w-3.5 h-3.5 text-[#8a6b32]" />
                    <span>Profile &amp; Password</span>
                  </Link>
                  <Link
                    href="/logout"
                    method="post"
                    as="button"
                    class="py-2.5 px-4 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 text-xs font-semibold rounded-lg transition-colors cursor-pointer inline-flex items-center justify-center gap-1.5"
                  >
                    <LogOut class="w-3.5 h-3.5" />
                    <span>Sign Out</span>
                  </Link>
                </template>
                <template v-else>
                  <Link
                    href="/login"
                    class="flex-1 py-2.5 bg-[#1a1a1a] hover:bg-black text-white text-xs font-semibold rounded-lg transition-colors text-center shadow-xs"
                  >
                    Sign In
                  </Link>
                  <Link
                    href="/register"
                    class="flex-1 py-2.5 bg-white hover:bg-[#f4efe6] text-[#1d1d1f] border border-[#dfd6c8] text-xs font-semibold rounded-lg transition-colors text-center"
                  >
                    Register
                  </Link>
                </template>
              </div>
            </div>

          </div>

          <!-- =============================================================== -->
          <!-- RIGHT COLUMN (lg:col-span-7): ORDERS & RECEIPTS                 -->
          <!-- =============================================================== -->
          <div class="lg:col-span-7 space-y-4">

            <div class="flex items-center justify-between border-b border-[#dfd6c8] pb-2.5">
              <div class="flex items-center gap-2">
                <h2 class="text-lg sm:text-xl font-serif font-medium text-[#1d1d1f] tracking-tight">Past orders</h2>
                <span class="font-devanagari text-xs text-[#7a5620] bg-[#f5eee2] px-2 py-0.5 rounded-md border border-[#e0d9cc]">पिछला ऑर्डर</span>
              </div>
              <span v-if="displayOrders.length > 0" class="text-xs font-normal text-[#6e6e73]">
                Showing {{ displayOrders.length }} of {{ totalOrdersCount }} order{{ totalOrdersCount > 1 ? 's' : '' }}
              </span>
            </div>

            <!-- Orders List -->
            <div v-if="displayOrders.length > 0" class="space-y-4">
              <div 
                v-for="order in displayOrders" 
                :key="order.id"
                class="rounded-xl border border-[#dfd6c8] bg-white overflow-hidden shadow-2xs hover:border-[#c8bba6] transition-colors"
              >
                <!-- Order Card Header Bar -->
                <div class="bg-[#faf7f2] border-b border-[#ece5d8] px-4 sm:px-5 py-3 flex flex-wrap items-center justify-between gap-2">
                  <div class="flex flex-wrap items-center gap-2">
                    <span class="font-mono font-bold text-xs text-[#1d1d1f]">{{ order.id }}</span>
                    <span class="text-stone-300">·</span>
                    <span class="text-xs font-semibold text-[#6e6e73]">{{ order.date }} · {{ order.type }}</span>
                    <span 
                      v-if="order.status"
                      class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-md text-[10.5px] font-semibold"
                      :class="[
                        order.status === 'confirmed' ? 'bg-amber-100 text-amber-800' :
                        order.status === 'packing' ? 'bg-blue-100 text-blue-800' :
                        order.status === 'ready_for_pickup' ? 'bg-emerald-100 text-emerald-800' :
                        order.status === 'cancelled' ? 'bg-rose-100 text-rose-800' :
                        'bg-stone-200/80 text-stone-700'
                      ]"
                    >
                      <span v-if="order.status !== 'completed'" class="w-1.5 h-1.5 rounded-full bg-current"></span>
                      {{ 
                        order.status === 'confirmed' ? 'Received' :
                        order.status === 'packing' ? 'Packing' :
                        order.status === 'ready_for_pickup' ? 'Ready for Pickup' :
                        order.status === 'cancelled' ? 'Cancelled & Refunded' :
                        (order.status === 'completed' ? 'Completed' : order.status)
                      }}
                    </span>
                    <span
                      v-if="Boolean(Number(order.refunded_total || 0))"
                      class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold bg-rose-50 text-rose-700 border border-rose-200"
                    >
                      Refunded -${{ Number(order.refunded_total).toFixed(2) }}
                    </span>
                  </div>

                  <span
                    class="font-serif font-semibold text-base sm:text-lg"
                    :class="isOrderFullyRefunded(order) ? 'line-through text-rose-400' : 'text-[#1d1d1f]'"
                  >
                    ${{ displayOrderTotal(order).toFixed(2) }}
                  </span>
                </div>

                <!-- Order Card Body -->
                <div class="p-4 sm:p-5 space-y-4">
                  <div class="text-xs text-[#6e6e73] leading-relaxed">
                    {{ order.summary }}
                  </div>

                  <!-- Per-Item Refund Pills if any item was refunded -->
                  <div
                    v-if="refundedItemsForOrder(order).length"
                    class="flex flex-wrap items-center gap-1.5"
                  >
                    <span
                      v-for="item in refundedItemsForOrder(order)"
                      :key="'ref-' + item.id"
                      class="inline-flex items-center px-2.5 py-1 rounded-md text-[10.5px] font-semibold bg-rose-50 text-rose-800 border border-rose-200"
                    >
                      {{ item.refunded_quantity }}× {{ item.name }} Out of Stock · Refunded -${{ Number(item.refunded_amount || 0).toFixed(2) }}
                    </span>
                  </div>

                  <!-- Product Thumbnails Strip -->
                  <div v-if="order.items && order.items.length" class="flex items-center gap-2 overflow-x-auto no-scrollbar">
                    <div 
                      v-for="item in order.items.slice(0, 6)" 
                      :key="item.id"
                      class="flex items-center gap-2 pr-2.5 rounded-lg bg-[#faf7f2] border border-[#ece5d8] shrink-0"
                      :title="item.name"
                    >
                      <div class="w-10 h-10 rounded-l-lg bg-[#f3efe7] overflow-hidden shrink-0">
                        <img :src="item.image || '/images/products/atta.jpg'" :alt="item.name" class="w-full h-full object-cover" :class="isOrderItemFullyRefunded(item) ? 'opacity-40 grayscale' : ''" />
                      </div>
                      <div class="py-1 max-w-[120px]">
                        <div class="text-[11px] font-semibold text-[#1d1d1f] truncate">{{ item.name }}</div>
                        <div class="text-[10px] text-[#6e6e73]">Qty {{ item.quantity }} · ${{ Number(item.price || 0).toFixed(2) }}</div>
                      </div>
                    </div>
                  </div>

                  <!-- Actions Row: Track Live & Reorder Button -->
                  <div class="pt-1 flex items-center gap-2.5">
                    <Link 
                      :href="'/orders/' + encodeURIComponent(order.id)"
                      class="flex-1 h-10 bg-[#f4efe6] hover:bg-[#ebe2d3] text-[#1d1d1f] border border-[#dfd6c8] font-semibold text-xs rounded-lg transition-colors flex items-center justify-center gap-1.5 cursor-pointer"
                    >
                      <Sparkles class="w-3.5 h-3.5 text-[#8a6b32]" />
                      <span>Track Live</span>
                    </Link>
                    <button 
                      @click="handleReorderOrder(order)"
                      class="flex-1 h-10 bg-[#1a1a1a] hover:bg-black text-white font-semibold text-xs rounded-lg transition-colors flex items-center justify-center gap-2 cursor-pointer shadow-xs"
                    >
                      <RotateCcw class="w-3.5 h-3.5 stroke-[2.2]" />
                      <span>Reorder ({{ order.itemCount }})</span>
                    </button>
                  </div>
                </div>
              </div>

              <!-- Load More Button -->
              <div v-if="hasMoreOrders" class="pt-1">
                <button
                  type="button"
                  :disabled="loadingMoreOrders"
                  @click="loadMoreOrders"
                  class="w-full h-11 rounded-xl border border-[#dfd6c8] bg-white hover:bg-[#f4efe6] disabled:opacity-60 text-[#1d1d1f] font-semibold text-xs transition-colors flex items-center justify-center gap-2 cursor-pointer shadow-2xs"
                >
                  <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': loadingMoreOrders }" />
                  <span>{{ loadingMoreOrders ? 'Loading more orders...' : 'Load More Orders' }}</span>
                </button>
              </div>
            </div>

            <!-- Empty Past Orders State -->
            <div v-else class="p-8 rounded-xl border border-[#dfd6c8] bg-white text-center space-y-3 shadow-2xs">
              <div class="w-12 h-12 rounded-xl bg-[#f4efe6] border border-[#dfd6c8] flex items-center justify-center mx-auto text-[#8a6b32]">
                <ShoppingBag class="w-5 h-5" />
              </div>
              <div class="text-base font-serif font-medium text-[#1d1d1f]">No past orders yet</div>
              <p class="text-xs text-[#6e6e73] max-w-sm mx-auto leading-relaxed">
                When you complete pickup orders, your receipts, live order tracking, and 1-click reorder buttons will appear here.
              </p>
              <div class="pt-1">
                <Link href="/" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-[#1a1a1a] hover:bg-black text-white text-xs font-semibold transition-colors">
                  <span>Start Shopping</span>
                  <ChevronRight class="w-3.5 h-3.5" />
                </Link>
              </div>
            </div>

          </div>

        </div>
      </template>

    </div>
  </StoreLayout>
</template>
