<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import MobileWebLayout from '../Layouts/MobileWebLayout.vue';
import { useStore } from '../stores/cart';
import { ChevronLeft, Store, Truck, Lock, CheckCircle2 } from 'lucide-vue-next';

const store = useStore();

const fulfillmentMode = ref('pickup'); // 'pickup' | 'delivery'
const selectedSlot = ref('today-1');

const timeSlots = [
  { id: 'today-1', day: 'TODAY', time: '4–5 pm', disabled: false },
  { id: 'today-full', day: 'TODAY', time: 'Full', disabled: true },
  { id: 'today-2', day: 'TODAY', time: '6–7 pm', disabled: false },
  { id: 'tomorrow-1', day: 'TOMORROW', time: '10–11 am', disabled: false },
  { id: 'tomorrow-2', day: 'TOMORROW', time: '11–12 pm', disabled: false },
  { id: 'tomorrow-3', day: 'TOMORROW', time: '12–1 pm', disabled: false },
];

const cardNumber = ref('1234 1234 1234 1234');
const expiry = ref('08/28');
const cvc = ref('123');
const cardName = ref('Priya Sharma');

const isProcessing = ref(false);

function handlePay() {
  isProcessing.value = true;
  setTimeout(() => {
    // Navigate to account page with success state
    router.visit('/account');
  }, 600);
}
</script>

