<script setup>
import { useStore } from '../stores/cart';
import { ShoppingBag, ChevronRight, Sparkles } from 'lucide-vue-next';

const store = useStore();
</script>

<template>
  <div 
    v-if="store.totalItemCount > 0"
    class="fixed bottom-0 left-0 right-0 z-40 px-0 sm:px-4 pb-0 sm:pb-3 pointer-events-none"
  >
    <!-- Floating Sharp Cart Banner -->
    <div 
      @click="store.isCartDrawerOpen = true"
      class="max-w-xl mx-auto bg-[#18181B] text-white rounded-none shadow-2xl overflow-hidden cursor-pointer hover:bg-zinc-900 transition-all pointer-events-auto active:scale-[0.99] border-t-2 sm:border-2 border-zinc-950"
    >
      <div class="px-5 py-3.5 flex items-center justify-between">
        <!-- Left Side: Count & Free Delivery info -->
        <div class="flex items-center gap-3.5">
          <div class="w-10 h-10 rounded-none bg-[#E52E04] text-white flex items-center justify-center font-bold shrink-0 border border-white/20">
            <ShoppingBag class="w-5 h-5 stroke-[2.2]" />
          </div>
          
          <div>
            <div class="text-base font-black tracking-tight flex items-center gap-1.5">
              <span>View cart</span>
              <span>·</span>
              <span>{{ store.totalItemCount }} {{ store.totalItemCount === 1 ? 'item' : 'items' }}</span>
              <ChevronRight class="w-4 h-4 text-zinc-400 inline" />
            </div>

            <div class="text-xs text-zinc-400 font-medium flex items-center gap-1 mt-0.5">
              <template v-if="store.amountToFreeDelivery > 0">
                <span>${{ store.amountToFreeDelivery.toFixed(2) }} to free delivery</span>
              </template>
              <template v-else>
                <Sparkles class="w-3.5 h-3.5 text-amber-400" />
                <span class="text-amber-400 font-bold">Free delivery unlocked!</span>
              </template>
            </div>
          </div>
        </div>

        <!-- Right Side: Total Price -->
        <div class="text-right">
          <div class="text-xl font-black text-white tracking-tight">
            ${{ store.subtotal.toFixed(2) }}
          </div>
        </div>
      </div>

      <!-- Red Accent Progress Bar -->
      <div class="w-full bg-zinc-800 h-1.5 relative overflow-hidden">
        <div 
          class="bg-[#E52E04] h-full transition-all duration-300 ease-out"
          :style="{ width: store.freeDeliveryProgressPercent + '%' }"
        ></div>
      </div>
    </div>
  </div>
</template>
