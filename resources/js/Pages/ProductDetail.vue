<script setup>
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import StoreLayout from '../Layouts/StoreLayout.vue';
import { useStore } from '../stores/cart';
import { ChevronLeft, ChevronRight, Clock, Plus, Minus, Check, Sparkles, RefreshCw, CheckCircle2, ShieldCheck } from 'lucide-vue-next';
import IconBag from '../Components/Icons/IconBag.vue';
import IconCold from '../Components/Icons/IconCold.vue';

const props = defineProps({
  slug: {
    type: String,
    default: 'paneer',
  },
});

const store = useStore();

const product = computed(() => {
  return store.getProductBySlug(props.slug);
});

const isSubscribed = ref(false);
const quantity = ref(1);
const garamMasalaChecked = ref(true);
const addedNotification = ref(false);

const unitPrice = computed(() => {
  if (isSubscribed.value) {
    return Number((product.value.price * 0.95).toFixed(2));
  }
  return Number(product.value.price.toFixed(2));
});

const displayPrice = computed(() => {
  return (unitPrice.value * quantity.value).toFixed(2);
});

function handleAction() {
  store.addToCart({
    id: product.value.id,
    name: product.value.name,
    weight: product.value.sizeMain,
    price: unitPrice.value,
    originalPrice: product.value.price,
    quantity: quantity.value,
    isSubscribed: isSubscribed.value,
    subscriptionInterval: isSubscribed.value ? 'Monthly' : undefined,
    nextDelivery: isSubscribed.value ? 'Oct 3' : undefined,
  });

  if (garamMasalaChecked.value && product.value.frequentlyBoughtTogether?.length > 0) {
    const fbt = product.value.frequentlyBoughtTogether[0];
    store.addToCart({
      id: fbt.id,
      name: fbt.name,
      weight: fbt.weight,
      price: fbt.price,
      originalPrice: fbt.price,
      quantity: 1,
      isSubscribed: false,
    });
  }

  addedNotification.value = true;
  setTimeout(() => {
    addedNotification.value = false;
  }, 2500);
}
</script>

