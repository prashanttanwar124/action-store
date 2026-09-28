<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import StoreLayout from '../Layouts/StoreLayout.vue';
import { useStore } from '../stores/cart';
import { 
  ChevronRight, 
  ChevronLeft,
  Plus, 
  Minus, 
  Sparkles,
  Store,
  Clock 
} from 'lucide-vue-next';

const props = defineProps({
  products: {
    type: Array,
    default: () => [],
  },
  sliders: {
    type: Array,
    default: () => [],
  },
  recipeKits: {
    type: Array,
    default: () => [],
  },
});

const store = useStore();

// Hero Banner Interactive Carousel Slides (Dynamic from DB / Admin)
const defaultBannerSlides = [
  {
    id: 'sweets',
    tag: 'FESTIVAL PRE-ORDER',
    title: 'Diwali sweets,\nboxed & ready.',
    subtitle: 'Artisanal kaju katli, besan ladoo & pista barfi in royal packaging.',
    highlight: 'Fresh Handcrafted Batch · Serves 6–8',
    cta: 'Pre-order for $24.99',
    link: '/products/sweets-box',
    image: '/images/products/sweets.jpg',
    alt: 'Assorted luxury Diwali sweets in royal gold gift box',
    photoLabel: 'mithai box',
    bg: '#181310',
  },
  {
    id: 'curry-kit',
    tag: 'CHEF-CRAFTED · 20 MINS',
    title: 'Restaurant curry,\ncooked at home.',
    subtitle: 'Fresh malai paneer, simmer sauce & stone-ground spices.',
    highlight: 'Pre-portioned ingredients · Zero waste',
    cta: 'Order kit for $14.99',
    link: '/recipe-kits/paneer-curry',
    image: '/images/products/paneer_curry.jpg',
    alt: 'Fresh Paneer Butter Masala curry kit with pre-portioned ingredients',
    photoLabel: 'paneer kit',
    bg: '#16120e',
  },
  {
    id: 'atta',
    tag: 'SUBSCRIBE & SAVE · 10% OFF',
    title: 'Never run out\nof fresh atta.',
    subtitle: '100% stone ground whole wheat flour for ultra-soft golden phulkas.',
    highlight: '20 lb bag · Zero maida added',
    cta: 'Subscribe for $18.04',
    link: '/products/atta',
    image: '/images/products/atta.jpg',
    alt: 'Chakki Atta 100% stone ground whole wheat flour',
    photoLabel: 'chakki atta',
    bg: '#191410',
  },
];

const cleanTag = (tag) => {
  if (!tag) return 'FEATURED';
  const parts = tag.split(/[·•]/);
  if (parts.length > 2) {
    return parts.slice(0, 2).map(p => p.trim()).join(' · ');
  }
  return tag.trim();
};

const formatTitle = (title) => {
  if (!title) return '';
  const lines = title.split('\n').map(l => l.trim()).filter(Boolean);
  if (lines.length >= 3) {
    return `${lines[0]}\n${lines.slice(1).join(' ')}`;
  }
  return title;
};

const bannerSlides = computed(() => {
  if (props.sliders && props.sliders.length > 0) {
    return props.sliders.map(s => {
      const defaultMatch = defaultBannerSlides.find(d => 
        d.id === s.id || 
        d.link === s.link_url || 
        (d.photoLabel && s.photo_label && d.photoLabel.toLowerCase() === s.photo_label.toLowerCase()) ||
        (d.image && s.image && d.image.toLowerCase() === s.image.toLowerCase()) ||
        (s.title && d.title && s.title.toLowerCase().includes(d.title.split('\n')[0].toLowerCase()))
      );

      const rawTag = s.tag || defaultMatch?.tag || 'FEATURED';
      const rawTitle = s.title || defaultMatch?.title || '';

      return {
        id: s.id,
        tag: cleanTag(rawTag),
        title: formatTitle(rawTitle),
        subtitle: defaultMatch?.subtitle || s.subtitle || 'Fresh click-and-collect batch ready for 1-hour store pickup.',
        highlight: s.photo_label ? `${s.photo_label} · Store Pickup in 1 hr` : (defaultMatch?.highlight || 'Click & Collect Ready'),
        cta: s.cta_text || defaultMatch?.cta || 'Order now',
        link: s.link_url || defaultMatch?.link || '/',
        image: s.image,
        alt: rawTitle.replace(/\n/g, ' '),
        photoLabel: s.photo_label || defaultMatch?.photoLabel || '',
        bg: s.bg_color || defaultMatch?.bg || '#16120e',
      };
    });
  }
  return defaultBannerSlides;
});

