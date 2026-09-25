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
  ArrowRight
} from 'lucide-vue-next';

const store = useStore();

function reorderPastOrder() {
  store.reorderAll();
}
</script>

<template>
  <Head title="My Account & Subscriptions — Masala Mart" />

  <StoreLayout>
    <div class="space-y-8 max-w-5xl mx-auto">

      <!-- Account Header Profile -->
      <div class="bg-white border border-zinc-300 p-6 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
          <span class="text-[10px] font-mono uppercase tracking-widest text-[#E52E04] font-bold">LOYALTY MEMBER</span>
          <h1 class="text-2xl sm:text-3xl font-black text-zinc-950 tracking-tight">Kavya Sharma</h1>
          <div class="text-xs text-zinc-500 font-mono mt-0.5">kavya.sharma@example.com · +1 (555) 392-1084</div>
        </div>

        <div class="flex items-center gap-3">
          <Link 
            href="/cart" 
            class="px-4 py-2 bg-zinc-950 text-white font-bold text-xs uppercase font-mono border border-zinc-950"
          >
            View Active Cart ({{ store.totalItemCount }})
          </Link>
        </div>
      </div>

      <!-- SECTION 1: Masala Points Reward Meter (Screen 1f) -->
      <div class="bg-zinc-950 text-white p-6 border border-zinc-900 shadow-sm space-y-4">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2">
          <div>
            <div class="text-xs font-mono font-bold uppercase tracking-wider text-amber-400 flex items-center gap-1.5">
              <Sparkles class="w-4 h-4" />
              <span>MASALA POINTS REWARDS</span>
            </div>
            <div class="text-3xl font-black font-mono tracking-tight mt-1 text-white">
              {{ store.masalaPoints.toLocaleString() }} <span class="text-base text-zinc-400 font-normal">pts</span>
            </div>
          </div>
          <div class="text-right text-xs font-mono text-zinc-400">
            <span>Next Tier: 1,500 pts</span>
            <div class="text-white font-bold mt-0.5">$10 Grocery Credit Unlock</div>
          </div>
        </div>

        <!-- 12-Segment Progress Meter (Screen 1f Specification) -->
        <div class="grid grid-cols-12 gap-1.5">
          <div 
            v-for="i in 12" 
            :key="i"
            :class="[
              'h-2.5 transition-colors',
              i <= 10 ? 'bg-[#E52E04]' : 'bg-zinc-800'
            ]"
          ></div>
        </div>

        <div class="flex justify-between text-xs font-mono text-zinc-400 pt-1">
          <span>260 pts to a $10 reward</span>
          <span>1 pt per $1 spent</span>
        </div>
      </div>

      <!-- SECTION 2: Subscriptions Management (Screen 1f) -->
      <div class="space-y-4">
        <div class="flex items-center justify-between border-b border-zinc-300 pb-2">
          <div>
            <h2 class="text-xl font-black text-zinc-950 tracking-tight">Subscriptions</h2>
            <p class="text-xs text-zinc-500">Auto-replenished pantry staples with 5% off every order.</p>
          </div>
          <span class="text-xs font-mono font-bold bg-zinc-100 border border-zinc-300 px-2.5 py-1 text-zinc-900">
            {{ store.subscriptions.filter(s => s.status === 'active').length }} active
          </span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <!-- Active Subscription Card (Chakki Atta) -->
          <div 
            v-for="sub in store.subscriptions" 
            :key="sub.id"
            :class="[
              'p-5 border-2 bg-white flex flex-col justify-between transition-colors space-y-4',
              sub.status === 'active' ? 'border-zinc-950' : 'border-zinc-300 bg-zinc-50 opacity-90'
            ]"
          >
            <div>
              <div class="flex items-start justify-between">
                <div>
                  <h3 class="font-black text-sm text-zinc-950">{{ sub.name }}</h3>
                  <div class="text-xs font-mono text-zinc-500 mt-0.5">{{ sub.weight }} · {{ sub.frequency }}</div>
                </div>
                <div class="text-right">
                  <span class="font-black font-mono text-sm text-zinc-950">${{ sub.price.toFixed(2) }}</span>
                  <div 
                    :class="[
                      'text-[10px] font-mono font-bold uppercase mt-0.5',
                      sub.status === 'active' ? 'text-emerald-700' : 'text-zinc-500'
                    ]"
                  >
                    {{ sub.status === 'active' ? 'Active' : 'Paused' }}
                  </div>
                </div>
              </div>

              <div class="mt-3 flex items-center gap-2 text-xs font-mono text-zinc-600 bg-zinc-100 p-2 border border-zinc-200">
                <Calendar class="w-3.5 h-3.5 text-zinc-500" />
                <span>Next pickup delivery: <strong>{{ sub.nextDate }}</strong></span>
              </div>
            </div>

            <!-- Subscription Action Buttons (Screen 1f) -->
            <div class="pt-2 border-t border-zinc-200 flex gap-2">
              <template v-if="sub.status === 'active'">
                <button 
                  @click="store.skipSubscription(sub.id)"
                  class="px-3.5 py-2 border border-zinc-400 bg-white hover:bg-zinc-100 text-xs font-bold text-zinc-900 transition-colors cursor-pointer"
                >
                  ▷| Skip next
                </button>
                <button 
                  @click="store.toggleSubscription(sub.id)"
                  class="px-3.5 py-2 border border-zinc-400 bg-white hover:bg-zinc-100 text-xs font-bold text-zinc-900 transition-colors cursor-pointer"
                >
                  Pause
                </button>
              </template>
              <template v-else>
                <button 
                  @click="store.toggleSubscription(sub.id)"
                  class="px-4 py-2 bg-zinc-950 text-white hover:bg-zinc-800 text-xs font-bold transition-colors cursor-pointer"
                >
                  Resume Subscription
                </button>
              </template>
            </div>
          </div>
        </div>
      </div>

      <!-- SECTION 3: Past Orders (Screen 1f) -->
      <div class="space-y-4">
        <div class="flex items-center justify-between border-b border-zinc-300 pb-2">
          <div>
            <h2 class="text-xl font-black text-zinc-950 tracking-tight">Past orders</h2>
            <p class="text-xs text-zinc-500">Pickups and deliveries fulfilled at your local store.</p>
          </div>
        </div>

        <div class="border-2 border-zinc-300 bg-white p-6 space-y-4">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-zinc-200 pb-3">
            <div>
              <div class="font-black text-sm text-zinc-950">Sep 18, 2026 · Picked up at Main St. Store</div>
              <div class="text-xs font-mono text-zinc-500">Order #10482 · Curbside Bay 3</div>
            </div>
            <div class="text-right">
              <span class="text-xl font-black font-mono text-zinc-950">$62.40</span>
              <div class="text-[10px] font-mono text-emerald-700 font-bold uppercase">6 items fulfilled</div>
            </div>
          </div>

          <div class="text-xs text-zinc-600 leading-relaxed font-mono">
            Chakki Atta (20 lb), Desi Ghee (500 ml), Fresh Okra Bhindi (1 lb), Fresh Curry Leaves (1 bunch), Glucose Biscuits (800 g), Maggi Masala Noodles (6-pack)
          </div>

          <!-- Reorder All 6 Items Button (Screen 1f) -->
          <div class="pt-2">
            <button 
              @click="reorderPastOrder"
              class="w-full sm:w-auto px-6 py-3 bg-[#E52E04] hover:bg-[#CC2500] text-white font-black text-xs uppercase tracking-wider border border-[#B82200] transition-colors flex items-center justify-center gap-2 cursor-pointer shadow-sm"
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
