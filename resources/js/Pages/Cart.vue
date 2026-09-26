<script setup>
import { Head, Link } from '@inertiajs/vue3';
import StoreLayout from '../Layouts/StoreLayout.vue';
import { useStore } from '../stores/cart';
import { 
  ChevronLeft, 
  Trash2, 
  Plus, 
  Minus, 
  Sparkles, 
  ArrowRight, 
  ShieldCheck, 
  CheckCircle2, 
  Clock,
  ShoppingBag
} from 'lucide-vue-next';

const store = useStore();
</script>

<template>
  <Head title="Shopping Cart — Masala Mart" />

  <StoreLayout 
    headerMode="cart" 
    headerTitle="Shopping Cart" 
    backUrl="/" 
    :showCartBar="false" 
    :showBottomNav="false"
  >
    <div class="space-y-6 pb-20 sm:pb-4 max-w-5xl mx-auto">

      <!-- Breadcrumbs -->
      <nav class="flex items-center gap-2 text-xs font-normal text-stone-500">
        <Link href="/" class="hover:text-stone-900 flex items-center gap-1 font-semibold text-stone-700">
          <ChevronLeft class="w-4 h-4" />
          <span>Continue Shopping</span>
        </Link>
        <span>/</span>
        <span class="text-stone-900 font-bold">Cart ({{ store.totalItemCount }} items)</span>
      </nav>

      <!-- Cart Header & Free Delivery Meter -->
      <div class="bg-[#1a1a1a] text-white p-5 sm:p-6 rounded-3xl shadow-sm border border-[#e0d9cc]/20">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div>
            <div class="text-[10px] sm:text-xs font-semibold uppercase tracking-wider text-[#a47a3c]">
              ORDER PROGRESS
            </div>
            <div class="text-base sm:text-lg font-serif font-medium tracking-tight mt-0.5">
              <template v-if="store.amountToFreeDelivery > 0">
                Add ${{ store.amountToFreeDelivery.toFixed(2) }} more to unlock free delivery
              </template>
              <template v-else>
                <span class="text-[#a47a3c] flex items-center gap-1.5">
                  <Sparkles class="w-4 h-4 text-[#a47a3c]" />
                  <span>You've unlocked FREE Home Delivery!</span>
                </span>
              </template>
            </div>
          </div>
          <div class="text-left sm:text-right text-xs text-stone-400 font-normal">
            <span>Threshold: $40.00</span>
            <span class="mx-1.5">·</span>
            <span class="text-white font-semibold">{{ Math.round(store.freeDeliveryProgressPercent) }}% complete</span>
          </div>
        </div>

        <!-- Brass Accent Progress Bar -->
        <div class="w-full bg-stone-800 h-1.5 rounded-full mt-3.5 overflow-hidden">
          <div 
            class="bg-[#a47a3c] h-full rounded-full transition-all duration-300"
            :style="{ width: store.freeDeliveryProgressPercent + '%' }"
          ></div>
        </div>
      </div>

      <!-- Main Cart Layout: 2 Columns on Desktop -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8 items-start">
        
        <!-- LEFT COLUMN: Cart Items & Impulse Add-ons (lg:col-span-8) -->
        <div class="lg:col-span-8 space-y-6">
          
          <!-- Empty State -->
          <div v-if="store.cartItems.length === 0" class="bg-white rounded-3xl border border-[#e0d9cc] p-12 text-center space-y-4 shadow-xs">
            <div class="w-16 h-16 rounded-full bg-[#f3efe7] flex items-center justify-center mx-auto text-[#6e6e73]">
              <ShoppingBag class="w-8 h-8 stroke-[1.8]" />
            </div>
            <h2 class="text-xl font-serif font-medium text-[#1d1d1f]">Your cart is empty</h2>
            <p class="text-xs text-[#6e6e73] font-normal max-w-sm mx-auto">
              Add some freshly batched paneer, authentic spices, or fragrant basmati rice to get started.
            </p>
            <Link 
              href="/"
              class="inline-flex items-center gap-2 px-6 py-3 bg-[#1a1a1a] hover:bg-black text-white text-xs font-semibold rounded-full shadow-sm transition-colors"
            >
              <span>Start Shopping</span>
              <ArrowRight class="w-3.5 h-3.5" />
            </Link>
          </div>

          <!-- Items Table / List -->
          <div v-else class="bg-white rounded-3xl border border-[#e0d9cc] divide-y divide-[#e0d9cc]/60 overflow-hidden shadow-xs">
            <div 
              v-for="item in store.cartItems" 
              :key="item.id"
              class="p-4 sm:p-5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 sm:gap-4"
            >
              <!-- Item Details -->
              <div class="flex items-center gap-3 sm:gap-4 w-full sm:w-auto">
                <div class="w-16 h-16 rounded-2xl bg-[#f3efe7] border border-[#e0d9cc] overflow-hidden shrink-0 relative">
                  <img 
                    :src="item.image || (item.name.toLowerCase().includes('atta') ? '/images/products/atta.jpg' : (item.name.toLowerCase().includes('ghee') ? '/images/products/ghee.jpg' : (item.name.toLowerCase().includes('paneer') ? '/images/products/paneer.jpg' : (item.name.toLowerCase().includes('rice') ? '/images/products/rice.jpg' : (item.name.toLowerCase().includes('biscuit') ? '/images/products/biscuits.jpg' : (item.name.toLowerCase().includes('masala') ? '/images/products/garam_masala.jpg' : '/images/products/sweets.jpg'))))))" 
                    :alt="item.name" 
                    class="w-full h-full object-cover object-center"
                  />
                </div>
                
                <div class="flex-1">
                  <div class="flex items-center gap-2">
                    <h3 class="font-semibold text-sm text-[#1d1d1f]">{{ item.name }}</h3>
                    <span 
                      v-if="item.isSubscribed" 
                      class="px-2 py-0.5 bg-[#f5eee2] text-[#7a5620] text-[10px] font-semibold rounded-full shrink-0 border border-[#e0d9cc]"
                    >
                      Monthly · -5%
                    </span>
                  </div>
                  <div class="text-xs text-[#6e6e73] font-normal mt-0.5">{{ item.weight }}</div>
                  <div class="text-xs font-serif font-medium text-[#1d1d1f] mt-1">
                    ${{ item.price.toFixed(2) }}
                    <span v-if="item.originalPrice && item.originalPrice !== item.price" class="text-[#86868b] font-normal line-through ml-1">
                      ${{ item.originalPrice.toFixed(2) }}
                    </span>
                  </div>
                </div>
              </div>

              <!-- Quantity Controls & Delete -->
              <div class="flex items-center justify-between sm:justify-end gap-3 w-full sm:w-auto pt-2 sm:pt-0 border-t sm:border-t-0 border-[#e0d9cc]/60">
                <div class="w-24 sm:w-28 h-9 bg-[#ece7de] rounded-full flex items-center justify-between px-2.5 border border-[#e0d9cc]">
                  <button 
                    @click="store.removeFromCart(item.id)" 
                    class="p-1 hover:text-[#1a1a1a] cursor-pointer flex items-center justify-center text-[#1d1d1f]"
                    aria-label="Decrease quantity"
                  >
                    <Minus class="w-3.5 h-3.5 stroke-[2.5]" />
                  </button>
                  <span class="text-xs font-bold text-[#1d1d1f]">{{ item.quantity }}</span>
                  <button 
                    @click="store.addToCart({ id: item.id, name: item.name, weight: item.weight, price: item.price, originalPrice: item.originalPrice, isSubscribed: item.isSubscribed, image: item.image })" 
                    class="p-1 hover:text-[#1a1a1a] cursor-pointer flex items-center justify-center text-[#1d1d1f]"
                    aria-label="Increase quantity"
                  >
                    <Plus class="w-3.5 h-3.5 stroke-[2.5]" />
                  </button>
                </div>

                <div class="text-right min-w-[65px]">
                  <div class="text-base font-serif font-medium text-[#1d1d1f]">
                    ${{ (item.price * item.quantity).toFixed(2) }}
                  </div>
                </div>

                <button 
                  @click="store.deleteItem(item.id)"
                  class="text-[#86868b] hover:text-red-600 p-1.5 transition-colors cursor-pointer"
                  aria-label="Remove item"
                >
                  <Trash2 class="w-4 h-4" />
                </button>
              </div>
            </div>
          </div>

          <!-- Impulse Items (<$2 One-Tap Add Row) -->
          <div class="bg-white rounded-3xl border border-[#e0d9cc] p-5 space-y-3 shadow-xs">
            <div class="flex items-center justify-between">
              <div>
                <h3 class="font-serif font-medium text-base text-[#1d1d1f]">Quick pantry add-ons</h3>
                <p class="text-xs text-[#6e6e73] font-normal">Popular items under $2 to reach free delivery.</p>
              </div>
              <span class="text-[10px] font-semibold text-[#7a5620] bg-[#f5eee2] border border-[#e0d9cc] px-2 py-0.5 rounded-full">&lt;$2 ITEMS</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-1">
              <div 
                v-for="impulse in store.impulseItems" 
                :key="impulse.id"
                class="border border-[#e0d9cc] rounded-2xl p-3 bg-[#f3efe7] flex items-center justify-between gap-2"
              >
                <div>
                  <div class="font-semibold text-xs text-[#1d1d1f] line-clamp-1">{{ impulse.name }}</div>
                  <div class="text-[11px] text-[#6e6e73] font-normal">{{ impulse.size }} · ${{ impulse.price.toFixed(2) }}</div>
                </div>
                <button 
                  @click="store.addImpulseItem(impulse.id)"
                  class="w-7 h-7 bg-[#1a1a1a] hover:bg-black text-white rounded-full flex items-center justify-center transition-colors shrink-0 active:scale-95 cursor-pointer shadow-xs"
                  aria-label="Add impulse item"
                >
                  <Plus class="w-3.5 h-3.5 stroke-[2.5]" />
                </button>
              </div>
            </div>
          </div>

        </div>

        <!-- RIGHT COLUMN: Order Summary & Checkout CTA (Desktop) -->
        <div class="lg:col-span-4 space-y-4">
          
          <div class="bg-white rounded-3xl border border-[#e0d9cc] p-6 space-y-5 shadow-xs">
            <h2 class="text-base font-serif font-medium text-[#1d1d1f] tracking-tight border-b border-[#e0d9cc]/60 pb-3">
              Order Summary
            </h2>

            <!-- Pricing Breakdown -->
            <div class="space-y-2.5 text-xs">
              <div class="flex justify-between text-[#6e6e73] font-normal">
                <span>Subtotal ({{ store.totalItemCount }} items)</span>
                <span class="font-serif font-medium text-[#1d1d1f]">${{ store.subtotal.toFixed(2) }}</span>
              </div>
              
              <div class="flex justify-between text-[#6e6e73] font-normal">
                <span>Store Pickup ({{ store.selectedStore }})</span>
                <span class="text-[#7a5620] font-semibold">FREE</span>
              </div>

              <div class="flex justify-between text-[#6e6e73] font-normal">
                <span>Estimated Sales Tax</span>
                <span>$0.00</span>
              </div>

              <div class="pt-3 border-t border-[#e0d9cc]/60 flex justify-between items-baseline text-base text-[#1d1d1f]">
                <span class="font-serif font-medium">Total Due</span>
                <span class="text-2xl font-serif font-medium">${{ store.total.toFixed(2) }}</span>
              </div>
            </div>

            <!-- Masala Points Box -->
            <div class="bg-[#f5eee2] border border-[#e0d9cc] rounded-2xl p-3 text-xs text-[#7a5620] flex items-center gap-2">
              <Sparkles class="w-4 h-4 shrink-0 text-[#a47a3c]" />
              <span class="font-normal">You will earn <strong class="font-bold text-[#7a5620]">+{{ Math.floor(store.subtotal) }} Masala Points</strong> on this order.</span>
            </div>

            <!-- Desktop Checkout CTA Button -->
            <Link 
              href="/checkout"
              class="hidden sm:flex w-full py-4 bg-[#1a1a1a] hover:bg-black text-white font-semibold text-center text-sm rounded-full transition-colors shadow-md cursor-pointer items-center justify-between px-6"
            >
              <span>Go to checkout</span>
              <div class="flex items-center gap-1.5 font-serif font-medium">
                <span>${{ store.total.toFixed(2) }}</span>
                <ArrowRight class="w-4 h-4 font-sans" />
              </div>
            </Link>

            <div class="pt-1 text-center text-xs text-[#86868b] font-normal flex items-center justify-center gap-1.5">
              <Clock class="w-3.5 h-3.5 text-[#a47a3c]" />
              <span>Curbside Pickup Ready in 1 Hr</span>
            </div>
          </div>

          <!-- Pickup Instructions Card -->
          <div class="bg-[#f3efe7] rounded-2xl border border-[#e0d9cc] p-4 space-y-2 text-xs">
            <div class="font-bold text-[#1d1d1f] flex items-center gap-1.5">
              <Clock class="w-3.5 h-3.5 text-[#a47a3c]" />
              <span>Click & Collect Guarantee</span>
            </div>
            <p class="text-[#6e6e73] font-normal leading-relaxed text-[11px]">
              Orders are packaged in thermal insulated bags. Bring your confirmation SMS or order number to Bay 3 at 482 Main Street.
            </p>
          </div>

        </div>

      </div>

      <!-- Mobile Sticky Bottom Checkout Bar -->
      <div 
        v-if="store.cartItems.length > 0"
        class="sm:hidden fixed bottom-0 left-0 right-0 z-40 bg-white/95 backdrop-blur-md border-t border-[#e0d9cc] p-3 shadow-2xl flex items-center justify-between gap-3"
      >
        <div>
          <div class="text-[10px] text-[#86868b] uppercase leading-none font-semibold">Total Due</div>
          <div class="text-xl font-serif font-medium text-[#1d1d1f] mt-0.5 leading-none">
            ${{ store.total.toFixed(2) }}
          </div>
        </div>

        <Link 
          href="/checkout"
          class="flex-1 py-3 bg-[#1a1a1a] active:bg-black text-white font-semibold text-xs rounded-full text-center cursor-pointer flex items-center justify-between px-5 shadow-md"
        >
          <span>Go to checkout</span>
          <div class="flex items-center gap-1 font-serif font-medium text-sm">
            <span>${{ store.total.toFixed(2) }}</span>
            <ArrowRight class="w-3.5 h-3.5 font-sans" />
          </div>
        </Link>
      </div>

    </div>
  </StoreLayout>
</template>