// Masala Dabba (Spice-Box) Essential Staples
const masalaDabbaSpices = [
  { id: 101, slug: 'haldi', name: 'Haldi', english: 'Turmeric Powder', size: '200g', price: 3.49, image: '/images/products/garam_masala.jpg', photoLabel: 'haldi' },
  { id: 102, slug: 'jeera', name: 'Jeera', english: 'Whole Cumin', size: '200g', price: 4.29, image: '/images/products/garam_masala.jpg', photoLabel: 'jeera' },
  { id: 103, slug: 'rai', name: 'Rai', english: 'Mustard Seeds', size: '200g', price: 2.99, image: '/images/products/garam_masala.jpg', photoLabel: 'rai' },
  { id: 104, slug: 'dhaniya', name: 'Dhaniya', english: 'Coriander Powder', size: '200g', price: 3.49, image: '/images/products/garam_masala.jpg', photoLabel: 'dhaniya' },
  { id: 105, slug: 'mirch', name: 'Lal Mirch', english: 'Kashmiri Chilli', size: '200g', price: 4.49, image: '/images/products/garam_masala.jpg', photoLabel: 'mirch' },
  { id: 'garam-masala', slug: 'garam-masala', name: 'Garam Masala', english: 'Royal 12-Spice', size: '100g', price: 3.49, image: '/images/products/garam_masala.jpg', photoLabel: 'garam masala' },
  { id: 107, slug: 'hing', name: 'Hing', english: 'Asafoetida', size: '50g', price: 3.99, image: '/images/products/garam_masala.jpg', photoLabel: 'hing' },
];

const getSpiceLink = (spice) => {
  const matched = (props.products || []).find(p => p.slug === spice.slug || p.name.toLowerCase().includes(spice.name.toLowerCase()));
  if (matched) {
    return `/products/${matched.slug}`;
  }
  return `/search?q=${encodeURIComponent(spice.name)}`;
};

const currentSlideIndex = ref(0);
const currentSlide = computed(() => {
  const slides = bannerSlides.value;
  return slides[currentSlideIndex.value] || slides[0] || null;
});

let autoplayTimer = null;

const startAutoplay = () => {
  stopAutoplay();
  if (!bannerSlides.value || bannerSlides.value.length <= 1) return;
  autoplayTimer = setInterval(() => {
    nextSlide();
  }, 5000);
};

const stopAutoplay = () => {
  if (autoplayTimer) {
    clearInterval(autoplayTimer);
    autoplayTimer = null;
  }
};

const pauseAutoplay = () => {
  stopAutoplay();
};

const resumeAutoplay = () => {
  startAutoplay();
};

const setSlide = (index) => {
  const total = bannerSlides.value.length;
  if (!total) return;
  currentSlideIndex.value = Math.max(0, Math.min(index, total - 1));
  startAutoplay();
};

const nextSlide = () => {
  const total = bannerSlides.value.length;
  if (total <= 1) return;
  currentSlideIndex.value = (currentSlideIndex.value + 1) % total;
};

const prevSlide = () => {
  const total = bannerSlides.value.length;
  if (total <= 1) return;
  currentSlideIndex.value = (currentSlideIndex.value - 1 + total) % total;
};

// Reset index safely if sliders change
watch(bannerSlides, (newSlides) => {
  if (newSlides && newSlides.length > 0 && currentSlideIndex.value >= newSlides.length) {
    currentSlideIndex.value = 0;
  }
});

// Drag & Swipe State for Hero Slider (Both Touch & Mouse)
const dragOffset = ref(0);
const isPointerDown = ref(false);
const isDragging = ref(false);
let startX = 0;
let dragThresholdPassed = false;

const handleDragStart = (e) => {
  if (e.type === 'mousedown' && e.button !== 0) return;
  if (!bannerSlides.value || bannerSlides.value.length <= 1) return;
  isPointerDown.value = true;
  dragThresholdPassed = false;
  startX = e.clientX;
  dragOffset.value = 0;
  pauseAutoplay();
};

const handleDragMove = (e) => {
  if (!isPointerDown.value) return;
  const currentX = e.clientX;
  const diff = currentX - startX;
  
  if (Math.abs(diff) > 8) {
    isDragging.value = true;
    dragThresholdPassed = true;
  }

  if (isDragging.value) {
    const total = bannerSlides.value.length;
    if ((currentSlideIndex.value === 0 && diff > 0) || (currentSlideIndex.value >= total - 1 && diff < 0)) {
      dragOffset.value = diff * 0.25;
    } else {
      dragOffset.value = diff;
    }
  }
};

const handleDragEnd = () => {
  if (!isPointerDown.value) return;
  isPointerDown.value = false;

  if (isDragging.value) {
    if (dragOffset.value < -45) {
      nextSlide();
    } else if (dragOffset.value > 45) {
      prevSlide();
    }
    dragOffset.value = 0;
    setTimeout(() => {
      isDragging.value = false;
      dragThresholdPassed = false;
    }, 120);
  } else {
    dragOffset.value = 0;
  }

  resumeAutoplay();
};

const handleWindowMouseUp = () => {
  if (isPointerDown.value) {
    handleDragEnd();
  }
};

// Touch Handlers
const handleTouchStart = (e) => {
  if (!bannerSlides.value || bannerSlides.value.length <= 1) return;
  if (e.touches && e.touches[0]) {
    isPointerDown.value = true;
    dragThresholdPassed = false;
    startX = e.touches[0].clientX;
    dragOffset.value = 0;
    pauseAutoplay();
  }
};

const handleTouchMove = (e) => {
  if (!isPointerDown.value || !e.touches || !e.touches[0]) return;
  const currentX = e.touches[0].clientX;
  const diff = currentX - startX;

  if (Math.abs(diff) > 8) {
    isDragging.value = true;
    dragThresholdPassed = true;
  }

  if (isDragging.value) {
    const total = bannerSlides.value.length;
    if ((currentSlideIndex.value === 0 && diff > 0) || (currentSlideIndex.value >= total - 1 && diff < 0)) {
      dragOffset.value = diff * 0.25;
    } else {
      dragOffset.value = diff;
    }
  }
};

