<script setup>
import { ref, computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useStore } from '../stores/cart';
import { usePageLoading } from '@/composables/usePageLoading';
import PageSkeleton from '@/Components/PageSkeleton.vue';
import { 
  ShoppingBag, 
  Search, 
  ChevronDown, 
  ChevronLeft,
  ChevronRight,
  X, 
  Sparkles, 
  Clock, 
  MapPin, 
  User, 
  RefreshCw, 
  UtensilsCrossed, 
  Truck, 
  Home, 
  Check 
} from 'lucide-vue-next';

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
    default: 'storefront', // 'storefront' | 'pdp' | 'cart' | 'simple'
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
const storeSelectorOpen = ref(false);
const searchInputRef = ref(null);

const currentUrl = computed(() => page.url);

const storesList = [
  { id: 'main', name: 'Main St. store', address: '482 Main Street', readyTime: 'ready in 1 hr' },
  { id: 'oak', name: 'Oakridge Plaza', address: '1204 Oakridge Ave', readyTime: 'ready in 2 hrs' },
  { id: 'downtown', name: 'Downtown Hub', address: '85 Market Square', readyTime: 'ready in 45 mins' },
];

function selectStore(st) {
  store.selectedStore = st.name;
  store.readyTime = st.readyTime;
  storeSelectorOpen.value = false;
}

function handleSearchClick() {
  if (searchInputRef.value) {
    searchInputRef.value.focus();
  }
}

const navTabs = computed(() => [
  { id: 'shop', label: 'Shop', href: '/', icon: Home, isActive: currentUrl.value === '/' || currentUrl.value.startsWith('/products') },
  { id: 'search', label: 'Search', href: '/search', icon: Search, isActive: currentUrl.value === '/search' },
  { id: 'reorder', label: 'Reorder', href: '/reorder', icon: RefreshCw, isActive: currentUrl.value === '/reorder' },
  { id: 'account', label: 'Account', href: '/account', icon: User, isActive: currentUrl.value === '/account' },
]);
</script>

