<script setup>
import { computed, ref } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import { useStore } from '../../stores/cart';
import { 
  ShoppingBag, 
  Search, 
  ChevronDown, 
  ChevronLeft, 
  ChevronRight, 
  X, 
  MapPin, 
  User, 
  RefreshCw, 
  UtensilsCrossed, 
  Info,
  Sparkles,
  Clock,
  Flame
} from 'lucide-vue-next';

const props = defineProps({
  headerMode: {
    type: String,
    default: 'storefront',
  },
  headerTitle: {
    type: String,
    default: '',
  },
  backUrl: {
    type: String,
    default: '/',
  },
  storeInfo: {
    type: Object,
    default: () => ({}),
  },
});

defineEmits(['openStoreInfo']);

const store = useStore();
const page = usePage();
const searchInputRef = ref(null);

const categoryLinks = [
  { id: 'all', label: 'All Categories', href: '/' },
  { id: 'dairy', label: 'Dairy & Fresh Paneer', href: '/categories/dairy' },
  { id: 'staples', label: 'Staples, Atta & Rice', href: '/categories/staples' },
  { id: 'snacks', label: 'Snacks & Namkeen', href: '/categories/snacks' },
  { id: 'spices', label: 'Spices & Masala', href: '/categories/spices' },
  { id: 'kits', label: 'Recipe Kits', href: '/recipe-kits', isKit: true },
];

const currentCategory = computed(() => {
  if (page.component === 'Home') return 'all';
  if (page.component === 'Search') {
    if (page.props.filters?.tab === 'kits') return 'kits';
    return page.props.filters?.category || 'all';
  }
  return '';
});

function handleSearchSubmit() {
  const query = store.searchQuery?.trim();
  if (query) {
    router.get('/search', { q: query });
  } else {
    router.get('/search');
  }
}
</script>

