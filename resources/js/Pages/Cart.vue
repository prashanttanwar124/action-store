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
  ShoppingBag, 
  ArrowRight,
  ShieldCheck,
  CheckCircle2,
  Clock
} from 'lucide-vue-next';

const store = useStore();
</script>

<template>
  <Head title="Shopping Cart — Masala Mart" />

  <StoreLayout :showCartBar="false">
    <div class="space-y-6">

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
      <div class="bg-zinc-950 text-white p-5 border border-zinc-900 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div>
            <div class="text-xs font-mono font-bold uppercase tracking-wider text-[#E52E04]">
              ORDER PROGRESS
            </div>
            <div class="text-lg font-black tracking-tight mt-0.5">
              <template v-if="store.amountToFreeDelivery > 0">
                Add ${{ store.amountToFreeDelivery.toFixed(2) }} more to unlock free delivery
              </template>
              <template v-else>
                🎉 You've unlocked FREE Home Delivery!
              </template>
            </div>
          </div>
          <div class="text-right font-mono text-xs text-zinc-400">
            <span>Threshold: $40.00</span>
            <span class="mx-2">·</span>
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
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        
        <!-- LEFT COLUMN: Cart Items & Impulse Add-ons (lg:col-span-8) -->
        <div class="lg:col-span-8 space-y-6">
          
          <!-- Empty State -->
          <div v-if="store.cartItems.length === 0" class="bg-white border border-zinc-300 p-12 text-center space-y-4">
            <ShoppingBag class="w-12 h-12 text-zinc-400 mx-auto" />
            <h2 class="text-xl font-black text-zinc-950">Your cart is empty</h2>
            <p class="text-xs text-zinc-500 max-w-sm mx-auto">
              Add some freshly batched paneer, authentic spices, or fragrant basmati rice to get started.
            </p>
            <Link 
              href="/"
              class="inline-block px-6 py-3 bg-[#E52E04] text-white text-xs font-black uppercase tracking-wider border border-[#B82200]"
            >
              Start Shopping
            </Link>
          </div>

          <!-- Items Table / List -->
          <div v-else class="bg-white border border-zinc-300 divide-y divide-zinc-200">
            <div 
              v-for="item in store.cartItems" 
              :key="item.id"
              class="p-4 sm:p-5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4"
            >
              <!-- Item Details -->
              <div class="flex items-center gap-4">
                <div class="w-16 h-16 bg-[#EAEAEA] border border-zinc-300 p-1 flex items-center justify-center font-mono text-[10px] text-zinc-600 bg-stripes shrink-0">
                  {{ item.name ? item.name.split(' ').slice(-1)[0].toLowerCase() : 'item' }}
                </div>
                
                <div>
                  <div class="flex items-center gap-2">
                    <h3 class="font-black text-sm text-zinc-950">{{ item.name }}</h3>
                    <span 
                      v-if="item.isSubscribed" 
                      class="px-1.5 py-0.5 bg-red-100 text-[#E52E04] text-[9px] font-mono font-bold uppercase"
                    >
                      Monthly · -5%
                    </span>
                  </div>
                  <div class="text-xs text-zinc-500 font-mono mt-0.5">{{ item.weight }}</div>
                  <div class="text-xs font-mono font-black text-zinc-950 mt-1">
                    ${{ item.price.toFixed(2) }}
                    <span v-if="item.originalPrice && item.originalPrice !== item.price" class="text-zinc-400 line-through ml-1">
                      ${{ item.originalPrice.toFixed(2) }}
                    </span>
                  </div>
                </div>
              </div>

              <!-- Quantity Controls & Delete -->
              <div class="flex items-center gap-4 self-end sm:self-center">
                <div class="w-28 h-9 bg-zinc-100 border border-zinc-400 flex items-center justify-between px-2">
                  <button 
                    @click="store.removeFromCart(item.id)" 
                    class="p-1 font-black text-sm text-zinc-950 hover:text-[#E52E04] cursor-pointer"
                    aria-label="Decrease quantity"
                  >
                    -
                  </button>
                  <span class="text-xs font-black font-mono">{{ item.quantity }}</span>
                  <button 
                    @click="store.addToCart({ id: item.id, name: item.name, weight: item.weight, price: item.price, originalPrice: item.originalPrice, isSubscribed: item.isSubscribed })" 
                    class="p-1 font-black text-sm text-zinc-950 hover:text-[#E52E04] cursor-pointer"
                    aria-label="Increase quantity"
                  >
                    +
                  </button>
                </div>

                <div class="text-right min-w-[70px]">
                  <div class="text-sm font-black font-mono text-zinc-950">
                    ${{ (item.price * item.quantity).toFixed(2) }}
                  </div>
                </div>

                <button 
                  @click="store.deleteItem(item.id)"
                  class="text-zinc-400 hover:text-[#E52E04] p-1 transition-colors"
                  aria-label="Remove item"
                >
                  <Trash2 class="w-4 h-4" />
                </button>
              </div>
            </div>
          </div>

          <!-- Impulse Items (<$2 One-Tap Add Row - Screen 1d) -->
          <div class="bg-white border border-zinc-300 p-5 space-y-3">
            <div class="flex items-center justify-between">
              <div>
                <h3 class="font-black text-sm text-zinc-950">Quick pantry add-ons</h3>
                <p class="text-xs text-zinc-500">Popular items under $2 to reach free delivery.</p>
              </div>
              <span class="text-xs font-mono text-zinc-500 font-bold">&lt;$2 ITEMS</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2">
              <div 
                v-for="impulse in store.impulseItems" 
                :key="impulse.id"
                class="border border-zinc-200 p-3 bg-zinc-50 flex items-center justify-between gap-2"
              >
                <div>
                  <div class="font-bold text-xs text-zinc-950 line-clamp-1">{{ impulse.name }}</div>
                  <div class="text-[10px] text-zinc-500 font-mono">{{ impulse.unit }} · ${{ impulse.price.toFixed(2) }}</div>
                </div>
                <button 
                  @click="store.addImpulseItem(impulse.id)"
                  class="w-7 h-7 bg-zinc-950 hover:bg-[#E52E04] text-white flex items-center justify-center font-black text-xs transition-colors shrink-0"
                  aria-label="Add impulse item"
                >
                  +
                </button>
              </div>
            </div>
          </div>

        </div>

        <!-- RIGHT COLUMN: Order Summary & Checkout CTA (lg:col-span-4) -->
        <div class="lg:col-span-4 space-y-4">
          
          <div class="bg-white border-2 border-zinc-950 p-6 space-y-4">
            <h2 class="text-lg font-black text-zinc-950 tracking-tight border-b border-zinc-200 pb-3">
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
                <span class="text-2xl font-mono">${{ store.total.toFixed(2) }}</span>
              </div>
            </div>

            <!-- Masala Points Box -->
            <div class="bg-[#FEF2F0] border border-[#FFCCBC] p-3 text-xs text-[#B82200] flex items-center gap-2">
              <Sparkles class="w-4 h-4 shrink-0 text-[#E52E04]" />
              <span>You will earn <strong>+{{ Math.floor(store.subtotal) }} Masala Points</strong> on this order.</span>
            </div>

            <!-- Checkout CTA Button (Matching Screen 1d) -->
            <Link 
              href="/checkout"
              class="w-full py-4 bg-[#E52E04] hover:bg-[#CC2500] text-white font-black text-center text-sm uppercase tracking-wider block border border-[#B82200] transition-colors shadow-sm"
            >
              Go to checkout ${{ store.total.toFixed(2) }}
            </Link>

            <div class="pt-2 text-center text-[11px] font-mono text-zinc-500">
              ⚡ Curbside Click & Collect Ready in 1 Hr
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

    </div>
  </StoreLayout>
</template>
