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
  Truck
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
});

const store = useStore();
const page = usePage();
const mobileMenuOpen = ref(false);
const storeSelectorOpen = ref(false);

const currentUrl = computed(() => page.url);

const storesList = [
  { id: 'main', name: 'Main St. Store', address: '482 Main Street', readyTime: 'Ready in 1 hr' },
  { id: 'oak', name: 'Oakridge Plaza', address: '1204 Oakridge Ave', readyTime: 'Ready in 2 hrs' },
  { id: 'downtown', name: 'Downtown Hub', address: '85 Market Square', readyTime: 'Ready in 45 mins' },
];

function selectStore(st) {
  store.selectedStore = st.name;
  store.readyTime = st.readyTime;
  storeSelectorOpen.value = false;
}
</script>

<template>
  <div class="min-h-screen bg-[#F4F4F5] text-zinc-950 flex flex-col font-sans selection:bg-[#E52E04] selection:text-white antialiased">
    
    <!-- Top Utility Announcement Bar -->
    <div class="bg-zinc-950 text-white text-[11px] font-mono py-1.5 px-4 border-b border-zinc-800">
      <div class="max-w-7xl mx-auto flex flex-col sm:flex-row justify-between items-center gap-2">
        <div class="flex items-center gap-2">
          <span class="bg-[#E52E04] text-white px-1.5 py-0.5 font-bold uppercase text-[9px] tracking-wider">Diwali 2026</span>
          <span class="text-zinc-300">Fresh Mithai Pre-Orders Open · Closes Oct 30</span>
        </div>
        <div class="flex items-center gap-4 text-zinc-400">
          <span class="flex items-center gap-1.5">
            <Clock class="w-3.5 h-3.5 text-amber-400" />
            <span>Click & Collect in 1 hr</span>
          </span>
          <span class="hidden md:inline text-zinc-700">|</span>
          <span class="flex items-center gap-1.5">
            <Truck class="w-3.5 h-3.5 text-zinc-400" />
            <span>Free delivery on orders $40+</span>
          </span>
          <span class="hidden md:inline text-zinc-700">|</span>
          <Link href="/account" class="text-zinc-300 hover:text-white font-medium flex items-center gap-1">
            <Sparkles class="w-3 h-3 text-[#E52E04]" />
            <span>{{ store.masalaPoints.toLocaleString() }} pts</span>
          </Link>
        </div>
      </div>
    </div>

    <!-- Main Store Navigation Header -->
    <header class="bg-white border-b border-zinc-300 sticky top-0 z-40">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16 sm:h-20 gap-4">
          
          <!-- Logo & Brand Identity -->
          <div class="flex items-center gap-6">
            <Link href="/" class="flex flex-col group">
              <span class="text-2xl sm:text-3xl font-black tracking-tight leading-none text-zinc-950 group-hover:text-[#E52E04] transition-colors">
                Masala Mart
              </span>
              <span class="text-[10px] font-mono uppercase tracking-widest text-zinc-500 font-semibold mt-0.5">
                Indian Groceries & Fresh Kitchen
              </span>
            </Link>

            <!-- Store Location Dropdown -->
            <div class="relative hidden lg:block">
              <button 
                @click="storeSelectorOpen = !storeSelectorOpen"
                class="flex items-center gap-2 px-3 py-1.5 border border-zinc-300 bg-zinc-50 hover:bg-zinc-100 text-left text-xs transition-colors rounded-none"
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
                  class="w-full text-left p-2 hover:bg-zinc-100 flex flex-col transition-colors border border-transparent hover:border-zinc-300"
                >
                  <span class="text-xs font-black text-zinc-950">{{ st.name }}</span>
                  <span class="text-[11px] text-zinc-500 font-mono">{{ st.address }} · {{ st.readyTime }}</span>
                </button>
              </div>
            </div>
          </div>

          <!-- Global Interactive Search -->
          <div class="flex-1 max-w-xl mx-2 hidden sm:block">
            <div class="relative flex items-center">
              <Search class="absolute left-3.5 w-4 h-4 text-zinc-500 stroke-[2.5]" />
              <input 
                v-model="store.searchQuery"
                type="text" 
                placeholder="Search paneer, atta, desi ghee, garam masala, maggi…"
                class="w-full pl-10 pr-9 py-2 bg-zinc-100 border border-zinc-400 text-xs text-zinc-950 placeholder-zinc-500 focus:outline-none focus:bg-white focus:border-zinc-950 font-medium rounded-none transition-all"
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

          <!-- Right Action Center (Reorder, Account, Cart) -->
          <div class="flex items-center gap-3">
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

            <!-- Cart Trigger Button -->
            <Link 
              href="/cart"
              class="flex items-center gap-2.5 px-3.5 py-2 bg-zinc-950 hover:bg-zinc-800 text-white font-bold border border-zinc-950 transition-colors group cursor-pointer relative"
              aria-label="Shopping Cart"
            >
              <div class="relative">
                <ShoppingBag class="w-4 h-4 stroke-[2.4]" />
                <span 
                  v-if="store.totalItemCount > 0"
                  class="absolute -top-2 -right-2 bg-[#E52E04] text-white text-[10px] font-black w-4 h-4 flex items-center justify-center border border-zinc-950"
                >
                  {{ store.totalItemCount }}
                </span>
              </div>
              <div class="flex flex-col text-left leading-none">
                <span class="text-[9px] font-mono text-zinc-400 uppercase">Cart</span>
                <span class="text-xs font-mono font-black mt-0.5 text-white">${{ store.subtotal.toFixed(2) }}</span>
              </div>
            </Link>
          </div>

        </div>

        <!-- Secondary Categories Bar -->
        <nav class="flex items-center justify-between border-t border-zinc-200 py-2.5 overflow-x-auto no-scrollbar text-xs font-bold">
          <div class="flex items-center gap-4 sm:gap-6 shrink-0">
            <Link 
              href="/" 
              :class="[
                'transition-colors py-1',
                currentUrl === '/' ? 'text-[#E52E04] border-b-2 border-[#E52E04]' : 'text-zinc-700 hover:text-zinc-950'
              ]"
            >
              All Categories
            </Link>
            <Link 
              href="/products/paneer" 
              :class="[
                'transition-colors py-1',
                currentUrl === '/products/paneer' ? 'text-[#E52E04] border-b-2 border-[#E52E04]' : 'text-zinc-700 hover:text-zinc-950'
              ]"
            >
              Dairy & Fresh Paneer
            </Link>
            <Link 
              href="/products/rice" 
              :class="[
                'transition-colors py-1',
                currentUrl === '/products/rice' ? 'text-[#E52E04] border-b-2 border-[#E52E04]' : 'text-zinc-700 hover:text-zinc-950'
              ]"
            >
              Staples, Atta & Rice
            </Link>
            <Link 
              href="/#recipe-kits" 
              class="text-zinc-700 hover:text-zinc-950 transition-colors py-1 flex items-center gap-1"
            >
              <UtensilsCrossed class="w-3.5 h-3.5 text-[#E52E04]" />
              <span>Recipe Kits (14)</span>
            </Link>
            <Link 
              href="/account" 
              class="text-zinc-700 hover:text-zinc-950 transition-colors py-1 flex items-center gap-1"
            >
              <RefreshCw class="w-3.5 h-3.5 text-zinc-500" />
              <span>Subscribe & Save (5% Off)</span>
            </Link>
          </div>

          <div class="hidden md:flex items-center gap-2 text-[11px] font-mono text-zinc-500">
            <span>Free Store Pickup</span>
            <span>·</span>
            <span class="text-emerald-700 font-bold">Curbside Ready</span>
          </div>
        </nav>

        <!-- Mobile Search Row -->
        <div class="sm:hidden pb-3 pt-1">
          <div class="relative flex items-center">
            <Search class="absolute left-3 w-4 h-4 text-zinc-500 stroke-[2.5]" />
            <input 
              v-model="store.searchQuery"
              type="text" 
              placeholder="Search paneer, atta, ghee..."
              class="w-full pl-9 pr-8 py-2 bg-zinc-100 border border-zinc-300 text-xs text-zinc-950 placeholder-zinc-500 rounded-none focus:outline-none focus:bg-white focus:border-zinc-950"
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

      </div>
    </header>

    <!-- Main Page Content Body -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6">
      <slot />
    </main>

    <!-- Floating Quick Cart Banner (Visible on mobile/tablet when items in cart) -->
    <div 
      v-if="showCartBar && store.totalItemCount > 0"
      class="fixed bottom-4 right-4 z-40 max-w-md w-full sm:w-auto left-4 sm:left-auto"
    >
      <Link 
        href="/cart"
        class="block bg-[#18181B] text-white border-2 border-zinc-950 shadow-2xl p-3 hover:bg-zinc-900 transition-all cursor-pointer"
      >
        <div class="flex items-center justify-between gap-6">
          <div>
            <div class="text-sm font-black tracking-tight flex items-center gap-1.5">
              <ShoppingBag class="w-4 h-4 text-[#E52E04]" />
              <span>View Cart · {{ store.totalItemCount }} items</span>
            </div>
            <div class="text-[11px] text-zinc-400 font-mono mt-0.5">
              <template v-if="store.amountToFreeDelivery > 0">
                ${{ store.amountToFreeDelivery.toFixed(2) }} to free delivery
              </template>
              <template v-else>
                <span class="text-amber-400 font-bold">🎉 Free Delivery Unlocked!</span>
              </template>
            </div>
          </div>
          <div class="text-right">
            <div class="text-base font-black font-mono tracking-tight">${{ store.subtotal.toFixed(2) }}</div>
            <div class="text-[10px] text-zinc-400 font-mono">Checkout &rsaquo;</div>
          </div>
        </div>
        <div class="w-full bg-zinc-800 h-1 mt-2 overflow-hidden">
          <div 
            class="bg-[#E52E04] h-full transition-all duration-300"
            :style="{ width: store.freeDeliveryProgressPercent + '%' }"
          ></div>
        </div>
      </Link>
    </div>

    <!-- Modernist Footer -->
    <footer class="bg-zinc-950 text-white border-t border-zinc-800 mt-16 pt-12 pb-8">
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