const handleTouchEnd = () => {
  handleDragEnd();
};

const handleSlideClick = (e) => {
  if (dragThresholdPassed || isDragging.value) {
    e.preventDefault();
    e.stopPropagation();
  }
};

onMounted(() => {
  window.addEventListener('mouseup', handleWindowMouseUp);
  if (props.products && props.products.length > 0) {
    store.setProducts(props.products);
  }
  startAutoplay();
});

onUnmounted(() => {
  window.removeEventListener('mouseup', handleWindowMouseUp);
  stopAutoplay();
});

// Products for "Buy it again"
const buyAgainItems = computed(() => {
  const items = store.products.filter(p => p.buyAgain);
  return items.length > 0 ? items : store.products.slice(0, 5);
});

// Recipe Kits (from live DB / Admin or fallback)
const recipeKitItems = computed(() => {
  if (props.recipeKits && props.recipeKits.length > 0) {
    return props.recipeKits.map(k => ({
      id: k.id,
      slug: k.slug,
      name: k.name,
      price: Number(k.price),
      originalPrice: Number(k.original_price || (Number(k.price) + 2.50)),
      image: k.image,
      servings: k.servings || 'Serves 4',
      cookingTime: k.cooking_time || '20 mins',
      photoLabel: k.subtitle_tag || 'recipe kit',
      freshnessLine: k.description || 'Pre-portioned fresh ingredients & spices · Zero food waste',
      productsCount: k.products ? k.products.length : 3,
      products: k.products || [],
    }));
  }
  return [
    {
      id: 6,
      slug: 'paneer-curry',
      name: 'Paneer Butter Masala Kit',
      price: 14.99,
      originalPrice: 18.99,
      image: '/images/products/paneer_curry.jpg',
      servings: 'Serves 4',
      cookingTime: '25 mins',
      photoLabel: 'paneer kit',
      productsCount: 3,
      products: [],
    },
    {
      id: 7,
      slug: 'biryani',
      name: 'Royal Dum Biryani Kit',
      price: 19.99,
      originalPrice: 24.49,
      image: '/images/products/biryani.jpg',
      servings: 'Serves 4',
      cookingTime: '45 mins',
      photoLabel: 'biryani kit',
      productsCount: 3,
      products: [],
    },
    {
      id: 8,
      slug: 'chana-masala',
      name: 'Punjabi Chana Masala Kit',
      price: 11.99,
      originalPrice: 14.49,
      image: '/images/products/garam_masala.jpg',
      servings: 'Serves 4',
      cookingTime: '30 mins',
      photoLabel: 'chana kit',
      productsCount: 2,
      products: [],
    },
    {
      id: 9,
      slug: 'dal-tadka',
      name: 'Dal Tadka & Jeera Rice Kit',
      price: 10.99,
      originalPrice: 13.99,
      image: '/images/products/toor_dal.jpg',
      servings: 'Serves 4',
      cookingTime: '20 mins',
      photoLabel: 'dal kit',
      productsCount: 3,
      products: [],
    },
  ];
});

const handleAddKitToCart = (kit) => {
  if (kit.products && kit.products.length > 0) {
    kit.products.forEach(p => {
      store.addToCart({
        id: p.id,
        name: p.name,
        price: Number(p.price),
        weight: p.size_main,
        quantity: p.pivot?.quantity || 1,
        image: p.image,
      });
    });
  } else {
    store.addToCart({
      id: kit.id || kit.slug,
      name: kit.name,
      price: kit.price,
      image: kit.image,
    });
  }
};
</script>

