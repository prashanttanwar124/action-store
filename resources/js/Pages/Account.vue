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
  Check, 
  Package, 
  Calendar,
  Clock,
  ArrowRight,
  CheckCircle2
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
  <Head title="My Account & Subscriptions — Masala Mart" />

  <StoreLayout>
    <div class="space-y-6 sm:space-y-8 max-w-5xl mx-auto">

      <!-- Toast Feedback -->
      <div 
        v-if="reorderedNotification"
        class="fixed top-16 left-4 right-4 sm:left-auto sm:right-6 sm:top-20 z-50 bg-zinc-950 text-white p-3 border border-zinc-700 shadow-2xl flex items-center justify-between text-xs font-bold"
      >
        <div class="flex items-center gap-2">
          <CheckCircle2 class="w-4 h-4 text-emerald-400" />
          <span>Reordered all 6 past items into your cart!</span>
        </div>
        <Link href="/cart" class="underline text-amber-400 font-mono">
          View Cart &rsaquo;
        </Link>
      </div>

      <!-- Account Header Profile -->
      <div class="bg-white border border-zinc-300 p-4 sm:p-6 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 sm:gap-4">
        <div>
          <span class="text-[9px] sm:text-[10px] font-mono uppercase tracking-widest text-[#E52E04] font-bold">LOYALTY MEMBER</span>
          <h1 class="text-xl sm:text-3xl font-black text-zinc-950 tracking-tight">Kavya Sharma</h1>
          <div class="text-[11px] sm:text-xs text-zinc-500 font-mono mt-0.5">kavya.sharma@example.com · +1 (555) 392-1084</div>
        </div>

        <div class="flex items-center gap-2.5 w-full sm:w-auto">
          <Link 
            href="/cart" 
            class="w-full sm:w-auto text-center px-4 py-2 bg-zinc-950 text-white font-bold text-xs uppercase font-mono border border-zinc-950"
          >
            Active Cart ({{ store.totalItemCount }})
          </Link>
        </div>
      </div>

      <!-- SECTION 1: Masala Points Reward Meter (Screen 1f Specification) -->
      <div class="bg-zinc-950 text-white p-4 sm:p-6 border border-zinc-900 shadow-sm space-y-3.5 sm:space-y-4">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2">
          <div>
            <div class="text-[10px] sm:text-xs font-mono font-bold uppercase tracking-wider text-amber-400 flex items-center gap-1.5">
              <Sparkles class="w-3.5 h-3.5" />
              <span>MASALA POINTS REWARDS</span>
            </div>
            <div class="text-2xl sm:text-3xl font-black font-mono tracking-tight mt-1 text-white">
              {{ store.masalaPoints.toLocaleString() }} <span class="text-xs sm:text-base text-zinc-400 font-normal">pts</span>
            </div>
          </div>
          <div class="text-left sm:text-right text-[11px] sm:text-xs font-mono text-zinc-400">
            <span>Next Tier: 1,500 pts</span>
            <div class="text-white font-bold mt-0.5">$10 Grocery Credit Unlock</div>
          </div>
        </div>

        <!-- 12-Segment Progress Meter (Screen 1f) -->
        <div class="grid grid-cols-12 gap-1 sm:gap-1.5">
          <div 
            v-for="i in 12" 
            :key="i"
            :class="[
              'h-2 sm:h-2.5 transition-colors',
              i <= 10 ? 'bg-[#E52E04]' : 'bg-zinc-800'
            ]"
          ></div>
        </div>

        <div class="flex justify-between text-[10px] sm:text-xs font-mono text-zinc-400 pt-0.5">
          <span>260 pts to a $10 reward</span>
          <span>1 pt per $1 spent</span>
        </div>
      </div>

      <!-- SECTION 2: Subscriptions Management (Screen 1f) -->
      <div class="space-y-3 sm:space-y-4">
        <div class="flex items-center justify-between border-b border-zinc-300 pb-2">
          <div>
            <h2 class="text-lg sm:text-xl font-black text-zinc-950 tracking-tight">Subscriptions</h2>
            <p class="text-[11px] sm:text-xs text-zinc-500">Auto-replenished pantry staples with 5% off every order.</p>
          </div>
          <span class="text-[10px] sm:text-xs font-mono font-bold bg-zinc-100 border border-zinc-300 px-2 py-0.5 text-zinc-900">
            {{ store.subscriptions.filter(s => s.status === 'active').length }} active
          </span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 sm:gap-4">
          <!-- Subscription Cards -->
          <div 
            v-for="sub in store.subscriptions" 
            :key="sub.id"
            :class="[
              'p-4 sm:p-5 border-2 bg-white flex flex-col justify-between transition-colors space-y-3.5',
              sub.status === 'active' ? 'border-zinc-950' : 'border-zinc-300 bg-zinc-50 opacity-90'
            ]"
          >
            <div>
              <div class="flex items-start justify-between">
                <div>
                  <h3 class="font-black text-xs sm:text-sm text-zinc-950">{{ sub.name }}</h3>
                  <div class="text-[11px] sm:text-xs font-mono text-zinc-500 mt-0.5">{{ sub.weight }} · {{ sub.frequency }}</div>
                </div>
                <div class="text-right">
                  <span class="font-black font-mono text-xs sm:text-sm text-zinc-950">${{ sub.price.toFixed(2) }}</span>
                  <div 
                    :class="[
                      'text-[9px] sm:text-[10px] font-mono font-bold uppercase mt-0.5',
                      sub.status === 'active' ? 'text-emerald-700' : 'text-zinc-500'
                    ]"
                  >
                    {{ sub.status === 'active' ? 'Active' : 'Paused' }}
                  </div>
                </div>
              </div>

              <div class="mt-2.5 flex items-center gap-2 text-[11px] sm:text-xs font-mono text-zinc-600 bg-zinc-100 p-2 border border-zinc-200">
                <Calendar class="w-3.5 h-3.5 text-zinc-500 shrink-0" />
                <span>Next delivery: <strong>{{ sub.nextDate }}</strong></span>
              </div>
            </div>

            <!-- Subscription Action Buttons (Screen 1f: Skip next / Pause / Resume) -->
            <div class="pt-2 border-t border-zinc-200 flex gap-2">
              <template v-if="sub.status === 'active'">
                <button 
                  @click="store.skipSubscription(sub.id)"
                  class="flex-1 sm:flex-none px-3 py-1.5 border border-zinc-400 bg-white hover:bg-zinc-100 active:bg-zinc-200 text-xs font-bold text-zinc-900 transition-colors cursor-pointer"
                >
                  ▷| Skip next
                </button>
                <button 
                  @click="store.toggleSubscription(sub.id)"
                  class="flex-1 sm:flex-none px-3 py-1.5 border border-zinc-400 bg-white hover:bg-zinc-100 active:bg-zinc-200 text-xs font-bold text-zinc-900 transition-colors cursor-pointer"
                >
                  Pause
                </button>
              </template>
              <template v-else>
                <button 
                  @click="store.toggleSubscription(sub.id)"
                  class="w-full sm:w-auto px-4 py-1.5 bg-zinc-950 text-white hover:bg-zinc-800 active:bg-black text-xs font-bold transition-colors cursor-pointer"
                >
                  Resume Subscription
                </button>
              </template>
            </div>
          </div>
        </div>
      </div>

      <!-- SECTION 3: Past Orders (Screen 1f) -->
      <div class="space-y-3 sm:space-y-4">
        <div class="flex items-center justify-between border-b border-zinc-300 pb-2">
          <div>
            <h2 class="text-lg sm:text-xl font-black text-zinc-950 tracking-tight">Past orders</h2>
            <p class="text-[11px] sm:text-xs text-zinc-500">Pickups and deliveries fulfilled at your local store.</p>
          </div>
        </div>

        <div class="border-2 border-zinc-300 bg-white p-4 sm:p-6 space-y-3.5 sm:space-y-4">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1.5 border-b border-zinc-200 pb-2.5">
            <div>
              <div class="font-black text-xs sm:text-sm text-zinc-950">Sep 18, 2026 · Picked up at Main St. Store</div>
              <div class="text-[10px] sm:text-xs font-mono text-zinc-500">Order #10482 · Curbside Bay 3</div>
            </div>
            <div class="text-left sm:text-right">
              <span class="text-lg sm:text-xl font-black font-mono text-zinc-950">$62.40</span>
              <div class="text-[9px] sm:text-[10px] font-mono text-emerald-700 font-bold uppercase">6 items fulfilled</div>
            </div>
          </div>

          <div class="text-[11px] sm:text-xs text-zinc-600 leading-relaxed font-mono">
            Chakki Atta (20 lb), Desi Ghee (500 ml), Fresh Okra Bhindi (1 lb), Fresh Curry Leaves (1 bunch), Glucose Biscuits (800 g), Maggi Masala Noodles (6-pack)
          </div>

          <!-- Reorder All 6 Items Button (Screen 1f) -->
          <div class="pt-1">
            <button 
              @click="reorderPastOrder"
              class="w-full sm:w-auto px-5 py-3 bg-[#E52E04] hover:bg-[#CC2500] active:bg-[#B82200] text-white font-black text-xs uppercase tracking-wider border border-[#B82200] transition-colors flex items-center justify-center gap-2 cursor-pointer shadow-sm"
            >
              <RotateCcw class="w-4 h-4 stroke-[2.5]" />
              <span>🔁 Reorder all 6 items into cart</span>
            </button>
          </div>
        </div>
      </div>

    </div>
  </StoreLayout>
</template>
