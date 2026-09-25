<script setup>
import { useStore } from '../stores/cart';
import { Plus, Check, Utensils, ChevronRight } from 'lucide-vue-next';

const store = useStore();
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5 border-t border-zinc-200">
    <!-- Header -->
    <div class="flex items-center justify-between mb-1">
      <h3 class="text-2xl font-black text-zinc-950 tracking-tight">Recipe kits</h3>
      <button 
        class="text-sm font-bold text-[#E52E04] hover:text-[#CC2500] transition-colors cursor-pointer flex items-center gap-1 group"
      >
        <span>All 14 kits</span>
        <ChevronRight class="w-4 h-4 group-hover:translate-x-1 transition-transform" />
      </button>
    </div>
    
    <p class="text-xs text-zinc-500 font-medium mb-4">
      Every ingredient for one dish, added in one tap.
    </p>

    <!-- Recipe Kits Grid / Horizontal Slider with sharp corners & diagonal stripe background -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <div 
        v-for="kit in store.recipeKits" 
        :key="kit.id"
        class="bg-white border-2 border-zinc-200 rounded-none overflow-hidden hover:border-zinc-950 transition-colors flex flex-col justify-between group p-3.5"
      >
        <!-- Texture Image Box -->
        <div class="relative w-full h-[160px] bg-[#EAEAEA] rounded-none border border-zinc-300 overflow-hidden flex flex-col justify-between p-2.5">
          <!-- Diagonal stripe texture matching screenshot -->
          <div class="absolute inset-0 bg-[linear-gradient(45deg,rgba(0,0,0,0.05)_25%,transparent_25%,transparent_50%,rgba(0,0,0,0.05)_50%,rgba(0,0,0,0.05)_75%,transparent_75%,transparent)] bg-[length:14px_14px] opacity-80 pointer-events-none"></div>

          <img 
            :src="kit.image" 
            :alt="kit.name" 
            class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
          />

          <!-- Sharp mono tag -->
          <span class="relative z-10 text-[11px] font-mono text-zinc-800 bg-white/95 px-2 py-0.5 rounded-none border border-zinc-300 self-start">
            {{ kit.label }}
          </span>

          <span class="relative z-10 text-[10px] font-mono font-bold text-white bg-zinc-950 px-2 py-0.5 self-end">
            {{ kit.servings }}
          </span>
        </div>

        <!-- Kit Details -->
        <div class="mt-3 text-left flex-1 flex flex-col justify-between">
          <div>
            <div class="text-lg font-black text-zinc-950 tracking-tight">
              ${{ kit.price.toFixed(2) }}
            </div>
            <h4 class="font-extrabold text-sm text-zinc-950 leading-snug line-clamp-1 mt-0.5 group-hover:text-[#E52E04] transition-colors">
              {{ kit.name }}
            </h4>
            <p class="text-xs text-zinc-500 line-clamp-1 mt-0.5 font-medium">
              {{ kit.subtitle }}
            </p>

            <!-- Ingredients pill list -->
            <div class="mt-2 flex flex-wrap gap-1">
              <span 
                v-for="(item, idx) in kit.items.slice(0, 2)" 
                :key="idx"
                class="text-[10px] bg-zinc-100 text-zinc-600 px-1.5 py-0.5 border border-zinc-200 font-mono"
              >
                + {{ item }}
              </span>
              <span v-if="kit.items.length > 2" class="text-[10px] text-zinc-400 font-mono self-center">
                +{{ kit.items.length - 2 }} more
              </span>
            </div>
          </div>

          <!-- Add Kit Button -->
          <div class="mt-3 pt-2.5 border-t border-zinc-200">
            <button 
              @click="store.addToCart(kit.id)"
              class="w-full py-2 bg-zinc-950 hover:bg-[#E52E04] active:scale-[0.98] text-white font-black text-xs uppercase tracking-wider rounded-none flex items-center justify-center gap-1.5 transition-colors cursor-pointer border border-zinc-950"
            >
              <Plus class="w-3.5 h-3.5 stroke-[3]" />
              <span>Add all ingredients</span>
            </button>
          </div>
        </div>

      </div>
    </div>
  </div>
</template>
