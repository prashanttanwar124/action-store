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
  Store,
  Lock,
  Loader2
} from 'lucide-vue-next';
import IconApplePay from '../Components/Icons/IconApplePay.vue';
import IconGooglePay from '../Components/Icons/IconGooglePay.vue';

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

  <StoreLayout :showCartBar="false" :showBottomNav="false">
    <!-- Frictionless Checkout Design System: Organic Style 
         - Caprasimo for headings and big prices
         - Figtree for body text (400), labels (600), and buttons (700)
         - Built-in monospace font for photo labels
    -->
    <div class="checkout-organic font-figtree space-y-6 max-w-5xl mx-auto pb-20 sm:pb-0">

      <!-- Breadcrumbs -->
      <nav class="flex items-center gap-2 text-xs font-figtree text-zinc-500">
        <Link href="/cart" class="hover:text-zinc-950 flex items-center gap-1 font-semibold text-zinc-700">
          <ChevronLeft class="w-4 h-4 stroke-[2]" />
          <span>Back to Cart</span>
        </Link>
        <span class="text-zinc-400">/</span>
        <span class="text-zinc-950 font-bold">Checkout</span>
      </nav>

      <!-- Order Confirmation State (Organic Style) -->
      <div v-if="orderPlaced" class="bg-white border-2 border-zinc-950 p-6 sm:p-10 text-center space-y-6 shadow-sm">
        <div class="w-16 h-16 bg-emerald-50 text-emerald-700 flex items-center justify-center mx-auto border-2 border-emerald-600">
          <CheckCircle2 class="w-9 h-9 stroke-[2.2]" />
        </div>
        
        <div>
          <span class="text-[11px] font-figtree font-bold uppercase tracking-widest text-[#E52E04]">
            ORDER CONFIRMED #MM-88492
          </span>
          <h1 class="font-caprasimo text-2xl sm:text-4xl text-zinc-950 tracking-tight mt-1.5 leading-tight">
            Thank you for your order!
          </h1>
          <p class="text-sm text-zinc-600 mt-2.5 max-w-md mx-auto font-figtree leading-relaxed">
            Your items are being hand-picked at <strong>{{ store.selectedStore }}</strong> and will be ready for pickup <strong>TODAY between 4–5 pm</strong>.
          </p>
        </div>

        <div class="max-w-md mx-auto bg-zinc-50 p-4 border border-zinc-300 text-left space-y-2.5 text-xs">
          <div class="flex justify-between items-center">
            <span class="text-zinc-500 font-figtree font-medium">Pickup Location:</span>
            <span class="font-figtree font-bold text-zinc-950">482 Main Street (Bay 3)</span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-zinc-500 font-figtree font-medium">Total Paid:</span>
            <span class="font-caprasimo text-base text-zinc-950">${{ store.total.toFixed(2) }}</span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-zinc-500 font-figtree font-medium">Masala Points Earned:</span>
            <span class="font-figtree font-bold text-[#E52E04]">+{{ Math.floor(store.subtotal) }} pts</span>
          </div>
        </div>

        <div class="flex flex-col sm:flex-row justify-center gap-3 pt-3">
          <Link 
            href="/account" 
            class="px-6 py-3.5 bg-zinc-950 text-white font-figtree font-bold text-xs uppercase tracking-wider border border-zinc-950 text-center hover:bg-zinc-800 transition-colors"
          >
            View Account & Orders
          </Link>
          <Link 
            href="/" 
            class="px-6 py-3.5 bg-[#E52E04] text-white font-figtree font-bold text-xs uppercase tracking-wider border border-[#B82200] text-center hover:bg-[#CC2500] transition-colors"
          >
            Continue Shopping
          </Link>
        </div>
      </div>

      <!-- Main Checkout 2-Column Form -->
      <div v-else class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8 items-start">
        
        <!-- LEFT COLUMN: Fulfillment & Payment Details (lg:col-span-7) -->
        <div class="lg:col-span-7 space-y-5">
          
          <!-- SECTION 1: Pickup / Delivery 2-Cell Switch (Screen 1e) -->
          <div class="bg-white border border-zinc-300 p-4 sm:p-6 space-y-4">
            <div class="flex items-center gap-2">
              <span class="w-6 h-6 bg-zinc-950 text-white flex items-center justify-center text-xs font-figtree font-bold">1</span>
              <h2 class="font-caprasimo text-lg sm:text-xl text-zinc-950 tracking-tight leading-none">
                Fulfillment Method
              </h2>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <button 
                type="button"
                @click="deliveryMethod = 'pickup'"
                :class="[
                  'p-3.5 sm:p-4 border-2 text-left transition-colors flex flex-col justify-between cursor-pointer',
                  deliveryMethod === 'pickup' 
                    ? 'border-zinc-950 bg-zinc-50 shadow-sm' 
                    : 'border-zinc-200 hover:border-zinc-400 bg-white'
                ]"
              >
                <div class="flex items-center justify-between">
                  <Store class="w-5 h-5 text-zinc-950 stroke-[2]" />
                  <span v-if="deliveryMethod === 'pickup'" class="w-3 h-3 bg-[#E52E04]"></span>
                </div>
                <div class="mt-3">
                  <div class="font-figtree font-bold text-xs sm:text-sm text-zinc-950">Store Pickup</div>
                  <div class="text-[11px] text-zinc-500 font-figtree font-medium mt-0.5">{{ store.selectedStore }} · FREE</div>
                </div>
              </button>

              <button 
                type="button"
                @click="deliveryMethod = 'delivery'"
                :class="[
                  'p-3.5 sm:p-4 border-2 text-left transition-colors flex flex-col justify-between cursor-pointer',
                  deliveryMethod === 'delivery' 
                    ? 'border-zinc-950 bg-zinc-50 shadow-sm' 
                    : 'border-zinc-200 hover:border-zinc-400 bg-white'
                ]"
              >
                <div class="flex items-center justify-between">
                  <Truck class="w-5 h-5 text-zinc-950 stroke-[2]" />
                  <span v-if="deliveryMethod === 'delivery'" class="w-3 h-3 bg-[#E52E04]"></span>
                </div>
                <div class="mt-3">
                  <div class="font-figtree font-bold text-xs sm:text-sm text-zinc-950">Local Delivery</div>
                  <div class="text-[11px] text-zinc-500 font-figtree font-medium mt-0.5">
                    {{ store.subtotal >= 40 ? 'FREE ($40+ met)' : '$4.99' }}
                  </div>
                </div>
              </button>
            </div>
          </div>

          <!-- SECTION 2: 1-Hour Time Slot Picker (Screen 1e) -->
          <div class="bg-white border border-zinc-300 p-4 sm:p-6 space-y-4">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <span class="w-6 h-6 bg-zinc-950 text-white flex items-center justify-center text-xs font-figtree font-bold">2</span>
                <h2 class="font-caprasimo text-lg sm:text-xl text-zinc-950 tracking-tight leading-none">
                  Select 1-Hour Window
                </h2>
              </div>
              <span class="text-[11px] font-figtree font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-0.5">
                Fast Turnaround
              </span>
            </div>

            <div class="space-y-2.5">
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
                    <span class="font-figtree font-bold text-xs sm:text-sm text-zinc-950">{{ slot.label }}</span>
                    <span v-if="slot.recommended" class="ml-2 px-1.5 py-0.5 bg-[#E52E04] text-white text-[9px] font-figtree font-bold uppercase tracking-wider">
                      Recommended
                    </span>
                  </div>
                </div>
                <span class="text-[11px] font-figtree font-medium text-zinc-500">{{ slot.status }}</span>
              </button>
            </div>
          </div>

          <!-- SECTION 3: Payment & Express Wallets (Screen 1e) -->
          <div class="bg-white border border-zinc-300 p-4 sm:p-6 space-y-4">
            <div class="flex items-center gap-2">
              <span class="w-6 h-6 bg-zinc-950 text-white flex items-center justify-center text-xs font-figtree font-bold">3</span>
              <h2 class="font-caprasimo text-lg sm:text-xl text-zinc-950 tracking-tight leading-none">
                Express Payment
              </h2>
            </div>

            <!-- Express Buttons: High Quality SVG Icon Components -->
            <div class="grid grid-cols-2 gap-3">
              <button 
                type="button"
                @click="paymentMethod = 'apple-pay'"
                :class="[
                  'h-12 px-4 bg-black text-white font-figtree font-bold text-xs sm:text-sm flex items-center justify-center gap-2 border-2 transition-all cursor-pointer shadow-sm active:scale-[0.99]',
                  paymentMethod === 'apple-pay' ? 'border-[#E52E04] ring-2 ring-[#E52E04]' : 'border-black hover:bg-zinc-900'
                ]"
                aria-label="Pay with Apple Pay"
              >
                <IconApplePay :width="48" :height="22" class="text-white" />
              </button>

              <button 
                type="button"
                @click="paymentMethod = 'google-pay'"
                :class="[
                  'h-12 px-4 bg-zinc-100 hover:bg-zinc-200 text-zinc-900 font-figtree font-bold text-xs sm:text-sm flex items-center justify-center gap-2 border-2 transition-all cursor-pointer shadow-sm active:scale-[0.99]',
                  paymentMethod === 'google-pay' ? 'border-[#E52E04] ring-2 ring-[#E52E04]' : 'border-zinc-300'
                ]"
                aria-label="Pay with Google Pay"
              >
                <IconGooglePay :width="54" :height="22" />
              </button>
            </div>

            <div class="relative flex items-center justify-center my-3">
              <div class="border-t border-zinc-300 w-full"></div>
              <span class="bg-white px-3 text-[10px] sm:text-[11px] font-figtree font-semibold text-zinc-500 uppercase tracking-wider absolute">
                Or pay with credit / debit card
              </span>
            </div>

            <!-- Card Inputs -->
            <div class="space-y-3.5 pt-1">
              <div>
                <label class="block text-[11px] font-figtree font-semibold uppercase text-zinc-700 mb-1.5">
                  Card Number
                </label>
                <div class="relative">
                  <input 
                    type="text" 
                    placeholder="4242 ···· ···· 4242"
                    value="•••• •••• •••• 4242"
                    class="w-full px-3.5 py-2.5 bg-zinc-50 border border-zinc-400 text-xs font-mono text-zinc-950 focus:bg-white focus:border-zinc-950 focus:outline-none"
                  />
                  <CreditCard class="absolute right-3.5 top-3 w-4 h-4 text-zinc-400 stroke-[1.8]" />
                </div>
              </div>

              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-[11px] font-figtree font-semibold uppercase text-zinc-700 mb-1.5">
                    Expires (MM/YY)
                  </label>
                  <input 
                    type="text" 
                    placeholder="12/28" 
                    value="12/28"
                    class="w-full px-3.5 py-2.5 bg-zinc-50 border border-zinc-400 text-xs font-mono text-zinc-950 focus:bg-white focus:border-zinc-950 focus:outline-none"
                  />
                </div>
                <div>
                  <label class="block text-[11px] font-figtree font-semibold uppercase text-zinc-700 mb-1.5">
                    Security Code (CVC)
                  </label>
                  <div class="relative">
                    <input 
                      type="text" 
                      placeholder="•••" 
                      value="882"
                      class="w-full px-3.5 py-2.5 bg-zinc-50 border border-zinc-400 text-xs font-mono text-zinc-950 focus:bg-white focus:border-zinc-950 focus:outline-none"
                    />
                    <Lock class="absolute right-3.5 top-3 w-3.5 h-3.5 text-zinc-400 stroke-[1.8]" />
                  </div>
                </div>
              </div>
            </div>

          </div>

        </div>

        <!-- RIGHT COLUMN: Order Summary & Desktop Pay Button (lg:col-span-5) -->
        <div class="lg:col-span-5 space-y-4">
          
          <div class="bg-white border-2 border-zinc-950 p-4 sm:p-6 space-y-4 shadow-sm">
            <div class="border-b border-zinc-200 pb-3 flex items-center justify-between">
              <h2 class="font-caprasimo text-lg sm:text-xl text-zinc-950 tracking-tight leading-none">
                Order Review
              </h2>
              <span class="text-xs font-figtree font-semibold text-zinc-500">
                {{ store.totalItemCount }} items
              </span>
            </div>

            <!-- Items Quick View with Device's Built-in Monospace Photo Placeholder Labels -->
            <div class="max-h-56 sm:max-h-64 overflow-y-auto divide-y divide-zinc-100 pr-1">
              <div 
                v-for="item in store.cartItems" 
                :key="item.id"
                class="py-2.5 flex items-center justify-between gap-3 text-xs"
              >
                <div class="flex items-center gap-3">
                  <!-- Photo placeholder label strictly using device built-in mono -->
                  <div class="w-11 h-11 bg-zinc-100 border border-zinc-300 p-1 flex items-center justify-center text-center bg-stripes shrink-0">
                    <span class="photo-label text-[8px] text-zinc-600 font-bold uppercase tracking-tight leading-tight">
                      {{ item.name.toLowerCase().includes('atta') ? 'atta bag' : (item.name.toLowerCase().includes('ghee') ? 'ghee jar' : (item.name.toLowerCase().includes('paneer') ? 'paneer block' : (item.name.toLowerCase().includes('mithai') ? 'mithai box' : 'photo label'))) }}
                    </span>
                  </div>

                  <div>
                    <div class="font-figtree font-bold text-zinc-950 leading-snug">{{ item.name }}</div>
                    <div class="text-[11px] text-zinc-500 font-figtree font-medium">{{ item.quantity }}x · {{ item.weight }}</div>
                  </div>
                </div>

                <div class="font-figtree font-bold text-zinc-950 shrink-0 text-right">
                  ${{ (item.price * item.quantity).toFixed(2) }}
                </div>
              </div>
            </div>

            <!-- Price Breakdown: Caprasimo for Big Total Price, Figtree for labels -->
            <div class="pt-3 border-t border-zinc-200 space-y-2 text-xs">
              <div class="flex justify-between text-zinc-600 font-figtree font-medium">
                <span>Subtotal</span>
                <span class="font-figtree font-semibold text-zinc-950">${{ store.subtotal.toFixed(2) }}</span>
              </div>
              <div class="flex justify-between text-zinc-600 font-figtree font-medium">
                <span>Fulfillment (Pickup)</span>
                <span class="text-emerald-700 font-figtree font-bold">FREE</span>
              </div>
              
              <!-- Big Total Due in Caprasimo -->
              <div class="pt-2.5 border-t border-zinc-200 flex justify-between items-baseline text-zinc-950">
                <span class="font-figtree font-bold text-sm sm:text-base">Total Due</span>
                <span class="big-price font-caprasimo text-2xl sm:text-3xl text-zinc-950">
                  ${{ store.total.toFixed(2) }}
                </span>
              </div>
            </div>

            <!-- Desktop Pay Now Button: Figtree 700 Bold -->
            <button 
              type="button"
              @click="completeOrder"
              :disabled="isProcessing"
              class="hidden sm:flex w-full py-4 bg-[#E52E04] hover:bg-[#CC2500] text-white font-figtree font-bold text-center text-sm uppercase tracking-wider border border-[#B82200] transition-colors cursor-pointer items-center justify-center gap-2 shadow-sm"
            >
              <Loader2 v-if="isProcessing" class="w-4 h-4 animate-spin text-white" />
              <span>{{ isProcessing ? 'Authorizing Payment...' : `Pay $${store.total.toFixed(2)}` }}</span>
            </button>

            <div class="flex items-center justify-center gap-1.5 text-[11px] font-figtree font-medium text-zinc-500 pt-1">
              <ShieldCheck class="w-4 h-4 text-emerald-600 stroke-[2]" />
              <span>256-Bit SSL Encrypted & Stripe Secured</span>
            </div>
          </div>

        </div>

      </div>

      <!-- Mobile Sticky Bottom Pay Bar: Big Price in Caprasimo, Button in Figtree Bold -->
      <div 
        v-if="!orderPlaced"
        class="sm:hidden fixed bottom-0 left-0 right-0 z-40 bg-white border-t border-zinc-300 p-3 shadow-2xl flex items-center justify-between gap-3"
      >
        <div>
          <div class="text-[10px] font-figtree font-semibold text-zinc-500 uppercase leading-none">Total Due</div>
          <div class="big-price font-caprasimo text-xl text-zinc-950 mt-1 leading-none">
            ${{ store.total.toFixed(2) }}
          </div>
        </div>

        <button 
          type="button"
          @click="completeOrder"
          :disabled="isProcessing"
          class="flex-1 py-3 bg-[#E52E04] active:bg-[#CC2500] text-white font-figtree font-bold text-xs uppercase tracking-wider text-center border border-[#B82200] cursor-pointer flex items-center justify-center gap-2 shadow-sm"
        >
          <Loader2 v-if="isProcessing" class="w-4 h-4 animate-spin text-white" />
          <span>{{ isProcessing ? 'Authorizing...' : `Pay $${store.total.toFixed(2)}` }}</span>
        </button>
      </div>

    </div>
  </StoreLayout>
</template>