<template>
  <div class="min-h-screen bg-[#fbf9f5] text-[#1d1d1f] flex flex-col font-sans selection:bg-[#1a1a1a] selection:text-white antialiased">
    
    <!-- Top Announcement Bar (Desktop) -->
    <div v-if="showHeader" class="bg-[#1a1a1a] text-white text-[11px] py-1.5 px-4 hidden sm:block">
      <div class="max-w-7xl mx-auto flex justify-between items-center">
        <div class="flex items-center gap-2">
          <span class="bg-[#f5eee2] text-[#7a5620] border border-[#e0d9cc]/50 px-2 py-0.5 rounded-full font-semibold text-[10px] tracking-wide">Diwali 2026</span>
          <span class="text-stone-300 font-normal">Fresh Mithai Pre-Orders Open · Closes Oct 30</span>
        </div>
        <div class="flex items-center gap-4 text-stone-400 font-normal">
          <span class="flex items-center gap-1.5">
            <Clock class="w-3.5 h-3.5 text-[#a47a3c]" />
            <span>Click & Collect in 1 hr</span>
          </span>
          <span class="text-stone-700">|</span>
          <span class="flex items-center gap-1.5">
            <Truck class="w-3.5 h-3.5 text-stone-400" />
            <span>Free delivery on orders $40+</span>
          </span>
          <span class="text-stone-700">|</span>
          <Link href="/account" class="text-stone-200 hover:text-white font-semibold flex items-center gap-1">
            <Sparkles class="w-3 h-3 text-[#a47a3c]" />
            <span>{{ store.masalaPoints.toLocaleString() }} pts</span>
          </Link>
        </div>
      </div>
    </div>

    <!-- Main Navigation Header (Clean & Premium) -->
    <header v-if="showHeader" class="bg-white/95 border-b border-[#e0d9cc] sticky top-0 z-40 backdrop-blur-md">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Mobile PDP & Cart Header (Clean Single Header matching Screen 1b, 1c & 1d) -->
        <div v-if="headerMode === 'pdp' || headerMode === 'cart'" class="sm:hidden flex items-center justify-between h-14">
          <Link 
            :href="backUrl || '/'" 
            class="p-1 -ml-1 text-[#1d1d1f] flex items-center hover:text-[#a47a3c]"
            aria-label="Back"
          >
            <ChevronLeft class="w-6 h-6 stroke-[2.2]" />
          </Link>
          <span class="text-xs font-semibold text-[#1d1d1f] truncate px-2 max-w-[210px] text-center">
            {{ headerTitle || (headerMode === 'cart' ? 'Shopping Cart' : 'Product Details') }}
          </span>
          <Link 
            v-if="headerMode === 'pdp'"
            href="/cart" 
            class="relative p-1 text-[#1d1d1f]"
            aria-label="Cart"
          >
            <ShoppingBag class="w-5 h-5 stroke-[2]" />
            <span 
              v-if="store.totalItemCount > 0"
              class="absolute -top-1 -right-1 bg-[#1a1a1a] text-white text-[10px] font-bold w-4 h-4 rounded-full flex items-center justify-center border-2 border-white shadow-xs"
            >
              {{ store.totalItemCount }}
            </span>
          </Link>
          <div v-else class="w-8"></div>
        </div>

        <!-- Storefront Mobile Header OR Desktop Full Header -->
        <div 
          class="items-center justify-between h-16 sm:h-20 gap-3"
          :class="(headerMode === 'pdp' || headerMode === 'cart') ? 'hidden sm:flex' : 'flex'"
        >
          <!-- Logo & Mobile Store Subtitle -->
          <div class="flex items-center gap-4">
            <div class="flex flex-col">
              <Link href="/" class="flex items-center gap-1.5 group">
                <span class="text-2xl sm:text-3xl font-serif font-medium tracking-tight leading-none text-[#1d1d1f]">
                  Masala Mart
                </span>
                <span class="font-devanagari text-[11px] text-[#7a5620] font-medium bg-[#f5eee2] px-1.5 py-0.5 rounded-md border border-[#e0d9cc] hidden sm:inline-block">
                  किराना
                </span>
              </Link>
              
              <!-- Location subtitle on Mobile (Screen 1a match) -->
              <div 
                @click="storeSelectorOpen = !storeSelectorOpen"
                class="flex items-center gap-1 text-xs text-[#6e6e73] mt-1 cursor-pointer select-none sm:hidden"
              >
                <span class="font-normal text-[#6e6e73]">
                  Pickup · {{ store.selectedStore }} · {{ store.readyTime }}
                </span>
                <ChevronDown class="w-3.5 h-3.5 text-[#86868b]" />
              </div>
            </div>

            <!-- Desktop Store Selector Dropdown -->
            <div class="relative hidden sm:block">
              <button 
                @click="storeSelectorOpen = !storeSelectorOpen"
                class="flex items-center gap-2 px-3 py-1.5 border border-[#e0d9cc] bg-[#f3efe7] hover:bg-[#ece7de] text-left text-xs transition-colors rounded-xl cursor-pointer"
              >
                <MapPin class="w-3.5 h-3.5 text-[#a47a3c]" />
                <div>
                  <div class="text-[10px] text-[#6e6e73] font-semibold uppercase">Store Pickup</div>
                  <div class="font-bold text-[#1d1d1f] leading-tight">{{ store.selectedStore }} · {{ store.readyTime }}</div>
                </div>
                <ChevronDown class="w-3.5 h-3.5 text-[#86868b] ml-1" />
              </button>

              <div 
                v-if="storeSelectorOpen" 
                class="absolute left-0 mt-1 w-72 bg-white border border-[#e0d9cc] rounded-2xl shadow-xl z-50 p-2 space-y-1"
              >
                <div class="px-2 py-1.5 text-[10px] text-[#86868b] uppercase font-semibold border-b border-[#e0d9cc]/60">
                  Select Pickup Location
                </div>
                <button
                  v-for="st in storesList"
                  :key="st.id"
                  @click="selectStore(st)"
                  class="w-full text-left p-2.5 hover:bg-[#f3efe7] rounded-xl flex flex-col transition-colors cursor-pointer"
                >
                  <span class="text-xs font-bold text-[#1d1d1f]">{{ st.name }}</span>
                  <span class="text-[11px] text-[#6e6e73] font-normal">{{ st.address }} · {{ st.readyTime }}</span>
                </button>
              </div>
            </div>
          </div>

          <!-- Desktop Global Search Bar -->
          <div class="flex-1 max-w-xl mx-4 hidden sm:block">
            <div class="relative flex items-center">
              <Search class="absolute left-3.5 w-4 h-4 text-[#86868b] stroke-[2.2]" />
              <input 
                v-model="store.searchQuery"
                ref="searchInputRef"
                type="text" 
                placeholder="Search paneer, atta, desi ghee, garam masala, maggi…"
                class="w-full pl-10 pr-9 py-2.5 bg-[#f3efe7] border border-[#e0d9cc] rounded-2xl text-xs text-[#1d1d1f] placeholder-[#86868b] focus:outline-none focus:bg-white focus:border-[#1a1a1a] focus:ring-2 focus:ring-[#1a1a1a]/10 transition-all font-normal"
              />
              <button 
                v-if="store.searchQuery" 
                @click="store.searchQuery = ''"
                class="absolute right-3 text-[#86868b] hover:text-[#1d1d1f]"
              >
                <X class="w-4 h-4" />
              </button>
            </div>
          </div>

          <!-- Header Right Actions -->
          <div class="flex items-center gap-2.5">
            <Link 
              href="/reorder" 
              class="hidden md:flex items-center gap-1.5 px-3 py-2 border border-[#e0d9cc] bg-white hover:bg-[#f3efe7] rounded-xl text-xs font-semibold text-[#1d1d1f] transition-colors"
            >
              <RefreshCw class="w-3.5 h-3.5 text-[#6e6e73]" />
              <span>Buy It Again</span>
            </Link>

            <Link 
              href="/account" 
              class="hidden sm:flex items-center gap-1.5 px-3 py-2 border border-[#e0d9cc] bg-white hover:bg-[#f3efe7] rounded-xl text-xs font-semibold text-[#1d1d1f] transition-colors"
            >
              <User class="w-3.5 h-3.5 text-[#6e6e73]" />
              <span>Account</span>
            </Link>

            <!-- Cart Trigger Button -->
            <Link 
              href="/cart"
              class="relative w-10 h-10 sm:w-auto sm:h-auto sm:px-4 sm:py-2.5 bg-[#f3efe7] sm:bg-[#1a1a1a] text-[#1d1d1f] sm:text-white rounded-2xl sm:rounded-xl transition-all hover:opacity-95 cursor-pointer flex items-center justify-center gap-2 border border-[#e0d9cc] sm:border-transparent"
              aria-label="Shopping Cart"
            >
              <div class="relative w-5 h-5 flex items-center justify-center">
                <ShoppingBag class="w-5 h-5 stroke-[2]" />
                <span 
                  v-if="store.totalItemCount > 0"
                  class="absolute -top-2.5 -right-2.5 bg-[#1a1a1a] sm:bg-[#a47a3c] text-white text-[10px] font-bold w-4 h-4 rounded-full flex items-center justify-center border-2 border-white shadow-xs"
                >
                  {{ store.totalItemCount }}
                </span>
              </div>
              <div class="hidden sm:flex flex-col text-left leading-none">
                <span class="text-[9px] text-stone-400 font-semibold uppercase">Cart</span>
                <span class="text-xs font-bold mt-0.5 text-white">${{ store.subtotal.toFixed(2) }}</span>
              </div>
            </Link>
          </div>

        </div>

        <!-- Mobile Store Selector Dropdown (Storefront mode only) -->
        <div 
          v-if="headerMode === 'storefront' && storeSelectorOpen" 
          class="sm:hidden border-t border-stone-200 py-2.5 bg-stone-50 px-2 rounded-2xl space-y-1 mb-2 shadow-inner"
        >
          <div class="text-[10px] text-stone-400 uppercase font-semibold px-2 py-0.5">Select Store Location:</div>
          <button
            v-for="st in storesList"
            :key="st.id"
            @click="selectStore(st)"
            class="w-full text-left p-2.5 bg-white rounded-xl border border-stone-200 flex justify-between items-center text-xs"
          >
            <div>
              <span class="font-bold text-stone-900">{{ st.name }}</span>
              <span class="text-stone-500 font-normal text-[11px] block">{{ st.readyTime }}</span>
            </div>
            <Check v-if="store.selectedStore === st.name" class="w-4 h-4 text-[#1a1a1a] stroke-[3]" />
          </button>
        </div>

        <!-- Mobile Pinned Search Row (Storefront mode only, Screen 1a) -->
        <div v-if="headerMode === 'storefront'" class="sm:hidden pb-3 pt-0.5">
          <div class="relative flex items-center">
            <Search class="absolute left-3.5 w-4 h-4 text-[#86868b] stroke-[2.2]" />
            <input 
              v-model="store.searchQuery"
              ref="searchInputRef"
              type="text" 
              placeholder="Search paneer, atta, curry leaves…"
              class="w-full pl-10 pr-8 py-2.5 bg-[#f3efe7] border border-[#e0d9cc] rounded-xl text-xs text-[#1d1d1f] placeholder-[#86868b] font-normal focus:bg-white focus:ring-2 focus:ring-[#1a1a1a]/10"
            />
            <button 
              v-if="store.searchQuery" 
              @click="store.searchQuery = ''"
              class="absolute right-3 text-[#86868b]"
            >
              <X class="w-4 h-4" />
            </button>
          </div>
        </div>

        <!-- Horizontal Categories Pill Bar (Storefront mode only) -->
        <nav v-if="headerMode === 'storefront'" class="flex items-center justify-between border-t border-[#e0d9cc]/60 py-2 overflow-x-auto no-scrollbar text-xs font-semibold -mx-4 px-4 sm:mx-0 sm:px-0">
          <div class="flex items-center gap-3 sm:gap-4 shrink-0">
            <Link 
              href="/" 
              :class="[
                'transition-colors px-3 py-1.5 rounded-full shrink-0',
                currentUrl === '/' ? 'bg-[#1a1a1a] text-white font-semibold shadow-xs' : 'bg-[#f3efe7] text-[#6e6e73] hover:text-[#1d1d1f] border border-[#e0d9cc]/60'
              ]"
            >
              All Categories
            </Link>
            <Link 
              href="/products/paneer" 
              :class="[
                'transition-colors px-3 py-1.5 rounded-full shrink-0',
                currentUrl === '/products/paneer' ? 'bg-[#1a1a1a] text-white font-semibold shadow-xs' : 'bg-[#f3efe7] text-[#6e6e73] hover:text-[#1d1d1f] border border-[#e0d9cc]/60'
              ]"
            >
              Dairy & Fresh Paneer
            </Link>
            <Link 
              href="/products/rice" 
              :class="[
                'transition-colors px-3 py-1.5 rounded-full shrink-0',
                currentUrl === '/products/rice' ? 'bg-[#1a1a1a] text-white font-semibold shadow-xs' : 'bg-[#f3efe7] text-[#6e6e73] hover:text-[#1d1d1f] border border-[#e0d9cc]/60'
              ]"
            >
              Staples, Atta & Rice
            </Link>
            <Link 
              href="/#recipe-kits" 
              class="bg-[#f3efe7] text-[#6e6e73] hover:text-[#1d1d1f] transition-colors px-3 py-1.5 rounded-full flex items-center gap-1.5 shrink-0 border border-[#e0d9cc]/60"
            >
              <UtensilsCrossed class="w-3.5 h-3.5 text-[#a47a3c]" />
              <span>Recipe Kits (14)</span>
            </Link>
            <Link 
              href="/account" 
              class="bg-[#f5eee2] text-[#7a5620] hover:text-[#1d1d1f] transition-colors px-3 py-1.5 rounded-full flex items-center gap-1.5 shrink-0 border border-[#e0d9cc]"
            >
              <RefreshCw class="w-3.5 h-3.5 text-[#7a5620]" />
              <span>Subscribe & Save (5%)</span>
            </Link>
          </div>

          <div class="hidden md:flex items-center gap-2 text-xs font-normal text-[#6e6e73] shrink-0">
            <span>Free Store Pickup</span>
            <span>·</span>
            <span class="text-[#a47a3c] font-semibold">Ready in 1 Hr</span>
          </div>
        </nav>

      </div>
    </header>

    <!-- Top Navigating Indicator (Thin brass loading line) -->
    <div 
      v-if="isNavigating" 
      class="fixed top-0 left-0 right-0 h-0.5 bg-[#a47a3c] z-50 animate-pulse shadow-xs"
    ></div>

    <!-- Main Page Content Body -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-5 sm:py-8 pb-36 sm:pb-20 relative">
      <Transition name="fade-skeleton" mode="out-in">
        <PageSkeleton v-if="isPageLoading" :type="targetPageType" key="skeleton" />
        <div v-else key="content">
          <slot />
        </div>
      </Transition>
    </main>

    <!-- Floating Dark Cart Bar (Ultra-compact, sleek & lightweight) -->
    <div 
      v-if="showCartBar && store.totalItemCount > 0"
      class="fixed bottom-[68px] sm:bottom-6 left-3 right-3 sm:left-auto sm:right-6 z-40 max-w-md sm:w-84 cursor-pointer pointer-events-auto mx-auto"
    >
      <Link 
        href="/cart"
        class="group block bg-[#1a1a1a]/95 backdrop-blur-md text-white rounded-2xl shadow-xl hover:bg-black active:scale-[0.99] transition-all border border-white/10 overflow-hidden"
      >
        <div class="flex items-center justify-between px-3.5 py-2 gap-2">
          <!-- Left: Shopping bag icon + item count + subtotal -->
          <div class="flex items-center gap-2.5 min-w-0">
            <div class="relative w-8 h-8 rounded-xl bg-white/10 flex items-center justify-center shrink-0 border border-white/10 group-hover:bg-[#a47a3c]/20 transition-colors">
              <ShoppingBag class="w-4 h-4 text-white group-hover:text-[#e4b97a] transition-colors stroke-[2.2]" />
              <span class="absolute -top-1 -right-1 bg-[#a47a3c] text-white text-[9px] font-bold w-4 h-4 rounded-full flex items-center justify-center border-2 border-[#1a1a1a]">
                {{ store.totalItemCount }}
              </span>
            </div>

            <div class="flex flex-col min-w-0">
              <div class="flex items-center gap-1.5 leading-none">
                <span class="text-xs font-bold text-white tracking-tight">View Cart</span>
                <span class="text-stone-500 text-[10px]">·</span>
                <span class="text-xs font-serif font-bold text-[#e4b97a]">${{ store.subtotal.toFixed(2) }}</span>
              </div>
              <span class="text-[10px] text-stone-400 mt-1 truncate leading-none">
                <template v-if="store.amountToFreeDelivery > 0">
                  Add <span class="text-stone-200 font-semibold">${{ store.amountToFreeDelivery.toFixed(2) }}</span> for free delivery
                </template>
                <template v-else>
                  <span class="text-[#e4b97a] font-semibold flex items-center gap-1">
                    <Sparkles class="w-2.5 h-2.5 inline" />
                    <span>Free Delivery Unlocked!</span>
                  </span>
                </template>
              </span>
            </div>
          </div>

          <!-- Right: Arrow Button -->
          <div class="flex items-center gap-1 text-[11px] font-bold text-stone-200 bg-white/10 group-hover:bg-white/20 group-hover:text-white px-2.5 py-1.5 rounded-xl border border-white/10 shrink-0 transition-colors">
            <span>Checkout</span>
            <ChevronRight class="w-3.5 h-3.5 stroke-[2.5]" />
          </div>
        </div>

        <!-- Sleek Hairline 2px Brass Progress Line at the very bottom edge -->
        <div class="w-full bg-white/10 h-0.5 relative overflow-hidden">
          <div 
            class="bg-[#a47a3c] h-full transition-all duration-300"
            :style="{ width: store.freeDeliveryProgressPercent + '%' }"
          ></div>
        </div>
      </Link>
    </div>

    <!-- Sticky Mobile Bottom Navigation Bar (Screen 1a) -->
    <nav v-if="showBottomNav" class="fixed bottom-0 left-0 right-0 z-40 bg-white/95 backdrop-blur-md border-t border-[#e0d9cc] sm:hidden">
      <div class="grid grid-cols-4 h-[60px] text-center max-w-md mx-auto">
        <Link
          v-for="tab in navTabs"
          :key="tab.id"
          :href="tab.href"
          @click="tab.id === 'search' ? handleSearchClick() : null"
          :class="[
            'flex flex-col items-center justify-center w-full h-full py-1 transition-colors select-none',
            tab.isActive ? 'text-[#1a1a1a] font-semibold' : 'text-[#6e6e73] font-normal'
          ]"
        >
          <component 
            :is="tab.icon" 
            :class="[
              'w-5 h-5 transition-transform',
              tab.isActive ? 'stroke-[2.5] scale-105 text-[#1a1a1a]' : 'stroke-[1.8]'
            ]" 
          />
          <span class="text-[10px] mt-0.5 tracking-tight font-semibold">
            {{ tab.label }}
          </span>
        </Link>
      </div>
    </nav>

    <!-- Modern Clean Footer -->
    <footer v-if="showFooter" class="bg-[#f3efe7] border-t border-[#e0d9cc] mt-16 pt-12 pb-10 hidden sm:block">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8 pb-10 border-b border-[#e0d9cc]/60">
          
          <!-- Column 1: Store Bio -->
          <div class="space-y-3">
            <div class="text-2xl font-serif font-medium tracking-tight text-[#1d1d1f] flex items-center gap-1.5">
              <span>Masala Mart</span>
              <span class="font-devanagari text-xs text-[#7a5620] font-medium bg-[#f5eee2] px-2 py-0.5 rounded-full border border-[#e0d9cc]">किराना</span>
            </div>
            <p class="text-xs text-[#6e6e73] font-normal leading-relaxed">
              Clean, premium click-and-collect Indian groceries. Fresh stone-ground atta, farm-batched paneer, pure desi ghee, and festival sweets ready in 60 minutes.
            </p>
            <div class="text-xs text-[#7a5620] font-semibold flex items-center gap-1">
              <span>✓ 100% freshness guarantee on every order</span>
            </div>
          </div>

          <!-- Column 2: Hours & Pickup -->
          <div class="space-y-2 text-xs">
            <div class="font-semibold uppercase text-[11px] text-[#7a5620] tracking-wider">
              Store Pickup
            </div>
            <div class="text-[#1d1d1f] font-bold">Main St. Store (Flagship)</div>
            <div class="text-[#6e6e73] font-normal">482 Main Street, Suite 100</div>
            <div class="text-[#6e6e73] font-normal">Mon–Sun: 8:00 AM – 10:00 PM</div>
            <div class="text-[#1d1d1f] font-semibold pt-1">
              Curbside bay parking available
            </div>
          </div>

          <!-- Column 3: Quick Links -->
          <div class="space-y-2 text-xs">
            <div class="font-semibold uppercase text-[11px] text-[#86868b] tracking-wider">
              Customer Services
            </div>
            <ul class="space-y-1.5 text-[#6e6e73] font-normal">
              <li><Link href="/cart" class="hover:text-[#1d1d1f]">Shopping Cart & Delivery Meter</Link></li>
              <li><Link href="/checkout" class="hover:text-[#1d1d1f]">Express Checkout</Link></li>
              <li><Link href="/account" class="hover:text-[#1d1d1f]">Subscriptions & Auto-Reorder</Link></li>
              <li><Link href="/reorder" class="hover:text-[#1d1d1f]">Past Orders & Instant Reorder</Link></li>
            </ul>
          </div>

          <!-- Column 4: Rewards & Delivery -->
          <div class="space-y-3 text-xs bg-[#f5eee2] p-4 rounded-2xl border border-[#e0d9cc]">
            <div class="font-semibold uppercase text-[11px] text-[#7a5620] flex items-center gap-1.5">
              <Sparkles class="w-3.5 h-3.5 text-[#a47a3c]" />
              <span>Masala Rewards</span>
            </div>
            <p class="text-[11px] text-[#6e6e73] font-normal leading-snug">
              Earn 1 point for every $1 spent. Unlock $10 grocery credit at 1,500 points.
            </p>
            <div class="flex items-center justify-between text-xs text-[#1d1d1f] border-t border-[#e0d9cc] pt-2 font-bold">
              <span>Your Balance:</span>
              <span class="text-[#7a5620] font-serif font-medium">{{ store.masalaPoints }} pts</span>
            </div>
          </div>

        </div>

        <div class="pt-6 flex flex-col sm:flex-row justify-between items-center text-xs text-[#86868b] gap-3 font-normal">
          <div>© 2026 Masala Mart Inc. All rights reserved. Modern Classic V3.</div>
          <div class="flex gap-4">
            <a href="#" class="hover:text-stone-600">Privacy Policy</a>
            <span>·</span>
            <a href="#" class="hover:text-stone-600">Terms of Service</a>
            <span>·</span>
            <a href="#" class="hover:text-stone-600">Click & Collect Terms</a>
          </div>
        </div>
      </div>
    </footer>

  </div>
</template>
