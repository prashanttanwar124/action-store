<script setup>
import { Head, Link } from '@inertiajs/vue3';
import MobileWebLayout from '../Layouts/MobileWebLayout.vue';
import { useStore } from '../stores/cart';
import { X, Plus, Minus, Truck, RefreshCw, ShoppingBag, ArrowRight } from 'lucide-vue-next';

const store = useStore();
</script>

<template>
  <Head title="Your Cart — Masala Mart" />

  <MobileWebLayout :showBottomNav="false" :showCartBar="false">
    
    <!-- Top Bar: Title & Close Button (Page 5: 1d) -->
    <div class="px-4 py-3 bg-white border-b border-zinc-200 flex items-center justify-between sticky top-[41px] z-30">
      <h1 class="text-xl font-black text-zinc-950 tracking-tight flex items-baseline gap-2">
        <span>Your cart</span>
        <span class="text-xs font-normal text-zinc-500 font-mono">{{ store.totalItemCount }} items</span>
      </h1>

      <Link 
        href="/"
        class="w-8 h-8 border border-zinc-950 bg-white flex items-center justify-center text-zinc-950 hover:bg-zinc-100 rounded-none cursor-pointer"
        aria-label="Close cart"
      >
        <X class="w-4 h-4 stroke-[2.5]" />
      </Link>
    </div>

    <!-- Main Cart Content -->
    <div class="p-4 space-y-4">
      
      <!-- Black Delivery Meter Box (Exact match to Page 5: 1d) -->
      <div class="bg-zinc-950 text-white p-3.5 rounded-none border border-zinc-800 space-y-2.5">
        <div class="flex items-start gap-2.5">
          <Truck class="w-4 h-4 text-[#E52E04] shrink-0 mt-0.5" />
          <div class="text-xs font-bold text-white leading-tight">
            <template v-if="store.amountToFreeDelivery > 0">
              Add ${{ store.amountToFreeDelivery.toFixed(2) }} more to unlock free delivery
            </template>
            <template v-else>
              🎉 You unlocked FREE Delivery & Store Pickup!
            </template>
          </div>
        </div>

        <!-- Red Progress Meter -->
        <div class="w-full bg-zinc-800 h-1.5 relative overflow-hidden">
          <div 
            class="bg-[#E52E04] h-full transition-all duration-300"
            :style="{ width: store.freeDeliveryProgressPercent + '%' }"
          ></div>
        </div>

        <div class="flex justify-between text-[11px] font-mono text-zinc-400">
          <span>${{ store.subtotal.toFixed(2) }}</span>
          <span>$40.00 · free delivery</span>
        </div>
      </div>

      <!-- Items List -->
      <div v-if="store.cartItems.length > 0" class="divide-y divide-zinc-200 border-y border-zinc-200">
        <div 
          v-for="item in store.cartItems"
          :key="item.id"
          class="py-3.5 flex items-start justify-between gap-3"
        >
          <!-- Left Thumbnail / Placeholder -->
          <div class="w-14 h-14 bg-[#EAEAEA] border border-zinc-300 relative overflow-hidden shrink-0 p-1 flex flex-col justify-end">
            <div class="absolute inset-0 bg-[linear-gradient(45deg,rgba(0,0,0,0.05)_25%,transparent_25%,transparent_50%,rgba(0,0,0,0.05)_50%,rgba(0,0,0,0.05)_75%,transparent_75%,transparent)] bg-[length:6px_6px] pointer-events-none"></div>
            <span class="relative z-10 text-[8px] font-mono text-zinc-600 truncate">{{ item.label || 'item' }}</span>
          </div>

          <!-- Center Details -->
          <div class="flex-1 min-w-0">
            <div class="font-black text-sm text-zinc-950 truncate">{{ item.name }}</div>
            <div class="text-xs font-mono text-zinc-500 mt-0.5">{{ item.size }}</div>

            <!-- Subscribe Tag if applicable -->
            <div 
              v-if="item.isSubscribed"
              class="inline-flex items-center gap-1 text-[10px] font-mono text-[#E52E04] bg-orange-50 px-1.5 py-0.5 border border-orange-200 mt-1 font-bold"
            >
              <RefreshCw class="w-2.5 h-2.5" />
              <span>Monthly · −5%</span>
            </div>
          </div>

          <!-- Right: Price & Quantity Controls -->
          <div class="text-right flex flex-col items-end gap-1.5">
            <div class="font-black text-sm text-zinc-950 font-mono">
              ${{ item.total.toFixed(2) }}
            </div>

            <!-- [ - 1 + ] in sharp bordered box -->
            <div class="flex items-center border border-zinc-950 bg-white h-7 px-1 rounded-none">
              <button 
                @click="store.removeFromCart(item.id)"
                class="w-5 h-5 flex items-center justify-center hover:bg-zinc-100 rounded-none cursor-pointer"
              >
                <Minus class="w-3 h-3 stroke-[3]" />
              </button>
              <span class="w-5 text-center font-black text-xs text-zinc-950 select-none">
                {{ item.quantity }}
              </span>
              <button 
                @click="store.addToCart(item.id)"
                class="w-5 h-5 flex items-center justify-center hover:bg-zinc-100 rounded-none cursor-pointer"
              >
                <Plus class="w-3 h-3 stroke-[3]" />
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else class="py-12 text-center text-zinc-500">
        <ShoppingBag class="w-10 h-10 mx-auto text-zinc-400 stroke-[1.5] mb-2" />
        <div class="font-bold text-sm text-zinc-800">Your cart is empty</div>
        <Link href="/" class="text-xs text-[#E52E04] font-bold underline mt-1 inline-block">
          Continue shopping
        </Link>
      </div>

      <!-- Don't Forget These Section (Page 5: 1d) -->
      <div class="pt-2">
        <h3 class="text-sm font-black text-zinc-950 tracking-tight">
          Don’t forget these
        </h3>
        <p class="text-[11px] text-zinc-500 mb-2.5">
          Under $2 · one tap to add
        </p>

        <!-- Horizontal scroll impulse items -->
        <div class="flex gap-2 overflow-x-auto no-scrollbar pb-1 -mx-4 px-4 snap-x">
          <div 
            v-for="imp in store.impulseItems" 
            :key="imp.id"
            class="w-[105px] shrink-0 snap-start bg-white border border-zinc-300 p-2 rounded-none flex flex-col justify-between"
          >
            <!-- Thumbnail with red plus button -->
            <div class="relative w-full h-16 bg-[#EAEAEA] border border-zinc-200 mb-1.5 p-1 flex flex-col justify-end">
              <div class="absolute inset-0 bg-[linear-gradient(45deg,rgba(0,0,0,0.05)_25%,transparent_25%,transparent_50%,rgba(0,0,0,0.05)_50%,rgba(0,0,0,0.05)_75%,transparent_75%,transparent)] bg-[length:6px_6px] pointer-events-none"></div>
              
              <button 
                @click="store.addToCart(imp.id)"
                class="relative z-10 self-end w-5 h-5 bg-[#E52E04] hover:bg-[#CC2500] text-white flex items-center justify-center rounded-none cursor-pointer"
              >
                <Plus class="w-3 h-3 stroke-[3]" />
              </button>
            </div>

            <div>
              <div class="font-black text-xs text-zinc-950 font-mono">${{ imp.price.toFixed(2) }}</div>
              <div class="font-bold text-[10px] text-zinc-900 leading-tight mt-0.5 truncate">{{ imp.name }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Pricing Breakdown (Page 5: 1d) -->
      <div v-if="store.cartItems.length > 0" class="pt-3 border-t border-zinc-200 space-y-1.5 text-xs">
        <div class="flex justify-between text-zinc-700">
          <span>Subtotal</span>
          <span class="font-mono font-bold">${{ store.rawSubtotal.toFixed(2) }}</span>
        </div>
        <div v-if="store.subscribeSavings > 0" class="flex justify-between text-[#E52E04] font-bold">
          <span>Subscribe & Save</span>
          <span class="font-mono">−${{ store.subscribeSavings.toFixed(2) }}</span>
        </div>
        <div class="flex justify-between text-zinc-700">
          <span>Store pickup</span>
          <span class="text-emerald-700 font-bold">Free</span>
        </div>
      </div>

    </div>

    <!-- Sticky Bottom CTA Button (Links to /checkout) -->
    <div 
      v-if="store.cartItems.length > 0"
      class="fixed bottom-0 left-0 right-0 max-w-[420px] mx-auto p-3 bg-white border-t border-zinc-300 z-40"
    >
      <Link 
        href="/checkout"
        class="w-full h-11 bg-[#E52E04] hover:bg-[#CC2500] text-white font-black text-xs uppercase tracking-wider rounded-none flex items-center justify-between px-4 transition-all cursor-pointer border border-[#B82200]"
      >
        <span>Go to checkout</span>
        <span class="font-black text-sm">${{ store.subtotal.toFixed(2) }}</span>
      </Link>
    </div>

  </MobileWebLayout>
</template>
