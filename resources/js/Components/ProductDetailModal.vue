<script setup>
import { ref, computed } from 'vue';
import { useStore } from '../stores/cart';
import { ChevronLeft, ShoppingBag, Clock, Plus, Minus, Check, CheckSquare, Square } from 'lucide-vue-next';

const store = useStore();

const isSubscribed = ref(false);
const detailQuantity = ref(1);
const garamMasalaAdded = ref(true);

const product = computed(() => {
  return store.selectedProduct || store.products[0];
});

function incrementDetailQty() {
  detailQuantity.value += 1;
}

function decrementDetailQty() {
  if (detailQuantity.value > 1) {
    detailQuantity.value -= 1;
  }
}

function handleAddCurrentProduct() {
  store.addToCart(product.value.id, detailQuantity.value);
  if (garamMasalaAdded.value && product.value.frequentlyBoughtTogether?.length > 0) {
    store.addToCart(product.value.frequentlyBoughtTogether[0].id, 1);
  }
  store.isCartDrawerOpen = true;
  store.closeProductDetail();
}

function toggleGaramMasala() {
  garamMasalaAdded.value = !garamMasalaAdded.value;
}
</script>

<template>
  <div 
    v-if="store.isProductDetailOpen"
    class="fixed inset-0 z-50 overflow-y-auto bg-black/70 backdrop-blur-xs flex justify-center items-start sm:items-center p-0 sm:p-4"
  >
    <!-- Modal Card Container (Sharp 90-degree corners) -->
    <div 
      class="bg-white w-full max-w-lg min-h-screen sm:min-h-0 sm:max-h-[92vh] overflow-y-auto border-0 sm:border-2 sm:border-zinc-950 rounded-none shadow-2xl animate-in zoom-in-95 duration-200 flex flex-col justify-between"
    >
      <!-- Top Bar matching exact screenshot -->
      <div class="px-4 py-3 border-b-2 border-zinc-200 bg-white sticky top-0 z-30 flex items-center justify-between">
        <button 
          @click="store.closeProductDetail()"
          class="p-1.5 text-zinc-950 hover:bg-zinc-100 rounded-none cursor-pointer flex items-center gap-1 font-bold text-xs"
          aria-label="Go back"
        >
          <ChevronLeft class="w-5 h-5 stroke-[2.5]" />
        </button>

        <span class="font-extrabold text-sm text-zinc-950 tracking-tight">
          {{ product.categoryTitle || 'Product Details' }}
        </span>

        <!-- Square Cart Icon with Red Badge -->
        <button 
          @click="store.isCartDrawerOpen = true; store.closeProductDetail()"
          class="relative p-2 border-2 border-zinc-950 bg-white text-zinc-950 hover:bg-zinc-100 rounded-none cursor-pointer"
        >
          <ShoppingBag class="w-4 h-4 stroke-[2.2]" />
          <span 
            v-if="store.totalItemCount > 0"
            class="absolute -top-1.5 -right-1.5 bg-[#E52E04] text-white text-[10px] font-black w-4 h-4 rounded-none flex items-center justify-center border border-white"
          >
            {{ store.totalItemCount }}
          </span>
        </button>
      </div>

      <!-- Scrollable Product Content -->
      <div class="flex-1 p-0">
        <!-- Top Image Section with Overlays -->
        <div class="relative w-full h-[280px] bg-[#EAEAEA] border-b-2 border-zinc-200 overflow-hidden flex flex-col justify-between p-4">
          <!-- Diagonal stripe texture background -->
          <div class="absolute inset-0 bg-[linear-gradient(45deg,rgba(0,0,0,0.04)_25%,transparent_25%,transparent_50%,rgba(0,0,0,0.04)_50%,rgba(0,0,0,0.04)_75%,transparent_75%,transparent)] bg-[length:14px_14px] opacity-80 pointer-events-none"></div>

          <!-- Product Image -->
          <img 
            :src="product.image" 
            :alt="product.name" 
            class="absolute inset-0 w-full h-full object-cover object-center"
          />

          <!-- Top Badge & Label Row -->
          <div class="relative z-10 flex items-center justify-between">
            <span class="bg-[#FFEFEA] text-[#B82200] border border-[#FFCCBC] px-2.5 py-0.5 text-xs font-mono font-bold tracking-tight rounded-none">
              {{ product.stockBadge || 'In stock' }}
            </span>

            <span class="text-xs font-mono text-zinc-800 bg-white/95 border border-zinc-300 px-2 py-0.5 rounded-none">
              {{ product.photoLabel || 'product photo' }}
            </span>
          </div>

          <!-- Bottom Left Weight/Serving Square Badge (Exact match to screenshot) -->
          <div class="relative z-10 self-start bg-zinc-950 text-white p-3 rounded-none border border-zinc-800 shadow-md">
            <div class="text-2xl font-black leading-none tracking-tight">
              {{ product.sizeMain || product.size }}
            </div>
            <div class="text-[11px] font-mono text-zinc-400 mt-1">
              {{ product.sizeSub || 'standard pack' }}
            </div>
          </div>
        </div>

        <!-- Product Details Section -->
        <div class="p-5 space-y-4">
          <div>
            <!-- Subtitle Tag in bold red uppercase -->
            <div class="text-xs font-mono font-bold tracking-wider uppercase text-[#E52E04]">
              {{ product.subtitleTag }}
            </div>
            
            <!-- Large Product Title -->
            <h1 class="text-2xl sm:text-3xl font-black text-zinc-950 tracking-tight mt-1 leading-tight">
              {{ product.name }}
            </h1>

            <!-- Price and Unit Price -->
            <div class="flex items-baseline gap-2 mt-2">
              <span class="text-3xl font-black text-zinc-950 tracking-tight">
                ${{ product.price.toFixed(2) }}
              </span>
              <span class="text-sm font-bold text-zinc-500 font-mono">
                {{ product.unitPrice }}
              </span>
            </div>

            <!-- Freshness & Pickup Info with Clock icon -->
            <div class="flex items-start gap-2 mt-3 pt-3 border-t border-zinc-200 text-xs font-semibold text-zinc-700">
              <Clock class="w-4 h-4 text-zinc-600 shrink-0 mt-0.5" />
              <span>{{ product.freshnessLine }}</span>
            </div>

            <!-- Description -->
            <p class="text-xs sm:text-sm text-zinc-600 leading-relaxed mt-2.5 font-medium">
              {{ product.description }}
            </p>
          </div>

          <!-- Subscribe & Save 5% Toggle Box (For staples like Basmati Rice) -->
          <div 
            v-if="product.hasSubscribeAndSave"
            class="p-4 border-2 border-zinc-950 bg-white flex items-center justify-between rounded-none shadow-xs"
          >
            <div>
              <div class="font-black text-sm text-zinc-950">
                Subscribe & Save 5%
              </div>
              <div class="text-xs text-zinc-500 font-medium mt-0.5">
                Auto-reorder. Skip, pause or cancel anytime.
              </div>
            </div>

            <!-- Custom Sharp Switch Toggle -->
            <button 
              @click="isSubscribed = !isSubscribed"
              :class="[
                'w-12 h-6 border-2 border-zinc-950 transition-colors p-0.5 flex items-center rounded-none cursor-pointer',
                isSubscribed ? 'bg-zinc-950 justify-end' : 'bg-zinc-200 justify-start'
              ]"
            >
              <div class="w-4 h-4 bg-white border border-zinc-950 rounded-none"></div>
            </button>
          </div>

          <!-- Frequently Bought Together Section (Screen 2 matching) -->
          <div v-if="product.frequentlyBoughtTogether?.length > 0" class="pt-4 border-t-2 border-zinc-200">
            <h3 class="text-base font-black text-zinc-950 tracking-tight">
              Frequently bought together
            </h3>
            <p class="text-xs text-zinc-500 font-medium mb-3">
              68% of paneer shoppers add these
            </p>

            <div 
              @click="toggleGaramMasala"
              class="p-3 border-2 border-zinc-200 hover:border-zinc-950 bg-zinc-50 flex items-center justify-between cursor-pointer rounded-none transition-colors"
            >
              <div class="flex items-center gap-3">
                <!-- Diagonal texture thumbnail box -->
                <div class="w-12 h-12 bg-zinc-200 border border-zinc-300 relative overflow-hidden shrink-0">
                  <div class="absolute inset-0 bg-[linear-gradient(45deg,rgba(0,0,0,0.06)_25%,transparent_25%,transparent_50%,rgba(0,0,0,0.06)_50%,rgba(0,0,0,0.06)_75%,transparent_75%,transparent)] bg-[length:8px_8px]"></div>
                </div>

                <div>
                  <div class="font-black text-sm text-zinc-950">Garam Masala</div>
                  <div class="text-xs font-mono text-zinc-500">100 g</div>
                </div>
              </div>

              <div class="flex items-center gap-3">
                <span class="font-black text-sm text-zinc-950">$3.49</span>
                
                <!-- Red Checkbox Button with Checkmark -->
                <div 
                  :class="[
                    'w-7 h-7 flex items-center justify-center rounded-none transition-colors',
                    garamMasalaAdded ? 'bg-[#E52E04] text-white border border-[#B82200]' : 'border-2 border-zinc-400 bg-white'
                  ]"
                >
                  <Check v-if="garamMasalaAdded" class="w-4 h-4 stroke-[3]" />
                </div>
              </div>
            </div>
          </div>

          <!-- Goes With It Section (Screen 3 matching) -->
          <div v-if="product.goesWithIt?.length > 0" class="pt-4 border-t-2 border-zinc-200">
            <h3 class="text-base font-black text-zinc-950 tracking-tight mb-3">
              Goes with it
            </h3>

            <div class="grid grid-cols-2 gap-3">
              <div 
                v-for="item in product.goesWithIt" 
                :key="item.id"
                class="border-2 border-zinc-200 p-3 bg-zinc-50 rounded-none flex flex-col justify-between"
              >
                <!-- Thumbnail with diagonal stripes -->
                <div class="relative w-full h-24 bg-[#EAEAEA] border border-zinc-300 mb-2 p-2 flex flex-col justify-between">
                  <div class="absolute inset-0 bg-[linear-gradient(45deg,rgba(0,0,0,0.05)_25%,transparent_25%,transparent_50%,rgba(0,0,0,0.05)_50%,rgba(0,0,0,0.05)_75%,transparent_75%,transparent)] bg-[length:10px_10px]"></div>
                  
                  <span class="relative z-10 text-[10px] font-mono text-zinc-700 bg-white/90 px-1 py-0.5 border border-zinc-300 self-start">
                    {{ item.label }}
                  </span>

                  <!-- Red Square Plus Button -->
                  <button 
                    @click="store.addToCart(item.id)"
                    class="relative z-10 self-end w-7 h-7 bg-[#E52E04] hover:bg-[#CC2500] text-white flex items-center justify-center rounded-none shadow-none cursor-pointer border border-[#B82200]"
                  >
                    <Plus class="w-4 h-4 stroke-[3]" />
                  </button>
                </div>

                <div>
                  <div class="font-black text-sm text-zinc-950">${{ item.price.toFixed(2) }}</div>
                  <div class="font-bold text-xs text-zinc-800">{{ item.name }}</div>
                  <div class="text-[11px] text-zinc-500 font-mono">{{ item.size }}</div>
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>

      <!-- Bottom Sticky Action Bar (Exact match to screenshot) -->
      <div class="p-4 bg-white border-t-2 border-zinc-950 sticky bottom-0 z-30 flex items-center gap-3">
        <!-- Quantity Box [ -  1  + ] with sharp borders -->
        <div class="flex items-center border-2 border-zinc-950 bg-white h-12 px-2 rounded-none">
          <button 
            @click="decrementDetailQty"
            class="w-8 h-8 flex items-center justify-center hover:bg-zinc-100 rounded-none cursor-pointer"
          >
            <Minus class="w-4 h-4 stroke-[3] text-zinc-950" />
          </button>
          
          <span class="w-8 text-center font-black text-base text-zinc-950 select-none">
            {{ detailQuantity }}
          </span>

          <button 
            @click="incrementDetailQty"
            class="w-8 h-8 flex items-center justify-center hover:bg-zinc-100 rounded-none cursor-pointer"
          >
            <Plus class="w-4 h-4 stroke-[3] text-zinc-950" />
          </button>
        </div>

        <!-- Red Main Action Button -->
        <button 
          @click="handleAddCurrentProduct"
          class="flex-1 h-12 bg-[#E52E04] hover:bg-[#CC2500] active:scale-[0.98] text-white font-black text-sm uppercase tracking-wider rounded-none flex items-center justify-between px-5 transition-all cursor-pointer border border-[#B82200] shadow-md"
        >
          <span>
            {{ store.getQuantity(product.id) > 0 ? 'Add another' : 'Add to cart' }}
          </span>
          <span class="font-black text-base">
            ${{ (product.price * detailQuantity).toFixed(2) }}
          </span>
        </button>
      </div>

    </div>
  </div>
</template>
