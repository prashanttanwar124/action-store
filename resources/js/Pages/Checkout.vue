<script setup>
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import StoreLayout from '../Layouts/StoreLayout.vue';
import { useStore } from '../stores/cart';
import { 
  ChevronLeft, 
  MapPin, 
  Clock, 
  CreditCard, 
  ShieldCheck, 
  Check, 
  CheckCircle2, 
  Truck, 
  Store 
} from 'lucide-vue-next';

const store = useStore();

const deliveryMethod = ref('pickup'); // 'pickup' | 'delivery'
const selectedSlot = ref('today-4-5');
const paymentMethod = ref('apple-pay'); // 'apple-pay' | 'google-pay' | 'card'
const isProcessing = ref(false);
const orderPlaced = ref(false);

const timeSlots = [
  { id: 'today-4-5', label: 'TODAY 4–5 pm', status: 'Next available', recommended: true },
  { id: 'today-6-7', label: 'TODAY 6–7 pm', status: 'Available' },
  { id: 'tomorrow-9-10', label: 'TOMORROW 9–10 am', status: 'Available' },
];

function completeOrder() {
  isProcessing.value = true;
  setTimeout(() => {
    isProcessing.value = false;
    orderPlaced.value = true;
    store.masalaPoints += Math.floor(store.subtotal);
  }, 1200);
}
</script>

