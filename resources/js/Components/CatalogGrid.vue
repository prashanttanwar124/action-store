<script setup>
import { ref, computed } from 'vue';
import { useStore } from '../stores/cart';
import { Plus, Minus, ArrowUpDown } from 'lucide-vue-next';

const store = useStore();
const sortBy = ref('default');

const categories = [
  { id: 'all', label: 'All Items' },
  { id: 'sweets', label: 'Diwali Sweets' },
  { id: 'staples', label: 'Atta & Rice' },
  { id: 'dairy', label: 'Ghee & Paneer' },
  { id: 'spices', label: 'Organic Spices' },
  { id: 'snacks', label: 'Chai & Biscuits' },
];

const sortedProducts = computed(() => {
  let list = [...store.filteredProducts];
  if (sortBy.value === 'price-low') {
    list.sort((a, b) => a.price - b.price);
  } else if (sortBy.value === 'price-high') {
    list.sort((a, b) => b.price - a.price);
  } else if (sortBy.value === 'name') {
    list.sort((a, b) => a.name.localeCompare(b.name));
  }
  return list;
});
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5">
    <!-- Category Pills Bar & Sort Controls (Sharp 90-degree corners) -->
    <div class="bg-white border-2 border-zinc-200 p-4 shadow-none mb-6 rounded-none">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        
        <!-- Category Buttons -->
        <div class="flex items-center gap-2 overflow-x-auto no-scrollbar py-1">
          <button 
            v-for="cat in categories"
            :key="cat.id"
            @click="store.selectedCategory = cat.id"
            :class="[
              'px-4 py-2 text-xs font-bold transition-all whitespace-nowrap cursor-pointer select-none rounded-none border border-zinc-950 uppercase tracking-wider',
              store.selectedCategory === cat.id 
                ? 'bg-[#E52E04] text-white border-[#B82200]' 
                : 'bg-zinc-100 text-zinc-900 hover:bg-zinc-200 border-zinc-300'
            ]"
          >
            {{ cat.label }}
          </button>
        </div>

        <!-- Sort & Count -->
        <div class="flex items-center justify-between md:justify-end gap-3 shrink-0 pt-2 md:pt-0 border-t md:border-t-0 border-zinc-200">
          <span class="text-xs text-zinc-600 font-semibold">
            Showing <span class="text-zinc-950 font-black">{{ sortedProducts.length }}</span> products
          </span>

          <div class="flex items-center gap-1.5 bg-zinc-100 px-3 py-1.5 border border-zinc-400 rounded-none">
            <ArrowUpDown class="w-3.5 h-3.5 text-zinc-600" />
            <select 
              v-model="sortBy"
              class="bg-transparent text-xs font-bold text-zinc-950 focus:outline-none cursor-pointer rounded-none"
            >
              <option value="default">Sort: Recommended</option>
              <option value="price-low">Price: Low to High</option>
              <option value="price-high">Price: High to Low</option>
              <option value="name">Name: A to Z</option>
            </select>
          </div>
        </div>

      </div>
    </div>

    <!-- Section Title -->
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-2xl font-black text-zinc-950 tracking-tight">
        {{ store.selectedCategory === 'all' ? 'All Pantry Products' : categories.find(c => c.id === store.selectedCategory)?.label }}
      </h3>
    </div>

    <!-- Product Grid -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
      <div 
        v-for="product in sortedProducts"
        :key="product.id"
        class="bg-white border-2 border-zinc-200 overflow-hidden shadow-none hover:border-zinc-950 transition-colors flex flex-col justify-between group rounded-none"
      >
        <!-- Top Image Section -->
        <div 
          @click="store.openProductDetail(product)"
          class="relative h-44 bg-[#EAEAEA] overflow-hidden border-b border-zinc-200 cursor-pointer"
        >
          <img 
            :src="product.image" 
            :alt="product.name" 
            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
          />
          
          <span 
            v-if="product.badge"
            class="absolute top-2.5 left-2.5 bg-[#E52E04] text-white text-[10px] font-black px-2 py-0.5 rounded-none uppercase tracking-wider border border-[#B82200]"
          >
            {{ product.badge }}
          </span>
        </div>

        <!-- Body Section -->
        <div class="p-4 flex-1 flex flex-col justify-between">
          <div 
            @click="store.openProductDetail(product)"
            class="cursor-pointer"
          >
            <span class="text-[10px] uppercase font-mono font-bold text-zinc-500 tracking-wider">
              {{ product.category }}
            </span>
            <h4 class="font-extrabold text-base text-zinc-950 leading-snug line-clamp-1 group-hover:text-[#E52E04] transition-colors mt-0.5">
              {{ product.name }}
            </h4>
            <p class="text-xs text-zinc-600 mt-1 line-clamp-1 font-medium">
              {{ product.tagline || product.subtitleTag }}
            </p>
          </div>

          <div class="flex items-end justify-between mt-4 pt-3 border-t border-zinc-200">
            <div>
              <span class="text-xs text-zinc-500 block leading-none font-medium">{{ product.size }}</span>
              <span class="text-lg font-black text-zinc-950 tracking-tight">
                ${{ product.price.toFixed(2) }}
              </span>
            </div>

            <!-- Red Quantity Controls (Sharp zero rounded corners) -->
            <div>
              <div 
                v-if="store.getQuantity(product.id) > 0"
                class="flex items-center gap-2 bg-[#E52E04] text-white rounded-none px-2.5 py-1.5 border border-[#B82200]"
              >
                <button 
                  @click="store.removeFromCart(product.id)"
                  class="p-0.5 hover:bg-black/20 rounded-none cursor-pointer"
                  aria-label="Decrease"
                >
                  <Minus class="w-3.5 h-3.5 stroke-[3]" />
                </button>
                <span class="text-xs font-black w-4 text-center select-none">{{ store.getQuantity(product.id) }}</span>
                <button 
                  @click="store.addToCart(product.id)"
                  class="p-0.5 hover:bg-black/20 rounded-none cursor-pointer"
                  aria-label="Increase"
                >
                  <Plus class="w-3.5 h-3.5 stroke-[3]" />
                </button>
              </div>

              <button 
                v-else
                @click="store.addToCart(product.id)"
                class="w-9 h-9 rounded-none bg-[#E52E04] hover:bg-[#CC2500] active:scale-90 text-white font-bold text-xs flex items-center justify-center border border-[#B82200] cursor-pointer transition-all"
                aria-label="Add product"
              >
                <Plus class="w-5 h-5 stroke-[3]" />
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
