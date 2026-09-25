<script setup>
import { ref, computed } from 'vue';
import { useStore } from '../stores/cart';
import { Search, ShoppingBag, ChevronDown, MapPin, Clock, X, User, Sparkles, RefreshCw } from 'lucide-vue-next';

const store = useStore();
const isStoreModalOpen = ref(false);
const isSearchFocused = ref(false);

const storesList = [
  { name: 'Main St. store', distance: '0.8 mi', ready: 'ready in 1 hr', address: '124 Main St, Suite B' },
  { name: 'Oakland Ave. store', distance: '2.4 mi', ready: 'ready in 2 hrs', address: '890 Oakland Ave' },
  { name: 'University Hub', distance: '4.1 mi', ready: 'ready in 45 mins', address: '45 University Plaza' },
];

const categoryLinks = [
  { id: 'all', label: 'All Items' },
  { id: 'sweets', label: 'Diwali Sweets' },
  { id: 'staples', label: 'Atta & Rice' },
  { id: 'dairy', label: 'Ghee & Paneer' },
  { id: 'spices', label: 'Organic Spices' },
  { id: 'snacks', label: 'Chai & Biscuits' },
];

function setStore(st) {
  store.selectedStore = st.name;
  store.readyTime = st.ready;
  isStoreModalOpen.value = false;
}

const searchSuggestions = computed(() => {
  if (!store.searchQuery.trim()) return [];
  const q = store.searchQuery.toLowerCase();
  return store.products.filter(p => 
    p.name.toLowerCase().includes(q) || p.tagline.toLowerCase().includes(q)
  ).slice(0, 4);
});
</script>

