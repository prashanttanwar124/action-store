<script setup>
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import StoreLayout from '../Layouts/StoreLayout.vue';
import { useStore } from '../stores/cart';
import { ChevronLeft, ShoppingBag, Clock, Plus, Minus, Check, Sparkles, RefreshCw, CheckCircle2 } from 'lucide-vue-next';

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

  <StoreLayout>
    <div class="space-y-8">

      <!-- Breadcrumbs & Navigation -->
      <nav class="flex items-center gap-2 text-xs font-mono text-zinc-500">
        <Link href="/" class="hover:text-zinc-950 flex items-center gap-1 font-bold">
          <ChevronLeft class="w-4 h-4" />
          <span>Home</span>
        </Link>
        <span>/</span>
        <span class="text-zinc-700 font-bold">{{ product.categoryTitle }}</span>
        <span>/</span>
        <span class="text-zinc-950 font-black truncate max-w-xs">{{ product.name }}</span>
      </nav>

      <!-- Main Product View: 2-Column Desktop Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        
        <!-- LEFT COLUMN: Product Visual Stage (Screens 1b & 1c) -->
        <div class="lg:col-span-6 space-y-4">
          <div class="relative w-full aspect-square bg-[#EAEAEA] border-2 border-zinc-950 flex flex-col justify-between p-4 bg-stripes overflow-hidden shadow-sm">
            
            <!-- Diagonal stripes background pattern -->
            <div class="absolute inset-0 bg-stripes pointer-events-none opacity-40"></div>

            <!-- Top Row Badges -->
            <div class="relative z-10 flex items-center justify-between">
              <span class="bg-[#FFEFEA] text-[#B82200] border border-[#FFCCBC] px-2.5 py-1 text-xs font-mono font-bold tracking-tight">
                {{ product.stockBadge }}
              </span>
              <span class="text-xs font-mono text-zinc-700 bg-white/95 border border-zinc-300 px-2 py-0.5">
                {{ product.photoLabel }}
              </span>
            </div>

            <!-- Bottom-Left Big Black Size Box (Exact match to Screens 1b & 1c) -->
            <div class="relative z-10 self-start bg-zinc-950 text-white p-4 border border-zinc-800 shadow-md">
              <div class="text-3xl font-black leading-none tracking-tight font-mono">
                {{ product.sizeMain }}
              </div>
              <div class="text-xs font-mono text-zinc-400 mt-1">
                {{ product.sizeSub }}
              </div>
            </div>
          </div>

          <!-- Product Assurance Strip -->
          <div class="grid grid-cols-3 gap-2 text-center text-[11px] font-mono text-zinc-600 bg-white border border-zinc-300 p-2.5">
            <div>
              <span class="font-bold text-zinc-950 block">Cold Storage</span>
              <span>Temp Monitored</span>
            </div>
            <div class="border-x border-zinc-200">
              <span class="font-bold text-zinc-950 block">Same Day</span>
              <span>Pickup in 1 Hr</span>
            </div>
            <div>
              <span class="font-bold text-zinc-950 block">Money Back</span>
              <span>Fresh Guarantee</span>
            </div>
          </div>
        </div>

        <!-- RIGHT COLUMN: Details, Pricing, Options & Actions -->
        <div class="lg:col-span-6 space-y-6">
          
          <!-- Product Title & Price Header -->
          <div class="bg-white border border-zinc-300 p-6 space-y-4">
            <div>
              <!-- Freshness Category Tag -->
              <div class="text-[11px] font-mono font-bold uppercase text-[#E52E04] tracking-wider mb-1">
                {{ product.freshnessLabel }}
              </div>
              
              <h1 class="text-3xl sm:text-4xl font-black text-zinc-950 tracking-tight leading-tight">
                {{ product.name }}
              </h1>

              <!-- Price & Unit -->
              <div class="flex items-baseline gap-3 mt-3">
                <span class="text-3xl font-black text-zinc-950 font-mono">
                  ${{ isSubscribed ? (product.price * 0.95).toFixed(2) : product.price.toFixed(2) }}
                </span>
                <span v-if="product.unitPrice" class="text-xs text-zinc-500 font-mono">
                  ${{ product.unitPrice }}
                </span>
                <span v-if="isSubscribed" class="text-xs font-black text-[#E52E04] font-mono bg-red-50 border border-red-200 px-2 py-0.5">
                  5% Subscribe & Save Applied
                </span>
              </div>
            </div>

            <!-- Pickup & Freshness Meta -->
            <div class="flex items-center gap-2 text-xs text-zinc-600 font-medium pt-2 border-t border-zinc-100">
              <Clock class="w-4 h-4 text-zinc-500" />
              <span>{{ product.pickupNote }}</span>
              <span>·</span>
              <span class="font-mono text-zinc-500">{{ product.expiryNote }}</span>
            </div>

            <!-- Description -->
            <p class="text-xs sm:text-sm text-zinc-600 leading-relaxed pt-2">
              {{ product.description }}
            </p>

            <!-- Subscribe & Save 5% Toggle (Screen 1c: Staple Item) -->
            <div 
              v-if="product.hasSubscription"
              class="border-2 border-zinc-950 p-4 bg-zinc-50 space-y-2 select-none"
            >
              <div class="flex items-center justify-between">
                <div>
                  <div class="font-black text-sm text-zinc-950 flex items-center gap-1.5">
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
                    'w-14 h-8 border-2 border-zinc-950 p-0.5 transition-colors relative cursor-pointer',
                    isSubscribed ? 'bg-[#E52E04]' : 'bg-white'
                  ]"
                  aria-label="Toggle Subscribe & Save"
                >
                  <div 
                    :class="[
                      'w-6 h-6 bg-zinc-950 transition-transform duration-200',
                      isSubscribed ? 'translate-x-6 bg-white' : 'translate-x-0'
                    ]"
                  ></div>
                </button>
              </div>
            </div>

            <!-- Frequently Bought Together (Screen 1b: Paneer) -->
            <div 
              v-if="product.frequentlyBoughtTogether?.length > 0" 
              class="border border-zinc-300 p-4 bg-[#F4F4F5] space-y-3"
            >
              <div>
                <h2 class="font-black text-sm text-zinc-950">Frequently bought together</h2>
                <p class="text-[11px] text-zinc-500">68% of paneer shoppers add this masala</p>
              </div>

              <div 
                v-for="item in product.frequentlyBoughtTogether" 
                :key="item.id"
                class="flex items-center justify-between bg-white border border-zinc-200 p-3"
              >
                <div class="flex items-center gap-3">
                  <div class="w-12 h-12 bg-zinc-100 border border-zinc-300 p-1 flex items-center justify-center font-mono text-[9px] text-zinc-600 bg-stripes">
                    masala
                  </div>
                  <div>
                    <div class="font-bold text-xs text-zinc-950">{{ item.name }}</div>
                    <div class="text-[11px] text-zinc-500 font-mono">{{ item.weight }}</div>
                  </div>
                </div>

                <div class="flex items-center gap-3">
                  <span class="font-black text-sm font-mono text-zinc-950">${{ item.price.toFixed(2) }}</span>
                  <button 
                    @click="garamMasalaChecked = !garamMasalaChecked"
                    :class="[
                      'w-7 h-7 flex items-center justify-center border font-bold text-white transition-colors cursor-pointer',
                      garamMasalaChecked ? 'bg-[#E52E04] border-[#B82200]' : 'bg-white border-zinc-400 text-transparent'
                    ]"
                    aria-label="Toggle Garam Masala"
                  >
                    <Check v-if="garamMasalaChecked" class="w-4 h-4 stroke-[3]" />
                  </button>
                </div>
              </div>
            </div>

            <!-- ACTION BAR: Quantity [- 1 +] and Big Vermilion Button -->
            <div class="pt-4 border-t border-zinc-200 space-y-3">
              <div class="flex items-center gap-3">
                <!-- Quantity Stepper -->
                <div class="w-36 h-12 bg-zinc-100 border-2 border-zinc-950 flex items-center justify-between px-3">
                  <button 
                    @click="quantity = Math.max(1, quantity - 1)" 
                    class="p-1 font-black text-base text-zinc-950 hover:text-[#E52E04] cursor-pointer"
                    aria-label="Decrease quantity"
                  >
                    -
                  </button>
                  <span class="text-sm font-black font-mono">{{ quantity }}</span>
                  <button 
                    @click="quantity = quantity + 1" 
                    class="p-1 font-black text-base text-zinc-950 hover:text-[#E52E04] cursor-pointer"
                    aria-label="Increase quantity"
                  >
                    +
                  </button>
                </div>

                <!-- Add to Cart Primary Button -->
                <button 
                  @click="handleAction"
                  class="flex-1 h-12 bg-[#E52E04] hover:bg-[#CC2500] text-white font-black text-sm uppercase tracking-wider flex items-center justify-center gap-2 border border-[#B82200] transition-colors cursor-pointer"
                >
                  <ShoppingBag class="w-4 h-4 stroke-[2.4]" />
                  <span>{{ product.actionLabel }} ${{ displayPrice }}</span>
                </button>
              </div>

              <!-- Success Notification Alert -->
              <div 
                v-if="addedNotification"
                class="bg-emerald-50 border border-emerald-300 text-emerald-800 p-2.5 text-xs font-bold flex items-center justify-between"
              >
                <div class="flex items-center gap-2">
                  <CheckCircle2 class="w-4 h-4 text-emerald-600" />
                  <span>Added {{ quantity }}x {{ product.name }} to cart!</span>
                </div>
                <Link href="/cart" class="underline text-emerald-900 font-mono">
                  View Cart &rsaquo;
                </Link>
              </div>
            </div>

          </div>

        </div>

      </div>

      <!-- SECTION: "Goes with it" Recommendations (Screen 1c) -->
      <section v-if="product.goesWithIt?.length > 0" class="space-y-4 pt-6 border-t border-zinc-300">
        <div>
          <h2 class="text-xl font-black text-zinc-950 tracking-tight">Goes with it</h2>
          <p class="text-xs text-zinc-500">Perfect pairings to complete your kitchen recipe.</p>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
          <div 
            v-for="item in product.goesWithIt" 
            :key="item.id"
            class="bg-white border border-zinc-300 p-3 flex flex-col justify-between"
          >
            <div>
              <div class="w-full h-28 bg-[#EAEAEA] border border-zinc-200 mb-2 p-2 bg-stripes flex flex-col justify-between">
                <span class="text-[10px] font-mono text-zinc-700">{{ item.tag }}</span>
                <button 
                  @click="store.addToCart({ id: item.id, name: item.name, weight: item.weight, price: item.price, originalPrice: item.price })"
                  class="self-end w-7 h-7 bg-[#E52E04] hover:bg-[#CC2500] text-white flex items-center justify-center border border-[#B82200] font-black cursor-pointer"
                  aria-label="Add pairing item"
                >
                  +
                </button>
              </div>

              <div class="font-black text-sm font-mono text-zinc-950">${{ item.price.toFixed(2) }}</div>
              <div class="font-bold text-xs text-zinc-950 mt-0.5">{{ item.name }}</div>
              <div class="text-[10px] text-zinc-500 font-mono">{{ item.weight }}</div>
            </div>
          </div>
        </div>
      </section>

    </div>
  </StoreLayout>
</template>
