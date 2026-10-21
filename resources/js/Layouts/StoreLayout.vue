<script setup>
import { ref, computed, watchEffect } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useStore } from '../stores/cart';
import { usePageLoading } from '@/composables/usePageLoading';
import PageSkeleton from '@/Components/PageSkeleton.vue';
import StoreAnnouncementBar from '@/Components/Store/StoreAnnouncementBar.vue';
import StoreHeader from '@/Components/Store/StoreHeader.vue';
import StoreInfoModal from '@/Components/Store/StoreInfoModal.vue';
import StoreFloatingCartBar from '@/Components/Store/StoreFloatingCartBar.vue';
import StoreBottomNav from '@/Components/Store/StoreBottomNav.vue';
import StoreFooter from '@/Components/Store/StoreFooter.vue';
import { Home, Search, RefreshCw, User, AlertTriangle, X, Info } from 'lucide-vue-next';

const props = defineProps({
  title: {
    type: String,
    default: 'Masala Mart',
  },
  showHeader: {
    type: Boolean,
    default: true,
  },
  showFooter: {
    type: Boolean,
    default: true,
  },
  showCartBar: {
    type: Boolean,
    default: true,
  },
  showBottomNav: {
    type: Boolean,
    default: true,
  },
  headerMode: {
    type: String,
    default: 'storefront', // 'storefront' | 'search' | 'pdp' | 'cart' | 'simple'
  },
  headerTitle: {
    type: String,
    default: '',
  },
  backUrl: {
    type: String,
    default: '/',
  },
});

const store = useStore();
const page = usePage();
const { isPageLoading, isNavigating, targetPageType } = usePageLoading();

// Modal state for Store Information & Pickup details
const storeInfoModalOpen = ref(false);

// Global Store Information directly from Laravel DB (shared via HandleInertiaRequests)
// Removed all dummy hardcoded fallback objects
const storeInfo = computed(() => page.props.storeInfo || {});

// Reactively keep Pinia cart store in sync with DB store settings, products, recipe kits & active user account
watchEffect(() => {
  if (storeInfo.value?.name) {
    store.selectedStore = storeInfo.value.name;
  }
  if (storeInfo.value?.pickup_time) {
    store.readyTime = storeInfo.value.pickup_time;
  }
  if (storeInfo.value?.address) {
    store.pickupLocation = `${storeInfo.value.address} · ${storeInfo.value.name || 'Masala Mart'}`;
  }
  if (page.props.products) {
    store.setProducts(page.props.products);
  }
  if (page.props.product) {
    store.setProducts([page.props.product]);
  }
  if (page.props.impulseItems && Array.isArray(page.props.impulseItems)) {
    store.setProducts(page.props.impulseItems);
  }
  if (page.props.frequentlyBoughtTogether && Array.isArray(page.props.frequentlyBoughtTogether)) {
    store.setProducts(page.props.frequentlyBoughtTogether);
  }
  if (page.props.recipeKits) {
    store.setRecipeKits(page.props.recipeKits);
  }
  if (page.props.kit) {
    store.setRecipeKits(page.props.kit);
  }
  store.syncUser(page.props.auth?.user);
});

const navTabs = computed(() => [
  { id: 'shop', label: 'Shop', href: '/', icon: Home, isActive: page.component === 'Home' || page.component === 'ProductDetail' },
  { id: 'search', label: 'Search', href: '/search', icon: Search, isActive: page.component === 'Search' },
  { id: 'reorder', label: 'Reorder', href: '/reorder', icon: RefreshCw, isActive: page.component === 'Account' && page.props.activeSection === 'reorder' },
  { id: 'account', label: 'Account', href: '/account', icon: User, isActive: page.component === 'Account' && page.props.activeSection !== 'reorder' },
]);
</script>

