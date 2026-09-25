<script setup>
import { ref, computed } from 'vue';
import { useStore } from '../stores/cart';
import { X, Plus, Minus, Trash2, ShoppingBag, ArrowRight, CheckCircle2, Tag } from 'lucide-vue-next';

const store = useStore();
const promoCode = ref('');
const promoApplied = ref(false);
const discountPercent = ref(0);
const isOrderPlaced = ref(false);

function applyPromo() {
  if (promoCode.value.trim().toUpperCase() === 'DIWALI2026' || promoCode.value.trim().toUpperCase() === 'MASALA10') {
    promoApplied.value = true;
    discountPercent.value = 10;
  } else {
    alert('Invalid code! Try "DIWALI2026" or "MASALA10" for 10% off.');
  }
}

const discountAmount = computed(() => {
  return promoApplied.value ? (store.subtotal * discountPercent.value) / 100 : 0;
});

const finalTotal = computed(() => {
  return Math.max(0, store.subtotal - discountAmount.value);
});

function handleCheckout() {
  if (store.cartItems.length === 0) return;
  isOrderPlaced.value = true;
  setTimeout(() => {
    store.clearCart();
  }, 500);
}

function closeDrawer() {
  store.isCartDrawerOpen = false;
  isOrderPlaced.value = false;
}
</script>

