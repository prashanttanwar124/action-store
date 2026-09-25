<script setup>
import { useStore } from '../stores/cart';
import { Plus, Minus, ChevronRight } from 'lucide-vue-next';

const store = useStore();
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5">
    <!-- Header -->
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-2xl font-black text-zinc-950 tracking-tight">Buy it again</h3>
      <button 
        @click="store.selectedCategory = 'staples'; store.activeTab = 'shop'"
        class="text-sm font-bold text-[#E52E04] hover:text-[#CC2500] transition-colors cursor-pointer flex items-center gap-1 group"
      >
        <span>See all</span>
        <ChevronRight class="w-4 h-4 group-hover:translate-x-1 transition-transform" />
      </button>
    </div>

    <!-- Products Slider / Grid Container with sharp zero rounded corners -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
      <div
        v-for="product in store.buyAgainProducts"
        :key="product.id"
        class="bg-white rounded-none border-2 border-zinc-200 overflow-hidden shadow-none flex flex-col justify-between group p-3 hover:border-zinc-950 transition-colors"
      >
        <!-- Card Image Box with exact mockup styling & sharp 90-degree corners -->
        <div 
          @click="store.openProductDetail(product)"
          class="relative w-full h-[180px] bg-[#EAEAEA] rounded-none border border-zinc-300 flex flex-col justify-between p-2.5 cursor-pointer"
        >
          <!-- Subtle diagonal stripe background texture -->
          <div class="absolute inset-0 bg-[linear-gradient(45deg,rgba(0,0,0,0.04)_25%,transparent_25%,transparent_50%,rgba(0,0,0,0.04)_50%,rgba(0,0,0,0.04)_75%,transparent_75%,transparent)] bg-[length:12px_12px] opacity-80 pointer-events-none"></div>

          <!-- Product Image -->
          <img 
            :src="product.image" 
            :alt="product.name"
            class="absolute inset-0 w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-300"
          />

          <!-- Product Tag Top Left (Sharp mono text) -->
          <span class="relative z-10 text-[11px] font-mono text-zinc-800 bg-white/95 px-2 py-0.5 rounded-none border border-zinc-300 self-start max-w-[90%] truncate">
            {{ product.label || product.name.toLowerCase() }}
          </span>

          <!-- Dynamic Red Quantity Bar (Exact match to prompt image with zero rounded corners) -->
          <div class="relative z-10 mt-auto self-end w-full" @click.stop>
            <!-- If quantity > 0: Red Control Bar (- count +) -->
            <div 
              v-if="store.getQuantity(product.id) > 0"
              class="w-full bg-[#E52E04] text-white h-10 rounded-none flex items-center justify-between px-3 shadow-none border border-[#B82200] animate-pop"
            >
              <button 
                @click="store.removeFromCart(product.id)"
                class="w-7 h-7 flex items-center justify-center hover:bg-black/20 rounded-none transition-colors cursor-pointer"
                aria-label="Decrease quantity"
              >
                <Minus class="w-4 h-4 stroke-[3]" />
              </button>
              
              <span class="font-black text-sm select-none">
                {{ store.getQuantity(product.id) }}
              </span>

              <button 
                @click="store.addToCart(product.id)"
                class="w-7 h-7 flex items-center justify-center hover:bg-black/20 rounded-none transition-colors cursor-pointer"
                aria-label="Increase quantity"
              >
                <Plus class="w-4 h-4 stroke-[3]" />
              </button>
            </div>

            <!-- If quantity === 0: Red Square Plus Button -->
            <button 
              v-else
              @click="store.addToCart(product.id)"
              class="ml-auto w-10 h-10 bg-[#E52E04] hover:bg-[#CC2500] active:scale-90 text-white rounded-none flex items-center justify-center shadow-none border border-[#B82200] transition-all cursor-pointer"
              aria-label="Add to cart"
            >
              <Plus class="w-5 h-5 stroke-[3]" />
            </button>
          </div>
        </div>

        <!-- Product Specs Below Image -->
        <div 
          @click="store.openProductDetail(product)"
          class="mt-3 text-left space-y-0.5 cursor-pointer"
        >
          <div class="text-lg font-black text-zinc-950 tracking-tight">
            ${{ product.price.toFixed(2) }}
          </div>
          <div class="text-sm font-bold text-zinc-950 leading-snug line-clamp-1 group-hover:text-[#E52E04] transition-colors">
            {{ product.name }}
          </div>
          <div class="text-xs text-zinc-500 font-medium">
            {{ product.size }}
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