<template>
  <div class="min-h-screen bg-[#fbf9f5] text-[#1d1d1f] flex flex-col font-sans selection:bg-[#1a1a1a] selection:text-white antialiased">
    
    <!-- Top Announcement Bar (Desktop) -->
    <StoreAnnouncementBar 
      v-if="showHeader"
      :storeInfo="storeInfo"
      @openStoreInfo="storeInfoModalOpen = true"
    />

    <!-- Main Navigation Header (Clean, Tactile & Premium) -->
    <StoreHeader 
      v-if="showHeader"
      :headerMode="headerMode"
      :headerTitle="headerTitle"
      :backUrl="backUrl"
      :storeInfo="storeInfo"
      @openStoreInfo="storeInfoModalOpen = true"
    />

    <!-- Top Navigating Indicator (Thin brass loading line) -->
    <div 
      v-if="isNavigating" 
      class="fixed top-0 left-0 right-0 h-0.5 bg-[#a47a3c] z-50 animate-pulse shadow-xs"
    ></div>

    <!-- Main Page Content Body -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-5 sm:py-8 pb-36 sm:pb-20 relative">
      <PageSkeleton v-if="isPageLoading" :type="targetPageType" />
      <div v-show="!isPageLoading">
        <slot />
      </div>
    </main>

    <!-- Global Stock Notification Toast -->
    <Transition
      enter-active-class="transform transition ease-out duration-200"
      enter-from-class="translate-y-3 opacity-0 scale-95"
      enter-to-class="translate-y-0 opacity-100 scale-100"
      leave-active-class="transform transition ease-in duration-150"
      leave-from-class="translate-y-0 opacity-100 scale-100"
      leave-to-class="translate-y-2 opacity-0 scale-95"
    >
      <div
        v-if="store.stockToast.visible"
        class="fixed top-20 right-4 left-4 sm:left-auto sm:w-96 z-50 rounded-lg shadow-xl border p-3.5 flex items-start justify-between gap-3 backdrop-blur-md"
        :class="store.stockToast.type === 'error'
          ? 'bg-rose-950/95 text-white border-rose-500/40'
          : (store.stockToast.type === 'info' ? 'bg-[#1a1a1a]/95 text-white border-[#a47a3c]/40' : 'bg-amber-950/95 text-white border-amber-500/40')"
      >
        <div class="flex items-start gap-2.5 min-w-0">
          <div
            class="w-7 h-7 rounded-md flex items-center justify-center shrink-0 mt-0.5"
            :class="store.stockToast.type === 'error'
              ? 'bg-rose-500/20 text-rose-300'
              : (store.stockToast.type === 'info' ? 'bg-[#a47a3c]/25 text-[#e4b97a]' : 'bg-amber-500/20 text-amber-300')"
          >
            <Info v-if="store.stockToast.type === 'info'" class="w-4 h-4 stroke-[2.2]" />
            <AlertTriangle v-else class="w-4 h-4 stroke-[2.2]" />
          </div>
          <div class="min-w-0">
            <div class="text-xs font-bold leading-snug">{{ store.stockToast.title }}</div>
            <div class="text-[11px] text-stone-200 mt-0.5 leading-relaxed">{{ store.stockToast.message }}</div>
          </div>
        </div>
        <button
          type="button"
          @click="store.dismissStockToast()"
          class="p-1 text-stone-300 hover:text-white rounded-md cursor-pointer shrink-0"
          aria-label="Dismiss notification"
        >
          <X class="w-3.5 h-3.5" />
        </button>
      </div>
    </Transition>

    <!-- Store Information & Pickup Guide Modal -->
    <StoreInfoModal 
      :isOpen="storeInfoModalOpen"
      :storeInfo="storeInfo"
      @close="storeInfoModalOpen = false"
    />

    <!-- Floating Dark Cart Bar -->
    <StoreFloatingCartBar 
      v-if="showCartBar"
      :storeInfo="storeInfo"
    />

    <!-- Sticky Mobile Bottom Navigation Bar -->
    <StoreBottomNav 
      v-if="showBottomNav"
      :navTabs="navTabs"
    />

    <!-- Modern Clean Footer -->
    <StoreFooter 
      v-if="showFooter"
      :storeInfo="storeInfo"
      @openStoreInfo="storeInfoModalOpen = true"
    />

  </div>
</template>
