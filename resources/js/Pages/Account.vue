<script setup>
import { Head, Link } from '@inertiajs/vue3';
import MobileWebLayout from '../Layouts/MobileWebLayout.vue';
import { useStore } from '../stores/cart';
import { ShoppingBag, RefreshCw, SkipForward, Pause, Play, Check } from 'lucide-vue-next';

const store = useStore();
</script>

<template>
  <Head title="Customer Dashboard — Masala Mart" />

  <MobileWebLayout :showBottomNav="true" :showCartBar="false">
    
    <!-- Top Header: Greeting & Cart Button (Page 7: 1f) -->
    <div class="px-4 pt-3 pb-2 flex items-center justify-between bg-white">
      <h1 class="text-2xl font-black tracking-tight text-zinc-950">
        Hi, Priya
      </h1>

      <Link 
        href="/cart"
        class="relative p-2 border-2 border-zinc-950 bg-white text-zinc-950 hover:bg-zinc-100 rounded-none cursor-pointer"
        aria-label="View Shopping Cart"
      >
        <ShoppingBag class="w-5 h-5 stroke-[2.2]" />
        <span 
          v-if="store.totalItemCount > 0"
          class="absolute -top-1.5 -right-1.5 bg-[#E52E04] text-white text-[11px] font-black w-5 h-5 rounded-none flex items-center justify-center border border-white animate-pop"
        >
          {{ store.totalItemCount }}
        </span>
      </Link>
    </div>

    <!-- Main Account Dashboard Content -->
    <div class="px-4 py-3 space-y-6">

      <!-- MASALA POINTS Box (Exact match to Page 7: 1f) -->
      <div class="bg-zinc-950 text-white p-4 rounded-none border border-zinc-800 space-y-3">
        <div class="flex justify-between items-center text-[10px] font-mono">
          <span class="font-bold tracking-wider uppercase text-zinc-400">MASALA POINTS</span>
          <span class="text-zinc-400">Gold member</span>
        </div>

        <div class="text-4xl font-black tracking-tight leading-none">
          1,240 <span class="text-sm font-normal text-zinc-400 font-mono">pts</span>
        </div>

        <!-- Segmented Red Meter Bar (12 blocks) -->
        <div class="grid grid-cols-12 gap-1 h-3 py-0.5">
          <span v-for="i in 10" :key="i" class="bg-[#E52E04] h-full"></span>
          <span v-for="i in 2" :key="`empty-${i}`" class="bg-zinc-800 h-full"></span>
        </div>

        <div class="flex justify-between items-center text-[10px] font-mono text-zinc-400 pt-0.5">
          <span>260 pts to a $10 reward</span>
          <span>1 pt per $1</span>
        </div>
      </div>

      <!-- Subscriptions Section (Page 7: 1f) -->
      <div>
        <div class="flex items-center justify-between mb-2.5">
          <h2 class="text-lg font-black text-zinc-950 tracking-tight">Subscriptions</h2>
          <span class="text-xs font-mono text-zinc-500">1 active</span>
        </div>

        <div class="space-y-3">
          <!-- Subscription Item 1: Atta -->
          <div class="p-3 border-2 border-zinc-300 bg-white rounded-none space-y-2.5">
            <div class="flex items-start justify-between gap-3">
              <div class="flex items-center gap-2.5">
                <div class="w-10 h-10 bg-[#EAEAEA] border border-zinc-300 relative overflow-hidden shrink-0">
                  <div class="absolute inset-0 bg-[linear-gradient(45deg,rgba(0,0,0,0.06)_25%,transparent_25%,transparent_50%,rgba(0,0,0,0.06)_50%,rgba(0,0,0,0.06)_75%,transparent_75%,transparent)] bg-[length:6px_6px]"></div>
                </div>
                <div>
                  <div class="font-black text-xs text-zinc-950">Chakki Atta · 20 lb</div>
                  <div class="text-[11px] font-mono text-zinc-500">Monthly · $18.04</div>
                </div>
              </div>

              <div class="text-[11px] font-mono font-bold text-zinc-900">
                Next Oct 3
              </div>
            </div>

            <!-- Action buttons -->
            <div class="flex gap-2 pt-1 border-t border-zinc-200">
              <button 
                @click="store.skipSubscription('sub-1')"
                class="px-3 py-1.5 border border-zinc-400 text-zinc-900 hover:border-zinc-950 text-xs font-bold rounded-none cursor-pointer"
              >
                ▷| Skip next
              </button>
              <button 
                @click="store.toggleSubscription('sub-1')"
                class="px-3 py-1.5 border border-zinc-400 text-zinc-900 hover:border-zinc-950 text-xs font-bold rounded-none cursor-pointer"
              >
                Pause
              </button>
            </div>
          </div>

          <!-- Subscription Item 2: Toor Dal -->
          <div class="p-3 border border-zinc-300 bg-zinc-50 rounded-none space-y-2.5">
            <div class="flex items-start justify-between gap-3">
              <div class="flex items-center gap-2.5">
                <div class="w-10 h-10 bg-[#EAEAEA] border border-zinc-300 relative overflow-hidden shrink-0">
                  <div class="absolute inset-0 bg-[linear-gradient(45deg,rgba(0,0,0,0.06)_25%,transparent_25%,transparent_50%,rgba(0,0,0,0.06)_50%,rgba(0,0,0,0.06)_75%,transparent_75%,transparent)] bg-[length:6px_6px]"></div>
                </div>
                <div>
                  <div class="font-black text-xs text-zinc-950">Toor Dal · 4 lb</div>
                  <div class="text-[11px] font-mono text-zinc-500">Every 2 weeks · $7.59</div>
                </div>
              </div>

              <div class="text-[11px] font-mono font-bold text-zinc-500">
                Paused
              </div>
            </div>

            <div class="flex gap-2 pt-1 border-t border-zinc-200">
              <button 
                @click="store.toggleSubscription('sub-2')"
                class="px-3 py-1.5 border border-zinc-400 text-zinc-900 hover:border-zinc-950 text-xs font-bold rounded-none cursor-pointer"
              >
                Resume
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Past Orders Section (Page 7: 1f) -->
      <div>
        <h2 class="text-lg font-black text-zinc-950 tracking-tight mb-2.5">
          Past orders
        </h2>

        <div class="space-y-3">
          <!-- Order 1: Sep 18 -->
          <div class="p-3 border-2 border-zinc-300 bg-white rounded-none space-y-2.5">
            <div class="flex justify-between items-baseline">
              <span class="font-black text-xs text-zinc-950">Sep 18 · Picked up</span>
              <span class="font-black text-sm text-zinc-950 font-mono">$62.40</span>
            </div>

            <div class="text-[11px] text-zinc-600 leading-snug">
              #10482 · Chakki Atta, Desi Ghee, Okra (Bhindi), Curry Leaves, Glucose Biscuits, Masala Noodles
            </div>

            <!-- Item Thumbnails row -->
            <div class="flex gap-1.5">
              <div v-for="i in 6" :key="i" class="w-8 h-8 bg-[#EAEAEA] border border-zinc-300 relative overflow-hidden">
                <div class="absolute inset-0 bg-[linear-gradient(45deg,rgba(0,0,0,0.05)_25%,transparent_25%,transparent_50%,rgba(0,0,0,0.05)_50%,rgba(0,0,0,0.05)_75%,transparent_75%,transparent)] bg-[length:5px_5px]"></div>
              </div>
            </div>

            <!-- Reorder All 6 Items Button (Drops entire past order into live cart) -->
            <button 
              @click="store.reorderAll('#10482')"
              class="w-full py-2 bg-[#E52E04] hover:bg-[#CC2500] text-white font-black text-xs uppercase tracking-wider rounded-none flex items-center justify-center gap-1.5 transition-colors cursor-pointer border border-[#B82200]"
            >
              <RefreshCw class="w-3.5 h-3.5" />
              <span>Reorder all 6 items</span>
            </button>
          </div>

          <!-- Order 2: Sep 4 -->
          <div class="p-3 border border-zinc-300 bg-zinc-50 rounded-none space-y-2">
            <div class="flex justify-between items-baseline">
              <span class="font-black text-xs text-zinc-950">Sep 4 · Delivered</span>
              <span class="font-black text-sm text-zinc-950 font-mono">$58.15</span>
            </div>

            <div class="text-[11px] text-zinc-600 leading-snug">
              #10391 · Aged Basmati Rice, Toor Dal, Fresh Coriander, Ghee
            </div>

            <button 
              @click="store.reorderAll('#10391')"
              class="w-full py-2 border border-zinc-950 bg-white text-zinc-950 font-bold text-xs uppercase rounded-none hover:bg-zinc-100 transition-colors cursor-pointer flex items-center justify-center gap-1"
            >
              <RefreshCw class="w-3 h-3" />
              <span>Reorder items</span>
            </button>
          </div>
        </div>
      </div>

    </div>

  </MobileWebLayout>
</template>