<template>
  <Head :title="`${product.name} — Masala Mart`" />

  <StoreLayout :showCartBar="false" :showBottomNav="false">
    <div class="space-y-6 pb-20 sm:pb-0">

      <!-- Mobile Top Sticky Sub-Header (Screen 1b & 1c exact match) -->
      <div class="flex items-center justify-between sm:hidden -mx-4 px-4 py-2.5 bg-white border-b border-zinc-200 sticky top-14 z-30">
        <Link 
          href="/" 
          class="p-1 -ml-1 text-zinc-950 flex items-center hover:text-[#E52E04]"
          aria-label="Back"
        >
          <ChevronLeft class="w-6 h-6 stroke-[2.4]" />
        </Link>
        <span class="text-xs font-mono font-bold text-zinc-950 uppercase tracking-tight">
          {{ product.categoryTitle }}
        </span>
        <Link 
          href="/cart" 
          class="relative p-1 text-zinc-950"
          aria-label="Cart"
        >
          <IconBag :size="20" />
          <span 
            v-if="store.totalItemCount > 0"
            class="absolute -top-1 -right-1 bg-[#E52E04] text-white text-[9px] font-mono font-bold w-4 h-4 flex items-center justify-center border border-white"
          >
            {{ store.totalItemCount }}
          </span>
        </Link>
      </div>

      <!-- Desktop Breadcrumbs -->
      <nav class="hidden sm:flex items-center gap-2 text-xs font-mono text-zinc-500">
        <Link href="/" class="hover:text-zinc-950 flex items-center gap-1 font-bold">
          <ChevronLeft class="w-4 h-4" />
          <span>Home</span>
        </Link>
        <span>/</span>
        <span class="text-zinc-700 font-bold">{{ product.categoryTitle }}</span>
        <span>/</span>
        <span class="text-zinc-950 font-black truncate max-w-xs">{{ product.name }}</span>
      </nav>

      <!-- Main Product View: 2-Column Desktop Grid / Compact Mobile -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8 items-start">
        
        <!-- LEFT COLUMN: Product Visual Stage (Screens 1b & 1c) -->
        <div class="lg:col-span-6 space-y-3">
          <div class="relative w-full h-72 sm:h-auto sm:aspect-square bg-[#EAEAEA] border-2 border-zinc-950 flex flex-col justify-between p-3.5 bg-stripes overflow-hidden shadow-sm">
            
            <!-- Diagonal stripes background pattern -->
            <div class="absolute inset-0 bg-stripes pointer-events-none opacity-40"></div>

            <!-- Top Row Badges -->
            <div class="relative z-10 flex items-center justify-between">
              <span class="bg-[#FFEFEA] text-[#B82200] border border-[#FFCCBC] px-2 py-0.5 text-[11px] font-mono font-bold tracking-tight">
                {{ product.stockBadge }}
              </span>
              <span class="photo-label text-[11px] text-zinc-700 bg-white/95 border border-zinc-300 px-2 py-0.5">
                {{ product.photoLabel }}
              </span>
            </div>

            <!-- Bottom-Left Big Black Size Box (Exact match to Screens 1b & 1c) -->
            <div class="relative z-10 self-start bg-zinc-950 text-white p-3 sm:p-4 border border-zinc-800 shadow-md">
              <div class="text-2xl sm:text-3xl font-black leading-none tracking-tight font-mono">
                {{ product.sizeMain }}
              </div>
              <div class="text-[10px] sm:text-xs font-mono text-zinc-400 mt-1">
                {{ product.sizeSub }}
              </div>
            </div>
          </div>

          <!-- Product Assurance Strip with Crisp Icons -->
          <div class="grid grid-cols-3 gap-2 text-center text-[10px] sm:text-[11px] font-mono text-zinc-600 bg-white border border-zinc-300 p-2.5 sm:p-3 shadow-xs">
            <div class="flex flex-col items-center justify-center gap-1">
              <IconCold :size="16" class="text-sky-600" />
              <div>
                <span class="font-bold text-zinc-950 block leading-tight">Cold Storage</span>
                <span class="text-zinc-500 text-[10px]">Temp Monitored</span>
              </div>
            </div>
            <div class="border-x border-zinc-200 flex flex-col items-center justify-center gap-1 px-1">
              <Clock class="w-4 h-4 text-amber-600 stroke-[2.2]" />
              <div>
                <span class="font-bold text-zinc-950 block leading-tight">Same Day</span>
                <span class="text-zinc-500 text-[10px]">Pickup in 1 Hr</span>
              </div>
            </div>
            <div class="flex flex-col items-center justify-center gap-1">
              <ShieldCheck class="w-4 h-4 text-emerald-600 stroke-[2.2]" />
              <div>
                <span class="font-bold text-zinc-950 block leading-tight">Fresh Guarantee</span>
                <span class="text-zinc-500 text-[10px]">100% Refundable</span>
              </div>
            </div>
          </div>
        </div>

        <!-- RIGHT COLUMN: Details, Pricing, Options & Actions -->
        <div class="lg:col-span-6 space-y-4 sm:space-y-6">
          
          <!-- Product Title & Price Header Card -->
          <div class="bg-white border border-zinc-300 p-4 sm:p-6 space-y-3.5">
            <div>
              <!-- Freshness Category Tag -->
              <div class="text-[10px] sm:text-[11px] font-mono font-bold uppercase text-[#E52E04] tracking-wider mb-1">
                {{ product.freshnessLabel }}
              </div>
              
              <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black text-zinc-950 tracking-tight leading-tight">
                {{ product.name }}
              </h1>

              <!-- Price & Unit -->
              <div class="flex items-baseline gap-2.5 mt-2">
                <span class="text-2xl sm:text-3xl font-black text-zinc-950 font-mono">
                  ${{ isSubscribed ? (product.price * 0.95).toFixed(2) : product.price.toFixed(2) }}
                </span>
                <span v-if="product.unitPrice" class="text-xs text-zinc-500 font-mono">
                  ${{ product.unitPrice }}
                </span>
                <span v-if="isSubscribed" class="text-[11px] font-black text-[#E52E04] font-mono bg-red-50 border border-red-200 px-2 py-0.5">
                  Save 5%
                </span>
              </div>
            </div>

            <!-- Pickup & Freshness Meta -->
            <div class="flex items-center gap-1.5 text-xs text-zinc-600 font-medium pt-2 border-t border-zinc-100">
              <Clock class="w-3.5 h-3.5 text-zinc-500 shrink-0" />
              <span>{{ product.pickupNote }}</span>
              <span>·</span>
              <span class="font-mono text-zinc-500">{{ product.expiryNote }}</span>
            </div>

            <!-- Description -->
            <p class="text-xs sm:text-sm text-zinc-600 leading-relaxed pt-1">
              {{ product.description }}
            </p>

            <!-- Subscribe & Save 5% Toggle (Screen 1c: Staple Item) -->
            <div 
              v-if="product.hasSubscription"
              class="border-2 border-zinc-950 p-3.5 sm:p-4 bg-zinc-50 space-y-2 select-none"
            >
              <div class="flex items-center justify-between">
                <div>
                  <div class="font-black text-xs sm:text-sm text-zinc-950 flex items-center gap-1.5">
                    <span>Subscribe & Save 5%</span>
                    <span class="text-xs font-mono text-[#E52E04]">(${{ (product.price * 0.95).toFixed(2) }}/ea)</span>
                  </div>
                  <div class="text-[11px] text-zinc-500">
                    Auto-reorder monthly. Skip, pause, or cancel anytime.
                  </div>
                </div>

                <!-- Custom Modernist Toggle Switch -->
                <button
                  type="button"
                  @click="isSubscribed = !isSubscribed"
                  :class="[
                    'w-13 h-7 sm:w-14 sm:h-8 border-2 border-zinc-950 p-0.5 transition-colors relative cursor-pointer',
                    isSubscribed ? 'bg-[#E52E04]' : 'bg-white'
                  ]"
                  aria-label="Toggle Subscribe & Save"
                >
                  <div 
                    :class="[
                      'w-5 h-5 sm:w-6 sm:h-6 bg-zinc-950 transition-transform duration-200',
                      isSubscribed ? 'translate-x-6 bg-white' : 'translate-x-0'
                    ]"
                  ></div>
                </button>
              </div>
            </div>

            <!-- Frequently Bought Together (Screen 1b: Paneer) -->
            <div 
              v-if="product.frequentlyBoughtTogether?.length > 0" 
              class="border border-zinc-300 p-3.5 bg-[#F4F4F5] space-y-2.5"
            >
              <div>
                <h2 class="font-black text-xs sm:text-sm text-zinc-950">Frequently bought together</h2>
                <p class="text-[10px] sm:text-[11px] text-zinc-500">68% of paneer shoppers add this masala</p>
              </div>

              <div 
                v-for="item in product.frequentlyBoughtTogether" 
                :key="item.id"
                class="flex items-center justify-between bg-white border border-zinc-200 p-2.5"
              >
                <div class="flex items-center gap-2.5">
                  <div class="w-10 h-10 bg-zinc-100 border border-zinc-300 p-1 flex items-center justify-center font-mono text-[9px] text-zinc-600 bg-stripes">
                    masala
                  </div>
                  <div>
                    <div class="font-bold text-xs text-zinc-950">{{ item.name }}</div>
                    <div class="text-[10px] text-zinc-500 font-mono">{{ item.weight }}</div>
                  </div>
                </div>

                <div class="flex items-center gap-2.5">
                  <span class="font-black text-xs sm:text-sm font-mono text-zinc-950">${{ item.price.toFixed(2) }}</span>
                  <button 
                    @click="garamMasalaChecked = !garamMasalaChecked"
                    :class="[
                      'w-6 h-6 sm:w-7 sm:h-7 flex items-center justify-center border font-bold text-white transition-colors cursor-pointer',
                      garamMasalaChecked ? 'bg-[#E52E04] border-[#B82200]' : 'bg-white border-zinc-400 text-transparent'
                    ]"
                    aria-label="Toggle Garam Masala"
                  >
                    <Check v-if="garamMasalaChecked" class="w-3.5 h-3.5 stroke-[3]" />
                  </button>
                </div>
              </div>
            </div>

            <!-- Desktop Inline Action Bar -->
            <div class="hidden sm:block pt-4 border-t border-zinc-200 space-y-3">
              <div class="flex items-center gap-3">
                <div class="w-36 h-12 bg-zinc-100 border-2 border-zinc-950 flex items-center justify-between px-3">
                  <button 
                    @click="quantity = Math.max(1, quantity - 1)" 
                    class="p-1 hover:text-[#E52E04] cursor-pointer flex items-center justify-center text-zinc-950"
                    aria-label="Decrease quantity"
                  >
                    <Minus class="w-4 h-4 stroke-[3]" />
                  </button>
                  <span class="text-sm font-black font-mono">{{ quantity }}</span>
                  <button 
                    @click="quantity = quantity + 1" 
                    class="p-1 hover:text-[#E52E04] cursor-pointer flex items-center justify-center text-zinc-950"
                    aria-label="Increase quantity"
                  >
                    <Plus class="w-4 h-4 stroke-[3]" />
                  </button>
                </div>

                <button 
                  @click="handleAction"
                  class="flex-1 h-12 bg-[#E52E04] hover:bg-[#CC2500] text-white font-black text-sm uppercase tracking-wider flex items-center justify-center gap-2 border border-[#B82200] transition-colors cursor-pointer active:scale-[0.99] shadow-xs"
                >
                  <IconBag :size="18" />
                  <span>{{ product.actionLabel }} ${{ displayPrice }}</span>
                </button>
              </div>

              <div 
                v-if="addedNotification"
                class="bg-emerald-50 border border-emerald-300 text-emerald-800 p-2.5 text-xs font-bold flex items-center justify-between"
              >
                <div class="flex items-center gap-2">
                  <CheckCircle2 class="w-4 h-4 text-emerald-600" />
                  <span>Added {{ quantity }}x {{ product.name }} to cart!</span>
                </div>
                <Link href="/cart" class="underline text-emerald-900 font-mono inline-flex items-center gap-1">
                  <span>View Cart</span>
                  <ChevronRight class="w-3.5 h-3.5" />
                </Link>
              </div>
            </div>

          </div>

        </div>

      </div>

      <!-- SECTION: "Goes with it" Recommendations (Screen 1c) -->
      <section v-if="product.goesWithIt?.length > 0" class="space-y-3 pt-4 border-t border-zinc-300">
        <div>
          <h2 class="text-lg sm:text-xl font-black text-zinc-950 tracking-tight">Goes with it</h2>
          <p class="text-xs text-zinc-500">Perfect pairings to complete your dish.</p>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 sm:gap-4">
          <div 
            v-for="item in product.goesWithIt" 
            :key="item.id"
            class="bg-white border border-zinc-300 p-2.5 sm:p-3 flex flex-col justify-between"
          >
            <div>
              <div class="w-full h-20 sm:h-28 bg-[#EAEAEA] border border-zinc-200 mb-1.5 p-1.5 bg-stripes flex flex-col justify-between">
                <span class="photo-label text-[9px] sm:text-[10px] text-zinc-700">{{ item.tag }}</span>
                <button 
                  @click="store.addToCart({ id: item.id, name: item.name, weight: item.weight, price: item.price, originalPrice: item.price })"
                  class="self-end w-6 h-6 sm:w-7 sm:h-7 bg-[#E52E04] hover:bg-[#CC2500] text-white flex items-center justify-center border border-[#B82200] cursor-pointer active:scale-95 shadow-xs"
                  aria-label="Add pairing item"
                >
                  <Plus class="w-3.5 h-3.5 stroke-[3]" />
                </button>
              </div>

              <div class="font-black text-xs sm:text-sm font-mono text-zinc-950">${{ item.price.toFixed(2) }}</div>
              <div class="font-bold text-xs text-zinc-950 mt-0.5 truncate">{{ item.name }}</div>
              <div class="text-[10px] text-zinc-500 font-mono">{{ item.weight }}</div>
            </div>
          </div>
        </div>
      </section>

      <!-- Mobile Sticky Bottom Action Bar (Screen 1b & 1c exact match) -->
      <div class="sm:hidden fixed bottom-0 left-0 right-0 z-40 bg-white border-t border-zinc-300 p-2.5 shadow-2xl flex items-center gap-2">
        <!-- Quantity Stepper -->
        <div class="w-28 h-11 bg-zinc-100 border border-zinc-950 flex items-center justify-between px-2.5">
          <button 
            @click="quantity = Math.max(1, quantity - 1)" 
            class="p-1 hover:text-[#E52E04] flex items-center justify-center text-zinc-950"
            aria-label="Decrease quantity"
          >
            <Minus class="w-4 h-4 stroke-[3]" />
          </button>
          <span class="text-xs font-black font-mono">{{ quantity }}</span>
          <button 
            @click="quantity = quantity + 1" 
            class="p-1 hover:text-[#E52E04] flex items-center justify-center text-zinc-950"
            aria-label="Increase quantity"
          >
            <Plus class="w-4 h-4 stroke-[3]" />
          </button>
        </div>

        <!-- Add to cart CTA -->
        <button 
          @click="handleAction"
          class="flex-1 h-11 bg-[#E52E04] active:bg-[#CC2500] text-white font-black text-xs uppercase tracking-wider flex items-center justify-center gap-2 border border-[#B82200] shadow-sm"
        >
          <IconBag :size="18" />
          <span>{{ product.actionLabel }} ${{ displayPrice }}</span>
        </button>
      </div>

      <!-- Mobile Toast/Notification when added -->
      <div 
        v-if="addedNotification"
        class="sm:hidden fixed top-16 left-4 right-4 z-50 bg-zinc-950 text-white p-3 border border-zinc-700 shadow-2xl flex items-center justify-between text-xs font-bold"
      >
        <div class="flex items-center gap-2">
          <CheckCircle2 class="w-4 h-4 text-emerald-400" />
          <span>Added to cart!</span>
        </div>
        <Link href="/cart" class="underline text-amber-400 font-mono inline-flex items-center gap-1">
          <span>View Cart</span>
          <ChevronRight class="w-3.5 h-3.5" />
        </Link>
      </div>

    </div>
  </StoreLayout>
</template>
