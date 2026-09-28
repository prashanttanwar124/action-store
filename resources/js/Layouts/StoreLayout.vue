<script setup>
import { ref, computed, watchEffect } from 'vue';
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
  Store, 
  Home, 
  Check,
  Info,
  ExternalLink,
  Phone,
  Mail,
  Car
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

// Modal state for Store Information & Pickup details
const storeInfoModalOpen = ref(false);
const searchInputRef = ref(null);

const currentUrl = computed(() => page.url);

// Global Store Information from Laravel DB (editable via Admin portal)
const storeInfo = computed(() => page.props.storeInfo || {
  name: 'Masala Mart — Main St.',
  tagline: 'Authentic Indian Groceries & Fresh Click-and-Collect',
  hindi_tagline: 'किराना',
  address: '214 Main St.',
  city: 'Edison',
  state: 'NJ',
  zip: '08817',
  phone: '+1 (555) 345-6789',
  email: 'support@masalamart.com',
  opening_hours: 'Daily 9:00 AM – 9:00 PM',
  pickup_time: 'Ready in 1 hr',
  curbside_instructions: 'Park in Curbside Bay 3 or pick up at the front express counter. Bring your order confirmation SMS.',
  announcement: 'Diwali 2026: Fresh Mithai Pre-Orders Open · Closes Oct 30',
  maps_url: 'https://maps.google.com/?q=214+Main+St,+Edison,+NJ',
  is_pickup_active: true,
});

