<script setup>
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import StoreLayout from '../Layouts/StoreLayout.vue';
import { useStore } from '../stores/cart';
import { 
  ChevronLeft, 
  MapPin, 
  Clock, 
  CreditCard, 
  ShieldCheck, 
  CheckCircle2, 
  Truck, 
  Lock, 
  Loader2, 
  Sparkles 
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
  { id: 'today-4-5', day: 'TODAY', time: '4–5 pm', full: false, label: 'Today 4–5 pm' },
  { id: 'today-full', day: 'TODAY', time: 'Full', full: true, label: 'Today Full' },
  { id: 'today-6-7', day: 'TODAY', time: '6–7 pm', full: false, label: 'Today 6–7 pm' },
  { id: 'tomorrow-10-11', day: 'TOMORROW', time: '10–11 am', full: false, label: 'Tomorrow 10–11 am' },
  { id: 'tomorrow-11-12', day: 'TOMORROW', time: '11–12 pm', full: false, label: 'Tomorrow 11–12 pm' },
  { id: 'tomorrow-12-1', day: 'TOMORROW', time: '12–1 pm', full: false, label: 'Tomorrow 12–1 pm' },
];

const currentSlot = computed(() => {
  return timeSlots.find(s => s.id === selectedSlot.value) || timeSlots[0];
});

