<script setup>
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import MobileWebLayout from '../Layouts/MobileWebLayout.vue';
import { useStore } from '../stores/cart';
import { ChevronLeft, ShoppingBag, Clock, Plus, Minus, Check } from 'lucide-vue-next';

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

const displayPrice = computed(() => {
  if (isSubscribed.value) {
    return (product.value.price * 0.95 * quantity.value).toFixed(2);
  }
  return (product.value.price * quantity.value).toFixed(2);
});

function handleAction() {
  store.addToCart(product.value.id, quantity.value, isSubscribed.value);
  if (garamMasalaChecked.value && product.value.frequentlyBoughtTogether?.length > 0) {
    store.addToCart(product.value.frequentlyBoughtTogether[0].id, 1);
  }
}
</script>

<template>
  <Head :title="`${product.name} — Masala Mart`" />

  <MobileWebLayout :showBottomNav="false" :showCartBar="false">
    
    <!-- Top Bar with Back Arrow, Category Title & Cart Icon (Pages 3 & 4) -->
    <div class="px-4 py-3 bg-white border-b border-zinc-200 flex items-center justify-between sticky top-[41px] z-30">
      <Link 
        href="/"
        class="p-1 text-zinc-950 hover:bg-zinc-100 rounded-none cursor-pointer flex items-center"
      >
        <ChevronLeft class="w-5 h-5 stroke-[2.5]" />
      </Link>

      <span class="font-extrabold text-xs text-zinc-950 tracking-tight font-mono">
        {{ product.categoryTitle }}
      </span>

      <!-- Square Cart Icon with Badge (Links to /cart) -->
      <Link 
        href="/cart"
        class="relative p-1.5 border-2 border-zinc-950 bg-white text-zinc-950 hover:bg-zinc-100 rounded-none cursor-pointer"
      >
        <ShoppingBag class="w-4 h-4 stroke-[2.2]" />
        <span 
          v-if="store.totalItemCount > 0"
          class="absolute -top-1.5 -right-1.5 bg-[#E52E04] text-white text-[10px] font-black w-4 h-4 rounded-none flex items-center justify-center border border-white"
        >
          {{ store.totalItemCount }}
        </span>
      </Link>
    </div>

    <!-- Product Image with Overlays -->
    <div class="relative w-full h-[300px] bg-[#EAEAEA] border-b border-zinc-300 flex flex-col justify-between p-3">
      <!-- Subtle diagonal stripe texture -->
      <div class="absolute inset-0 bg-[linear-gradient(45deg,rgba(0,0,0,0.04)_25%,transparent_25%,transparent_50%,rgba(0,0,0,0.04)_50%,rgba(0,0,0,0.04)_75%,transparent_75%,transparent)] bg-[length:14px_14px] pointer-events-none"></div>

      <!-- Real Product Shot Photo -->
      <img 
        :src="product.image" 
        :alt="product.name" 
        class="absolute inset-0 w-full h-full object-cover"
      />

      <!-- Top Row Badges -->
      <div class="relative z-10 flex items-center justify-between">
        <span class="bg-[#FFEFEA] text-[#B82200] border border-[#FFCCBC] px-2 py-0.5 text-[11px] font-mono font-bold tracking-tight rounded-none">
          {{ product.stockBadge }}
        </span>
        <span class="text-[11px] font-mono text-zinc-700 bg-white/95 border border-zinc-300 px-2 py-0.5 rounded-none">
          {{ product.photoLabel }}
        </span>
      </div>

      <!-- Bottom-Left Big Black Size Box (Exact match to Pages 3 & 4) -->
      <div class="relative z-10 self-start bg-zinc-950 text-white p-2.5 rounded-none border border-zinc-800">
        <div class="text-2xl font-black leading-none tracking-tight">
          {{ product.sizeMain }}
        </div>
        <div class="text-[10px] font-mono text-zinc-400 mt-1">
          {{ product.sizeSub }}
        </div>
      </div>
    </div>

    <!-- Product Information (Exact match to Pages 3 & 4) -->
    <div class="p-4 space-y-4">
      <div>
        <div class="text-[10px] font-mono font-bold tracking-wider uppercase text-[#E52E04]">
          {{ product.subtitleTag }}
        </div>
        <h1 class="text-2xl font-black text-zinc-950 tracking-tight mt-0.5">
          {{ product.name }}
        </h1>
        <div class="flex items-baseline gap-2 mt-1">
          <span class="text-2xl font-black text-zinc-950 tracking-tight">
            ${{ product.price.toFixed(2) }}
          </span>
          <span class="text-xs font-mono text-zinc-500">
            {{ product.unitPrice }}
          </span>
        </div>

        <!-- Freshness line with clock -->
        <div class="flex items-center gap-1.5 mt-2.5 text-xs text-zinc-700 font-medium">
          <Clock class="w-3.5 h-3.5 text-zinc-600 shrink-0" />
          <span>{{ product.freshnessLine }}</span>
        </div>

        <!-- Description -->
        <p class="text-xs text-zinc-600 leading-relaxed mt-2">
          {{ product.description }}
        </p>
      </div>

      <!-- Subscribe & Save Row (Page 4: 1c Staple) -->
      <div 
        v-if="product.hasSubscribeAndSave"
        class="p-3 border-2 border-zinc-950 bg-white flex items-center justify-between rounded-none"
      >
        <div>
          <div class="font-black text-xs text-zinc-950">
            Subscribe & Save 5%
          </div>
          <div class="text-[10px] text-zinc-500 font-medium mt-0.5">
            Auto-reorder. Skip, pause or cancel anytime.
          </div>
        </div>

        <button 
          @click="isSubscribed = !isSubscribed"
          :class="[
            'w-12 h-6 border-2 border-zinc-950 transition-colors p-0.5 flex items-center rounded-none cursor-pointer',
            isSubscribed ? 'bg-zinc-950 justify-end' : 'bg-zinc-200 justify-start'
          ]"
        >
          <div class="w-4 h-4 bg-white border border-zinc-950"></div>
        </button>
      </div>

      <!-- Frequently Bought Together (Page 3: 1b Fresh Item) -->
      <div 
        v-if="product.frequentlyBoughtTogether?.length > 0"
        class="pt-3 border-t border-zinc-200"
      >
        <h3 class="text-sm font-black text-zinc-950 tracking-tight">
          Frequently bought together
        </h3>
        <p class="text-[11px] text-zinc-500 mb-2.5">
          68% of paneer shoppers add these
        </p>

        <div class="space-y-2">
          <div 
            v-for="item in product.frequentlyBoughtTogether"
            :key="item.id"
            @click="garamMasalaChecked = !garamMasalaChecked"
            class="p-2.5 border border-zinc-300 bg-zinc-50 flex items-center justify-between cursor-pointer rounded-none hover:border-zinc-950 transition-colors"
          >
            <div class="flex items-center gap-2.5">
              <div class="w-10 h-10 bg-[#EAEAEA] border border-zinc-300 relative overflow-hidden shrink-0">
                <div class="absolute inset-0 bg-[linear-gradient(45deg,rgba(0,0,0,0.06)_25%,transparent_25%,transparent_50%,rgba(0,0,0,0.06)_50%,rgba(0,0,0,0.06)_75%,transparent_75%,transparent)] bg-[length:6px_6px]"></div>
              </div>
              <div>
                <div class="font-black text-xs text-zinc-950">{{ item.name }}</div>
                <div class="text-[10px] font-mono text-zinc-500">{{ item.size }}</div>
              </div>
            </div>

            <div class="flex items-center gap-2.5">
              <span class="font-black text-xs text-zinc-950">${{ item.price.toFixed(2) }}</span>
              <div 
                :class="[
                  'w-6 h-6 flex items-center justify-center rounded-none',
                  garamMasalaChecked ? 'bg-[#E52E04] text-white border border-[#B82200]' : 'border-2 border-zinc-400 bg-white'
                ]"
              >
                <Check v-if="garamMasalaChecked" class="w-3.5 h-3.5 stroke-[3]" />
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Goes With It (Page 4: 1c Staple Item) -->
      <div 
        v-if="product.goesWithIt?.length > 0"
        class="pt-3 border-t border-zinc-200"
      >
        <h3 class="text-sm font-black text-zinc-950 tracking-tight mb-2.5">
          Goes with it
        </h3>

        <div class="grid grid-cols-2 gap-2.5">
          <div 
            v-for="item in product.goesWithIt"
            :key="item.id"
            class="border border-zinc-300 p-2 bg-zinc-50 rounded-none flex flex-col justify-between"
          >
            <div class="relative w-full h-20 bg-[#EAEAEA] border border-zinc-200 mb-1.5 p-1.5 flex flex-col justify-between">
              <div class="absolute inset-0 bg-[linear-gradient(45deg,rgba(0,0,0,0.05)_25%,transparent_25%,transparent_50%,rgba(0,0,0,0.05)_50%,rgba(0,0,0,0.05)_75%,transparent_75%,transparent)] bg-[length:8px_8px] pointer-events-none"></div>
              <span class="relative z-10 text-[9px] font-mono text-zinc-700">{{ item.label }}</span>
              <button 
                @click="store.addToCart(item.id)"
                class="relative z-10 self-end w-6 h-6 bg-[#E52E04] hover:bg-[#CC2500] text-white flex items-center justify-center rounded-none cursor-pointer"
              >
                <Plus class="w-3.5 h-3.5 stroke-[3]" />
              </button>
            </div>
            <div>
              <div class="font-black text-xs text-zinc-950">${{ item.price.toFixed(2) }}</div>
              <div class="font-bold text-[11px] text-zinc-950">{{ item.name }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Sticky Bottom Action Bar (Exact match to Pages 3 & 4) -->
    <div class="fixed bottom-0 left-0 right-0 max-w-[420px] mx-auto p-3 bg-white border-t border-zinc-300 z-40 flex items-center gap-2">
      <!-- Quantity selector [ -  1  + ] (Page 3: 1b) -->
      <div 
        v-if="!product.hasSubscribeAndSave || !isSubscribed"
        class="flex items-center border border-zinc-950 bg-white h-11 px-1 rounded-none"
      >
        <button 
          @click="quantity > 1 ? quantity-- : null"
          class="w-7 h-7 flex items-center justify-center hover:bg-zinc-100 rounded-none cursor-pointer"
        >
          <Minus class="w-3.5 h-3.5 stroke-[3] text-zinc-950" />
        </button>
        <span class="w-7 text-center font-black text-sm text-zinc-950 select-none">
          {{ quantity }}
        </span>
        <button 
          @click="quantity++"
          class="w-7 h-7 flex items-center justify-center hover:bg-zinc-100 rounded-none cursor-pointer"
        >
          <Plus class="w-3.5 h-3.5 stroke-[3] text-zinc-950" />
        </button>
      </div>

      <!-- Primary Red Button -->
      <button 
        @click="handleAction"
        class="flex-1 h-11 bg-[#E52E04] hover:bg-[#CC2500] text-white font-black text-xs uppercase tracking-wider rounded-none flex items-center justify-between px-4 transition-all cursor-pointer border border-[#B82200]"
      >
        <span>
          {{ isSubscribed ? 'Subscribe' : (store.getQuantity(product.id) > 0 ? 'Add another' : 'Add to cart') }}
        </span>
        <span class="font-black text-sm">
          ${{ displayPrice }}
        </span>
      </button>
    </div>

  </MobileWebLayout>
</template>
