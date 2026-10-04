<script setup>
import { Link } from '@inertiajs/vue3';
import { useStore } from '../../stores/cart';
import { ShoppingBag, ChevronRight } from 'lucide-vue-next';

defineProps({
  storeInfo: {
    type: Object,
    default: () => ({}),
  },
});

const store = useStore();
</script>

<template>
  <div 
    v-if="store.totalItemCount > 0"
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
              <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="storeInfo?.is_busy ? 'bg-amber-400 animate-pulse' : 'bg-emerald-400'"></span>
              <span v-if="storeInfo?.is_delivery_active">
                Pickup ({{ storeInfo?.effective_pickup_time || '15 mins' }}) · Delivery Available
              </span>
              <span v-else>
                Free Store Pickup · {{ storeInfo?.effective_pickup_time || storeInfo?.pickup_time || 'Ready in 15 mins' }}
              </span>
            </span>
          </div>
        </div>

        <!-- Right: Arrow Button -->
        <div class="flex items-center gap-1 text-[11px] font-bold text-stone-200 bg-white/10 group-hover:bg-white/20 group-hover:text-white px-2.5 py-1.5 rounded-xl border border-white/10 shrink-0 transition-colors">
          <span>Checkout</span>
          <ChevronRight class="w-3.5 h-3.5 stroke-[2.5]" />
        </div>
      </div>

      <!-- Sleek Hairline 2px Brass Accent Line at the very bottom edge -->
      <div class="w-full bg-[#a47a3c]/30 h-0.5 relative overflow-hidden">
        <div class="bg-[#a47a3c] h-full w-full"></div>
      </div>
    </Link>
  </div>
</template>