<template>
  <Head title="Express Checkout — Masala Mart" />

  <StoreLayout :showCartBar="false">
    <div class="space-y-6 max-w-5xl mx-auto">

      <!-- Breadcrumbs -->
      <nav class="flex items-center gap-2 text-xs font-mono text-zinc-500">
        <Link href="/cart" class="hover:text-zinc-950 flex items-center gap-1 font-bold">
          <ChevronLeft class="w-4 h-4" />
          <span>Back to Cart</span>
        </Link>
        <span>/</span>
        <span class="text-zinc-950 font-bold">Checkout</span>
      </nav>

      <!-- Order Confirmation State -->
      <div v-if="orderPlaced" class="bg-white border-2 border-zinc-950 p-8 text-center space-y-5">
        <div class="w-16 h-16 bg-emerald-100 text-emerald-700 flex items-center justify-center mx-auto border-2 border-emerald-500">
          <CheckCircle2 class="w-10 h-10" />
        </div>
        
        <div>
          <span class="text-[11px] font-mono uppercase tracking-widest text-[#E52E04] font-bold">ORDER CONFIRMED #MM-88492</span>
          <h1 class="text-3xl font-black text-zinc-950 tracking-tight mt-1">Thank you for your order!</h1>
          <p class="text-sm text-zinc-600 mt-2 max-w-md mx-auto">
            Your items are being packed at <strong>{{ store.selectedStore }}</strong> and will be ready for pickup <strong>TODAY between 4–5 pm</strong>.
          </p>
        </div>

        <div class="max-w-md mx-auto bg-zinc-50 p-4 border border-zinc-300 text-left space-y-2 text-xs font-mono">
          <div class="flex justify-between">
            <span class="text-zinc-500">Pickup Location:</span>
            <span class="font-bold text-zinc-950">482 Main Street (Bay 3)</span>
          </div>
          <div class="flex justify-between">
            <span class="text-zinc-500">Total Paid:</span>
            <span class="font-bold text-zinc-950">${{ store.total.toFixed(2) }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-zinc-500">Masala Points Earned:</span>
            <span class="font-bold text-[#E52E04]">+{{ Math.floor(store.subtotal) }} pts</span>
          </div>
        </div>

        <div class="flex justify-center gap-4 pt-4">
          <Link 
            href="/account" 
            class="px-6 py-3 bg-zinc-950 text-white font-bold text-xs uppercase tracking-wider border border-zinc-950"
          >
            View Account & Orders
          </Link>
          <Link 
            href="/" 
            class="px-6 py-3 bg-[#E52E04] text-white font-bold text-xs uppercase tracking-wider border border-[#B82200]"
          >
            Continue Shopping
          </Link>
        </div>
      </div>

      <!-- Main Checkout 2-Column Form -->
      <div v-else class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        
        <!-- LEFT COLUMN: Fulfillment & Payment Details (lg:col-span-7) -->
        <div class="lg:col-span-7 space-y-6">
          
          <!-- SECTION 1: Pickup / Delivery 2-Cell Switch (Screen 1e) -->
          <div class="bg-white border border-zinc-300 p-6 space-y-4">
            <h2 class="text-base font-black text-zinc-950 uppercase tracking-wide font-mono flex items-center gap-2">
              <span class="w-5 h-5 bg-zinc-950 text-white flex items-center justify-center text-xs">1</span>
              <span>Fulfillment Method</span>
            </h2>

            <div class="grid grid-cols-2 gap-3">
              <button 
                type="button"
                @click="deliveryMethod = 'pickup'"
                :class="[
                  'p-4 border-2 text-left transition-colors flex flex-col justify-between cursor-pointer',
                  deliveryMethod === 'pickup' 
                    ? 'border-zinc-950 bg-zinc-50 shadow-sm' 
                    : 'border-zinc-200 hover:border-zinc-400 bg-white'
                ]"
              >
                <div class="flex items-center justify-between">
                  <Store class="w-5 h-5 text-zinc-950" />
                  <span v-if="deliveryMethod === 'pickup'" class="w-3 h-3 bg-[#E52E04]"></span>
                </div>
                <div class="mt-3">
                  <div class="font-black text-sm text-zinc-950">Store Pickup</div>
                  <div class="text-[11px] text-zinc-500 font-mono mt-0.5">{{ store.selectedStore }} · FREE</div>
                </div>
              </button>

              <button 
                type="button"
                @click="deliveryMethod = 'delivery'"
                :class="[
                  'p-4 border-2 text-left transition-colors flex flex-col justify-between cursor-pointer',
                  deliveryMethod === 'delivery' 
                    ? 'border-zinc-950 bg-zinc-50 shadow-sm' 
                    : 'border-zinc-200 hover:border-zinc-400 bg-white'
                ]"
              >
                <div class="flex items-center justify-between">
                  <Truck class="w-5 h-5 text-zinc-950" />
                  <span v-if="deliveryMethod === 'delivery'" class="w-3 h-3 bg-[#E52E04]"></span>
                </div>
                <div class="mt-3">
                  <div class="font-black text-sm text-zinc-950">Local Delivery</div>
                  <div class="text-[11px] text-zinc-500 font-mono mt-0.5">
                    {{ store.subtotal >= 40 ? 'FREE ($40+ met)' : '$4.99 or free over $40' }}
                  </div>
                </div>
              </button>
            </div>
          </div>

          <!-- SECTION 2: 1-Hour Time Slot Picker (Screen 1e) -->
          <div class="bg-white border border-zinc-300 p-6 space-y-4">
            <div class="flex items-center justify-between">
              <h2 class="text-base font-black text-zinc-950 uppercase tracking-wide font-mono flex items-center gap-2">
                <span class="w-5 h-5 bg-zinc-950 text-white flex items-center justify-center text-xs">2</span>
                <span>Select 1-Hour Window</span>
              </h2>
              <span class="text-[11px] font-mono text-emerald-700 font-bold">Fast Turnaround</span>
            </div>

            <div class="space-y-2">
              <button 
                v-for="slot in timeSlots"
                :key="slot.id"
                type="button"
                @click="selectedSlot = slot.id"
                :class="[
                  'w-full p-3.5 border-2 text-left flex items-center justify-between transition-colors cursor-pointer',
                  selectedSlot === slot.id 
                    ? 'border-zinc-950 bg-zinc-50 shadow-sm' 
                    : 'border-zinc-200 hover:border-zinc-400 bg-white'
                ]"
              >
                <div class="flex items-center gap-3">
                  <div :class="['w-4 h-4 border-2 flex items-center justify-center', selectedSlot === slot.id ? 'border-zinc-950 bg-zinc-950' : 'border-zinc-400']">
                    <Check v-if="selectedSlot === slot.id" class="w-3 h-3 text-white stroke-[3]" />
                  </div>
                  <div>
                    <span class="font-black text-xs sm:text-sm text-zinc-950 font-mono">{{ slot.label }}</span>
                    <span v-if="slot.recommended" class="ml-2 px-1.5 py-0.5 bg-[#E52E04] text-white text-[9px] font-mono uppercase font-bold">
                      Recommended
                    </span>
                  </div>
                </div>
                <span class="text-[11px] font-mono text-zinc-500">{{ slot.status }}</span>
              </button>
            </div>
          </div>

          <!-- SECTION 3: Payment & Express Wallets (Screen 1e) -->
          <div class="bg-white border border-zinc-300 p-6 space-y-5">
            <h2 class="text-base font-black text-zinc-950 uppercase tracking-wide font-mono flex items-center gap-2">
              <span class="w-5 h-5 bg-zinc-950 text-white flex items-center justify-center text-xs">3</span>
              <span>Express Payment</span>
            </h2>

            <!-- Express Buttons: Apple Pay & Google Pay -->
            <div class="grid grid-cols-2 gap-3">
              <button 
                type="button"
                @click="paymentMethod = 'apple-pay'"
                :class="[
                  'py-3.5 px-4 bg-black text-white font-bold text-sm flex items-center justify-center gap-2 border-2 transition-transform active:scale-[0.99] cursor-pointer',
                  paymentMethod === 'apple-pay' ? 'border-[#E52E04] ring-2 ring-[#E52E04]' : 'border-black'
                ]"
              >
                <span class="text-base"></span>
                <span>Pay</span>
              </button>

              <button 
                type="button"
                @click="paymentMethod = 'google-pay'"
                :class="[
                  'py-3.5 px-4 bg-zinc-100 hover:bg-zinc-200 text-zinc-900 font-bold text-sm flex items-center justify-center gap-2 border-2 transition-transform active:scale-[0.99] cursor-pointer',
                  paymentMethod === 'google-pay' ? 'border-[#E52E04] ring-2 ring-[#E52E04]' : 'border-zinc-300'
                ]"
              >
                <span class="font-black text-blue-600">G</span>
                <span>Pay</span>
              </button>
            </div>

            <div class="relative flex items-center justify-center">
              <div class="border-t border-zinc-300 w-full"></div>
              <span class="bg-white px-3 text-[10px] font-mono text-zinc-500 uppercase tracking-wider absolute">
                Or pay with credit / debit card
              </span>
            </div>

            <!-- Card Inputs -->
            <div class="space-y-3 pt-1">
              <div>
                <label class="block text-[10px] font-mono uppercase text-zinc-500 font-bold mb-1">Card Number</label>
                <div class="relative">
                  <input 
                    type="text" 
                    placeholder="4242 ···· ···· 4242"
                    value="•••• •••• •••• 4242"
                    class="w-full px-3 py-2.5 bg-zinc-50 border border-zinc-400 text-xs font-mono text-zinc-950 focus:bg-white focus:outline-none focus:border-zinc-950"
                  />
                  <CreditCard class="absolute right-3 top-3 w-4 h-4 text-zinc-400" />
                </div>
              </div>

              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-[10px] font-mono uppercase text-zinc-500 font-bold mb-1">Expires (MM/YY)</label>
                  <input 
                    type="text" 
                    placeholder="12/28" 
                    value="12/28"
                    class="w-full px-3 py-2.5 bg-zinc-50 border border-zinc-400 text-xs font-mono text-zinc-950 focus:bg-white focus:outline-none"
                  />
                </div>
                <div>
                  <label class="block text-[10px] font-mono uppercase text-zinc-500 font-bold mb-1">Security Code (CVC)</label>
                  <input 
                    type="text" 
                    placeholder="•••" 
                    value="882"
                    class="w-full px-3 py-2.5 bg-zinc-50 border border-zinc-400 text-xs font-mono text-zinc-950 focus:bg-white focus:outline-none"
                  />
                </div>
              </div>
            </div>

          </div>

        </div>

        <!-- RIGHT COLUMN: Order Summary & Pay Button (lg:col-span-5) -->
        <div class="lg:col-span-5 space-y-4">
          
          <div class="bg-white border-2 border-zinc-950 p-6 space-y-4 shadow-sm">
            <h2 class="text-base font-black text-zinc-950 uppercase tracking-wide font-mono border-b border-zinc-200 pb-3">
              Order Review ({{ store.totalItemCount }} items)
            </h2>

            <!-- Items Quick View -->
            <div class="max-h-60 overflow-y-auto divide-y divide-zinc-100 pr-1">
              <div 
                v-for="item in store.cartItems" 
                :key="item.id"
                class="py-2.5 flex items-center justify-between text-xs"
              >
                <div>
                  <div class="font-bold text-zinc-950">{{ item.name }}</div>
                  <div class="text-[10px] text-zinc-500 font-mono">{{ item.quantity }}x · {{ item.weight }}</div>
                </div>
                <div class="font-mono font-bold text-zinc-950">
                  ${{ (item.price * item.quantity).toFixed(2) }}
                </div>
              </div>
            </div>

            <!-- Price Breakdown -->
            <div class="pt-3 border-t border-zinc-200 space-y-1.5 text-xs font-mono">
              <div class="flex justify-between text-zinc-600">
                <span>Subtotal</span>
                <span>${{ store.subtotal.toFixed(2) }}</span>
              </div>
              <div class="flex justify-between text-zinc-600">
                <span>Pickup</span>
                <span class="text-emerald-700 font-bold">FREE</span>
              </div>
              <div class="pt-2 border-t border-zinc-200 flex justify-between items-baseline text-lg font-black text-zinc-950">
                <span>Total Due</span>
                <span class="text-2xl font-mono text-zinc-950">${{ store.total.toFixed(2) }}</span>
              </div>
            </div>

            <!-- Pay Now Button (Screen 1e) -->
            <button 
              type="button"
              @click="completeOrder"
              :disabled="isProcessing"
              class="w-full py-4 bg-[#E52E04] hover:bg-[#CC2500] text-white font-black text-center text-sm uppercase tracking-wider border border-[#B82200] transition-colors cursor-pointer flex items-center justify-center gap-2"
            >
              <span v-if="isProcessing" class="animate-spin text-base">⟳</span>
              <span>{{ isProcessing ? 'Authorizing Payment...' : `Pay $${store.total.toFixed(2)}` }}</span>
            </button>

            <div class="flex items-center justify-center gap-2 text-[10px] font-mono text-zinc-500 pt-1">
              <ShieldCheck class="w-3.5 h-3.5 text-emerald-600" />
              <span>256-Bit SSL Encrypted & Stripe Secured</span>
            </div>
          </div>

        </div>

      </div>

    </div>
  </StoreLayout>
</template>
