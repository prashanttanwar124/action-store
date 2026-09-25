<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useStore } from '../stores/cart';
import { Home, Search, RefreshCw, User, ShoppingBag, ChevronRight, Sparkles } from 'lucide-vue-next';

const props = defineProps({
  showBottomNav: {
    type: Boolean,
    default: true,
  },
  showCartBar: {
    type: Boolean,
    default: true,
  },
});

const store = useStore();
const page = usePage();

const currentRoute = computed(() => {
  return page.url;
});

const navItems = [
  { id: 'shop', label: 'Shop', href: '/', icon: Home, isActive: currentRoute.value === '/' || currentRoute.value.startsWith('/products') },
  { id: 'search', label: 'Search', href: '/search', icon: Search, isActive: currentRoute.value === '/search' },
  { id: 'reorder', label: 'Reorder', href: '/reorder', icon: RefreshCw, isActive: currentRoute.value === '/reorder' },
  { id: 'account', label: 'Account', href: '/account', icon: User, isActive: currentRoute.value === '/account' },
];
</script>

<template>
  <!-- Full Screen Outer Stage matching PDF Presentation -->
  <div class="min-h-screen bg-[#EAEAEA] flex justify-center items-start sm:py-6 selection:bg-[#E52E04] selection:text-white">
    
    <!-- Mobile Web Frame: 420px max width with sharp borders -->
    <div class="w-full max-w-[420px] bg-white min-h-screen sm:min-h-[860px] border-x border-zinc-300 shadow-2xl flex flex-col relative rounded-none overflow-x-hidden">
      
      <!-- Top Mobile System Status Bar (9:41 · 5G) -->
      <div class="px-5 pt-3 pb-2 flex justify-between items-center text-xs font-bold text-zinc-950 select-none bg-white z-40 border-b border-zinc-100">
        <span>9:41</span>
        <div class="flex items-center gap-1.5 text-zinc-950">
          <span class="text-[10px] tracking-widest font-black">5G</span>
          <div class="flex items-end gap-0.5 h-3">
            <span class="w-1 h-1.5 bg-zinc-950"></span>
            <span class="w-1 h-2.5 bg-zinc-950"></span>
            <span class="w-1 h-3 bg-zinc-950"></span>
          </div>
        </div>
      </div>

      <!-- Main Slot Content -->
      <div class="flex-1 flex flex-col pb-24">
        <slot />
      </div>

      <!-- Floating Dark Cart Bar (Matching Screen 1a & 1b) -->
      <div 
        v-if="showCartBar && store.totalItemCount > 0"
        class="fixed bottom-[62px] left-0 right-0 z-40 px-2.5 max-w-[420px] mx-auto pointer-events-none"
      >
        <Link 
          href="/cart"
          class="w-full block bg-[#18181B] text-white rounded-none border border-zinc-800 shadow-2xl cursor-pointer hover:bg-zinc-900 transition-all pointer-events-auto active:scale-[0.99]"
        >
          <div class="px-3.5 py-2.5 flex items-center justify-between">
            <!-- Left Info -->
            <div class="flex items-center gap-2.5">
              <div>
                <div class="text-sm font-black tracking-tight flex items-center gap-1">
                  <span>View cart</span>
                  <span>·</span>
                  <span>{{ store.totalItemCount }} items</span>
                </div>

                <div class="text-[11px] text-zinc-400 font-medium flex items-center gap-1 mt-0.5 font-mono">
                  <template v-if="store.amountToFreeDelivery > 0">
                    <span>${{ store.amountToFreeDelivery.toFixed(2) }} to free delivery</span>
                  </template>
                  <template v-else>
                    <Sparkles class="w-3 h-3 text-amber-400" />
                    <span class="text-amber-400 font-bold">Free delivery unlocked!</span>
                  </template>
                </div>
              </div>
            </div>

            <!-- Right Price -->
            <div class="text-right">
              <div class="text-lg font-black text-white tracking-tight">
                ${{ store.subtotal.toFixed(2) }}
              </div>
            </div>
          </div>

          <!-- Red Progress Meter underneath -->
          <div class="w-full bg-zinc-800 h-1 relative overflow-hidden">
            <div 
              class="bg-[#E52E04] h-full transition-all duration-300 ease-out"
              :style="{ width: store.freeDeliveryProgressPercent + '%' }"
            ></div>
          </div>
        </Link>
      </div>

      <!-- Sticky Mobile Bottom Navigation (Shop, Search, Reorder, Account) -->
      <nav 
        v-if="showBottomNav"
        class="fixed bottom-0 left-0 right-0 z-40 max-w-[420px] mx-auto bg-white border-t border-zinc-200"
      >
        <div class="flex items-center justify-around h-[62px] px-2">
          <Link
            v-for="item in navItems"
            :key="item.id"
            :href="item.href"
            :class="[
              'flex flex-col items-center justify-center w-full h-full py-1 transition-colors select-none rounded-none',
              item.isActive ? 'text-[#E52E04]' : 'text-zinc-600 hover:text-zinc-950'
            ]"
          >
            <component 
              :is="item.icon" 
              :class="[
                'w-5 h-5 transition-transform',
                item.isActive ? 'scale-110 stroke-[2.4]' : 'stroke-[1.8]'
              ]" 
            />
            <span 
              :class="[
                'text-[11px] mt-1 tracking-tight',
                item.isActive ? 'font-black' : 'font-medium'
              ]"
            >
              {{ item.label }}
            </span>
          </Link>
        </div>
      </nav>

    </div>

  </div>
</template>
