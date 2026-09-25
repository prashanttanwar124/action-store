<script setup>
import { ref } from 'vue';
import { useStore } from '../stores/cart';
import { ChevronRight, Sparkles } from 'lucide-vue-next';

const store = useStore();

const activeSlide = ref(0);
const slides = [
  {
    tagline: 'FESTIVAL PRE-ORDER · CLOSES OCT 30',
    title: 'Diwali sweets, boxed & ready.',
    priceText: 'Pre-order from $24.99',
    buttonText: 'Pre-order from $24.99',
    bgClass: 'bg-[#E52E04]',
    label: 'mithai box',
    productId: 1,
    image: 'https://images.unsplash.com/photo-1599785209707-a456fc1337bb?w=900&auto=format&fit=crop&q=80',
  },
  {
    tagline: 'FRESH ARRIVAL · SPICE SPECIAL',
    title: 'Whole Organic Spices from Kerala.',
    priceText: 'Shop from $4.49',
    buttonText: 'Explore Spice Pantry',
    bgClass: 'bg-amber-800',
    label: 'spices box',
    productId: 6,
    image: 'https://images.unsplash.com/photo-1615485290382-441e4d049cb5?w=900&auto=format&fit=crop&q=80',
  }
];

function handleBannerClick(productId) {
  store.addToCart(productId);
  store.isCartDrawerOpen = true;
}
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5">
    <!-- Main Hero Box with sharp 90-degree corners -->
    <div 
      :class="[
        'rounded-none overflow-hidden border-2 border-zinc-950 grid grid-cols-1 md:grid-cols-12 min-h-[220px] md:min-h-[250px] relative shadow-none',
        slides[activeSlide].bgClass
      ]"
    >
      <!-- Left Content Column -->
      <div class="md:col-span-7 p-6 md:p-8 flex flex-col justify-between text-white relative z-10">
        <div>
          <div class="text-xs font-mono font-bold tracking-widest uppercase text-white/95 mb-2">
            {{ slides[activeSlide].tagline }}
          </div>
          <h2 class="text-3xl sm:text-4xl md:text-5xl font-black leading-tight tracking-tight drop-shadow-xs">
            {{ slides[activeSlide].title }}
          </h2>
        </div>

        <div class="pt-4">
          <button 
            @click="handleBannerClick(slides[activeSlide].productId)"
            class="inline-flex items-center gap-1.5 text-sm md:text-base font-black text-white hover:underline cursor-pointer group"
          >
            <span>{{ slides[activeSlide].buttonText }}</span>
            <ChevronRight class="w-5 h-5 group-hover:translate-x-1 transition-transform stroke-[3]" />
          </button>
        </div>
      </div>

      <!-- Right Visual Image Section (Exact stripe texture from mockup) -->
      <div class="md:col-span-5 relative bg-red-900/40 overflow-hidden flex items-center justify-center p-3 border-t md:border-t-0 md:border-l-2 border-white/20">
        <div class="absolute inset-0 bg-[linear-gradient(135deg,rgba(255,255,255,0.18)_25%,transparent_25%,transparent_50%,rgba(255,255,255,0.18)_50%,rgba(255,255,255,0.18)_75%,transparent_75%,transparent)] bg-[length:18px_18px] opacity-70"></div>
        
        <img 
          :src="slides[activeSlide].image" 
          :alt="slides[activeSlide].title"
          class="w-full h-full object-cover rounded-none border border-white/30 relative z-10 hover:scale-105 transition-transform duration-300"
        />

        <!-- Sharp mono label matching image prompt -->
        <span class="absolute bottom-3 left-3 z-20 text-xs font-mono font-bold text-white bg-black/70 px-2 py-0.5 rounded-none border border-white/20">
          {{ slides[activeSlide].label }}
        </span>
      </div>
    </div>

    <!-- Carousel Rectangle Dots -->
    <div class="flex items-center justify-start gap-1.5 mt-3">
      <button 
        v-for="(slide, idx) in slides" 
        :key="idx"
        @click="activeSlide = idx"
        :class="[
          'h-1.5 transition-all rounded-none cursor-pointer',
          activeSlide === idx ? 'w-8 bg-zinc-950' : 'w-3 bg-zinc-400 hover:bg-zinc-600'
        ]"
      ></button>
    </div>
  </div>
</template>
