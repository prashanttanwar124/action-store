<script setup>
import { ref, computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useStore } from '../stores/cart';
import { 
  ShoppingBag, 
  Search, 
  ChevronDown, 
  X, 
  Sparkles, 
  Clock, 
  MapPin, 
  User, 
  RefreshCw, 
  UtensilsCrossed, 
  Package, 
  Gift, 
  ArrowRight,
  ShieldCheck,
  Truck,
  Home
} from 'lucide-vue-next';

const props = defineProps({
  title: {
    type: String,
    default: 'Masala Mart',
  },
  showCartBar: {
    type: Boolean,
    default: true,
  },
  showBottomNav: {
    type: Boolean,
    default: true,
  },
});

const store = useStore();
const page = usePage();
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
  <div class="min-h-screen bg-[#F4F4F5] text-zinc-950 flex flex-col font-sans selection:bg-[#E52E04] selection:text-white antialiased">
    
    <!-- Top Utility Announcement Bar (Desktop & Tablet) -->
    <div class="bg-zinc-950 text-white text-[11px] font-mono py-1.5 px-4 border-b border-zinc-800">
      <div class="max-w-7xl mx-auto flex flex-col sm:flex-row justify-between items-center gap-1.5">
        <div class="flex items-center gap-2">
          <span class="bg-[#E52E04] text-white px-1.5 py-0.5 font-bold uppercase text-[9px] tracking-wider">Diwali 2026</span>
          <span class="text-zinc-300">Fresh Mithai Pre-Orders Open · Closes Oct 30</span>
        </div>
        <div class="hidden sm:flex items-center gap-4 text-zinc-400">
          <span class="flex items-center gap-1.5">
            <Clock class="w-3.5 h-3.5 text-amber-400" />
            <span>Click & Collect in 1 hr</span>
          </span>
          <span class="text-zinc-700">|</span>
          <span class="flex items-center gap-1.5">
            <Truck class="w-3.5 h-3.5 text-zinc-400" />
            <span>Free delivery on orders $40+</span>
          </span>
          <span class="text-zinc-700">|</span>
          <Link href="/account" class="text-zinc-300 hover:text-white font-medium flex items-center gap-1">
            <Sparkles class="w-3 h-3 text-[#E52E04]" />
            <span>{{ store.masalaPoints.toLocaleString() }} pts</span>
          </Link>
        </div>
      </div>
    </div>

    <!-- Main Navigation Header -->
    <header class="bg-white border-b border-zinc-300 sticky top-0 z-40">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Header Top Row (Logo, Store Picker & Cart) -->
        <div class="flex items-center justify-between h-14 sm:h-20 gap-3">
          
          <!-- Logo & Mobile Store Subtitle -->
          <div class="flex items-center gap-4">
            <div class="flex flex-col">
              <Link href="/" class="flex flex-col group">
                <span class="text-2xl sm:text-3xl font-black tracking-tight leading-none text-zinc-950 group-hover:text-[#E52E04] transition-colors">
                  Masala Mart
                </span>
              </Link>
              
              <!-- Location subtitle on Mobile (Screen 1a match) -->
              <div 
                @click="storeSelectorOpen = !storeSelectorOpen"
                class="flex items-center gap-1 text-xs text-zinc-600 mt-1 cursor-pointer select-none sm:hidden"
              >
                <span class="font-medium text-zinc-700">
                  Pickup · {{ store.selectedStore }} · {{ store.readyTime }}
                </span>
                <span class="text-zinc-400 text-[10px]">▾</span>
              </div>
            </div>

            <!-- Desktop Store Selector Dropdown -->
            <div class="relative hidden sm:block">
              <button 
                @click="storeSelectorOpen = !storeSelectorOpen"
                class="flex items-center gap-2 px-3 py-1.5 border border-zinc-300 bg-zinc-50 hover:bg-zinc-100 text-left text-xs transition-colors rounded-none cursor-pointer"
              >
                <MapPin class="w-3.5 h-3.5 text-[#E52E04]" />
                <div>
                  <div class="text-[10px] font-mono text-zinc-500 uppercase font-bold">Store Pickup</div>
                  <div class="font-bold text-zinc-900 leading-tight">{{ store.selectedStore }} · {{ store.readyTime }}</div>
                </div>
                <ChevronDown class="w-3.5 h-3.5 text-zinc-500 ml-1" />
              </button>

              <div 
                v-if="storeSelectorOpen" 
                class="absolute left-0 mt-1 w-72 bg-white border-2 border-zinc-950 shadow-2xl z-50 p-2 space-y-1"
              >
                <div class="px-2 py-1 text-[10px] font-mono text-zinc-500 uppercase font-bold border-b border-zinc-200">
                  Select Pickup Location
                </div>
                <button
                  v-for="st in storesList"
                  :key="st.id"
                  @click="selectStore(st)"
                  class="w-full text-left p-2 hover:bg-zinc-100 flex flex-col transition-colors border border-transparent hover:border-zinc-300 cursor-pointer"
                >
                  <span class="text-xs font-black text-zinc-950">{{ st.name }}</span>
                  <span class="text-[11px] text-zinc-500 font-mono">{{ st.address }} · {{ st.readyTime }}</span>
                </button>
              </div>
            </div>
          </div>

          <!-- Desktop Global Search Bar -->
          <div class="flex-1 max-w-xl mx-4 hidden sm:block">
            <div class="relative flex items-center">
              <Search class="absolute left-3.5 w-4 h-4 text-zinc-500 stroke-[2.5]" />
              <input 
                v-model="store.searchQuery"
                ref="searchInputRef"
                type="text" 
                placeholder="Search paneer, atta, desi ghee, garam masala, maggi…"
                class="w-full pl-10 pr-9 py-2.5 bg-zinc-100 border border-zinc-400 text-xs text-zinc-950 placeholder-zinc-500 focus:outline-none focus:bg-white focus:border-zinc-950 font-medium rounded-none transition-all"
              />
              <button 
                v-if="store.searchQuery" 
                @click="store.searchQuery = ''"
                class="absolute right-3 text-zinc-400 hover:text-zinc-700"
              >
                <X class="w-4 h-4" />
              </button>
            </div>
          </div>

          <!-- Header Right Actions -->
          <div class="flex items-center gap-2.5">
            <Link 
              href="/reorder" 
              class="hidden md:flex items-center gap-1.5 px-3 py-2 border border-zinc-300 bg-white hover:bg-zinc-50 text-xs font-bold text-zinc-900 transition-colors"
            >
              <RefreshCw class="w-3.5 h-3.5 text-zinc-600" />
              <span>Buy It Again</span>
            </Link>

            <Link 
              href="/account" 
              class="hidden sm:flex items-center gap-1.5 px-3 py-2 border border-zinc-300 bg-white hover:bg-zinc-50 text-xs font-bold text-zinc-900 transition-colors"
            >
              <User class="w-3.5 h-3.5 text-zinc-600" />
              <span>Account</span>
            </Link>

            <!-- Cart Trigger Button (Screen 1a match on mobile) -->
            <Link 
              href="/cart"
              class="relative p-2 sm:px-3.5 sm:py-2 bg-white sm:bg-zinc-950 text-zinc-950 sm:text-white border-2 border-zinc-950 sm:border-zinc-950 transition-colors cursor-pointer group flex items-center gap-2"
              aria-label="Shopping Cart"
            >
              <div class="relative">
                <ShoppingBag class="w-5 h-5 sm:w-4 sm:h-4 stroke-[2.2]" />
                <span 
                  v-if="store.totalItemCount > 0"
                  class="absolute -top-2 -right-2 bg-[#E52E04] text-white text-[10px] font-black w-4 h-4 flex items-center justify-center border border-white sm:border-zinc-950"
                >
                  {{ store.totalItemCount }}
                </span>
              </div>
              <div class="hidden sm:flex flex-col text-left leading-none">
                <span class="text-[9px] font-mono text-zinc-400 uppercase">Cart</span>
                <span class="text-xs font-mono font-black mt-0.5 text-white">${{ store.subtotal.toFixed(2) }}</span>
              </div>
            </Link>
          </div>

        </div>

        <!-- Mobile Store Selector Modal/Drawer (When clicked on mobile) -->
        <div 
          v-if="storeSelectorOpen" 
          class="sm:hidden border-t border-zinc-200 py-2 bg-zinc-50 px-1 space-y-1"
        >
          <div class="text-[10px] font-mono text-zinc-500 uppercase font-bold px-2 py-0.5">Select Store Location:</div>
          <button
            v-for="st in storesList"
            :key="st.id"
            @click="selectStore(st)"
            class="w-full text-left p-2 bg-white border border-zinc-200 flex justify-between items-center text-xs"
          >
            <div>
              <span class="font-bold text-zinc-950">{{ st.name }}</span>
              <span class="text-zinc-500 font-mono text-[10px] block">{{ st.readyTime }}</span>
            </div>
            <span v-if="store.selectedStore === st.name" class="text-[#E52E04] font-black">✓</span>
          </button>
        </div>

        <!-- Mobile Pinned Search Row (Exact match to Screen 1a: pinned search) -->
        <div class="sm:hidden pb-2.5 pt-0.5">
          <div class="relative flex items-center">
            <Search class="absolute left-3 w-4 h-4 text-zinc-600 stroke-[2.5]" />
            <input 
              v-model="store.searchQuery"
              ref="searchInputRef"
              type="text" 
              placeholder="Search paneer, atta, curry leaves…"
              class="w-full pl-9 pr-8 py-2 bg-zinc-100 border border-zinc-400 text-xs text-zinc-950 placeholder-zinc-500 rounded-none focus:outline-none focus:bg-white focus:border-zinc-950"
            />
            <button 
              v-if="store.searchQuery" 
              @click="store.searchQuery = ''"
              class="absolute right-2 text-zinc-400"
            >
              <X class="w-4 h-4" />
            </button>
          </div>
        </div>

        <!-- Horizontal Categories Pill Bar -->
        <nav class="flex items-center justify-between border-t border-zinc-200 py-2 overflow-x-auto no-scrollbar text-xs font-bold -mx-4 px-4 sm:mx-0 sm:px-0">
          <div class="flex items-center gap-4 sm:gap-6 shrink-0">
            <Link 
              href="/" 
              :class="[
                'transition-colors py-1 shrink-0',
                currentUrl === '/' ? 'text-[#E52E04] border-b-2 border-[#E52E04]' : 'text-zinc-700 hover:text-zinc-950'
              ]"
            >
              All Categories
            </Link>
            <Link 
              href="/products/paneer" 
              :class="[
                'transition-colors py-1 shrink-0',
                currentUrl === '/products/paneer' ? 'text-[#E52E04] border-b-2 border-[#E52E04]' : 'text-zinc-700 hover:text-zinc-950'
              ]"
            >
              Dairy & Fresh Paneer
            </Link>
            <Link 
              href="/products/rice" 
              :class="[
                'transition-colors py-1 shrink-0',
                currentUrl === '/products/rice' ? 'text-[#E52E04] border-b-2 border-[#E52E04]' : 'text-zinc-700 hover:text-zinc-950'
              ]"
            >
              Staples, Atta & Rice
            </Link>
            <Link 
              href="/#recipe-kits" 
              class="text-zinc-700 hover:text-zinc-950 transition-colors py-1 flex items-center gap-1 shrink-0"
            >
              <UtensilsCrossed class="w-3.5 h-3.5 text-[#E52E04]" />
              <span>Recipe Kits (14)</span>
            </Link>
            <Link 
              href="/account" 
              class="text-zinc-700 hover:text-zinc-950 transition-colors py-1 flex items-center gap-1 shrink-0"
            >
              <RefreshCw class="w-3.5 h-3.5 text-zinc-500" />
              <span>Subscribe & Save (5%)</span>
            </Link>
          </div>

          <div class="hidden md:flex items-center gap-2 text-[11px] font-mono text-zinc-500 shrink-0">
            <span>Free Store Pickup</span>
            <span>·</span>
            <span class="text-emerald-700 font-bold">Curbside Ready in 1 Hr</span>
          </div>
        </nav>

      </div>
    </header>

    <!-- Main Page Content Body (Extra bottom padding on mobile for sticky bars) -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-6 pb-36 sm:pb-12">
      <slot />
    </main>

    <!-- Floating Dark Cart Bar (Exact match to Screens 1a & 1b) -->
    <div 
      v-if="showCartBar && store.totalItemCount > 0"
      class="fixed bottom-[56px] sm:bottom-6 left-2.5 right-2.5 sm:left-auto sm:right-6 z-40 max-w-sm sm:w-80 cursor-pointer pointer-events-auto"
    >
      <Link 
        href="/cart"
        class="block bg-[#18181B] text-white border border-zinc-800 shadow-2xl p-2.5 sm:p-3 hover:bg-zinc-900 active:scale-[0.99] transition-all"
      >
        <div class="flex items-center justify-between">
          <div>
            <div class="text-xs sm:text-sm font-black tracking-tight flex items-center gap-1.5">
              <span>View cart</span>
              <span>·</span>
              <span>{{ store.totalItemCount }} items</span>
            </div>
            <div class="text-[10px] sm:text-[11px] text-zinc-400 font-mono mt-0.5">
              <template v-if="store.amountToFreeDelivery > 0">
                ${{ store.amountToFreeDelivery.toFixed(2) }} to free delivery
              </template>
              <template v-else>
                <span class="text-amber-400 font-bold">🎉 Free Delivery Unlocked!</span>
              </template>
            </div>
          </div>
          <div class="text-right">
            <div class="text-base sm:text-lg font-black font-mono tracking-tight">${{ store.subtotal.toFixed(2) }}</div>
          </div>
        </div>
        
        <!-- Red delivery progress bar -->
        <div class="w-full bg-zinc-800 h-1 mt-1.5 relative overflow-hidden">
          <div 
            class="bg-[#E52E04] h-full transition-all duration-300"
            :style="{ width: store.freeDeliveryProgressPercent + '%' }"
          ></div>
        </div>
      </Link>
    </div>

    <!-- Sticky Mobile Bottom Navigation Bar (Screens 1a to 1f: Shop, Search, Reorder, Account) -->
    <nav v-if="showBottomNav" class="fixed bottom-0 left-0 right-0 z-40 bg-white border-t border-zinc-200 sm:hidden">
      <div class="flex items-center justify-around h-[56px] px-1 text-center">
        <Link
          v-for="tab in navTabs"
          :key="tab.id"
          :href="tab.href"
          @click="tab.id === 'search' ? handleSearchClick() : null"
          :class="[
            'flex flex-col items-center justify-center w-full h-full py-1 transition-colors select-none',
            tab.isActive ? 'text-[#E52E04] font-black' : 'text-zinc-600 font-medium'
          ]"
        >
          <component 
            :is="tab.icon" 
            :class="[
              'w-5 h-5 transition-transform',
              tab.isActive ? 'stroke-[2.5] scale-105' : 'stroke-[1.8]'
            ]" 
          />
          <span class="text-[10px] mt-0.5 tracking-tight">
            {{ tab.label }}
          </span>
        </Link>
      </div>
    </nav>

    <!-- Modernist Footer (Desktop & Tablet) -->
    <footer class="bg-zinc-950 text-white border-t border-zinc-800 mt-12 pt-12 pb-8 hidden sm:block">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8 pb-10 border-b border-zinc-800">
          
          <!-- Column 1: Store Bio -->
          <div class="space-y-3">
            <div class="text-2xl font-black tracking-tight">Masala Mart</div>
            <p class="text-xs text-zinc-400 leading-relaxed">
              Modern click-and-collect Indian grocery. Highest quality grains, freshly batched dairy, authentic spices, and festive sweets ready in 60 minutes.
            </p>
            <div class="text-[11px] font-mono text-zinc-500">
              Zero compromises. 100% satisfaction guarantee.
            </div>
          </div>

          <!-- Column 2: Hours & Pickup -->
          <div class="space-y-2 text-xs">
            <div class="font-mono font-bold uppercase text-[11px] text-[#E52E04] tracking-wider">
              Store Pickup
            </div>
            <div class="text-zinc-300 font-bold">Main St. Store (Flagship)</div>
            <div class="text-zinc-400 text-[11px] font-mono">482 Main Street, Suite 100</div>
            <div class="text-zinc-400 text-[11px] font-mono">Mon–Sun: 8:00 AM – 10:00 PM</div>
            <div class="text-emerald-400 font-semibold text-[11px] pt-1">
              ✓ Designated Curbside Pickup Spots Available
            </div>
          </div>

          <!-- Column 3: Quick Links -->
          <div class="space-y-2 text-xs">
            <div class="font-mono font-bold uppercase text-[11px] text-zinc-400 tracking-wider">
              Customer Services
            </div>
            <ul class="space-y-1.5 text-zinc-300">
              <li><Link href="/cart" class="hover:text-white">Shopping Cart & Delivery Meter</Link></li>
              <li><Link href="/checkout" class="hover:text-white">Express Checkout (Apple/Google Pay)</Link></li>
              <li><Link href="/account" class="hover:text-white">Subscriptions & Auto-Reorder</Link></li>
              <li><Link href="/reorder" class="hover:text-white">Past Orders & Instant Reorder</Link></li>
            </ul>
          </div>

          <!-- Column 4: Rewards & Delivery -->
          <div class="space-y-3 text-xs bg-zinc-900 p-4 border border-zinc-800">
            <div class="font-mono font-bold uppercase text-[11px] text-amber-400 flex items-center gap-1.5">
              <Sparkles class="w-3.5 h-3.5" />
              <span>Masala Rewards</span>
            </div>
            <p class="text-[11px] text-zinc-400 leading-snug">
              Earn 1 point for every $1 spent. Unlock $10 grocery credit at 1,500 points.
            </p>
            <div class="flex items-center justify-between text-[11px] font-mono text-zinc-300 border-t border-zinc-800 pt-2">
              <span>Your Balance:</span>
              <span class="font-black text-amber-400">{{ store.masalaPoints }} pts</span>
            </div>
          </div>

        </div>

        <div class="pt-6 flex flex-col sm:flex-row justify-between items-center text-[11px] font-mono text-zinc-500 gap-3">
          <div>© 2026 Masala Mart Inc. All rights reserved. Strict Modernist Layout.</div>
          <div class="flex gap-4">
            <a href="#" class="hover:text-zinc-300">Privacy Policy</a>
            <span>·</span>
            <a href="#" class="hover:text-zinc-300">Terms of Service</a>
            <span>·</span>
            <a href="#" class="hover:text-zinc-300">Click & Collect Terms</a>
          </div>
        </div>
      </div>
    </footer>

  </div>
</template>