<template>
  <Head title="Checkout — Masala Mart" />

  <MobileWebLayout :showBottomNav="false" :showCartBar="false">
    
    <!-- Top Bar with Back Arrow (Page 6: 1e) -->
    <div class="px-4 py-3 bg-white border-b border-zinc-200 flex items-center gap-2 sticky top-[41px] z-30">
      <Link 
        href="/cart"
        class="p-1 text-zinc-950 hover:bg-zinc-100 rounded-none cursor-pointer flex items-center"
      >
        <ChevronLeft class="w-5 h-5 stroke-[2.5]" />
      </Link>
      <h1 class="text-xl font-black text-zinc-950 tracking-tight">Checkout</h1>
    </div>

    <!-- Main Checkout Form Content -->
    <div class="p-4 space-y-6">

      <!-- 01 · HOW DO YOU WANT IT? (Page 6: 1e) -->
      <div>
        <div class="text-[10px] font-mono font-bold tracking-wider uppercase text-zinc-500 mb-2">
          01 · HOW DO YOU WANT IT?
        </div>

        <!-- Two-cell switch box -->
        <div class="grid grid-cols-2 border-2 border-zinc-950 rounded-none overflow-hidden">
          
          <!-- In-store pickup Cell -->
          <div 
            @click="fulfillmentMode = 'pickup'"
            :class="[
              'p-3 flex flex-col justify-between cursor-pointer transition-colors',
              fulfillmentMode === 'pickup' 
                ? 'bg-zinc-950 text-white' 
                : 'bg-white text-zinc-900 border-r-2 border-zinc-950'
            ]"
          >
            <div class="flex items-center gap-1.5 mb-1.5">
              <Store class="w-4 h-4" />
              <span class="font-black text-xs">In-store pickup</span>
            </div>
            <div class="text-[10px] font-mono" :class="fulfillmentMode === 'pickup' ? 'text-zinc-300' : 'text-zinc-500'">
              Free · ready in 1 hr
            </div>
          </div>

          <!-- Local delivery Cell -->
          <div 
            @click="fulfillmentMode = 'delivery'"
            :class="[
              'p-3 flex flex-col justify-between cursor-pointer transition-colors',
              fulfillmentMode === 'delivery' 
                ? 'bg-zinc-950 text-white' 
                : 'bg-white text-zinc-900'
            ]"
          >
            <div class="flex items-center gap-1.5 mb-1.5">
              <Truck class="w-4 h-4" />
              <span class="font-black text-xs">Local delivery</span>
            </div>
            <div class="text-[10px] font-mono" :class="fulfillmentMode === 'delivery' ? 'text-zinc-300' : 'text-zinc-500'">
              $4.99 · free over $40.00
            </div>
          </div>
        </div>

        <!-- Store / Address Address Info -->
        <div class="mt-3 p-3 bg-zinc-50 border border-zinc-300 rounded-none text-xs">
          <div class="font-black text-zinc-950">
            {{ fulfillmentMode === 'pickup' ? 'Masala Mart — Main St.' : 'Delivery to Home' }}
          </div>
          <div class="text-zinc-600 mt-0.5 font-mono text-[11px]">
            {{ fulfillmentMode === 'pickup' ? '214 Main St. · curbside bay or counter' : '108 Apt 4B, Park Avenue, NY' }}
          </div>
        </div>

        <!-- Pick a time slot grid -->
        <div class="mt-4">
          <div class="text-xs font-bold text-zinc-900 mb-2">Pick a time slot</div>
          <div class="grid grid-cols-3 gap-2">
            <button
              v-for="slot in timeSlots"
              :key="slot.id"
              :disabled="slot.disabled"
              @click="selectedSlot = slot.id"
              :class="[
                'p-2 text-center rounded-none border transition-colors cursor-pointer flex flex-col items-center justify-center min-h-[52px]',
                slot.disabled 
                  ? 'border-zinc-200 bg-zinc-100 text-zinc-400 cursor-not-allowed'
                  : selectedSlot === slot.id
                    ? 'border-[#E52E04] bg-[#E52E04] text-white font-bold'
                    : 'border-zinc-400 bg-white text-zinc-950 hover:border-zinc-950'
              ]"
            >
              <span class="text-[9px] font-mono uppercase tracking-wider block" :class="selectedSlot === slot.id ? 'text-white/80' : 'text-zinc-500'">
                {{ slot.day }}
              </span>
              <span class="text-xs font-black tracking-tight mt-0.5">
                {{ slot.time }}
              </span>
            </button>
          </div>
        </div>
      </div>

      <!-- 02 · PAYMENT (Page 6: 1e) -->
      <div>
        <div class="text-[10px] font-mono font-bold tracking-wider uppercase text-zinc-500 mb-2">
          02 · PAYMENT
        </div>

        <!-- Express Wallets -->
        <div class="grid grid-cols-2 gap-2 mb-3">
          <button class="py-2.5 bg-zinc-950 text-white font-black text-xs rounded-none border border-zinc-950 hover:bg-zinc-800 flex items-center justify-center gap-1 cursor-pointer">
            <span> Pay</span>
          </button>
          <button class="py-2.5 bg-zinc-950 text-white font-black text-xs rounded-none border border-zinc-950 hover:bg-zinc-800 flex items-center justify-center gap-1 cursor-pointer">
            <span>G Pay</span>
          </button>
        </div>

        <!-- Divider -->
        <div class="text-center text-[10px] font-mono text-zinc-400 uppercase tracking-widest my-2.5">
          or pay by card
        </div>

        <!-- Card Inputs matching Page 6 -->
        <div class="space-y-2">
          <!-- Card Number Field -->
          <div>
            <label class="block text-[10px] font-mono text-zinc-600 mb-0.5 uppercase">Card number</label>
            <div class="relative flex items-center">
              <input 
                v-model="cardNumber"
                type="text" 
                class="w-full px-3 py-2 bg-white border border-zinc-400 rounded-none text-xs font-mono text-zinc-950 focus:outline-none focus:ring-1 focus:ring-zinc-950"
              />
              <div class="absolute right-2 flex items-center gap-1 text-[9px] font-mono font-bold text-zinc-500">
                <span class="px-1 bg-zinc-100 border border-zinc-300">VISA</span>
                <span class="px-1 bg-zinc-100 border border-zinc-300">MC</span>
                <span class="px-1 bg-zinc-100 border border-zinc-300">AMEX</span>
              </div>
            </div>
          </div>

          <!-- Expiry & CVC Grid -->
          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="block text-[10px] font-mono text-zinc-600 mb-0.5 uppercase">Expiry</label>
              <input 
                v-model="expiry"
                type="text" 
                placeholder="MM / YY"
                class="w-full px-3 py-2 bg-white border border-zinc-400 rounded-none text-xs font-mono text-zinc-950 focus:outline-none focus:ring-1 focus:ring-zinc-950"
              />
            </div>
            <div>
              <label class="block text-[10px] font-mono text-zinc-600 mb-0.5 uppercase">CVC</label>
              <input 
                v-model="cvc"
                type="text" 
                placeholder="123"
                class="w-full px-3 py-2 bg-white border border-zinc-400 rounded-none text-xs font-mono text-zinc-950 focus:outline-none focus:ring-1 focus:ring-zinc-950"
              />
            </div>
          </div>

          <!-- Name on card -->
          <div>
            <label class="block text-[10px] font-mono text-zinc-600 mb-0.5 uppercase">Name on card</label>
            <input 
              v-model="cardName"
              type="text" 
              class="w-full px-3 py-2 bg-white border border-zinc-400 rounded-none text-xs font-medium text-zinc-950 focus:outline-none focus:ring-1 focus:ring-zinc-950"
            />
          </div>
        </div>

      </div>

    </div>

    <!-- Sticky Bottom Pay Button (Page 6: 1e) -->
    <div class="fixed bottom-0 left-0 right-0 max-w-[420px] mx-auto p-3 bg-white border-t border-zinc-300 z-40">
      <button 
        @click="handlePay"
        :disabled="isProcessing"
        class="w-full h-11 bg-[#E52E04] hover:bg-[#CC2500] text-white font-black text-xs uppercase tracking-wider rounded-none flex items-center justify-between px-4 transition-all cursor-pointer border border-[#B82200]"
      >
        <span class="flex items-center gap-1.5">
          <Lock class="w-3.5 h-3.5" />
          <span>Pay · Today 4–5 pm</span>
        </span>
        <span class="font-black text-sm">${{ store.subtotal.toFixed(2) }}</span>
      </button>
    </div>

  </MobileWebLayout>
</template>