<template>
  <Head title="Masala Mart — Indian Groceries & Fresh Click-and-Collect" />

  <StoreLayout>
    <div class="space-y-7 sm:space-y-10">

      <!-- HERO SECTION: Premium Editorial Banner & Click & Collect Hub -->
      <section class="grid grid-cols-1 lg:grid-cols-12 gap-4 lg:gap-5 items-stretch">
        <!-- Main Festival Pre-Order Banner (Interactive Sliding Carousel with Drag & Swipe) -->
        <div class="lg:col-span-8 flex flex-col justify-between">
          <div 
            class="relative overflow-hidden rounded-2xl sm:rounded-3xl shadow-sm h-[205px] xs:h-[215px] sm:h-[265px] md:h-[295px] lg:h-[345px] group bg-[#16120e] select-none cursor-grab active:cursor-grabbing touch-pan-y border border-black/10"
            @mouseenter="pauseAutoplay"
            @mousedown="handleDragStart"
            @mousemove="handleDragMove"
            @mouseup="handleDragEnd"
            @mouseleave="handleDragEnd"
            @touchstart.passive="handleTouchStart"
            @touchmove="handleTouchMove"
            @touchend="handleTouchEnd"
          >
            <!-- Horizontal sliding track -->
            <div 
              class="flex h-full w-full"
              :class="{ 'transition-transform duration-500 ease-out': !isPointerDown }"
              :style="{ 
                transform: `translateX(calc(-${currentSlideIndex * 100}% + ${dragOffset}px))` 
              }"
            >
              <div 
                v-for="slide in bannerSlides"
                :key="slide.id"
                class="w-full shrink-0 h-full text-white relative overflow-hidden select-none flex items-stretch"
                :style="{ backgroundColor: slide.bg || '#16120e' }"
              >
                <!-- Background Image Layer: Cleanly placed on right side with organic gradient dissolve -->
                <div class="absolute inset-y-0 right-0 w-[54%] xs:w-[52%] sm:w-[54%] lg:w-[50%] overflow-hidden pointer-events-none">
                  <img 
                    :src="slide.image" 
                    :alt="slide.alt" 
                    class="w-full h-full object-cover object-center scale-100 group-hover:scale-105 transition-transform duration-700 ease-out select-none"
                    draggable="false"
                  />
                  <!-- Smooth Dissolve Scrim on Left Edge of Image so it merges seamlessly into slide.bg -->
                  <div 
                    class="absolute inset-0 pointer-events-none"
                    :style="{
                      background: `linear-gradient(to right, ${slide.bg || '#16120e'} 0%, ${slide.bg || '#16120e'}E6 20%, ${slide.bg || '#16120e'}33 55%, transparent 100%)`
                    }"
                  ></div>
                  <!-- Warm Golden Ambient Radial Bloom -->
                  <div class="absolute inset-0 bg-[radial-gradient(circle_at_70%_50%,rgba(228,185,122,0.12),transparent_70%)] pointer-events-none"></div>

                  <!-- Photo Category Tag Badge in Bottom Right (Clean desktop/tablet only) -->
                  <div v-if="slide.photoLabel" class="hidden sm:inline-flex absolute bottom-3.5 right-3.5 sm:bottom-4 sm:right-4 z-10 pointer-events-none">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-black/60 backdrop-blur-md text-[10px] text-white/90 font-medium border border-white/15 shadow-sm">
                      <span class="w-1.5 h-1.5 rounded-full bg-[#e4b97a]"></span>
                      <span class="capitalize">{{ slide.photoLabel }}</span>
                    </span>
                  </div>
                </div>

                <!-- Text Content & Interactive CTA: Cleanly isolated on left, 100% legible, vertically centered -->
                <Link 
                  :href="slide.link"
                  @click="handleSlideClick"
                  class="relative z-20 w-full max-w-[58%] xs:max-w-[56%] sm:max-w-[54%] lg:max-w-[50%] p-4 xs:p-4.5 sm:p-7 lg:p-8 flex flex-col justify-center h-full select-none cursor-pointer"
                  draggable="false"
                >
                  <div class="space-y-1.5 xs:space-y-2 sm:space-y-2.5">
                    <!-- Crisp Category Pill Badge -->
                    <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 sm:px-3 sm:py-1 rounded-full bg-white/10 backdrop-blur-md border border-white/15 text-[#f5d8a8] text-[8.5px] xs:text-[9.5px] sm:text-[10.5px] font-bold tracking-wider uppercase shadow-xs w-fit">
                      <Sparkles class="w-2.5 h-2.5 sm:w-3 sm:h-3 text-[#e4b97a] shrink-0" />
                      <span class="truncate max-w-[170px] sm:max-w-none">{{ slide.tag }}</span>
                    </div>

                    <!-- Grand Editorial Serif Title -->
                    <h2 class="text-[18px] xs:text-[20px] sm:text-2xl lg:text-[28px] font-serif font-medium leading-[1.16] sm:leading-[1.12] tracking-tight text-white drop-shadow-xs whitespace-pre-line line-clamp-2">
                      {{ slide.title }}
                    </h2>

                    <!-- Descriptive Subtitle (Always visible, 100% crisp on solid dark backdrop) -->
                    <p v-if="slide.subtitle" class="text-[11px] xs:text-[11.5px] sm:text-[12.5px] text-stone-300 font-normal leading-snug line-clamp-2 max-w-[210px] sm:max-w-xs drop-shadow-xs">
                      {{ slide.subtitle }}
                    </p>
                  </div>

                  <!-- CTA Button & Highlight Pill -->
                  <div class="pt-2 xs:pt-2.5 sm:pt-3.5 flex flex-wrap items-center gap-2 sm:gap-3">
                    <span class="inline-flex items-center gap-1.5 sm:gap-2 px-3.5 py-1.5 xs:px-4 xs:py-2 sm:px-4.5 sm:py-2.5 rounded-full bg-white text-[#1a1a1a] hover:bg-[#f5eee2] text-[11px] xs:text-xs font-bold shadow-sm hover:shadow-md transition-all duration-300 group-hover:scale-[1.02] shrink-0">
                      <span>{{ slide.cta }}</span>
                      <ChevronRight class="w-3.5 h-3.5 stroke-[2.5] text-[#1a1a1a] transition-transform group-hover:translate-x-0.5" />
                    </span>

                    <span v-if="slide.highlight" class="hidden md:inline-flex items-center gap-1.5 text-xs text-stone-300 font-normal px-2.5 py-0.5 rounded-full bg-black/40 backdrop-blur-xs border border-white/10">
                      <span class="w-1.5 h-1.5 rounded-full bg-[#e4b97a]"></span>
                      <span>{{ slide.highlight }}</span>
                    </span>
                  </div>
                </Link>
              </div>
            </div>

            <!-- Floating Glassmorphic Hover Arrows -->
            <button 
              type="button"
              @click.stop.prevent="prevSlide"
              class="hidden sm:flex absolute left-3 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full bg-black/40 hover:bg-black/85 text-white items-center justify-center backdrop-blur-md border border-white/15 opacity-0 group-hover:opacity-100 transition-all duration-200 cursor-pointer z-30 shadow-lg active:scale-95"
              aria-label="Previous slide"
            >
              <ChevronLeft class="w-4 h-4 stroke-[2.5]" />
            </button>
            <button 
              type="button"
              @click.stop.prevent="nextSlide"
              class="hidden sm:flex absolute right-3 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full bg-black/40 hover:bg-black/85 text-white items-center justify-center backdrop-blur-md border border-white/15 opacity-0 group-hover:opacity-100 transition-all duration-200 cursor-pointer z-30 shadow-lg active:scale-95"
              aria-label="Next slide"
            >
              <ChevronRight class="w-4 h-4 stroke-[2.5]" />
            </button>
          </div>

          <!-- Modern Segmented Pill Indicator & Counter -->
          <div class="flex items-center justify-between mt-2.5 sm:mt-3 px-1">
            <div class="flex items-center gap-1.5 sm:gap-2" role="tablist" aria-label="Hero banner pagination">
              <button
                v-for="(slide, idx) in bannerSlides"
                :key="slide.id"
                type="button"
                @click="setSlide(idx)"
                :aria-label="`Slide ${idx + 1}: ${slide.tag}`"
                :aria-selected="currentSlideIndex === idx"
                role="tab"
                class="group/indicator flex items-center py-1 cursor-pointer focus:outline-none"
              >
                <span 
                  class="h-1 sm:h-1.5 rounded-full transition-all duration-300 block"
                  :class="currentSlideIndex === idx ? 'w-6 sm:w-8 bg-[#a47a3c] shadow-xs' : 'w-1.5 sm:w-2 bg-[#dfd6c8] group-hover/indicator:bg-[#86868b]'"
                ></span>
              </button>
            </div>

            <div class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-[#f5eee2] border border-[#e0d9cc] text-[10px] sm:text-[11px] font-semibold text-stone-600 tracking-wider">
              <span class="text-stone-900 font-bold">0{{ currentSlideIndex + 1 }}</span>
              <span class="text-stone-300 font-normal">/</span>
              <span>0{{ bannerSlides.length }}</span>
            </div>
          </div>
        </div>

        <!-- Desktop Click & Collect Guarantee Card (Matching Elevation & Height) -->
        <div class="hidden lg:flex lg:col-span-4 bg-[#f5eee2] rounded-3xl border border-[#e0d9cc] p-6 lg:p-7 flex-col justify-between shadow-xs h-[345px]">
          <div class="space-y-3">
            <div class="flex items-center justify-between text-[10px] font-bold uppercase tracking-wider text-[#7a5620]">
              <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-white/70 border border-[#e0d9cc]">
                <Store class="w-3 h-3 text-[#a47a3c]" />
                EXPRESS STORE PICKUP
              </span>
              <span class="text-[#7a5620] font-bold bg-[#a47a3c]/15 px-2 py-0.5 rounded-full">1 HR READY</span>
            </div>
            <h3 class="text-2xl font-serif font-medium text-[#1d1d1f] tracking-tight leading-snug">
              Order online, pull up, pop the trunk.
            </h3>
            <p class="text-xs text-[#6e6e73] font-normal leading-relaxed">
              We hand-pick chilled produce, fresh dairy, and heavy pantry bags so your trunk is loaded in under 2 minutes.
            </p>

            <div class="space-y-2 pt-2 text-xs text-[#1d1d1f] font-medium">
              <div class="flex items-center gap-2 text-[11px] text-stone-700">
                <span class="w-4 h-4 rounded-full bg-[#1a1a1a] text-white flex items-center justify-center text-[9px]">✓</span>
                <span>Thermal insulated bags for fresh dairy & paneer</span>
              </div>
              <div class="flex items-center gap-2 text-[11px] text-stone-700">
                <span class="w-4 h-4 rounded-full bg-[#1a1a1a] text-white flex items-center justify-center text-[9px]">✓</span>
                <span>Zero convenience fees · Always 100% free</span>
              </div>
            </div>
          </div>

          <div class="pt-4 border-t border-[#e0d9cc] flex items-center justify-between">
            <div>
              <div class="text-xs font-bold text-[#1d1d1f]">{{ store.selectedStore }}</div>
              <div class="text-[11px] font-normal text-[#6e6e73]">Bay 3 curbside · Ready in {{ store.readyTime }}</div>
            </div>
            <Link 
              href="/cart" 
              class="px-4 py-2.5 bg-[#1a1a1a] hover:bg-black text-xs font-semibold text-white rounded-full inline-flex items-center gap-1.5 transition-colors shadow-xs"
            >
              <span>Pickup Order</span>
              <ChevronRight class="w-3.5 h-3.5" />
            </Link>
          </div>
        </div>
      </section>

      <!-- SECTION: "Buy it again" (Screen 1a Specification with Devanagari badge) -->
      <section class="space-y-3.5">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <h2 class="text-xl sm:text-2xl font-serif font-medium text-[#1d1d1f] tracking-tight">Buy it again</h2>
            <span class="font-devanagari text-xs text-[#7a5620] bg-[#f5eee2] px-2 py-0.5 rounded-full border border-[#e0d9cc]">फिर से</span>
          </div>
          <Link 
            href="/reorder" 
            class="text-xs font-semibold text-[#1a1a1a] hover:text-[#a47a3c] cursor-pointer"
          >
            See all
          </Link>
        </div>

        <!-- Products Carousel / Grid (Full-bleed mobile scroll with padding so cards don't crop on the left) -->
        <div class="-mx-4 px-4 sm:mx-0 sm:px-0 flex sm:grid sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3.5 sm:gap-5 overflow-x-auto no-scrollbar pb-2 pt-1 snap-x scroll-pl-4">
          
          <Link 
            v-for="item in buyAgainItems" 
            :key="item.slug || item.id"
            :href="'/products/' + item.slug"
            class="relative block w-[155px] sm:w-auto shrink-0 snap-start flex flex-col group text-left cursor-pointer transition-transform duration-200"
          >
            <!-- Card Image Tile -->
            <div class="relative w-full aspect-square bg-[#f3efe7] rounded-[22px] sm:rounded-3xl overflow-hidden img-zoom-container shrink-0 border border-[#e0d9cc]/60 shadow-2xs">
              <img 
                :src="item.image" 
                :alt="item.name" 
                class="w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-300"
              />
              <span class="photo-label absolute top-2.5 left-2.5 text-[11px] text-[#1d1d1f] font-mono bg-white/80 backdrop-blur-xs px-2 py-0.5 rounded-lg z-10 shadow-2xs">
                {{ item.photoLabel || item.photo_label || item.label || 'product' }}
              </span>
              
              <!-- When item is in cart: show Primary [- qty +] stepper pill -->
              <div 
                v-if="store.getItemQuantity(item.id || item.slug) > 0"
                class="absolute bottom-2.5 inset-x-2.5 bg-[#1a1a1a] text-white h-8 sm:h-9 rounded-full flex items-center justify-between px-2.5 shadow-md z-10"
                @click.stop.prevent
              >
                <button 
                  type="button"
                  @click.stop.prevent="store.removeFromCart(item.id || item.slug)" 
                  class="p-1 hover:opacity-80 cursor-pointer flex items-center justify-center"
                  aria-label="Decrease quantity"
                >
                  <Minus class="w-3.5 h-3.5 stroke-[2.5]" />
                </button>
                <span class="text-xs font-bold">{{ store.getItemQuantity(item.id || item.slug) }}</span>
                <button 
                  type="button"
                  @click.stop.prevent="store.addToCart(item)" 
                  class="p-1 hover:opacity-80 cursor-pointer flex items-center justify-center"
                  aria-label="Increase quantity"
                >
                  <Plus class="w-3.5 h-3.5 stroke-[2.5]" />
                </button>
              </div>

              <!-- When item is not in cart: show round Primary + button -->
              <button 
                v-else
                type="button"
                @click.stop.prevent="store.addToCart(item)"
                class="absolute bottom-2.5 right-2.5 w-8 h-8 sm:w-9 sm:h-9 bg-[#1a1a1a] hover:bg-black text-white rounded-full flex items-center justify-center font-bold cursor-pointer active:scale-95 shadow-md z-10 transition-transform group-hover:scale-105"
                :aria-label="'Add ' + item.name + ' to cart'"
              >
                <Plus class="w-4 h-4 stroke-[2.5]" />
              </button>
            </div>

            <!-- Card Typography Below Image (Big price in Newsreader Medium 500) -->
            <div class="pt-2.5 text-left">
              <div class="text-lg sm:text-xl font-serif font-medium text-[#1d1d1f] leading-none">
                ${{ Number(item.price).toFixed(2) }}
              </div>
              <div class="text-sm sm:text-base font-semibold text-[#1d1d1f] mt-1 truncate">
                {{ item.name }}
              </div>
              <div class="text-xs sm:text-sm text-[#6e6e73] font-normal mt-0.5">
                {{ item.sizeMain || item.size_main || item.weight || item.size || 'Standard' }}
              </div>
            </div>
          </Link>

        </div>
      </section>

      <!-- SECTION: Masala Dabba (Spice-Box) Essentials -->
      <section class="space-y-3.5">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <h2 class="text-xl sm:text-2xl font-serif font-medium text-[#1d1d1f] tracking-tight">Masala Dabba Staples</h2>
            <span class="font-devanagari text-xs text-[#7a5620] bg-[#f5eee2] px-2 py-0.5 rounded-full border border-[#e0d9cc]">मसाला डब्बा</span>
          </div>
          <span class="text-xs text-[#6e6e73]">7 Daily Kitchen Spices</span>
        </div>

        <div class="-mx-4 px-4 sm:mx-0 sm:px-0 flex sm:grid sm:grid-cols-4 lg:grid-cols-7 gap-3 sm:gap-4 overflow-x-auto no-scrollbar pb-2 pt-1 snap-x sm:overflow-visible scroll-pl-4">
          <Link 
            v-for="spice in masalaDabbaSpices" 
            :key="spice.id"
            :href="getSpiceLink(spice)"
            class="relative block w-[140px] sm:w-auto shrink-0 snap-start flex flex-col group text-left cursor-pointer transition-transform duration-200"
          >
            <!-- Card Image Tile with Modern Classic v3 styling -->
            <div class="relative w-full aspect-square bg-[#f3efe7] rounded-[22px] sm:rounded-3xl overflow-hidden img-zoom-container shrink-0 border border-[#e0d9cc]/70 shadow-2xs group-hover:border-[#a47a3c]/60 transition-all group-hover:shadow-xs">
              <img 
                :src="spice.image" 
                :alt="spice.name" 
                class="w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-300"
              />
              <span class="photo-label absolute top-2.5 left-2.5 text-[10px] sm:text-[11px] text-[#1d1d1f] font-mono bg-white/85 backdrop-blur-xs px-2 py-0.5 rounded-lg z-10 shadow-2xs">
                {{ spice.photoLabel }}
              </span>

              <!-- When item is in cart: show Primary [- qty +] stepper pill -->
              <div 
                v-if="store.getItemQuantity(spice.id) > 0"
                class="absolute bottom-2.5 inset-x-2.5 bg-[#1a1a1a] text-white h-8 sm:h-9 rounded-full flex items-center justify-between px-2 sm:px-2.5 shadow-md z-10"
                @click.stop.prevent
              >
                <button 
                  type="button"
                  @click.stop.prevent="store.removeFromCart(spice.id)" 
                  class="p-1 hover:opacity-80 cursor-pointer flex items-center justify-center"
                  :aria-label="'Decrease ' + spice.name"
                >
                  <Minus class="w-3.5 h-3.5 stroke-[2.5]" />
                </button>
                <span class="text-xs font-bold">{{ store.getItemQuantity(spice.id) }}</span>
                <button 
                  type="button"
                  @click.stop.prevent="store.addToCart({ id: spice.id, name: spice.name + ' (' + spice.english + ')', price: spice.price, weight: spice.size, image: spice.image, label: spice.photoLabel })" 
                  class="p-1 hover:opacity-80 cursor-pointer flex items-center justify-center"
                  :aria-label="'Increase ' + spice.name"
                >
                  <Plus class="w-3.5 h-3.5 stroke-[2.5]" />
                </button>
              </div>

              <!-- When item is not in cart: show round Primary + button -->
              <button 
                v-else
                type="button"
                @click.stop.prevent="store.addToCart({ id: spice.id, name: spice.name + ' (' + spice.english + ')', price: spice.price, weight: spice.size, image: spice.image, label: spice.photoLabel })"
                class="absolute bottom-2.5 right-2.5 w-8 h-8 sm:w-8.5 sm:h-8.5 bg-[#1a1a1a] hover:bg-black text-white rounded-full flex items-center justify-center font-bold cursor-pointer active:scale-95 shadow-md z-10 transition-transform group-hover:scale-105"
                :aria-label="'Add ' + spice.name + ' to cart'"
              >
                <Plus class="w-4 h-4 stroke-[2.5]" />
              </button>
            </div>

            <!-- Card Typography Below Image (Big price in Newsreader Medium 500) -->
            <div class="pt-2 sm:pt-2.5 text-left">
              <div class="text-base sm:text-lg font-serif font-medium text-[#1d1d1f] leading-none">
                ${{ spice.price.toFixed(2) }}
              </div>
              <div class="text-sm sm:text-base font-serif font-medium text-[#1d1d1f] mt-1 truncate group-hover:text-[#a47a3c] transition-colors">
                {{ spice.name }}
              </div>
              <div class="text-xs text-[#6e6e73] font-normal mt-0.5 truncate">
                {{ spice.english }} · {{ spice.size }}
              </div>
            </div>
          </Link>
        </div>
      </section>

      <!-- SECTION: "Recipe kits" (Screen 1a Specification with Devanagari badge) -->
      <section id="recipe-kits" class="space-y-3.5">
        <div class="flex items-center justify-between">
          <div>
            <div class="flex items-center gap-2">
              <h2 class="text-xl sm:text-2xl font-serif font-medium text-[#1d1d1f] tracking-tight">Recipe kits</h2>
              <span class="font-devanagari text-xs text-[#7a5620] bg-[#f5eee2] px-2.5 py-0.5 rounded-full border border-[#e0d9cc]">एक टैप में रसोई</span>
            </div>
            <p class="text-xs text-[#6e6e73] font-normal mt-0.5">
              Every ingredient for one dish, added in one tap.
            </p>
          </div>
          <span class="text-xs font-semibold text-[#7a5620] hover:underline cursor-pointer">All 14 kits</span>
        </div>

        <!-- 2 Columns on mobile, 4 Columns on desktop -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 pt-1">
          
          <div 
            v-for="kit in recipeKitItems"
            :key="kit.slug || kit.id"
            class="bg-[#f3efe7] border border-[#e0d9cc] rounded-3xl p-4 flex flex-col justify-between hover:border-[#a47a3c]/60 transition-colors shadow-2xs group"
          >
            <div>
              <!-- Kit Photo Tile -->
              <Link :href="'/recipe-kits/' + kit.slug" class="block relative w-full aspect-[4/3] bg-white rounded-2xl overflow-hidden img-zoom-container border border-[#e0d9cc]/60">
                <img 
                  :src="kit.image || (kit.slug === 'veg-manchurian' ? '/images/products/sweets.jpg' : '/images/products/paneer_curry.jpg')" 
                  :alt="kit.name" 
                  class="w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-300"
                />
                <!-- Monospace tag (Screen 2: manchurian, butter masala) -->
                <span class="font-mono text-xs text-[#1d1d1f] bg-white/85 backdrop-blur-xs px-2 py-0.5 rounded-md absolute top-2.5 left-2.5 shadow-2xs">
                  {{ kit.photoLabel || kit.photo_label || 'recipe kit' }}
                </span>
                <!-- Dynamic items count dark pill -->
                <span class="absolute top-2.5 right-2.5 bg-[#1a1a1a] text-white text-[11px] font-semibold px-2 py-0.5 rounded-md shadow-xs">
                  {{ kit.productsCount || 3 }} items
                </span>
              </Link>

              <!-- Kit Typography Below Image -->
              <div class="pt-3 text-left">
                <Link :href="'/recipe-kits/' + kit.slug" class="block">
                  <h3 class="font-serif font-medium text-base sm:text-lg text-[#1d1d1f] leading-snug group-hover:text-[#a47a3c] transition-colors">
                    {{ kit.name }}
                  </h3>
                </Link>
                <!-- Ingredients list -->
                <p class="text-xs text-[#6e6e73] font-normal mt-1 leading-snug line-clamp-2 min-h-[32px]">
                  {{ kit.freshnessLine || kit.freshness_line || 'Pre-portioned fresh ingredients & spices · Zero food waste' }}
                </p>

                <!-- Price row: Big price, old price, Save tag -->
                <div class="flex items-baseline gap-2 mt-2">
                  <span class="font-serif font-medium text-lg sm:text-xl text-[#1d1d1f]">
                    ${{ Number(kit.price).toFixed(2) }}
                  </span>
                  <span class="text-xs text-[#86868b] line-through font-normal">
                    ${{ Number(kit.originalPrice || kit.original_price || (Number(kit.price) + 2.45)).toFixed(2) }}
                  </span>
                  <span class="text-[11px] font-bold text-[#7a5620] bg-[#f5eee2] border border-[#e0d9cc] px-2 py-0.5 rounded-full">
                    Save ${{ (Number(kit.originalPrice || kit.original_price || (Number(kit.price) + 2.45)) - Number(kit.price)).toFixed(2) }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Full-width CTA Button: "+ Add all to cart" -->
            <button 
              type="button"
              @click.stop.prevent="handleAddKitToCart(kit)"
              class="w-full mt-3.5 py-2.5 sm:py-3 bg-[#1a1a1a] hover:bg-black text-white font-semibold text-xs rounded-full flex items-center justify-center gap-1.5 transition-colors cursor-pointer active:scale-98 shadow-xs"
              :aria-label="'Add all ' + (kit.productsCount || 3) + ' to cart for ' + kit.name"
            >
              <Plus class="w-3.5 h-3.5 stroke-[2.5]" />
              <span>Add all {{ kit.productsCount || 3 }} to cart</span>
            </button>
          </div>

        </div>
      </section>

      <!-- SECTION: Subscribe & Save Banner Callout -->
      <section class="bg-[#1a1a1a] text-white p-6 sm:p-9 rounded-3xl flex flex-col md:flex-row items-start md:items-center justify-between gap-6 shadow-sm border border-[#e0d9cc]/20">
        <div class="space-y-2">
          <div class="flex items-center gap-2 text-[11px] font-semibold text-[#a47a3c] uppercase tracking-wider">
            <span class="w-2 h-2 rounded-full bg-[#a47a3c]"></span>
            <span>AUTOMATIC REPLENISHMENT · ZERO COMMITMENT</span>
          </div>
          <h3 class="text-xl sm:text-2xl font-serif font-medium tracking-tight">
            Never run out of Chakki Atta or Basmati again.
          </h3>
          <p class="text-xs text-stone-300 font-normal max-w-xl leading-relaxed">
            Subscribe to monthly essentials and get 5% discount on every bag. Skip, pause, or cancel anytime in one click.
          </p>
        </div>

        <div class="shrink-0 flex flex-row gap-3 w-full md:w-auto">
          <Link 
            href="/products/rice" 
            class="flex-1 sm:flex-none px-5 py-3 bg-[#a47a3c] hover:bg-[#8e6630] text-white text-xs font-semibold rounded-full text-center transition-colors shadow-sm"
          >
            Explore Staples (-5%)
          </Link>
          <Link 
            href="/account" 
            class="flex-1 sm:flex-none px-5 py-3 bg-stone-800 hover:bg-stone-700 text-white text-xs font-semibold rounded-full text-center transition-colors"
          >
            Manage Subscriptions
          </Link>
        </div>
      </section>

    </div>
  </StoreLayout>
</template>

<style scoped>
.fade-banner-enter-active,
.fade-banner-leave-active {
  transition: opacity 0.25s ease;
}

.fade-banner-enter-from,
.fade-banner-leave-to {
  opacity: 0;
}
</style>