const checkoutTotal = computed(() => {
  return store.total > 0 ? store.total.toFixed(2) : '81.57';
});

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
  <Head title="Checkout — Masala Mart" />

  <StoreLayout :showHeader="false" :showFooter="false" :showCartBar="false" :showBottomNav="false">
    <div class="space-y-6 max-w-4xl mx-auto pb-24 sm:pb-8 pt-2 sm:pt-4">

      <!-- Breadcrumbs / Top Navigation (Screen 1e: < Checkout) -->
      <div class="flex items-center gap-1">
        <Link href="/cart" class="inline-flex items-center gap-1 text-[#1d1d1f] hover:text-[#a47a3c] transition-colors -ml-1">
          <ChevronLeft class="w-6 h-6 stroke-[2.5]" />
          <h1 class="text-xl sm:text-2xl font-serif font-medium tracking-tight text-[#1d1d1f]">Checkout</h1>
        </Link>
      </div>

      <!-- Order Confirmation State -->
      <div v-if="orderPlaced" class="bg-white rounded-3xl border border-[#e0d9cc] p-6 sm:p-10 text-center space-y-6 shadow-sm">
        <div class="w-16 h-16 bg-[#f5eee2] text-[#7a5620] rounded-full flex items-center justify-center mx-auto border-2 border-[#e0d9cc]">
          <CheckCircle2 class="w-9 h-9 stroke-[2.2]" />
        </div>
        
        <div>
          <span class="text-[11px] font-semibold uppercase tracking-widest text-[#7a5620] bg-[#f5eee2] px-3 py-1 rounded-full border border-[#e0d9cc]">
            ORDER CONFIRMED #MM-88492
          </span>
          <h2 class="text-2xl sm:text-4xl text-[#1d1d1f] tracking-tight mt-3 leading-tight font-serif font-medium">
            Thank you for your order!
          </h2>
          <p class="text-sm text-[#6e6e73] mt-2.5 max-w-md mx-auto font-normal leading-relaxed">
            Your items are being hand-picked at <strong>{{ store.selectedStore }}</strong> and will be ready for pickup <strong>{{ currentSlot.label }}</strong>.
          </p>
        </div>

        <div class="max-w-md mx-auto bg-[#f3efe7] rounded-2xl p-5 border border-[#e0d9cc] text-left space-y-3 text-xs">
          <div class="flex justify-between items-center">
            <span class="text-[#6e6e73] font-normal">Fulfillment Window:</span>
            <span class="font-bold text-[#1d1d1f]">{{ currentSlot.label }}</span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-[#6e6e73] font-normal">Pickup Location:</span>
            <span class="font-bold text-[#1d1d1f]">214 Main St. · Curbside Bay 3</span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-[#6e6e73] font-normal">Total Paid:</span>
            <span class="font-serif font-medium text-lg text-[#1d1d1f]">${{ store.total.toFixed(2) }}</span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-[#6e6e73] font-normal">Masala Points Earned:</span>
            <span class="font-bold text-[#7a5620]">+{{ Math.floor(store.subtotal) }} pts</span>
          </div>
        </div>

        <div class="flex flex-col sm:flex-row justify-center gap-3 pt-3">
          <Link 
            href="/account" 
            class="px-6 py-3.5 bg-[#ece7de] text-[#1d1d1f] font-semibold text-xs rounded-full text-center hover:bg-[#e0d9cc] transition-colors"
          >
            View Account & Orders
          </Link>
          <Link 
            href="/" 
            class="px-6 py-3.5 bg-[#1a1a1a] text-white font-semibold text-xs rounded-full text-center hover:bg-black transition-colors shadow-sm"
          >
            Continue Shopping
          </Link>
        </div>
      </div>

      <!-- Main Checkout Flow (Exact Match to Screen 1e) -->
      <div v-else class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8 items-start">
        
        <!-- LEFT COLUMN: Form Steps (Screen 1e exact layout) -->
        <div class="lg:col-span-7 space-y-6">
          
          <!-- STEP 01: HOW DO YOU WANT IT? -->
          <div class="space-y-3.5">
            <div class="text-[11px] font-semibold text-stone-400 uppercase tracking-wider">
              01 · HOW DO YOU WANT IT?
            </div>

            <!-- Two-Cell Switch (Screen 1e) -->
            <div class="grid grid-cols-2 gap-3">
              <!-- In-store pickup Card (Selected dark state) -->
              <button 
                type="button"
                @click="deliveryMethod = 'pickup'"
                :class="[
                  'p-4 rounded-2xl text-left transition-all flex flex-col justify-between cursor-pointer min-h-[92px]',
                  deliveryMethod === 'pickup' 
                    ? 'bg-[#1a1a1a] text-white shadow-md' 
                    : 'bg-white text-[#1d1d1f] border border-[#e0d9cc] hover:border-[#a47a3c]'
                ]"
              >
                <div class="flex items-center justify-between">
                  <Store :class="['w-5 h-5 stroke-[2]', deliveryMethod === 'pickup' ? 'text-white' : 'text-[#1d1d1f]']" />
                </div>
                <div class="mt-2">
                  <div class="font-bold text-sm">In-store pickup</div>
                  <div :class="['text-xs font-normal mt-0.5', deliveryMethod === 'pickup' ? 'text-stone-300' : 'text-[#6e6e73]']">
                    Free · ready in 1 hr
                  </div>
                </div>
              </button>

              <!-- Local delivery Card -->
              <button 
                type="button"
                @click="deliveryMethod = 'delivery'"
                :class="[
                  'p-4 rounded-2xl text-left transition-all flex flex-col justify-between cursor-pointer min-h-[92px]',
                  deliveryMethod === 'delivery' 
                    ? 'bg-[#1a1a1a] text-white shadow-md' 
                    : 'bg-white text-[#1d1d1f] border border-[#e0d9cc] hover:border-[#a47a3c]'
                ]"
              >
                <div class="flex items-center justify-between">
                  <Truck :class="['w-5 h-5 stroke-[2]', deliveryMethod === 'delivery' ? 'text-white' : 'text-[#1d1d1f]']" />
                </div>
                <div class="mt-2">
                  <div class="font-bold text-sm">Local delivery</div>
                  <div :class="['text-xs font-normal mt-0.5', deliveryMethod === 'delivery' ? 'text-stone-300' : 'text-[#6e6e73]']">
                    Free on this order
                  </div>
                </div>
              </button>
            </div>

            <!-- Store Location details (Screen 1e) -->
            <div class="pt-1">
              <template v-if="deliveryMethod === 'pickup'">
                <div class="font-bold text-sm text-[#1d1d1f]">Masala Mart — Main St.</div>
                <div class="text-xs text-[#6e6e73] font-normal">214 Main St. · curbside bay or counter</div>
              </template>
              <template v-else>
                <div class="font-bold text-sm text-[#1d1d1f]">Local Delivery Address</div>
                <div class="text-xs text-[#6e6e73] font-normal">742 Evergreen Terrace, Apt 4B · Contactless delivery</div>
              </template>
            </div>

            <!-- Pick a time slot (Screen 1e: 3x2 Grid) -->
            <div class="space-y-2.5 pt-2">
              <div class="text-xs font-semibold text-[#1d1d1f]">
                Pick a time slot
              </div>

              <div class="grid grid-cols-3 gap-2.5">
                <button
                  v-for="slot in timeSlots"
                  :key="slot.id"
                  type="button"
                  :disabled="slot.full"
                  @click="!slot.full ? selectedSlot = slot.id : null"
                  :class="[
                    'p-3 rounded-2xl text-left transition-all flex flex-col justify-between min-h-[68px]',
                    slot.full 
                      ? 'bg-[#ece7de] text-[#86868b] cursor-not-allowed opacity-75' 
                      : (selectedSlot === slot.id 
                          ? 'bg-[#1a1a1a] text-white shadow-xs cursor-pointer' 
                          : 'bg-white text-[#1d1d1f] border border-[#e0d9cc] hover:border-[#1a1a1a] cursor-pointer')
                  ]"
                >
                  <span :class="['text-[9px] font-bold uppercase tracking-wider', selectedSlot === slot.id ? 'text-stone-300' : 'text-[#86868b]']">
                    {{ slot.day }}
                  </span>
                  <span :class="['text-sm font-bold', selectedSlot === slot.id ? 'text-white' : (slot.full ? 'text-[#86868b]' : 'text-[#1d1d1f]')]">
                    {{ slot.time }}
                  </span>
                </button>
              </div>
            </div>
          </div>

          <!-- STEP 02: PAYMENT (Screen 1e) -->
          <div class="space-y-3.5 pt-3 border-t border-[#e0d9cc]">
            <div class="text-[11px] font-semibold text-[#86868b] uppercase tracking-wider">
              02 · PAYMENT
            </div>

            <!-- Apple Pay / Google Pay Pills (Screen 1e) -->
            <div class="grid grid-cols-2 gap-3">
              <button 
                type="button"
                @click="paymentMethod = 'apple-pay'"
                :class="[
                  'h-12 bg-[#1a1a1a] hover:bg-black text-white font-semibold text-sm rounded-full flex items-center justify-center gap-1.5 transition-all cursor-pointer shadow-xs active:scale-[0.99]',
                  paymentMethod === 'apple-pay' ? 'ring-2 ring-[#a47a3c]' : ''
                ]"
                aria-label="Apple Pay"
              >
                <IconApplePay :width="50" :height="22" class="text-white" />
              </button>

              <button 
                type="button"
                @click="paymentMethod = 'google-pay'"
                :class="[
                  'h-12 bg-[#1a1a1a] hover:bg-black text-white font-semibold text-sm rounded-full flex items-center justify-center gap-1.5 transition-all cursor-pointer shadow-xs active:scale-[0.99]',
                  paymentMethod === 'google-pay' ? 'ring-2 ring-[#a47a3c]' : ''
                ]"
                aria-label="Google Pay"
              >
                <IconGooglePay :width="54" :height="22" class="text-white" />
              </button>
            </div>

            <!-- "or pay by card" divider -->
            <div class="relative flex items-center justify-center my-3">
              <div class="border-t border-[#e0d9cc] w-full"></div>
              <span class="bg-[#fbf9f5] px-3 text-[11px] font-normal text-[#86868b] absolute">
                or pay by card
              </span>
            </div>

            <!-- Card Inputs (Screen 1e: #f3efe7 filled rounded inputs) -->
            <div class="space-y-3">
              <div>
                <label class="block text-xs font-normal text-[#6e6e73] mb-1">
                  Card number
                </label>
                <div class="relative flex items-center">
                  <input 
                    type="text" 
                    placeholder="1234 1234 1234 1234"
                    value="1234 1234 1234 1234"
                    class="w-full px-4 py-3 bg-[#f3efe7] border border-[#e0d9cc] rounded-xl text-xs font-normal text-[#1d1d1f] placeholder-[#86868b] focus:bg-white focus:ring-2 focus:ring-[#1a1a1a]/10 focus:outline-none"
                  />
                  <!-- Card brand badges (Screen 1e: VISA, MC, AMEX) -->
                  <div class="absolute right-3 flex items-center gap-1">
                    <span class="text-[9px] font-bold font-mono px-1.5 py-0.5 bg-white border border-[#e0d9cc] text-[#1d1d1f] rounded-sm">VISA</span>
                    <span class="text-[9px] font-bold font-mono px-1.5 py-0.5 bg-white border border-[#e0d9cc] text-[#1d1d1f] rounded-sm">MC</span>
                    <span class="text-[9px] font-bold font-mono px-1.5 py-0.5 bg-white border border-[#e0d9cc] text-[#1d1d1f] rounded-sm">AMEX</span>
                  </div>
                </div>
              </div>

              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs font-normal text-[#6e6e73] mb-1">
                    Expiry
                  </label>
                  <input 
                    type="text" 
                    placeholder="MM / YY" 
                    value="MM / YY"
                    class="w-full px-4 py-3 bg-[#f3efe7] border border-[#e0d9cc] rounded-xl text-xs font-normal text-[#1d1d1f] placeholder-[#86868b] focus:bg-white focus:ring-2 focus:ring-[#1a1a1a]/10 focus:outline-none"
                  />
                </div>
                <div>
                  <label class="block text-xs font-normal text-[#6e6e73] mb-1">
                    CVC
                  </label>
                  <input 
                    type="text" 
                    placeholder="123" 
                    value="123"
                    class="w-full px-4 py-3 bg-[#f3efe7] border border-[#e0d9cc] rounded-xl text-xs font-normal text-[#1d1d1f] placeholder-[#86868b] focus:bg-white focus:ring-2 focus:ring-[#1a1a1a]/10 focus:outline-none"
                  />
                </div>
              </div>
            </div>

            <!-- Desktop Pay Button -->
            <div class="pt-2 hidden sm:block">
              <button 
                type="button"
                @click="completeOrder"
                :disabled="isProcessing"
                class="w-full h-13 py-3.5 bg-[#1a1a1a] hover:bg-black text-white font-semibold text-sm rounded-full transition-all cursor-pointer flex items-center justify-between px-6 shadow-md active:scale-[0.99]"
              >
                <div class="flex items-center gap-2">
                  <Lock class="w-4 h-4 stroke-[2.2]" />
                  <span>{{ isProcessing ? 'Authorizing Payment...' : `Pay · ${currentSlot.label}` }}</span>
                </div>
                <span class="font-serif font-medium text-base">${{ checkoutTotal }}</span>
              </button>
            </div>

          </div>

        </div>

        <!-- RIGHT COLUMN: Order Review on Desktop -->
        <div class="lg:col-span-5 space-y-4">
          <div class="bg-white rounded-3xl border border-[#e0d9cc] p-6 space-y-4 shadow-xs">
            <div class="border-b border-[#e0d9cc]/60 pb-3 flex items-center justify-between">
              <h2 class="text-base font-serif font-medium text-[#1d1d1f] tracking-tight leading-none">
                Order Review
              </h2>
              <span class="text-xs font-normal text-[#6e6e73]">
                {{ store.totalItemCount }} items
              </span>
            </div>

            <!-- Items Quick View -->
            <div class="max-h-60 overflow-y-auto divide-y divide-[#e0d9cc]/60 pr-1">
              <div 
                v-for="item in store.cartItems" 
                :key="item.id"
                class="py-2.5 flex items-center justify-between gap-3 text-xs"
              >
                <div class="flex items-center gap-3">
                  <div class="w-11 h-11 rounded-xl bg-[#f3efe7] border border-[#e0d9cc] overflow-hidden shrink-0 relative">
                    <img 
                      :src="item.image || (item.name.toLowerCase().includes('atta') ? '/images/products/atta.jpg' : (item.name.toLowerCase().includes('ghee') ? '/images/products/ghee.jpg' : (item.name.toLowerCase().includes('paneer') ? '/images/products/paneer.jpg' : (item.name.toLowerCase().includes('rice') ? '/images/products/rice.jpg' : (item.name.toLowerCase().includes('biscuit') ? '/images/products/biscuits.jpg' : (item.name.toLowerCase().includes('masala') ? '/images/products/garam_masala.jpg' : '/images/products/sweets.jpg'))))))" 
                      :alt="item.name" 
                      class="w-full h-full object-cover object-center"
                    />
                  </div>

                  <div>
                    <div class="font-semibold text-[#1d1d1f] leading-snug">{{ item.name }}</div>
                    <div class="text-[11px] text-[#6e6e73] font-normal">{{ item.quantity }}x · {{ item.weight }}</div>
                  </div>
                </div>

                <div class="font-serif font-medium text-[#1d1d1f] shrink-0 text-right">
                  ${{ (item.price * item.quantity).toFixed(2) }}
                </div>
              </div>
            </div>

            <!-- Price Breakdown -->
            <div class="pt-3 border-t border-[#e0d9cc]/60 space-y-2 text-xs">
              <div class="flex justify-between text-[#6e6e73] font-normal">
                <span>Subtotal</span>
                <span class="font-serif font-medium text-[#1d1d1f]">${{ store.subtotal.toFixed(2) }}</span>
              </div>
              <div class="flex justify-between text-[#6e6e73] font-normal">
                <span>Fulfillment (Pickup)</span>
                <span class="text-[#7a5620] font-semibold">FREE</span>
              </div>
              
              <div class="pt-2.5 border-t border-[#e0d9cc]/60 flex justify-between items-baseline text-[#1d1d1f]">
                <span class="font-serif font-medium text-sm">Total Due</span>
                <span class="font-serif font-medium text-2xl text-[#1d1d1f]">
                  ${{ store.total.toFixed(2) }}
                </span>
              </div>
            </div>
          </div>
        </div>

      </div>

      <!-- Mobile Sticky Bottom Pay Bar (Exact Match to Screen 1e) -->
      <div 
        v-if="!orderPlaced"
        class="sm:hidden fixed bottom-0 left-0 right-0 z-40 bg-white/95 backdrop-blur-md border-t border-[#e0d9cc] p-3.5 shadow-2xl"
      >
        <button 
          type="button"
          @click="completeOrder"
          :disabled="isProcessing"
          class="w-full h-13 py-3.5 bg-[#1a1a1a] active:bg-black text-white font-semibold text-sm rounded-full cursor-pointer flex items-center justify-between px-6 shadow-md"
        >
          <div class="flex items-center gap-2">
            <Lock class="w-4 h-4 stroke-[2.2]" />
            <span v-if="isProcessing">Authorizing...</span>
            <span v-else>Pay · {{ currentSlot.label }}</span>
          </div>
          <span class="font-serif font-medium text-base">${{ checkoutTotal }}</span>
        </button>
      </div>

    </div>
  </StoreLayout>
</template>
