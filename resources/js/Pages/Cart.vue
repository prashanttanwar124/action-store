<script setup>
import { computed } from 'vue';
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
  Clock
} from 'lucide-vue-next';
import IconBag from '../Components/Icons/IconBag.vue';

const store = useStore();
</script>

<template>
  <Head title="Shopping Cart — Masala Mart" />

  <StoreLayout :showCartBar="false" :showBottomNav="false">
    <div class="space-y-6 pb-20 sm:pb-0">

      <!-- Breadcrumbs -->
      <nav class="flex items-center gap-2 text-xs font-mono text-zinc-500">
        <Link href="/" class="hover:text-zinc-950 flex items-center gap-1 font-bold">
          <ChevronLeft class="w-4 h-4" />
          <span>Continue Shopping</span>
        </Link>
        <span>/</span>
        <span class="text-zinc-950 font-bold">Cart ({{ store.totalItemCount }} items)</span>
      </nav>

      <!-- Cart Header & Free Delivery Meter (Screen 1d) -->
      <div class="bg-zinc-950 text-white p-4 sm:p-5 border border-zinc-900 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
          <div>
            <div class="text-[10px] sm:text-xs font-mono font-bold uppercase tracking-wider text-[#E52E04]">
              ORDER PROGRESS
            </div>
            <div class="text-base sm:text-lg font-black tracking-tight mt-0.5">
              <template v-if="store.amountToFreeDelivery > 0">
                Add ${{ store.amountToFreeDelivery.toFixed(2) }} more to unlock free delivery
              </template>
              <template v-else>
                <span class="text-amber-400 flex items-center gap-1.5">
                  <Sparkles class="w-4 h-4 text-amber-400" />
                  <span>You've unlocked FREE Home Delivery!</span>
                </span>
              </template>
            </div>
          </div>
          <div class="text-left sm:text-right font-mono text-[11px] sm:text-xs text-zinc-400">
            <span>Threshold: $40.00</span>
            <span class="mx-1.5">·</span>
            <span class="text-white font-bold">{{ store.freeDeliveryProgressPercent }}% complete</span>
          </div>
        </div>

        <!-- Red Progress Bar -->
        <div class="w-full bg-zinc-800 h-2 mt-3 overflow-hidden">
          <div 
            class="bg-[#E52E04] h-full transition-all duration-300"
            :style="{ width: store.freeDeliveryProgressPercent + '%' }"
          ></div>
        </div>
      </div>

      <!-- Main Cart Layout: 2 Columns on Desktop -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8 items-start">
        
        <!-- LEFT COLUMN: Cart Items & Impulse Add-ons (lg:col-span-8) -->
        <div class="lg:col-span-8 space-y-6">
          
          <!-- Empty State -->
          <div v-if="store.cartItems.length === 0" class="bg-white border border-zinc-300 p-12 text-center space-y-4">
            <IconBag :size="48" class="text-zinc-400 mx-auto" />
            <h2 class="text-xl font-black text-zinc-950">Your cart is empty</h2>
            <p class="text-xs text-zinc-500 max-w-sm mx-auto">
              Add some freshly batched paneer, authentic spices, or fragrant basmati rice to get started.
            </p>
            <Link 
              href="/"
              class="inline-flex items-center gap-1.5 px-6 py-3 bg-[#E52E04] text-white text-xs font-black uppercase tracking-wider border border-[#B82200]"
            >
              <span>Start Shopping</span>
              <ArrowRight class="w-3.5 h-3.5" />
            </Link>
          </div>

          <!-- Items Table / List -->
          <div v-else class="bg-white border border-zinc-300 divide-y divide-zinc-200">
            <div 
              v-for="item in store.cartItems" 
              :key="item.id"
              class="p-3.5 sm:p-5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 sm:gap-4"
            >
              <!-- Item Details -->
              <div class="flex items-center gap-3 sm:gap-4 w-full sm:w-auto">
                <div class="w-14 h-14 sm:w-16 sm:h-16 bg-[#EAEAEA] border border-zinc-300 p-1 flex items-center justify-center text-zinc-600 bg-stripes shrink-0">
                  <span class="photo-label text-[9px] sm:text-[10px] text-center leading-tight font-bold">
                    {{ item.name.toLowerCase().includes('atta') ? 'atta bag' : (item.name.toLowerCase().includes('ghee') ? 'ghee jar' : (item.name.toLowerCase().includes('paneer') ? 'paneer block' : (item.name.toLowerCase().includes('rice') ? 'rice sack' : 'photo label'))) }}
                  </span>
                </div>
                
                <div class="flex-1">
                  <div class="flex items-center gap-2">
                    <h3 class="font-black text-xs sm:text-sm text-zinc-950">{{ item.name }}</h3>
                    <span 
                      v-if="item.isSubscribed" 
                      class="px-1.5 py-0.5 bg-red-100 text-[#E52E04] text-[9px] font-mono font-bold uppercase shrink-0"
                    >
                      Monthly · -5%
                    </span>
                  </div>
                  <div class="text-[11px] sm:text-xs text-zinc-500 font-mono mt-0.5">{{ item.weight }}</div>
                  <div class="text-xs font-mono font-black text-zinc-950 mt-1">
                    ${{ item.price.toFixed(2) }}
                    <span v-if="item.originalPrice && item.originalPrice !== item.price" class="text-zinc-400 line-through ml-1">
                      ${{ item.originalPrice.toFixed(2) }}
                    </span>
                  </div>
                </div>
              </div>

              <!-- Quantity Controls & Delete -->
              <div class="flex items-center justify-between sm:justify-end gap-3 w-full sm:w-auto pt-2 sm:pt-0 border-t sm:border-t-0 border-zinc-100">
                <div class="w-24 sm:w-28 h-8 sm:h-9 bg-zinc-100 border border-zinc-400 flex items-center justify-between px-2">
                  <button 
                    @click="store.removeFromCart(item.id)" 
                    class="p-1 hover:text-[#E52E04] cursor-pointer flex items-center justify-center text-zinc-950"
                    aria-label="Decrease quantity"
                  >
                    <Minus class="w-3.5 h-3.5 stroke-[3]" />
                  </button>
                  <span class="text-xs font-black font-mono">{{ item.quantity }}</span>
                  <button 
                    @click="store.addToCart({ id: item.id, name: item.name, weight: item.weight, price: item.price, originalPrice: item.originalPrice, isSubscribed: item.isSubscribed })" 
                    class="p-1 hover:text-[#E52E04] cursor-pointer flex items-center justify-center text-zinc-950"
                    aria-label="Increase quantity"
                  >
                    <Plus class="w-3.5 h-3.5 stroke-[3]" />
                  </button>
                </div>

                <div class="text-right min-w-[60px] sm:min-w-[70px]">
                  <div class="text-xs sm:text-sm font-black font-mono text-zinc-950">
                    ${{ (item.price * item.quantity).toFixed(2) }}
                  </div>
                </div>

                <button 
                  @click="store.deleteItem(item.id)"
                  class="text-zinc-400 hover:text-[#E52E04] p-1.5 transition-colors cursor-pointer"
                  aria-label="Remove item"
                >
                  <Trash2 class="w-4 h-4" />
                </button>
              </div>
            </div>
          </div>

          <!-- Impulse Items (<$2 One-Tap Add Row - Screen 1d) -->
          <div class="bg-white border border-zinc-300 p-4 sm:p-5 space-y-3">
            <div class="flex items-center justify-between">
              <div>
                <h3 class="font-black text-xs sm:text-sm text-zinc-950">Quick pantry add-ons</h3>
                <p class="text-[11px] text-zinc-500">Popular items under $2 to reach free delivery.</p>
              </div>
              <span class="text-[10px] font-mono text-zinc-500 font-bold">&lt;$2 ITEMS</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 pt-1">
              <div 
                v-for="impulse in store.impulseItems" 
                :key="impulse.id"
                class="border border-zinc-200 p-2.5 sm:p-3 bg-zinc-50 flex items-center justify-between gap-2"
              >
                <div>
                  <div class="font-bold text-xs text-zinc-950 line-clamp-1">{{ impulse.name }}</div>
                  <div class="text-[10px] text-zinc-500 font-mono">{{ impulse.size }} · ${{ impulse.price.toFixed(2) }}</div>
                </div>
                <button 
                  @click="store.addImpulseItem(impulse.id)"
                  class="w-7 h-7 bg-zinc-950 hover:bg-[#E52E04] text-white flex items-center justify-center transition-colors shrink-0 active:scale-95 cursor-pointer"
                  aria-label="Add impulse item"
                >
                  <Plus class="w-3.5 h-3.5 stroke-[3]" />
                </button>
              </div>
            </div>
          </div>

        </div>

        <!-- RIGHT COLUMN: Order Summary & Checkout CTA (Desktop) -->
        <div class="lg:col-span-4 space-y-4">
          
          <div class="bg-white border-2 border-zinc-950 p-5 sm:p-6 space-y-4 shadow-sm">
            <h2 class="text-base font-black text-zinc-950 tracking-tight border-b border-zinc-200 pb-3">
              Order Summary
            </h2>

            <!-- Pricing Breakdown -->
            <div class="space-y-2 text-xs font-mono">
              <div class="flex justify-between text-zinc-600">
                <span>Subtotal ({{ store.totalItemCount }} items)</span>
                <span class="font-bold text-zinc-950">${{ store.subtotal.toFixed(2) }}</span>
              </div>
              
              <div class="flex justify-between text-zinc-600">
                <span>Store Pickup ({{ store.selectedStore }})</span>
                <span class="text-emerald-700 font-bold">FREE</span>
              </div>

              <div class="flex justify-between text-zinc-600">
                <span>Estimated Sales Tax</span>
                <span>$0.00</span>
              </div>

              <div class="pt-3 border-t border-zinc-200 flex justify-between items-baseline text-base font-black text-zinc-950">
                <span>Estimated Total</span>
                <span class="text-xl sm:text-2xl font-mono">${{ store.total.toFixed(2) }}</span>
              </div>
            </div>

            <!-- Masala Points Box -->
            <div class="bg-[#FEF2F0] border border-[#FFCCBC] p-2.5 text-xs text-[#B82200] flex items-center gap-2">
              <Sparkles class="w-4 h-4 shrink-0 text-[#E52E04]" />
              <span>You will earn <strong>+{{ Math.floor(store.subtotal) }} Masala Points</strong> on this order.</span>
            </div>

            <!-- Desktop Checkout CTA Button -->
            <Link 
              href="/checkout"
              class="hidden sm:flex w-full py-4 bg-[#E52E04] hover:bg-[#CC2500] text-white font-black text-center text-sm uppercase tracking-wider border border-[#B82200] transition-colors shadow-sm cursor-pointer items-center justify-center gap-2"
            >
              <span>Go to checkout</span>
              <ArrowRight class="w-4 h-4" />
              <span>${{ store.total.toFixed(2) }}</span>
            </Link>

            <div class="pt-1 text-center text-[10px] font-mono text-zinc-500 flex items-center justify-center gap-1.5">
              <Clock class="w-3.5 h-3.5 text-[#E52E04]" />
              <span>Curbside Click & Collect Ready in 1 Hr</span>
            </div>
          </div>

          <!-- Pickup Instructions Card -->
          <div class="bg-zinc-50 border border-zinc-300 p-4 space-y-2 text-xs">
            <div class="font-bold text-zinc-950 flex items-center gap-1.5">
              <Clock class="w-3.5 h-3.5 text-[#E52E04]" />
              <span>Click & Collect Guarantee</span>
            </div>
            <p class="text-zinc-600 leading-relaxed text-[11px]">
              Orders are packaged in thermal insulated bags. Bring your confirmation SMS or order number to Bay 3 at 482 Main Street.
            </p>
          </div>

        </div>

      </div>

      <!-- Mobile Sticky Bottom Checkout Bar (Screen 1d match) -->
      <div 
        v-if="store.cartItems.length > 0"
        class="sm:hidden fixed bottom-0 left-0 right-0 z-40 bg-white border-t border-zinc-300 p-3 shadow-2xl flex items-center justify-between gap-3"
      >
        <div>
          <div class="text-[10px] font-mono text-zinc-500 uppercase leading-none">Total Due</div>
          <div class="text-lg font-black font-mono text-zinc-950 mt-0.5 leading-none">
            ${{ store.total.toFixed(2) }}
          </div>
        </div>

        <Link 
          href="/checkout"
          class="flex-1 py-3 bg-[#E52E04] active:bg-[#CC2500] text-white font-black text-xs uppercase tracking-wider text-center border border-[#B82200] cursor-pointer flex items-center justify-center gap-2"
        >
          <span>Go to checkout</span>
          <ArrowRight class="w-4 h-4" />
          <span>${{ store.total.toFixed(2) }}</span>
        </Link>
      </div>

    </div>
  </StoreLayout>
</template>