<template>
  <div 
    v-if="store.isCartDrawerOpen" 
    class="fixed inset-0 z-50 overflow-hidden bg-black/60 backdrop-blur-xs flex justify-end"
  >
    <div 
      class="w-full max-w-md bg-white h-full flex flex-col border-l-2 border-zinc-950 shadow-2xl rounded-none animate-in slide-in-from-right duration-300"
    >
      <!-- Header -->
      <div class="p-4 border-b-2 border-zinc-950 flex items-center justify-between bg-zinc-50 rounded-none">
        <div class="flex items-center gap-2.5">
          <div class="w-9 h-9 rounded-none bg-[#E52E04] text-white flex items-center justify-center font-bold border border-[#B82200]">
            <ShoppingBag class="w-4 h-4" />
          </div>
          <div>
            <h2 class="text-lg font-black text-zinc-950 leading-none uppercase tracking-tight">Your Cart</h2>
            <p class="text-xs text-zinc-600 mt-0.5 font-medium">{{ store.selectedStore }} · {{ store.readyTime }}</p>
          </div>
        </div>
        <button 
          @click="closeDrawer"
          class="p-1.5 text-zinc-500 hover:text-zinc-950 hover:bg-zinc-200 rounded-none transition-colors cursor-pointer"
        >
          <X class="w-5 h-5" />
        </button>
      </div>

      <!-- Order Placed View -->
      <div v-if="isOrderPlaced" class="flex-1 p-6 flex flex-col items-center justify-center text-center">
        <div class="w-16 h-16 bg-emerald-100 text-emerald-700 rounded-none border border-emerald-300 flex items-center justify-center mb-4">
          <CheckCircle2 class="w-10 h-10" />
        </div>
        <h3 class="text-2xl font-black text-zinc-950 uppercase tracking-tight">Order Confirmed!</h3>
        <p class="text-sm text-zinc-600 mt-2 max-w-xs font-medium">
          Ready for pickup at <span class="font-bold text-zinc-950">{{ store.selectedStore }}</span> in 1 hour.
        </p>

        <button 
          @click="closeDrawer"
          class="mt-6 w-full py-3 bg-[#E52E04] text-white font-black text-xs uppercase tracking-wider rounded-none hover:bg-[#CC2500] transition-all cursor-pointer border border-[#B82200]"
        >
          Continue Shopping
        </button>
      </div>

      <!-- Main Cart Items List View -->
      <template v-else>
        <div v-if="store.cartItems.length > 0" class="flex-1 overflow-y-auto p-4 space-y-3">
          <!-- Free delivery tracker banner -->
          <div class="p-3 bg-orange-50 border border-orange-300 rounded-none flex items-center justify-between text-xs font-bold text-zinc-800">
            <span>
              <template v-if="store.amountToFreeDelivery > 0">
                Add <span class="text-[#E52E04] font-black">${{ store.amountToFreeDelivery.toFixed(2) }}</span> more for FREE Delivery!
              </template>
              <template v-else>
                🎉 FREE Pickup & Delivery Unlocked!
              </template>
            </span>
          </div>

          <!-- Items loop with sharp zero-rounded corners -->
          <div 
            v-for="item in store.cartItems" 
            :key="item.id"
            class="p-3 bg-zinc-50 border border-zinc-300 rounded-none flex gap-3 items-center"
          >
            <img 
              :src="item.image" 
              :alt="item.name" 
              class="w-16 h-16 object-cover border border-zinc-400 shrink-0 bg-white rounded-none"
            />
            
            <div class="flex-1 min-w-0">
              <div class="flex justify-between items-start">
                <div>
                  <h4 class="font-black text-sm text-zinc-950 truncate">{{ item.name }}</h4>
                  <p class="text-xs text-zinc-500 font-medium">{{ item.size }}</p>
                </div>
                <button 
                  @click="store.deleteItem(item.id)"
                  class="text-zinc-400 hover:text-red-600 p-1 transition-colors"
                >
                  <Trash2 class="w-4 h-4" />
                </button>
              </div>

              <div class="flex items-center justify-between mt-2">
                <span class="font-black text-base text-zinc-950">${{ item.price.toFixed(2) }}</span>

                <!-- Red Quantity Controls -->
                <div class="flex items-center gap-2 bg-[#E52E04] text-white rounded-none px-2 py-1 border border-[#B82200]">
                  <button 
                    @click="store.removeFromCart(item.id)"
                    class="hover:bg-black/20 p-0.5 cursor-pointer"
                  >
                    <Minus class="w-3.5 h-3.5 stroke-[3]" />
                  </button>
                  <span class="text-xs font-black w-4 text-center select-none">{{ item.quantity }}</span>
                  <button 
                    @click="store.addToCart(item.id)"
                    class="hover:bg-black/20 p-0.5 cursor-pointer"
                  >
                    <Plus class="w-3.5 h-3.5 stroke-[3]" />
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Coupon Input -->
          <div class="pt-2">
            <div class="flex gap-2">
              <div class="relative flex-1">
                <Tag class="w-4 h-4 absolute left-3 top-3 text-zinc-400" />
                <input 
                  v-model="promoCode"
                  type="text" 
                  placeholder="PROMO CODE (DIWALI2026)"
                  class="w-full pl-9 pr-3 py-2 bg-white border border-zinc-400 text-xs font-mono font-bold uppercase rounded-none focus:ring-2 focus:ring-[#E52E04] focus:outline-none"
                />
              </div>
              <button 
                @click="applyPromo"
                class="px-4 py-2 bg-zinc-950 text-white font-black text-xs uppercase tracking-wider rounded-none hover:bg-zinc-800 cursor-pointer"
              >
                Apply
              </button>
            </div>
            <p v-if="promoApplied" class="text-[11px] text-emerald-700 font-bold mt-1">
              ✓ Promo applied! 10% off subtotal.
            </p>
          </div>
        </div>

        <!-- Empty Cart -->
        <div v-else class="flex-1 p-6 flex flex-col items-center justify-center text-center text-zinc-500">
          <ShoppingBag class="w-12 h-12 stroke-[1.5] text-zinc-300 mb-2" />
          <p class="font-bold text-zinc-800">Your cart is empty</p>
          <p class="text-xs text-zinc-500 mt-1">Add items from the store catalog.</p>
        </div>

        <!-- Footer Checkout Summary -->
        <div v-if="store.cartItems.length > 0" class="p-4 border-t-2 border-zinc-950 bg-white space-y-2 rounded-none">
          <div class="flex justify-between text-xs text-zinc-600 font-medium">
            <span>Subtotal</span>
            <span>${{ store.subtotal.toFixed(2) }}</span>
          </div>
          <div v-if="promoApplied" class="flex justify-between text-xs text-emerald-700 font-bold">
            <span>Discount (10%)</span>
            <span>-${{ discountAmount.toFixed(2) }}</span>
          </div>
          <div class="flex justify-between text-xs text-zinc-600 font-medium">
            <span>Pickup / Delivery</span>
            <span class="text-emerald-700 font-bold">FREE</span>
          </div>

          <div class="flex justify-between text-lg font-black text-zinc-950 pt-2 border-t border-zinc-200">
            <span>Total</span>
            <span>${{ finalTotal.toFixed(2) }}</span>
          </div>

          <button 
            @click="handleCheckout"
            class="w-full py-3.5 bg-[#E52E04] hover:bg-[#CC2500] text-white font-black text-xs uppercase tracking-wider rounded-none flex items-center justify-center gap-2 border border-[#B82200] active:scale-[0.98] transition-all cursor-pointer mt-3"
          >
            <span>Checkout · ${{ finalTotal.toFixed(2) }}</span>
            <ArrowRight class="w-4 h-4 stroke-[3]" />
          </button>
        </div>
      </template>
    </div>
  </div>
</template>
