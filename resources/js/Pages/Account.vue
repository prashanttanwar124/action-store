<script setup>
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import StoreLayout from '../Layouts/StoreLayout.vue';
import { useStore } from '../stores/cart';
import { 
  Sparkles, 
  RefreshCw, 
  RotateCcw, 
  ChevronRight, 
  CheckCircle2, 
  SkipForward 
} from 'lucide-vue-next';

const store = useStore();
const reorderedNotification = ref(false);

function reorderPastOrder() {
  store.reorderAll();
  reorderedNotification.value = true;
  setTimeout(() => {
    reorderedNotification.value = false;
  }, 2500);
}
</script>

<template>
  <Head title="Customer Dashboard — Masala Mart" />

  <StoreLayout :showHeader="false" :showFooter="false" :showBottomNav="true" :showCartBar="false">
    <div class="space-y-6 sm:space-y-8 max-w-xl mx-auto pb-24 pt-2 sm:pt-4">

      <!-- Toast Feedback -->
      <div 
        v-if="reorderedNotification"
        class="fixed top-16 left-4 right-4 sm:left-auto sm:right-6 sm:top-20 z-50 bg-[#191514] text-white p-3.5 rounded-2xl shadow-2xl flex items-center justify-between text-xs font-semibold"
      >
        <div class="flex items-center gap-2">
          <CheckCircle2 class="w-4 h-4 text-[#F59E0B]" />
          <span>Reordered all 6 past items into your cart!</span>
        </div>
        <Link href="/cart" class="underline text-[#F59E0B] font-semibold inline-flex items-center gap-1">
          <span>View Cart</span>
          <ChevronRight class="w-3.5 h-3.5" />
        </Link>
      </div>

      <!-- Account Header Profile (Screen 1f: "Hi, Priya") -->
      <div class="flex items-center justify-between pt-1">
        <div>
          <h1 class="text-2xl sm:text-3xl font-serif font-medium text-[#1d1d1f] tracking-tight">Hi, Priya</h1>
        </div>

        <Link 
          href="/cart" 
          class="relative w-10 h-10 rounded-2xl bg-white border border-[#e0d9cc] flex items-center justify-center text-[#1d1d1f] hover:bg-[#f3efe7] transition-colors shadow-xs"
          aria-label="Cart"
        >
          <ShoppingBag class="w-5 h-5 stroke-[2]" />
          <span 
            class="absolute -top-1.5 -right-1.5 bg-[#1a1a1a] text-white text-[11px] font-bold w-5 h-5 rounded-full flex items-center justify-center border-2 border-white shadow-xs"
          >
            {{ store.totalItemCount > 0 ? store.totalItemCount : 8 }}
          </span>
        </Link>
      </div>

      <!-- SECTION 1: Masala Points Reward Meter (Screen 1f Exact Match) -->
      <div class="bg-[#1a1a1a] text-white p-5 sm:p-6 rounded-3xl shadow-sm space-y-3.5 border border-[#e0d9cc]/20">
        <div class="flex items-center justify-between">
          <span class="text-[10px] font-semibold uppercase tracking-wider text-[#a47a3c]">
            MASALA REWARDS
          </span>
          <span class="text-xs text-stone-300 font-normal">
            Gold Member
          </span>
        </div>

        <div class="flex items-baseline">
          <span class="text-3xl sm:text-4xl font-serif font-medium tracking-tight text-white leading-none">
            {{ store.masalaPoints.toLocaleString() }}
          </span>
          <span class="text-sm text-stone-400 font-normal ml-1.5">pts</span>
        </div>

        <!-- 10-Segment Progress Meter -->
        <div class="grid grid-cols-10 gap-1.5 pt-1">
          <div 
            v-for="i in 10" 
            :key="i"
            :class="[
              'h-2 rounded-full transition-colors',
              i <= 7 ? 'bg-[#a47a3c]' : 'bg-stone-800'
            ]"
          ></div>
        </div>

        <div class="flex justify-between text-xs text-stone-400 font-normal pt-0.5">
          <span>260 pts to a $10 reward</span>
          <span>1 pt per $1</span>
        </div>
      </div>

      <!-- SECTION 2: Subscriptions Management (Screen 1f Exact Match) -->
      <div class="space-y-4">
        <div class="flex items-center justify-between border-b border-[#e0d9cc]/60 pb-2">
          <div class="flex items-center gap-2">
            <h2 class="text-lg sm:text-xl font-serif font-medium text-[#1d1d1f] tracking-tight">Subscriptions</h2>
            <span class="font-devanagari text-xs text-[#7a5620] bg-[#f5eee2] px-2 py-0.5 rounded-full border border-[#e0d9cc]">फिर से</span>
          </div>
          <span class="text-xs font-normal text-[#6e6e73]">
            {{ store.subscriptions.filter(s => s.status === 'active').length }} active
          </span>
        </div>

        <div class="space-y-4">
          <!-- Item 1: Chakki Atta · 20 lb (Active) -->
          <div class="p-4 sm:p-5 rounded-3xl border border-[#e0d9cc] bg-white shadow-xs space-y-3">
            <div class="flex items-start justify-between gap-3">
              <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-[#f3efe7] overflow-hidden shrink-0 border border-[#e0d9cc]">
                  <img src="/images/products/atta.jpg" alt="Chakki Atta" class="w-full h-full object-cover" />
                </div>
                <div>
                  <h3 class="font-bold text-sm text-[#1d1d1f]">Chakki Atta · 20 lb</h3>
                  <div class="text-xs text-[#6e6e73] font-normal mt-0.5">Monthly · $18.04</div>
                </div>
              </div>
              <span class="text-[11px] font-semibold text-[#7a5620] bg-[#f5eee2] border border-[#e0d9cc] px-2.5 py-0.5 rounded-full shrink-0">
                Next Oct 3
              </span>
            </div>

            <!-- Action Pill Buttons (Screen 1f) -->
            <div class="flex items-center gap-2 pt-1 pl-[60px]">
              <button 
                @click="store.skipSubscription('sub-1')"
                class="px-4 py-1.5 border border-[#e0d9cc] bg-white hover:bg-[#f3efe7] rounded-full text-xs font-semibold text-[#1d1d1f] transition-colors cursor-pointer flex items-center gap-1.5"
              >
                <SkipForward class="w-3.5 h-3.5 text-[#6e6e73]" />
                <span>Skip next</span>
              </button>
              <button 
                @click="store.toggleSubscription('sub-1')"
                class="px-4 py-1.5 border border-[#e0d9cc] bg-white hover:bg-[#f3efe7] rounded-full text-xs font-semibold text-[#1d1d1f] transition-colors cursor-pointer"
              >
                Pause
              </button>
            </div>
          </div>

          <!-- Item 2: Toor Dal · 4 lb (Paused) -->
          <div class="p-4 sm:p-5 rounded-3xl border border-[#e0d9cc] bg-white shadow-xs space-y-3">
            <div class="flex items-start justify-between gap-3">
              <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-[#f3efe7] overflow-hidden shrink-0 border border-[#e0d9cc]">
                  <img src="/images/products/toor_dal.jpg" alt="Toor Dal" class="w-full h-full object-cover" />
                </div>
                <div>
                  <h3 class="font-bold text-sm text-[#1d1d1f]">Toor Dal · 4 lb</h3>
                  <div class="text-xs text-[#6e6e73] font-normal mt-0.5">Every 2 weeks · $7.59</div>
                </div>
              </div>
              <span class="text-[11px] font-normal text-[#86868b] bg-[#ece7de] px-2.5 py-0.5 rounded-full shrink-0">
                Paused
              </span>
            </div>

            <!-- Action Pill Buttons (Screen 1f) -->
            <div class="flex items-center gap-2 pt-1 pl-[60px]">
              <button 
                class="px-4 py-1.5 border border-[#e0d9cc] bg-white hover:bg-stone-50 rounded-full text-xs font-semibold text-[#86868b] transition-colors cursor-not-allowed flex items-center gap-1.5 opacity-60"
              >
                <SkipForward class="w-3.5 h-3.5 text-[#86868b]" />
                <span>Skip next</span>
              </button>
              <button 
                @click="store.toggleSubscription('sub-2')"
                class="px-4 py-1.5 border border-[#e0d9cc] bg-white hover:bg-[#f3efe7] rounded-full text-xs font-semibold text-[#1d1d1f] transition-colors cursor-pointer"
              >
                Resume
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- SECTION 3: Past Orders (Screen 1f Exact Match) -->
      <div class="space-y-4">
        <div class="flex items-center justify-between border-b border-[#e0d9cc]/60 pb-2">
          <div class="flex items-center gap-2">
            <h2 class="text-lg sm:text-xl font-serif font-medium text-[#1d1d1f] tracking-tight">Past orders</h2>
            <span class="font-devanagari text-xs text-[#7a5620] bg-[#f5eee2] px-2 py-0.5 rounded-full border border-[#e0d9cc]">पिछला ऑर्डर</span>
          </div>
        </div>

        <div class="rounded-3xl border border-[#e0d9cc] bg-white p-5 space-y-4 shadow-xs">
          <div class="flex items-center justify-between">
            <span class="font-bold text-sm text-[#1d1d1f]">Sep 18 · Picked up</span>
            <span class="font-serif font-medium text-base text-[#1d1d1f]">$62.40</span>
          </div>

          <div class="text-xs text-[#6e6e73] font-normal leading-relaxed">
            #10482 · Chakki Atta, Desi Ghee, Okra (Bhindi), Curry Leaves, Glucose Biscuits, Masala Noodles
          </div>

          <!-- Product Thumbnails Row (Screen 1f) -->
          <div class="flex items-center gap-2 overflow-x-auto no-scrollbar py-1">
            <div class="w-10 h-10 rounded-2xl bg-[#f3efe7] overflow-hidden shrink-0 border border-[#e0d9cc]">
              <img src="/images/products/atta.jpg" alt="Atta" class="w-full h-full object-cover" />
            </div>
            <div class="w-10 h-10 rounded-2xl bg-[#f3efe7] overflow-hidden shrink-0 border border-[#e0d9cc]">
              <img src="/images/products/ghee.jpg" alt="Ghee" class="w-full h-full object-cover" />
            </div>
            <div class="w-10 h-10 rounded-2xl bg-[#f3efe7] overflow-hidden shrink-0 border border-[#e0d9cc]">
              <img src="/images/products/okra.jpg" alt="Okra (Bhindi)" class="w-full h-full object-cover" />
            </div>
            <div class="w-10 h-10 rounded-2xl bg-[#f3efe7] overflow-hidden shrink-0 border border-[#e0d9cc]">
              <img src="/images/products/curry_leaves.jpg" alt="Curry Leaves" class="w-full h-full object-cover" />
            </div>
            <div class="w-10 h-10 rounded-2xl bg-[#f3efe7] overflow-hidden shrink-0 border border-[#e0d9cc]">
              <img src="/images/products/biscuits.jpg" alt="Biscuits" class="w-full h-full object-cover" />
            </div>
            <div class="w-10 h-10 rounded-2xl bg-[#f3efe7] overflow-hidden shrink-0 border border-[#e0d9cc]">
              <img src="/images/products/garam_masala.jpg" alt="Spices" class="w-full h-full object-cover" />
            </div>
          </div>

          <!-- Reorder All 6 Items Button (Primary #1a1a1a) -->
          <div class="pt-1">
            <button 
              @click="reorderPastOrder"
              class="w-full h-12 bg-[#1a1a1a] hover:bg-black text-white font-semibold text-sm rounded-full transition-colors flex items-center justify-center gap-2 cursor-pointer shadow-sm active:scale-[0.99]"
            >
              <RotateCcw class="w-4 h-4 stroke-[2.2]" />
              <span>Reorder all 6 items</span>
            </button>
          </div>
        </div>
      </div>

    </div>
  </StoreLayout>
</template>