<template>
  <header class="bg-white border-b-2 border-zinc-200 sticky top-0 z-40">
    <!-- Top Announcement Bar -->
    <div class="bg-zinc-950 text-white text-xs py-1.5 px-4 font-medium flex items-center justify-between max-w-7xl mx-auto rounded-none">
      <div class="hidden sm:flex items-center gap-2">
        <Sparkles class="w-3.5 h-3.5 text-amber-400" />
        <span class="font-bold">FESTIVAL PRE-ORDER IS LIVE · Diwali Sweets Boxed & Ready!</span>
      </div>
      <div class="w-full sm:w-auto flex items-center justify-center sm:justify-end gap-4 text-[11px] text-zinc-300 font-mono">
        <span>FREE PICKUP OVER $40</span>
        <span class="hidden md:inline">•</span>
        <span class="hidden md:inline">STORE READY IN 1 HR</span>
      </div>
    </div>

    <!-- Main Navigation Container -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="py-3 flex items-center justify-between gap-4">
        
        <!-- Left Logo & Location -->
        <div class="flex items-center gap-6">
          <a 
            href="#" 
            @click.prevent="store.activeTab = 'shop'"
            class="flex items-center gap-2.5 group cursor-pointer"
          >
            <div class="w-10 h-10 rounded-none bg-[#E52E04] text-white font-black text-xl flex items-center justify-center border-2 border-zinc-950 group-hover:bg-[#CC2500] transition-colors">
              M
            </div>
            <div class="leading-none">
              <span class="text-2xl font-black tracking-tight text-zinc-950 block">Masala Mart</span>
              <button 
                @click.stop="isStoreModalOpen = true"
                class="text-xs font-semibold text-zinc-600 hover:text-zinc-950 flex items-center gap-1 mt-0.5"
              >
                <span>{{ store.deliveryMode }} · {{ store.selectedStore }} · {{ store.readyTime }}</span>
                <ChevronDown class="w-3.5 h-3.5 text-zinc-600" />
              </button>
            </div>
          </a>
        </div>

        <!-- Center Search Bar (Sharp rectangular border) -->
        <div class="flex-1 max-w-xl relative">
          <div class="relative flex items-center">
            <Search class="absolute left-3.5 w-4 h-4 text-zinc-600 pointer-events-none stroke-[2.5]" />
            <input
              v-model="store.searchQuery"
              @focus="isSearchFocused = true"
              @blur="setTimeout(() => isSearchFocused = false, 200)"
              type="text"
              placeholder="Search paneer, atta, curry leaves..."
              class="w-full pl-10 pr-10 py-2.5 bg-zinc-100/90 border border-zinc-400 rounded-none text-sm text-zinc-950 placeholder-zinc-500 focus:outline-none focus:ring-2 focus:ring-[#E52E04] focus:bg-white transition-all shadow-none"
            />
            <button 
              v-if="store.searchQuery" 
              @click="store.searchQuery = ''"
              class="absolute right-3 p-1 text-zinc-400 hover:text-zinc-600 rounded-none"
            >
              <X class="w-4 h-4" />
            </button>
          </div>

          <!-- Autocomplete Dropdown with sharp corners -->
          <div 
            v-if="isSearchFocused && searchSuggestions.length > 0"
            class="absolute left-0 right-0 top-full mt-1 bg-white border-2 border-zinc-950 shadow-2xl p-2 z-50 divide-y divide-zinc-200 rounded-none"
          >
            <div 
              v-for="item in searchSuggestions" 
              :key="item.id"
              @mousedown="store.addToCart(item.id)"
              class="p-2.5 flex items-center justify-between hover:bg-orange-50 rounded-none cursor-pointer transition-colors"
            >
              <div class="flex items-center gap-3">
                <img :src="item.image" :alt="item.name" class="w-10 h-10 object-cover border border-zinc-300 rounded-none" />
                <div>
                  <div class="font-bold text-xs text-zinc-950">{{ item.name }}</div>
                  <div class="text-[11px] text-zinc-600">{{ item.size }} · ${{ item.price.toFixed(2) }}</div>
                </div>
              </div>
              <span class="text-xs text-[#E52E04] font-black uppercase">+ Add</span>
            </div>
          </div>
        </div>

        <!-- Right Cart & Actions -->
        <div class="flex items-center gap-3">
          <button 
            @click="store.activeTab = 'reorder'"
            class="hidden lg:flex items-center gap-1.5 text-xs font-bold text-zinc-800 hover:text-[#E52E04] px-3 py-2 border border-zinc-300 hover:border-zinc-950 rounded-none transition-colors cursor-pointer"
          >
            <RefreshCw class="w-4 h-4 text-zinc-600" />
            <span>Reorder</span>
          </button>

          <button 
            @click="store.activeTab = 'account'"
            class="hidden sm:flex items-center gap-2 text-xs font-bold text-zinc-900 px-3 py-2 border border-zinc-300 hover:border-zinc-950 rounded-none transition-colors cursor-pointer"
          >
            <User class="w-4 h-4 text-zinc-600" />
            <span>Account</span>
          </button>

          <!-- Shopping Bag Button (Exact sharp border icon from image) -->
          <button 
            @click="store.isCartDrawerOpen = true"
            class="relative p-2.5 border-2 border-zinc-900 bg-white text-zinc-900 hover:bg-zinc-100 transition-transform active:scale-95 cursor-pointer rounded-none"
            aria-label="View Shopping Cart"
          >
            <ShoppingBag class="w-5 h-5 stroke-[2.2]" />
            
            <span 
              v-if="store.totalItemCount > 0"
              class="absolute -top-2 -right-2 bg-[#E52E04] text-white text-[11px] font-black w-5 h-5 rounded-none flex items-center justify-center border border-white animate-pop"
            >
              {{ store.totalItemCount }}
            </span>
          </button>
        </div>
      </div>

      <!-- Desktop Sharp Navigation Links -->
      <nav class="hidden md:flex items-center gap-0 border-t border-zinc-200 text-xs font-bold text-zinc-800">
        <button 
          v-for="cat in categoryLinks" 
          :key="cat.id"
          @click="store.selectedCategory = cat.id; store.activeTab = 'shop'"
          :class="[
            'px-4 py-2.5 border-b-2 transition-all cursor-pointer rounded-none uppercase tracking-wider',
            store.selectedCategory === cat.id && store.activeTab === 'shop'
              ? 'border-[#E52E04] text-[#E52E04] bg-orange-50/50 font-black' 
              : 'border-transparent hover:border-zinc-400 hover:bg-zinc-50'
          ]"
        >
          {{ cat.label }}
        </button>
      </nav>
    </div>

    <!-- Store Selector Modal -->
    <div 
      v-if="isStoreModalOpen"
      class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4"
    >
      <div class="bg-white w-full max-w-md rounded-none border-2 border-zinc-950 p-6 shadow-2xl">
        <div class="flex items-center justify-between pb-3 border-b-2 border-zinc-950">
          <div>
            <h3 class="text-lg font-black text-zinc-950 uppercase tracking-tight">Select Store Location</h3>
            <p class="text-xs text-zinc-600">Choose your preferred pickup store</p>
          </div>
          <button @click="isStoreModalOpen = false" class="p-1 text-zinc-400 hover:text-zinc-950 rounded-none">
            <X class="w-5 h-5" />
          </button>
        </div>

        <div class="mt-4 space-y-2">
          <div 
            v-for="st in storesList" 
            :key="st.name"
            @click="setStore(st)"
            :class="[
              'p-3.5 rounded-none border-2 flex items-center justify-between cursor-pointer transition-all',
              store.selectedStore === st.name ? 'border-[#E52E04] bg-orange-50' : 'border-zinc-300 hover:border-zinc-800'
            ]"
          >
            <div class="flex items-start gap-3">
              <MapPin class="w-5 h-5 text-[#E52E04] shrink-0 mt-0.5" />
              <div>
                <div class="font-black text-sm text-zinc-950">{{ st.name }}</div>
                <div class="text-xs text-zinc-600">{{ st.address }}</div>
                <div class="flex items-center gap-1.5 mt-1 text-[11px] font-bold">
                  <Clock class="w-3 h-3 text-emerald-600" />
                  <span class="text-emerald-700">{{ st.ready }}</span>
                  <span>•</span>
                  <span class="text-zinc-500">{{ st.distance }}</span>
                </div>
              </div>
            </div>
            <div v-if="store.selectedStore === st.name" class="w-3 h-3 bg-[#E52E04] rounded-none"></div>
          </div>
        </div>

        <button 
          @click="isStoreModalOpen = false"
          class="w-full mt-5 py-3 bg-zinc-950 text-white font-black text-xs uppercase tracking-wider rounded-none hover:bg-zinc-800 cursor-pointer"
        >
          Confirm Store Location
        </button>
      </div>
    </div>
  </header>
</template>
