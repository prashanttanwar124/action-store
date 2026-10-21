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
  User as UserIcon,
  Plus,
  Minus,
  Package,
  Clock,
  Award,
  Flame,
  UtensilsCrossed
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
const showPointsLedger = ref(false);
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
const userName = computed(() => authUser.value ? authUser.value.name.split(' ')[0] : 'there');

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
  return 'Bronze Member';
});

const pointsIntoCurrentCycle = computed(() => {
  return userPoints.value % REWARD_MILESTONE;
});

const pointsToNextReward = computed(() => {
  return REWARD_MILESTONE - pointsIntoCurrentCycle.value;
});

const filledRewardSegments = computed(() => {
  if (userPoints.value > 0 && pointsIntoCurrentCycle.value === 0) {
    return 10;
  }
  return Math.max(1, Math.min(10, Math.round((pointsIntoCurrentCycle.value / REWARD_MILESTONE) * 10)));
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

function activeOrderStepIndex(status) {
  if (status === 'ready_for_pickup') return 3;
  if (status === 'packing') return 2;
  return 1;
}
</script>

<template>
  <Head :title="isReorderMode ? `Buy It Again — ${storeInfo.brand_name}` : `Customer Dashboard — ${storeInfo.brand_name}`" />

  <StoreLayout :showHeader="false" :showFooter="false" :showBottomNav="true" :showCartBar="isReorderMode">
    <div class="space-y-6 sm:space-y-7 max-w-xl mx-auto pb-28 pt-2 sm:pt-4">

      <!-- Toast Feedback -->
      <div 
        v-if="reorderedNotification"
        class="fixed top-16 left-4 right-4 sm:left-auto sm:right-6 sm:top-20 z-50 bg-[#191514] text-white p-3.5 rounded-lg shadow-2xl flex items-center justify-between text-xs font-semibold"
      >
        <div class="flex items-center gap-2">
          <CheckCircle2 class="w-4 h-4 text-[#F59E0B]" />
          <span>Reordered {{ reorderedItemCount }} item{{ reorderedItemCount > 1 ? 's' : '' }} into your cart!</span>
        </div>
        <Link href="/cart" class="underline text-[#F59E0B] font-semibold inline-flex items-center gap-1">
          <span>View Cart</span>
          <ChevronRight class="w-3.5 h-3.5" />
        </Link>
      </div>

      <!-- Top Page Header -->
      <div class="flex items-center justify-between pt-1">
        <div>
          <template v-if="isReorderMode">
            <div class="flex items-center gap-2">
              <h1 class="text-2xl sm:text-3xl font-serif font-medium text-[#1d1d1f] tracking-tight">
                Buy It Again
              </h1>
              <span class="font-devanagari text-xs text-[#7a5620] bg-[#f5eee2] px-2 py-0.5 rounded-md border border-[#e0d9cc]">
                फिर से खरीदें
              </span>
            </div>
            <p class="text-xs text-[#6e6e73] font-normal mt-0.5">
              {{ authUser ? `Restock your favorite ${storeInfo.brand_name} staples in one tap` : 'Quickly restock popular Indian pantry essentials' }}
            </p>
          </template>

          <template v-else>
            <div class="flex items-center gap-2">
              <h1 class="text-2xl sm:text-3xl font-serif font-medium text-[#1d1d1f] tracking-tight">
                {{ authUser ? 'Hi, ' + userName : 'Welcome to ' + storeInfo.brand_name }}
              </h1>
              <span v-if="authUser" class="w-2 h-2 rounded-full bg-emerald-500" title="Signed in"></span>
            </div>
            <p v-if="authUser" class="text-xs text-[#6e6e73] font-normal mt-0.5">
              {{ authUser.email }} · Customer Account
            </p>
            <p v-else class="text-xs text-[#86868b] font-normal mt-0.5 flex items-center gap-1.5">
              <span>Guest Preview</span>
              <span>·</span>
              <Link href="/login" class="text-[#a47a3c] font-semibold hover:underline">Sign In</Link>
              <span>or</span>
              <Link href="/register" class="text-[#a47a3c] font-semibold hover:underline">Register</Link>
            </p>
          </template>
        </div>

        <div class="flex items-center gap-2">
          <Link
            v-if="authUser && !isReorderMode"
            href="/logout"
            method="post"
            as="button"
            class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md border border-[#e0d9cc] bg-white hover:bg-rose-50 hover:text-rose-600 hover:border-rose-200 text-xs font-semibold text-[#6e6e73] transition-colors cursor-pointer"
            title="Sign out of your account"
          >
            <LogOut class="w-3.5 h-3.5" />
            <span>Sign Out</span>
          </Link>

          <Link 
            href="/cart" 
            class="relative w-10 h-10 rounded-lg bg-white border border-[#e0d9cc] flex items-center justify-center text-[#1d1d1f] hover:bg-[#f3efe7] transition-colors shadow-xs"
            aria-label="Cart"
          >
            <ShoppingBag class="w-5 h-5 stroke-[2] text-[#1d1d1f]" />
            <span 
              v-if="store.totalItemCount > 0"
              class="absolute -top-1.5 -right-1.5 bg-[#1a1a1a] text-white text-[11px] font-bold w-5 h-5 rounded-md flex items-center justify-center border-2 border-white shadow-xs"
            >
              {{ store.totalItemCount }}
            </span>
          </Link>
        </div>
      </div>

      <!-- Segmented Mode Switcher (/account vs /reorder) -->
      <div class="grid grid-cols-2 gap-1.5 p-1 bg-[#f3efe7] border border-[#e0d9cc] rounded-lg">
        <Link
          href="/account"
          class="py-2 px-3 rounded-md text-xs font-semibold flex items-center justify-center gap-1.5 transition-all"
          :class="!isReorderMode ? 'bg-[#1a1a1a] text-white shadow-xs' : 'text-[#6e6e73] hover:text-[#1d1d1f]'"
        >
          <UserIcon class="w-3.5 h-3.5" />
          <span>Account & Rewards</span>
        </Link>
        <Link
          href="/reorder"
          class="py-2 px-3 rounded-md text-xs font-semibold flex items-center justify-center gap-1.5 transition-all"
          :class="isReorderMode ? 'bg-[#1a1a1a] text-white shadow-xs' : 'text-[#6e6e73] hover:text-[#1d1d1f]'"
        >
          <RefreshCw class="w-3.5 h-3.5" />
          <span>Buy It Again</span>
        </Link>
      </div>

      <!-- Guest Welcome Banner if not logged in -->
      <div 
        v-if="!authUser" 
        class="bg-[#f5eee2] border border-[#e0d9cc] rounded-lg p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3.5 shadow-xs"
      >
        <div class="space-y-1">
          <div class="flex items-center gap-2">
            <Sparkles class="w-4 h-4 text-[#a47a3c]" />
            <span class="text-xs font-bold text-[#1d1d1f] uppercase tracking-wider">
              {{ isReorderMode ? 'Save Your Personal Pantry' : `Unlock Your ${storeInfo.brand_name} Rewards` }}
            </span>
          </div>
          <p class="text-xs text-[#6e6e73] leading-relaxed">
            {{ isReorderMode
              ? 'Sign in to automatically track your most-ordered items and re-buy your weekly groceries in one click.'
              : 'Sign in to track orders and earn 1 point per $1 spent on fresh Indian groceries.'
            }}
          </p>
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
            class="px-4 py-2 bg-white hover:bg-[#f3efe7] text-[#1d1d1f] border border-[#e0d9cc] text-xs font-semibold rounded-md transition-colors shadow-xs"
          >
            Create Account
          </Link>
        </div>
      </div>

      <!-- ======================================================================= -->
      <!-- MODE A: /reorder — BUY IT AGAIN (MY PANTRY & QUICK RESTOCK)             -->
      <!-- ======================================================================= -->
      <template v-if="isReorderMode">

        <!-- SECTION R1: Frequent Essentials Grid (Authenticated user with past items) -->
        <div v-if="buyAgainItems.length > 0" class="space-y-4">
          <div class="flex flex-wrap items-center justify-between gap-2 border-b border-[#e0d9cc]/60 pb-2.5">
            <div>
              <div class="flex items-center gap-2">
                <h2 class="text-lg sm:text-xl font-serif font-medium text-[#1d1d1f] tracking-tight">
                  Your Frequent Essentials
                </h2>
                <span class="text-[11px] font-semibold text-[#7a5620] bg-[#f5eee2] px-2 py-0.5 rounded-md border border-[#e0d9cc]">
                  {{ buyAgainItems.length }} item{{ buyAgainItems.length > 1 ? 's' : '' }}
                </span>
              </div>
              <p class="text-xs text-[#6e6e73] mt-0.5">
                Sorted by what you order most often · Live store pricing & stock
              </p>
            </div>

            <button
              type="button"
              @click="handleAddAllEssentials"
              class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-[#a47a3c] hover:bg-[#8a6b32] text-white text-xs font-semibold transition-colors cursor-pointer shadow-xs"
            >
              <Plus class="w-3.5 h-3.5 stroke-[2.5]" />
              <span>Add All to Cart</span>
            </button>
          </div>

          <!-- Category Filter Pills -->
          <div v-if="availableCategories.length > 1" class="flex items-center gap-1.5 overflow-x-auto no-scrollbar pb-1">
            <button
              type="button"
              @click="selectedCategory = 'all'"
              class="px-3 py-1.5 rounded-md text-xs font-semibold shrink-0 transition-colors cursor-pointer"
              :class="selectedCategory === 'all' ? 'bg-[#1a1a1a] text-white' : 'bg-white border border-[#e0d9cc] text-[#6e6e73] hover:text-[#1d1d1f]'"
            >
              All ({{ buyAgainItems.length }})
            </button>
            <button
              v-for="cat in availableCategories"
              :key="cat.id"
              type="button"
              @click="selectedCategory = cat.id"
              class="px-3 py-1.5 rounded-md text-xs font-semibold shrink-0 transition-colors cursor-pointer flex items-center gap-1"
              :class="selectedCategory === cat.id ? 'bg-[#1a1a1a] text-white' : 'bg-white border border-[#e0d9cc] text-[#6e6e73] hover:text-[#1d1d1f]'"
            >
              <UtensilsCrossed v-if="cat.id === 'recipe-kit'" class="w-3 h-3 text-[#a47a3c]" />
              <span>{{ cat.label }} ({{ cat.count }})</span>
            </button>
          </div>

          <!-- 2-Column Item Grid -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
            <div
              v-for="item in filteredBuyAgainItems"
              :key="item.id"
              class="rounded-lg border border-[#e0d9cc] bg-white p-3.5 flex flex-col justify-between gap-3 shadow-xs hover:border-[#c9bda8] transition-colors"
            >
              <div class="flex items-start gap-3">
                <Link
                  :href="itemDetailHref(item)"
                  class="w-16 h-16 rounded-lg bg-[#f3efe7] border border-[#e0d9cc] overflow-hidden shrink-0 relative"
                >
                  <img
                    :src="item.image || '/images/products/atta.jpg'"
                    :alt="item.name"
                    class="w-full h-full object-cover"
                    :class="isItemOutOfStock(item) ? 'opacity-40 grayscale' : ''"
                  />
                </Link>

                <div class="min-w-0 flex-1 space-y-1">
                  <div class="flex flex-wrap items-center gap-1.5">
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold bg-[#f5eee2] text-[#7a5620] border border-[#e0d9cc]">
                      <Flame class="w-2.5 h-2.5 text-[#a47a3c]" />
                      Ordered {{ item.times_ordered }}×
                    </span>
                    <span
                      v-if="item.isRecipeKit"
                      class="inline-flex items-center px-1.5 py-0.5 rounded-md text-[9.5px] font-semibold bg-amber-50 text-amber-800 border border-amber-200"
                    >
                      Recipe Kit
                    </span>
                  </div>

                  <Link
                    :href="itemDetailHref(item)"
                    class="block font-bold text-xs sm:text-sm text-[#1d1d1f] hover:text-[#a47a3c] leading-snug line-clamp-2"
                  >
                    {{ item.name }}
                  </Link>

                  <div class="text-[11px] text-[#6e6e73] flex items-center gap-1.5">
                    <span v-if="item.size">{{ item.size }}</span>
                    <span v-if="item.size && item.last_ordered_human">·</span>
                    <span v-if="item.last_ordered_human">Last {{ item.last_ordered_human }}</span>
                  </div>
                </div>
              </div>

              <!-- Price + Add/Stepper Controls -->
              <div class="flex items-center justify-between pt-2 border-t border-[#f3efe7]">
                <div>
                  <span class="font-serif font-medium text-base text-[#1d1d1f]">
                    ${{ Number(item.price || 0).toFixed(2) }}
                  </span>
                  <span
                    class="block text-[10px] font-medium"
                    :class="isItemOutOfStock(item) ? 'text-rose-600' : 'text-emerald-700'"
                  >
                    {{ isItemOutOfStock(item) ? 'Out of stock' : (item.stock_badge || 'In stock') }}
                  </span>
                </div>

                <!-- Out of Stock Disabled Button -->
                <button
                  v-if="isItemOutOfStock(item)"
                  type="button"
                  disabled
                  class="px-3 py-1.5 rounded-md bg-stone-100 text-stone-400 border border-stone-200 text-xs font-semibold cursor-not-allowed"
                >
                  Unavailable
                </button>

                <!-- Quantity Stepper if already in cart -->
                <div
                  v-else-if="getItemCartQty(item) > 0"
                  class="inline-flex items-center bg-[#1a1a1a] text-white rounded-md h-8 px-1 shadow-xs"
                >
                  <button
                    type="button"
                    @click="handleDecrementSingleItem(item)"
                    class="w-7 h-7 flex items-center justify-center hover:bg-white/10 rounded-md cursor-pointer"
                    aria-label="Decrease quantity"
                  >
                    <Minus class="w-3 h-3 stroke-[2.5]" />
                  </button>
                  <span class="px-2 text-xs font-bold min-w-[22px] text-center">
                    {{ getItemCartQty(item) }}
                  </span>
                  <button
                    type="button"
                    @click="handleAddSingleItem(item)"
                    class="w-7 h-7 flex items-center justify-center hover:bg-white/10 rounded-md cursor-pointer"
                    aria-label="Increase quantity"
                  >
                    <Plus class="w-3 h-3 stroke-[2.5]" />
                  </button>
                </div>

                <!-- 1-Tap Add Button -->
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

        <!-- SECTION R2: Popular Kitchen Staples Fallback (when user is guest or has no past orders) -->
        <div v-else-if="popularEssentials.length > 0" class="space-y-4">
          <div class="flex items-center justify-between border-b border-[#e0d9cc]/60 pb-2">
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

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
            <div
              v-for="item in popularEssentials"
              :key="item.id"
              class="rounded-lg border border-[#e0d9cc] bg-white p-3.5 flex flex-col justify-between gap-3 shadow-xs"
            >
              <div class="flex items-start gap-3">
                <Link
                  :href="itemDetailHref(item)"
                  class="w-14 h-14 rounded-lg bg-[#f3efe7] border border-[#e0d9cc] overflow-hidden shrink-0"
                >
                  <img :src="item.image || '/images/products/atta.jpg'" :alt="item.name" class="w-full h-full object-cover" />
                </Link>
                <div class="min-w-0 flex-1">
                  <span class="text-[10px] font-semibold uppercase tracking-wider text-[#a47a3c]">
                    {{ item.category_title || 'Essential' }}
                  </span>
                  <Link
                    :href="itemDetailHref(item)"
                    class="block font-bold text-xs sm:text-sm text-[#1d1d1f] hover:text-[#a47a3c] leading-snug line-clamp-2 mt-0.5"
                  >
                    {{ item.name }}
                  </Link>
                  <span v-if="item.size" class="text-[11px] text-[#6e6e73]">{{ item.size }}</span>
                </div>
              </div>

              <div class="flex items-center justify-between pt-2 border-t border-[#f3efe7]">
                <span class="font-serif font-medium text-base text-[#1d1d1f]">
                  ${{ Number(item.price || 0).toFixed(2) }}
                </span>

                <div
                  v-if="getItemCartQty(item) > 0"
                  class="inline-flex items-center bg-[#1a1a1a] text-white rounded-md h-8 px-1 shadow-xs"
                >
                  <button
                    type="button"
                    @click="handleDecrementSingleItem(item)"
                    class="w-7 h-7 flex items-center justify-center hover:bg-white/10 rounded-md cursor-pointer"
                  >
                    <Minus class="w-3 h-3 stroke-[2.5]" />
                  </button>
                  <span class="px-2 text-xs font-bold min-w-[22px] text-center">
                    {{ getItemCartQty(item) }}
                  </span>
                  <button
                    type="button"
                    @click="handleAddSingleItem(item)"
                    class="w-7 h-7 flex items-center justify-center hover:bg-white/10 rounded-md cursor-pointer"
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

        <!-- SECTION R3: Reorder Full Past Baskets -->
        <div v-if="displayOrders.length > 0" class="space-y-4 pt-2">
          <div class="flex items-center justify-between border-b border-[#e0d9cc]/60 pb-2">
            <div class="flex items-center gap-2">
              <h2 class="text-lg sm:text-xl font-serif font-medium text-[#1d1d1f] tracking-tight">
                Reorder Full Past Baskets
              </h2>
            </div>
            <span class="text-xs font-normal text-[#6e6e73]">
              {{ displayOrders.length }} of {{ totalOrdersCount }}
            </span>
          </div>

          <div class="space-y-3">
            <div
              v-for="order in displayOrders"
              :key="order.id"
              class="rounded-lg border border-[#e0d9cc] bg-white p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3.5 shadow-xs"
            >
              <div class="space-y-1.5 min-w-0">
                <div class="flex items-center gap-2">
                  <span class="font-bold text-xs sm:text-sm text-[#1d1d1f]">{{ order.date }}</span>
                  <span class="text-xs text-[#6e6e73]">· ${{ displayOrderTotal(order).toFixed(2) }}</span>
                  <span class="text-[11px] text-[#86868b]">({{ order.id }})</span>
                </div>
                <p class="text-xs text-[#6e6e73] line-clamp-1">
                  {{ order.summary }}
                </p>
                <div v-if="order.items && order.items.length" class="flex items-center gap-1.5 pt-0.5">
                  <div
                    v-for="item in order.items.slice(0, 5)"
                    :key="item.id"
                    class="w-8 h-8 rounded-md bg-[#f3efe7] overflow-hidden shrink-0 border border-[#e0d9cc]"
                    :title="item.name"
                  >
                    <img :src="item.image || '/images/products/atta.jpg'" :alt="item.name" class="w-full h-full object-cover" />
                  </div>
                </div>
              </div>

              <button
                type="button"
                @click="handleReorderOrder(order)"
                class="h-10 px-4 bg-[#1a1a1a] hover:bg-black text-white font-semibold text-xs rounded-md transition-colors flex items-center justify-center gap-2 cursor-pointer shadow-xs shrink-0"
              >
                <RotateCcw class="w-3.5 h-3.5 stroke-[2.2]" />
                <span>Reorder Basket ({{ order.itemCount }})</span>
              </button>
            </div>

            <div v-if="hasMoreOrders" class="pt-1">
              <button
                type="button"
                :disabled="loadingMoreOrders"
                @click="loadMoreOrders"
                class="w-full h-10 rounded-lg border border-[#e0d9cc] bg-white hover:bg-[#f3efe7] disabled:opacity-60 text-[#1d1d1f] font-semibold text-xs transition-colors flex items-center justify-center gap-2 cursor-pointer shadow-xs"
              >
                <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': loadingMoreOrders }" />
                <span>{{ loadingMoreOrders ? 'Loading...' : 'Load More Past Baskets' }}</span>
              </button>
            </div>
          </div>
        </div>
      </template>

      <!-- ======================================================================= -->
      <!-- MODE B: /account — CUSTOMER DASHBOARD                                   -->
      <!-- ======================================================================= -->
      <template v-else>

        <!-- SECTION A1: Live Active Pickup Order Tracker Banner -->
        <div v-if="activeOrders.length > 0" class="space-y-3">
          <div
            v-for="active in activeOrders"
            :key="active.id"
            class="rounded-lg border-2 border-[#a47a3c] bg-gradient-to-br from-[#fffdf9] to-[#f7f0e4] p-4 sm:p-5 space-y-3.5 shadow-sm"
          >
            <div class="flex items-start justify-between gap-2">
              <div class="space-y-0.5">
                <div class="flex items-center gap-2">
                  <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                  <span class="text-[11px] font-bold uppercase tracking-wider text-[#8a6b32]">
                    Live Pickup Order · {{ active.type }}
                  </span>
                </div>
                <h2 class="text-base sm:text-lg font-serif font-medium text-[#1d1d1f]">
                  {{
                    active.status === 'ready_for_pickup'
                      ? 'Your order is Ready for Pickup!'
                      : active.status === 'packing'
                        ? 'Our team is packing your fresh groceries'
                        : 'Order received — preparing shortly'
                  }}
                </h2>
              </div>

              <div
                class="bg-[#1a1a1a] text-white px-2.5 py-1 rounded-md text-right shrink-0"
              >
                <span class="block text-[9px] uppercase tracking-wider text-stone-400 leading-none">Order #</span>
                <span class="font-mono font-bold text-xs text-[#e4b97a]">{{ active.id }}</span>
              </div>
            </div>

            <!-- 3-Step Progress Bar -->
            <div class="grid grid-cols-3 gap-2 pt-0.5">
              <div class="space-y-1">
                <div class="h-1.5 rounded-full bg-[#a47a3c]"></div>
                <span class="block text-[10px] font-semibold text-[#1d1d1f]">1. Received</span>
              </div>
              <div class="space-y-1">
                <div
                  class="h-1.5 rounded-full transition-colors"
                  :class="activeOrderStepIndex(active.status) >= 2 ? 'bg-[#a47a3c]' : 'bg-[#e0d9cc]'"
                ></div>
                <span class="block text-[10px] font-semibold" :class="activeOrderStepIndex(active.status) >= 2 ? 'text-[#1d1d1f]' : 'text-[#86868b]'">
                  2. Packing
                </span>
              </div>
              <div class="space-y-1">
                <div
                  class="h-1.5 rounded-full transition-colors"
                  :class="activeOrderStepIndex(active.status) >= 3 ? 'bg-emerald-600' : 'bg-[#e0d9cc]'"
                ></div>
                <span class="block text-[10px] font-semibold" :class="activeOrderStepIndex(active.status) >= 3 ? 'text-emerald-800' : 'text-[#86868b]'">
                  3. Ready
                </span>
              </div>
            </div>

            <div class="flex items-center justify-between pt-1 border-t border-[#e0d9cc]/80 text-xs">
              <div class="text-[#6e6e73] truncate pr-2">
                <span class="font-semibold text-[#1d1d1f]">{{ active.id }}</span>
                <span> · {{ active.itemCount }} item{{ active.itemCount > 1 ? 's' : '' }}</span>
                <span v-if="active.pickup_slot"> · {{ active.pickup_slot }}</span>
              </div>

              <Link
                :href="'/orders/' + encodeURIComponent(active.id)"
                class="px-3.5 py-2 rounded-md bg-[#1a1a1a] hover:bg-black text-white font-semibold text-xs inline-flex items-center gap-1.5 shrink-0 transition-colors shadow-xs"
              >
                <Sparkles class="w-3.5 h-3.5 text-[#e4b97a]" />
                <span>Track Live</span>
              </Link>
            </div>
          </div>
        </div>

        <!-- SECTION A2: Dynamic Points Reward Meter + Points Activity Ledger -->
        <div class="bg-[#1a1a1a] text-white p-5 sm:p-6 rounded-lg shadow-sm space-y-3.5 border border-[#e0d9cc]/20">
          <div class="flex items-center justify-between">
            <span class="text-[10px] font-semibold uppercase tracking-wider text-[#a47a3c]">
              {{ storeInfo.brand_name }} REWARDS
            </span>
            <span class="text-xs text-stone-300 font-semibold inline-flex items-center gap-1 bg-white/10 px-2.5 py-0.5 rounded-md">
              <Award class="w-3.5 h-3.5 text-[#e4b97a]" />
              <span>{{ rewardTier }}</span>
            </span>
          </div>

          <div class="flex items-baseline justify-between">
            <div class="flex items-baseline">
              <span class="text-3xl sm:text-4xl font-serif font-medium tracking-tight text-white leading-none">
                {{ userPoints.toLocaleString() }}
              </span>
              <span class="text-sm text-stone-400 font-normal ml-1.5">{{ storeInfo.brand_name }} Points</span>
            </div>

            <button
              v-if="authUser && rewardLedger.length > 0"
              type="button"
              @click="showPointsLedger = !showPointsLedger"
              class="text-xs font-semibold text-[#e4b97a] hover:underline inline-flex items-center gap-1 cursor-pointer"
            >
              <span>{{ showPointsLedger ? 'Hide Activity' : 'Points Activity' }}</span>
              <ChevronUp v-if="showPointsLedger" class="w-3.5 h-3.5" />
              <ChevronDown v-else class="w-3.5 h-3.5" />
            </button>
          </div>

          <!-- 10-Segment Dynamic Progress Meter -->
          <div class="grid grid-cols-10 gap-1.5 pt-1">
            <div 
              v-for="i in 10" 
              :key="i"
              :class="[
                'h-2 rounded-md transition-colors',
                i <= filledRewardSegments ? 'bg-[#a47a3c]' : 'bg-stone-800'
              ]"
            ></div>
          </div>

          <div class="flex justify-between text-xs text-stone-400 font-normal pt-0.5">
            <span>{{ pointsToNextReward }} pts to next $10 reward</span>
            <span>1 pt per $1 spent</span>
          </div>

          <!-- Expandable Points Activity Ledger -->
          <div
            v-if="showPointsLedger && rewardLedger.length > 0"
            class="pt-3 mt-2 border-t border-stone-800 space-y-2"
          >
            <div class="text-[11px] font-semibold uppercase tracking-wider text-stone-400">
              Recent Points History
            </div>
            <div class="space-y-1.5 max-h-52 overflow-y-auto pr-1">
              <div
                v-for="entry in rewardLedger"
                :key="entry.id"
                class="flex items-center justify-between py-1.5 px-2.5 rounded-md bg-stone-900/90 text-xs"
              >
                <div class="min-w-0 pr-2">
                  <div class="font-medium text-stone-200 truncate">
                    {{ entry.description }}
                  </div>
                  <div class="text-[10px] text-stone-400">
                    {{ entry.date }}
                    <span v-if="entry.order_number"> · {{ entry.order_number }}</span>
                  </div>
                </div>
                <span
                  class="font-mono font-bold text-xs shrink-0"
                  :class="entry.points >= 0 ? 'text-emerald-400' : 'text-rose-400'"
                >
                  {{ entry.points >= 0 ? '+' + entry.points : entry.points }} pts
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- SECTION A3: Quick "Buy It Again" Preview Strip (links to /reorder) -->
        <div v-if="buyAgainItems.length > 0" class="space-y-3">
          <div class="flex items-center justify-between border-b border-[#e0d9cc]/60 pb-2">
            <div class="flex items-center gap-2">
              <h2 class="text-lg sm:text-xl font-serif font-medium text-[#1d1d1f] tracking-tight">
                Quick Restock
              </h2>
              <span class="text-[11px] font-semibold text-[#7a5620] bg-[#f5eee2] px-2 py-0.5 rounded-md border border-[#e0d9cc]">
                Buy It Again
              </span>
            </div>
            <Link
              href="/reorder"
              class="text-xs font-semibold text-[#a47a3c] hover:underline inline-flex items-center gap-1"
            >
              <span>View All Pantry</span>
              <ChevronRight class="w-3.5 h-3.5" />
            </Link>
          </div>

          <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
            <div
              v-for="item in buyAgainItems.slice(0, 4)"
              :key="item.id"
              class="rounded-lg border border-[#e0d9cc] bg-white p-2.5 flex flex-col justify-between gap-2 shadow-2xs"
            >
              <Link :href="itemDetailHref(item)" class="space-y-1.5">
                <div class="w-full h-20 rounded-md bg-[#f3efe7] overflow-hidden border border-[#e0d9cc]/60">
                  <img :src="item.image || '/images/products/atta.jpg'" :alt="item.name" class="w-full h-full object-cover" />
                </div>
                <div class="text-xs font-bold text-[#1d1d1f] line-clamp-1">{{ item.name }}</div>
              </Link>
              <div class="flex items-center justify-between pt-1">
                <span class="text-xs font-serif font-medium text-[#1d1d1f]">${{ Number(item.price || 0).toFixed(2) }}</span>
                <button
                  type="button"
                  :disabled="isItemOutOfStock(item)"
                  @click="handleAddSingleItem(item)"
                  class="h-7 px-2.5 rounded-md bg-[#1a1a1a] hover:bg-black disabled:opacity-40 text-white text-[11px] font-semibold inline-flex items-center gap-1 cursor-pointer"
                >
                  <Plus class="w-3 h-3" />
                  <span>{{ getItemCartQty(item) > 0 ? `In Cart (${getItemCartQty(item)})` : 'Add' }}</span>
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- SECTION A4: Past Orders -->
        <div class="space-y-4">
          <div class="flex items-center justify-between border-b border-[#e0d9cc]/60 pb-2">
            <div class="flex items-center gap-2">
              <h2 class="text-lg sm:text-xl font-serif font-medium text-[#1d1d1f] tracking-tight">Past orders</h2>
              <span class="font-devanagari text-xs text-[#7a5620] bg-[#f5eee2] px-2 py-0.5 rounded-md border border-[#e0d9cc]">पिछला ऑर्डर</span>
            </div>
            <span v-if="displayOrders.length > 0" class="text-xs font-normal text-[#6e6e73]">
              Showing {{ displayOrders.length }} of {{ totalOrdersCount }} order{{ totalOrdersCount > 1 ? 's' : '' }}
            </span>
          </div>

          <!-- Dynamic Past Orders List or Empty State -->
          <div v-if="displayOrders.length > 0" class="space-y-4">
            <div 
              v-for="order in displayOrders" 
              :key="order.id"
              class="rounded-lg border border-[#e0d9cc] bg-white p-5 space-y-4 shadow-xs"
            >
              <div class="flex items-center justify-between">
                <div class="flex flex-wrap items-center gap-2">
                  <span class="font-bold text-sm text-[#1d1d1f]">{{ order.date }} · {{ order.type }}</span>
                  <span 
                    v-if="order.status"
                    class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-md text-[10px] font-semibold"
                    :class="[
                      order.status === 'confirmed' ? 'bg-amber-100 text-amber-800' :
                      order.status === 'packing' ? 'bg-blue-100 text-blue-800' :
                      order.status === 'ready_for_pickup' ? 'bg-emerald-100 text-emerald-800 animate-pulse' :
                      order.status === 'cancelled' ? 'bg-rose-100 text-rose-800' :
                      'bg-zinc-100 text-zinc-700'
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
                  class="font-serif font-medium text-base"
                  :class="isOrderFullyRefunded(order) ? 'line-through text-rose-400' : 'text-[#1d1d1f]'"
                >
                  ${{ displayOrderTotal(order).toFixed(2) }}
                </span>
              </div>

              <div class="text-xs text-[#6e6e73] font-normal leading-relaxed">
                {{ order.id }} · {{ order.summary }}
              </div>

              <!-- Per-Item Refund Pills if any item was refunded -->
              <div
                v-if="refundedItemsForOrder(order).length"
                class="flex flex-wrap items-center gap-1.5"
              >
                <span
                  v-for="item in refundedItemsForOrder(order)"
                  :key="'ref-' + item.id"
                  class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold bg-rose-50 text-rose-800 border border-rose-200"
                >
                  {{ item.refunded_quantity }}× {{ item.name }} Out of Stock · Refunded -${{ Number(item.refunded_amount || 0).toFixed(2) }}
                </span>
              </div>

              <!-- Product Thumbnails Row if items are attached -->
              <div v-if="order.items && order.items.length" class="flex items-center gap-2 overflow-x-auto no-scrollbar py-1">
                <div 
                  v-for="item in order.items.slice(0, 6)" 
                  :key="item.id"
                  class="w-10 h-10 rounded-lg bg-[#f3efe7] overflow-hidden shrink-0 border border-[#e0d9cc] relative"
                  :title="item.name"
                >
                  <img :src="item.image || '/images/products/atta.jpg'" :alt="item.name" class="w-full h-full object-cover" :class="isOrderItemFullyRefunded(item) ? 'opacity-40 grayscale' : ''" />
                </div>
              </div>

              <!-- Actions Row: Track Live & Reorder Button -->
              <div class="pt-1 flex items-center gap-2">
                <Link 
                  :href="'/orders/' + encodeURIComponent(order.id)"
                  class="flex-1 h-11 bg-[#a47a3c] hover:bg-[#8a6b32] text-white font-semibold text-xs rounded-md transition-colors flex items-center justify-center gap-1.5 cursor-pointer shadow-xs active:scale-[0.99]"
                >
                  <Sparkles class="w-3.5 h-3.5" />
                  <span>Track Live</span>
                </Link>
                <button 
                  @click="handleReorderOrder(order)"
                  class="flex-1 h-11 bg-[#1a1a1a] hover:bg-black text-white font-semibold text-xs rounded-md transition-colors flex items-center justify-center gap-2 cursor-pointer shadow-sm active:scale-[0.99]"
                >
                  <RotateCcw class="w-3.5 h-3.5 stroke-[2.2]" />
                  <span>Reorder ({{ order.itemCount }})</span>
                </button>
              </div>
            </div>

            <!-- Load More Button -->
            <div v-if="hasMoreOrders" class="pt-2">
              <button
                type="button"
                :disabled="loadingMoreOrders"
                @click="loadMoreOrders"
                class="w-full h-11 rounded-lg border border-[#e0d9cc] bg-white hover:bg-[#f3efe7] disabled:opacity-60 text-[#1d1d1f] font-semibold text-xs transition-colors flex items-center justify-center gap-2 cursor-pointer shadow-xs"
              >
                <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': loadingMoreOrders }" />
                <span>{{ loadingMoreOrders ? 'Loading more orders...' : 'Load More Orders' }}</span>
              </button>
            </div>
          </div>

          <!-- Empty Past Orders State -->
          <div v-else class="p-6 rounded-lg border border-[#e0d9cc] bg-white text-center space-y-2.5 shadow-2xs">
            <div class="text-sm font-semibold text-[#1d1d1f]">No past orders yet</div>
            <p class="text-xs text-[#6e6e73] max-w-sm mx-auto">
              When you complete pickup orders, your order details and quick reorder buttons will appear here.
            </p>
            <div class="pt-1">
              <Link href="/" class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#a47a3c] hover:underline">
                <span>Start Shopping</span>
                <ChevronRight class="w-3.5 h-3.5" />
              </Link>
            </div>
          </div>
        </div>

        <!-- SECTION A5: Account & Security Management -->
        <div class="space-y-4">
          <div class="flex items-center justify-between border-b border-[#e0d9cc]/60 pb-2">
            <div class="flex items-center gap-2">
              <h2 class="text-lg sm:text-xl font-serif font-medium text-[#1d1d1f] tracking-tight">Account & Security</h2>
              <span class="font-devanagari text-xs text-[#7a5620] bg-[#f5eee2] px-2 py-0.5 rounded-md border border-[#e0d9cc]">खाता</span>
            </div>
          </div>

          <div class="rounded-lg border border-[#e0d9cc] bg-white p-5 space-y-4 shadow-xs">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-md bg-[#f3efe7] border border-[#e0d9cc] flex items-center justify-center text-[#1a1a1a] font-bold text-sm">
                  {{ authUser ? authUser.name.charAt(0).toUpperCase() : 'G' }}
                </div>
                <div>
                  <div class="font-bold text-sm text-[#1d1d1f]">
                    {{ authUser ? authUser.name : 'Guest Customer' }}
                  </div>
                  <div class="text-xs text-[#6e6e73]">
                    {{ authUser ? authUser.email : 'Not signed in · Guest Mode' }}
                  </div>
                </div>
              </div>

              <span 
                v-if="authUser" 
                class="text-[11px] font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 rounded-md"
              >
                Active Session
              </span>
              <span 
                v-else 
                class="text-[11px] font-semibold text-amber-700 bg-amber-50 border border-amber-200 px-2.5 py-0.5 rounded-md"
              >
                Guest Mode
              </span>
            </div>

            <div class="pt-2 border-t border-[#e0d9cc]/60 flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
              <template v-if="authUser">
                <Link
                  href="/profile"
                  class="flex-1 py-2.5 bg-[#f5eee2] hover:bg-[#ede3d1] text-[#1d1d1f] text-xs font-semibold rounded-md transition-colors border border-[#e0d9cc] text-center"
                >
                  Edit Profile & Password
                </Link>
                <Link
                  href="/logout"
                  method="post"
                  as="button"
                  class="flex-1 py-2.5 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 text-xs font-semibold rounded-md transition-colors cursor-pointer text-center flex items-center justify-center gap-1.5"
                >
                  <LogOut class="w-3.5 h-3.5" />
                  <span>Sign Out</span>
                </Link>
              </template>
              <template v-else>
                <Link
                  href="/login"
                  class="flex-1 py-2.5 bg-[#1a1a1a] hover:bg-black text-white text-xs font-semibold rounded-md transition-colors text-center shadow-xs"
                >
                  Sign In to Account
                </Link>
                <Link
                  href="/register"
                  class="flex-1 py-2.5 bg-white hover:bg-[#f3efe7] text-[#1d1d1f] border border-[#e0d9cc] text-xs font-semibold rounded-md transition-colors text-center shadow-xs"
                >
                  Create New Account
                </Link>
              </template>
            </div>
          </div>
        </div>

      </template>

    </div>
  </StoreLayout>
</template>