<template>
  <header class="bg-white/95 border-b border-[#e0d9cc] sticky top-0 z-40 backdrop-blur-md">
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
            
            <!-- Store Location on Mobile (Clear, clean & non-repetitive) -->
            <button 
              type="button"
              @click="$emit('openStoreInfo')"
              class="flex items-center gap-1 text-[11.5px] text-[#6e6e73] mt-0.5 cursor-pointer select-none sm:hidden text-left hover:text-[#1d1d1f] transition-colors"
              title="View store information and hours"
            >
              <MapPin class="w-3 h-3 text-[#a47a3c] shrink-0" />
              <span class="font-medium text-[#1d1d1f] truncate max-w-[130px]">{{ storeInfo.address || '214 Main St.' }}</span>
              <span class="text-stone-300">·</span>
              <span class="truncate max-w-[95px] text-[#6e6e73]">{{ storeInfo.city || 'Edison' }}</span>
              <ChevronDown class="w-3 h-3 text-[#86868b] shrink-0 ml-0.5" />
            </button>
          </div>

          <!-- Desktop Single Store Pickup Pill (Uniform h-[42px] rounded-full) -->
          <div class="relative hidden sm:block">
            <button 
              type="button"
              @click="$emit('openStoreInfo')"
              class="h-[42px] inline-flex items-center gap-2 px-3 bg-[#f4efe6] hover:bg-[#ede6da] border border-[#dfd6c8] hover:border-[#cfc4b2] text-left rounded-full transition-all duration-150 cursor-pointer shadow-xs active:scale-[0.99] shrink-0"
              title="View store hours, location & curbside directions"
            >
              <div class="relative w-6 h-6 rounded-full bg-white border border-[#dfd6c8] flex items-center justify-center shrink-0">
                <MapPin class="w-3.5 h-3.5 text-[#8a6b32] stroke-[2.2]" />
                <span class="absolute -top-0.5 -right-0.5 w-1.5 h-1.5 rounded-full" :class="storeInfo.is_busy ? 'bg-amber-500 animate-pulse' : 'bg-emerald-500'"></span>
              </div>
              <div class="flex flex-col justify-center min-w-0 pr-0.5">
                <div class="flex items-center gap-1 text-[9px] text-[#8a6b32] font-bold uppercase tracking-wider leading-none">
                  <span>PICKUP</span>
                  <span class="text-[#c5baaa]">·</span>
                  <span class="font-medium normal-case flex items-center gap-0.5" :class="storeInfo.is_busy ? 'text-amber-800 font-bold' : 'text-[#6e6e73]'">
                    <Flame v-if="storeInfo.is_busy" class="w-2.5 h-2.5 text-amber-600 inline shrink-0" />
                    <span>{{ storeInfo.effective_pickup_time || storeInfo.pickup_time || 'Ready in 15 mins' }}</span>
                  </span>
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
        <div v-if="headerMode !== 'search' && page.component !== 'Search'" class="flex-1 max-w-xl mx-2 hidden sm:block">
          <div class="relative flex items-center h-[42px] group">
            <Search class="absolute left-3.5 w-4 h-4 text-[#86868b] group-focus-within:text-[#1a1a1a] transition-colors stroke-[2.2]" />
            <input 
              v-model="store.searchQuery"
              ref="searchInputRef"
              type="text" 
              placeholder="Search paneer, atta, desi ghee, garam masala, maggi…"
              @keydown.enter="handleSearchSubmit"
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
            v-if="page.props.auth?.user"
            href="/account" 
            class="hidden sm:inline-flex items-center gap-1.5 h-[42px] px-3.5 border border-[#dfd6c8] bg-[#f4efe6] hover:bg-[#ede6da] rounded-full text-xs font-semibold text-[#1d1d1f] transition-colors shadow-xs shrink-0"
            :title="'Logged in as ' + page.props.auth.user.name"
          >
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
            <User class="w-3.5 h-3.5 text-[#a47a3c]" />
            <span class="max-w-[100px] truncate">{{ page.props.auth.user.name.split(' ')[0] }}</span>
          </Link>
          <Link 
            v-else
            href="/login" 
            class="hidden sm:inline-flex items-center gap-1.5 h-[42px] px-3.5 border border-[#dfd6c8] bg-[#f4efe6] hover:bg-[#ede6da] rounded-full text-xs font-semibold text-[#1d1d1f] transition-colors shadow-xs shrink-0"
            title="Sign in to your account"
          >
            <User class="w-3.5 h-3.5 text-[#6e6e73]" />
            <span>Sign In</span>
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

      <!-- Mobile Store Announcement & Curbside Highlights Card (Non-repetitive & Actionable) -->
      <div v-if="headerMode === 'storefront' && page.component !== 'Search'" class="sm:hidden pb-2.5 pt-0.5">
        <div 
          @click="$emit('openStoreInfo')"
          class="flex items-center justify-between px-3 py-2 bg-gradient-to-r from-[#f7f3ec] via-[#f5efe5] to-[#faf6ef] hover:from-[#f3ece0] hover:to-[#f5eee2] border border-[#dfd6c8] rounded-2xl text-xs text-[#1d1d1f] transition-all cursor-pointer shadow-2xs active:scale-[0.99]"
        >
          <div class="flex items-center gap-2.5 min-w-0">
            <span class="w-7 h-7 rounded-xl bg-white border border-[#dfd6c8] text-[#a47a3c] flex items-center justify-center shrink-0 shadow-2xs">
              <Sparkles class="w-3.5 h-3.5 text-[#a47a3c]" />
            </span>
            <div class="flex flex-col min-w-0 leading-tight">
              <span class="font-bold text-[11px] text-[#1d1d1f] truncate">
                {{ storeInfo.announcement || 'Diwali Special: Fresh Mithai & Pre-Orders' }}
              </span>
              <span class="text-[10px] text-[#7a5620] mt-0.5 truncate font-medium flex items-center gap-1">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0"></span>
                <span>Ready in {{ storeInfo.pickup_time || '1 hr' }} · Free Curbside Bay 3</span>
              </span>
            </div>
          </div>
          <div class="flex items-center gap-0.5 text-[10.5px] font-bold text-[#7a5620] bg-white/90 border border-[#dfd6c8] px-2 py-1 rounded-lg shrink-0 ml-1.5 shadow-2xs">
            <span>Details</span>
            <ChevronRight class="w-3 h-3 stroke-[2.5]" />
          </div>
        </div>
      </div>

      <!-- Horizontal Categories Pill Bar (Storefront & Category browsing) -->
      <nav v-if="headerMode === 'storefront' && page.component !== 'Search'" class="flex items-center justify-between border-t border-[#e0d9cc]/60 py-2 overflow-x-auto no-scrollbar text-xs font-semibold -mx-4 px-4 sm:mx-0 sm:px-0">
        <div class="flex items-center gap-2 sm:gap-2.5 shrink-0">
          <Link 
            v-for="cat in categoryLinks"
            :key="cat.id"
            :href="cat.href" 
            :class="[
              'transition-colors px-3 py-1.5 rounded-full shrink-0 flex items-center gap-1.5',
              currentCategory === cat.id 
                ? 'bg-[#1a1a1a] text-white font-semibold shadow-xs' 
                : 'bg-[#f3efe7] text-[#6e6e73] hover:text-[#1d1d1f] border border-[#e0d9cc]/60'
            ]"
          >
            <UtensilsCrossed v-if="cat.id === 'kits'" class="w-3 h-3 text-[#a47a3c]" />
            <span>{{ cat.label }}</span>
          </Link>
        </div>

        <div class="hidden md:flex items-center gap-2 text-xs font-normal text-[#6e6e73] shrink-0">
          <span>Free Store Pickup</span>
          <span>·</span>
          <span class="font-semibold" :class="storeInfo.is_busy ? 'text-amber-800' : 'text-[#a47a3c]'">
            {{ storeInfo.effective_pickup_time || storeInfo.pickup_time || 'Ready in 15 mins' }}
          </span>
        </div>
      </nav>

    </div>
  </header>
</template>