// Reactively keep Pinia cart store in sync with DB store settings
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
});

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
    <div v-if="showHeader && storeInfo.announcement" class="bg-[#1a1a1a] text-white text-[11px] py-1.5 px-4 hidden sm:block">
      <div class="max-w-7xl mx-auto flex justify-between items-center">
        <div class="flex items-center gap-2">
          <span class="bg-[#f5eee2] text-[#7a5620] border border-[#e0d9cc]/50 px-2 py-0.5 rounded-full font-semibold text-[10px] tracking-wide">
            {{ storeInfo.hindi_tagline || 'किराना' }} Notice
          </span>
          <span class="text-stone-300 font-normal">{{ storeInfo.announcement }}</span>
        </div>
        <div class="flex items-center gap-4 text-stone-400 font-normal">
          <button 
            type="button" 
            @click="storeInfoModalOpen = true"
            class="flex items-center gap-1.5 hover:text-stone-200 transition-colors cursor-pointer"
            title="Click to view pickup hours and location"
          >
            <Clock class="w-3.5 h-3.5 text-[#a47a3c]" />
            <span>Pickup: {{ storeInfo.pickup_time }}</span>
          </button>
          <span class="text-stone-700">|</span>
          <button 
            type="button" 
            @click="storeInfoModalOpen = true"
            class="flex items-center gap-1.5 hover:text-stone-200 transition-colors cursor-pointer"
            title="Click to view address and directions"
          >
            <Store class="w-3.5 h-3.5 text-[#a47a3c]" />
            <span>Store Info & Directions</span>
          </button>
          <span class="text-stone-700">|</span>
          <Link href="/account" class="text-stone-200 hover:text-white font-semibold flex items-center gap-1">
            <Sparkles class="w-3 h-3 text-[#a47a3c]" />
            <span>{{ store.masalaPoints.toLocaleString() }} pts</span>
          </Link>
        </div>
      </div>
    </div>

    <!-- Main Navigation Header (Clean, Tactile & Premium) -->
    <header v-if="showHeader" class="bg-white/95 border-b border-[#e0d9cc] sticky top-0 z-40 backdrop-blur-md">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Mobile PDP & Cart Header (Clean Single Header) -->
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
          class="items-center justify-between h-16 sm:h-[68px] gap-2.5 sm:gap-3"
          :class="(headerMode === 'pdp' || headerMode === 'cart') ? 'hidden sm:flex' : 'flex'"
        >
          <!-- Logo & Store Location Badge -->
          <div class="flex items-center gap-2.5 sm:gap-3 shrink-0">
            <div class="flex flex-col">
              <Link href="/" class="flex items-center gap-1.5 group">
                <span class="text-2xl sm:text-[27px] font-serif font-medium tracking-tight leading-none text-[#1d1d1f] group-hover:text-[#a47a3c] transition-colors">
                  Masala Mart
                </span>
                <span class="font-devanagari text-[10.5px] text-[#7a5620] font-medium bg-[#f5eee2] px-1.5 py-0.5 rounded-md border border-[#e0d9cc] hidden sm:inline-block">
                  {{ storeInfo.hindi_tagline || 'किराना' }}
                </span>
              </Link>
              
              <!-- Single Store Location Tap Target on Mobile (Opens Store Info Modal) -->
              <button 
                type="button"
                @click="storeInfoModalOpen = true"
                class="flex items-center gap-1.5 text-[11px] text-[#6e6e73] mt-1 cursor-pointer select-none sm:hidden text-left hover:text-[#1d1d1f] transition-colors"
                title="View store information and hours"
              >
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0"></span>
                <span class="font-medium text-[#1d1d1f] truncate max-w-[210px]">
                  Pickup · {{ storeInfo.address }} · {{ storeInfo.pickup_time }}
                </span>
                <Info class="w-3 h-3 text-[#a47a3c] shrink-0" />
              </button>
            </div>

            <!-- Desktop Single Store Pickup Pill (Uniform h-[42px] rounded-full) -->
            <div class="relative hidden sm:block">
              <button 
                type="button"
                @click="storeInfoModalOpen = true"
                class="h-[42px] inline-flex items-center gap-2 px-3 bg-[#f4efe6] hover:bg-[#ede6da] border border-[#dfd6c8] hover:border-[#cfc4b2] text-left rounded-full transition-all duration-150 cursor-pointer shadow-xs active:scale-[0.99] shrink-0"
                title="View store hours, location & curbside directions"
              >
                <div class="relative w-6 h-6 rounded-full bg-white border border-[#dfd6c8] flex items-center justify-center shrink-0">
                  <MapPin class="w-3.5 h-3.5 text-[#8a6b32] stroke-[2.2]" />
                  <span class="absolute -top-0.5 -right-0.5 w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                </div>
                <div class="flex flex-col justify-center min-w-0 pr-0.5">
                  <div class="flex items-center gap-1 text-[9px] text-[#8a6b32] font-bold uppercase tracking-wider leading-none">
                    <span>PICKUP</span>
                    <span class="text-[#c5baaa]">·</span>
                    <span class="font-medium text-[#6e6e73] normal-case">{{ storeInfo.pickup_time }}</span>
                  </div>
                  <div class="font-bold text-[#1d1d1f] text-[11.5px] leading-tight truncate max-w-[150px] mt-0.5">
                    {{ storeInfo.name }}
                  </div>
                </div>
                <ChevronDown class="w-3.5 h-3.5 text-[#86868b] shrink-0 ml-0.5" />
              </button>
            </div>
          </div>

          <!-- Desktop Global Search Bar (Uniform h-[42px] rounded-full) -->
          <div class="flex-1 max-w-xl mx-2 hidden sm:block">
            <div class="relative flex items-center h-[42px] group">
              <Search class="absolute left-3.5 w-4 h-4 text-[#86868b] group-focus-within:text-[#1a1a1a] transition-colors stroke-[2.2]" />
              <input 
                v-model="store.searchQuery"
                ref="searchInputRef"
                type="text" 
                placeholder="Search paneer, atta, desi ghee, garam masala, maggi…"
                class="w-full h-full pl-10 pr-10 bg-[#f4efe6] hover:bg-[#ede6da] border border-[#dfd6c8] focus:border-[#1a1a1a] rounded-full text-xs text-[#1d1d1f] placeholder-[#86868b] focus:outline-none focus:bg-white focus:ring-4 focus:ring-[#1a1a1a]/5 transition-all font-normal shadow-xs"
              />
              <button 
                v-if="store.searchQuery" 
                type="button"
                @click="store.searchQuery = ''"
                class="absolute right-3 p-1 text-[#86868b] hover:text-[#1d1d1f] rounded-full hover:bg-stone-200/60 transition-colors"
                title="Clear search"
              >
                <X class="w-3.5 h-3.5" />
              </button>
              <span 
                v-else 
                class="hidden xl:inline-flex absolute right-3 text-[10px] font-semibold text-stone-400 bg-white/80 border border-stone-200/80 px-1.5 py-0.5 rounded-md pointer-events-none"
              >
                ⌘K
              </span>
            </div>
          </div>

          <!-- Header Right Actions (Uniform h-[42px] rounded-full) -->
          <div class="flex items-center gap-2 sm:gap-2.5 shrink-0">
            <Link 
              href="/reorder" 
              class="hidden md:inline-flex items-center gap-1.5 h-[42px] px-3.5 border border-[#dfd6c8] bg-[#f4efe6] hover:bg-[#ede6da] rounded-full text-xs font-semibold text-[#1d1d1f] transition-colors shadow-xs shrink-0"
            >
              <RefreshCw class="w-3.5 h-3.5 text-[#6e6e73]" />
              <span>Buy It Again</span>
            </Link>

            <Link 
              href="/account" 
              class="hidden sm:inline-flex items-center gap-1.5 h-[42px] px-3.5 border border-[#dfd6c8] bg-[#f4efe6] hover:bg-[#ede6da] rounded-full text-xs font-semibold text-[#1d1d1f] transition-colors shadow-xs shrink-0"
            >
              <User class="w-3.5 h-3.5 text-[#6e6e73]" />
              <span>Account</span>
            </Link>

            <!-- Cart Trigger Button (Uniform h-[42px] rounded-full) -->
            <Link 
              href="/cart"
              class="relative h-[42px] px-3.5 sm:px-4 bg-[#1a1a1a] hover:bg-black text-white rounded-full transition-all hover:opacity-95 active:scale-[0.98] cursor-pointer inline-flex items-center justify-center gap-2.5 border border-[#1a1a1a] shadow-xs shrink-0"
              aria-label="Shopping Cart"
            >
              <div class="relative w-5 h-5 flex items-center justify-center">
                <ShoppingBag class="w-4 h-4 stroke-[2]" />
                <span 
                  v-if="store.totalItemCount > 0"
                  class="absolute -top-1.5 -right-2 bg-[#a47a3c] text-white text-[9.5px] font-bold w-4 h-4 rounded-full flex items-center justify-center border-2 border-[#1a1a1a] shadow-xs"
                >
                  {{ store.totalItemCount }}
                </span>
              </div>
              <div class="hidden sm:flex flex-col text-left leading-none">
                <span class="text-[8.5px] text-stone-400 font-semibold uppercase tracking-wider">Cart</span>
                <span class="text-xs font-bold mt-0.5 text-white">${{ store.subtotal.toFixed(2) }}</span>
              </div>
            </Link>
          </div>

        </div>

        <!-- Mobile Pinned Search Row (Storefront mode only, Screen 1a) -->
        <div v-if="headerMode === 'storefront'" class="sm:hidden pb-3 pt-1">
          <div class="relative flex items-center">
            <Search class="absolute left-3.5 w-4 h-4 text-[#86868b] stroke-[2.2]" />
            <input 
              v-model="store.searchQuery"
              ref="searchInputRef"
              type="text" 
              placeholder="Search paneer, atta, curry leaves, maggi…"
              class="w-full pl-10 pr-9 py-2.5 bg-[#f4efe6] border border-[#dfd6c8] rounded-full text-xs text-[#1d1d1f] placeholder-[#86868b] font-normal focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#1a1a1a]/15 focus:border-[#1a1a1a] transition-all shadow-xs"
            />
            <button 
              v-if="store.searchQuery" 
              type="button"
              @click="store.searchQuery = ''"
              class="absolute right-3 text-[#86868b] hover:text-[#1d1d1f]"
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
            <span class="text-[#a47a3c] font-semibold">{{ storeInfo.pickup_time }}</span>
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

    <!-- Store Information & Pickup Guide Modal (Accessible, Clean & Informative) -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div 
          v-if="storeInfoModalOpen" 
          class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs"
          @click.self="storeInfoModalOpen = false"
        >
          <div 
            class="bg-white rounded-3xl max-w-lg w-full shadow-2xl border border-stone-200 overflow-hidden transform transition-all duration-200 max-h-[92vh] flex flex-col"
          >
            <!-- Modal Header -->
            <div class="px-6 py-5 bg-[#f7f4ee] border-b border-[#e5decb] flex items-start justify-between">
              <div>
                <div class="flex items-center gap-2">
                  <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                  <span class="text-[10px] font-bold text-[#8a6b32] uppercase tracking-wider">
                    {{ storeInfo.is_pickup_active ? 'Open for Store Pickup Today' : 'Pickup Currently Paused' }}
                  </span>
                </div>
                <h3 class="text-xl font-serif font-bold text-[#1d1d1f] mt-1">
                  {{ storeInfo.name }}
                </h3>
                <p class="text-xs text-[#6e6e73] mt-0.5">
                  {{ storeInfo.tagline || 'Authentic Indian Groceries & Fresh Click-and-Collect' }}
                </p>
              </div>
              <button 
                type="button"
                @click="storeInfoModalOpen = false"
                class="w-8 h-8 rounded-full bg-white/90 hover:bg-white text-stone-500 hover:text-stone-900 border border-stone-200 flex items-center justify-center transition-colors cursor-pointer shadow-xs"
                aria-label="Close"
              >
                <X class="w-4 h-4" />
              </button>
            </div>

            <!-- Modal Content (Scrollable) -->
            <div class="p-6 space-y-4 overflow-y-auto text-xs">
              
              <!-- Address & Google Maps Directions -->
              <div class="p-4 rounded-2xl bg-[#faf7f2] border border-[#e8e1d3] space-y-3">
                <div class="flex items-start gap-3">
                  <div class="w-9 h-9 rounded-xl bg-white border border-[#dfd6c8] flex items-center justify-center text-[#8a6b32] shrink-0 shadow-xs">
                    <MapPin class="w-4 h-4 stroke-[2.2]" />
                  </div>
                  <div class="min-w-0 flex-1">
                    <div class="font-bold text-stone-900 text-sm">Store Address</div>
                    <div class="text-stone-700 text-xs mt-0.5 font-medium leading-relaxed">
                      {{ storeInfo.address }}<br />
                      {{ storeInfo.city }}, {{ storeInfo.state }} {{ storeInfo.zip }}
                    </div>
                  </div>
                </div>

                <a 
                  v-if="storeInfo.maps_url"
                  :href="storeInfo.maps_url"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="w-full inline-flex items-center justify-center gap-2 px-3.5 py-2.5 bg-white hover:bg-stone-50 text-stone-900 font-semibold rounded-xl border border-[#dfd6c8] shadow-xs transition-colors cursor-pointer"
                >
                  <ExternalLink class="w-3.5 h-3.5 text-[#8a6b32]" />
                  <span>Get Directions in Google Maps</span>
                </a>
              </div>

              <!-- Pickup Prep Time & Store Hours -->
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="p-3.5 rounded-2xl bg-white border border-stone-200 space-y-1">
                  <div class="flex items-center gap-1.5 text-stone-500 text-[11px] font-semibold uppercase tracking-wider">
                    <Clock class="w-3.5 h-3.5 text-[#8a6b32]" />
                    <span>Order Prep Time</span>
                  </div>
                  <div class="text-sm font-bold text-stone-900">
                    {{ storeInfo.pickup_time }}
                  </div>
                  <p class="text-[11px] text-stone-500 font-normal leading-tight">
                    Order online and pick up at the express counter.
                  </p>
                </div>

                <div class="p-3.5 rounded-2xl bg-white border border-stone-200 space-y-1">
                  <div class="flex items-center gap-1.5 text-stone-500 text-[11px] font-semibold uppercase tracking-wider">
                    <Store class="w-3.5 h-3.5 text-[#8a6b32]" />
                    <span>Store Hours</span>
                  </div>
                  <div class="text-sm font-bold text-stone-900">
                    {{ storeInfo.opening_hours }}
                  </div>
                  <p class="text-[11px] text-stone-500 font-normal leading-tight">
                    Open 7 days a week for in-store shopping & pickup.
                  </p>
                </div>
              </div>

              <!-- Curbside & Bay Instructions -->
              <div v-if="storeInfo.curbside_instructions" class="p-4 rounded-2xl bg-[#fdfbf7] border border-[#e8e1d3] space-y-2">
                <div class="flex items-center gap-2 text-stone-900 font-bold text-xs">
                  <Car class="w-4 h-4 text-[#8a6b32]" />
                  <span>Curbside & Parking Bay Instructions</span>
                </div>
                <p class="text-stone-600 text-xs leading-relaxed font-normal">
                  {{ storeInfo.curbside_instructions }}
                </p>
              </div>

              <!-- Contact & Phone -->
              <div class="p-4 rounded-2xl bg-white border border-stone-200 space-y-2.5">
                <div class="text-[11px] font-bold text-stone-400 uppercase tracking-wider">
                  Store Contact & Inquiries
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                  <a 
                    :href="'tel:' + storeInfo.phone" 
                    class="flex items-center gap-2 p-2.5 rounded-xl bg-stone-50 hover:bg-stone-100 text-stone-800 transition-colors"
                  >
                    <Phone class="w-3.5 h-3.5 text-[#8a6b32] shrink-0" />
                    <span class="font-semibold">{{ storeInfo.phone }}</span>
                  </a>
                  <a 
                    :href="'mailto:' + storeInfo.email" 
                    class="flex items-center gap-2 p-2.5 rounded-xl bg-stone-50 hover:bg-stone-100 text-stone-800 transition-colors truncate"
                  >
                    <Mail class="w-3.5 h-3.5 text-[#8a6b32] shrink-0" />
                    <span class="font-semibold truncate">{{ storeInfo.email }}</span>
                  </a>
                </div>
              </div>

            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-4 bg-stone-50 border-t border-stone-200 flex items-center justify-between">
              <span class="text-[11px] text-stone-500">
                Single store location · 100% Free Store Pickup
              </span>
              <button 
                type="button"
                @click="storeInfoModalOpen = false"
                class="px-5 py-2 bg-[#1a1a1a] hover:bg-black text-white text-xs font-semibold rounded-xl transition-all shadow-xs cursor-pointer"
              >
                Done
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

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
              <span class="text-[10px] text-stone-300 mt-1 truncate leading-none flex items-center gap-1">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 shrink-0"></span>
                <span>Free Store Pickup · {{ storeInfo.pickup_time }}</span>
              </span>
            </div>
          </div>

          <!-- Right: Arrow Button -->
          <div class="flex items-center gap-1 text-[11px] font-bold text-stone-200 bg-white/10 group-hover:bg-white/20 group-hover:text-white px-2.5 py-1.5 rounded-xl border border-white/10 shrink-0 transition-colors">
            <span>Pickup Checkout</span>
            <ChevronRight class="w-3.5 h-3.5 stroke-[2.5]" />
          </div>
        </div>

        <!-- Sleek Hairline 2px Brass Accent Line at the very bottom edge -->
        <div class="w-full bg-[#a47a3c]/30 h-0.5 relative overflow-hidden">
          <div class="bg-[#a47a3c] h-full w-full"></div>
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
              <span class="font-devanagari text-xs text-[#7a5620] font-medium bg-[#f5eee2] px-2 py-0.5 rounded-full border border-[#e0d9cc]">
                {{ storeInfo.hindi_tagline || 'किराना' }}
              </span>
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
              Store Pickup Location
            </div>
            <div class="text-[#1d1d1f] font-bold">{{ storeInfo.name }}</div>
            <div class="text-[#6e6e73] font-normal">{{ storeInfo.address }}, {{ storeInfo.city }} {{ storeInfo.state }}</div>
            <div class="text-[#6e6e73] font-normal">{{ storeInfo.opening_hours }}</div>
            <button 
              type="button"
              @click="storeInfoModalOpen = true"
              class="text-[#7a5620] font-semibold pt-1 hover:underline text-left cursor-pointer flex items-center gap-1"
            >
              <span>View full pickup guide & directions</span>
              <ChevronRight class="w-3 h-3" />
            </button>
          </div>

          <!-- Column 3: Quick Links -->
          <div class="space-y-2 text-xs">
            <div class="font-semibold uppercase text-[11px] text-[#86868b] tracking-wider">
              Customer Services
            </div>
            <ul class="space-y-1.5 text-[#6e6e73] font-normal">
              <li><Link href="/cart" class="hover:text-[#1d1d1f]">Shopping Cart & Pickup Status</Link></li>
              <li><Link href="/checkout" class="hover:text-[#1d1d1f]">Express Store Pickup</Link></li>
              <li><Link href="/account" class="hover:text-[#1d1d1f]">Subscriptions & Auto-Reorder</Link></li>
              <li><Link href="/reorder" class="hover:text-[#1d1d1f]">Past Orders & Instant Reorder</Link></li>
            </ul>
          </div>

          <!-- Column 4: Rewards & Pickup -->
          <div class="space-y-3 text-xs bg-[#f5eee2] p-4 rounded-2xl border border-[#e0d9cc]">
            <div class="font-semibold uppercase text-[11px] text-[#7a5620] flex items-center gap-1.5">
              <Sparkles class="w-3.5 h-3.5 text-[#a47a3c]" />
              <span>Masala Rewards</span>
            </div>
            <p class="text-[11px] text-[#6e6e73] font-normal leading-snug">
              Earn 1 point for every $1 spent. Curbside pickup ready in {{ storeInfo.pickup_time }} at {{ storeInfo.name }}.
            </p>
            <div class="flex items-center justify-between text-xs text-[#1d1d1f] border-t border-[#e0d9cc] pt-2 font-bold">
              <span>Your Balance:</span>
              <span class="text-[#7a5620] font-serif font-medium">{{ store.masalaPoints }} pts</span>
            </div>
          </div>

        </div>

        <div class="pt-6 flex flex-col sm:flex-row justify-between items-center text-xs text-[#86868b] gap-3 font-normal">
          <div>© 2026 {{ storeInfo.name }}. All rights reserved. Modern Classic V3.</div>
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
