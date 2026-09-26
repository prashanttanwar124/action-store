<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import StoreLayout from '../Layouts/StoreLayout.vue';
import { useStore } from '../stores/cart';
import { 
  ChevronRight, 
  ChevronLeft,
  Plus, 
  Minus, 
  Sparkles 
} from 'lucide-vue-next';

const props = defineProps({
  products: {
    type: Array,
    default: () => [],
  },
});

const store = useStore();

// Hero Banner Interactive Carousel Slides
const bannerSlides = [
  {
    id: 'sweets',
    tag: 'FESTIVAL PRE-ORDER · CLOSES OCT 30',
    title: 'Diwali sweets,\nboxed &\nready.',
    cta: 'Pre-order from $24.99',
    link: '/products/sweets-box',
    image: '/images/products/sweets.jpg',
    alt: 'Assorted luxury Diwali sweets in royal gold gift box',
    photoLabel: 'mithai box',
    bg: 'bg-[#1a1a1a]',
  },
  {
    id: 'curry-kit',
    tag: 'CHEF-CRAFTED · DINNER IN 20 MINS',
    title: 'Restaurant curry,\ncooked at\nhome.',
    cta: 'Order kit for $14.99',
    link: '/products/paneer-curry',
    image: '/images/products/paneer_curry.jpg',
    alt: 'Fresh Paneer Butter Masala curry kit with pre-portioned ingredients',
    photoLabel: 'paneer kit',
    bg: 'bg-[#221c17]',
  },
  {
    id: 'atta',
    tag: 'SUBSCRIBE & SAVE · 10% OFF AUTO-DELIVER',
    title: 'Never run out\nof fresh atta\nagain.',
    cta: 'Subscribe from $18.04',
    link: '/products/atta',
    image: '/images/products/atta.jpg',
    alt: 'Chakki Atta 100% stone ground whole wheat flour',
    photoLabel: 'chakki atta',
    bg: 'bg-[#26201b]',
  },
];

// Masala Dabba (Spice-Box) Essential Staples
const masalaDabbaSpices = [
  { id: 101, slug: 'garam-masala', name: 'Haldi', english: 'Turmeric Powder', size: '200g', price: 3.49, image: '/images/products/garam_masala.jpg', photoLabel: 'haldi' },
  { id: 102, slug: 'garam-masala', name: 'Jeera', english: 'Whole Cumin', size: '200g', price: 4.29, image: '/images/products/garam_masala.jpg', photoLabel: 'jeera' },
  { id: 103, slug: 'garam-masala', name: 'Rai', english: 'Mustard Seeds', size: '200g', price: 2.99, image: '/images/products/garam_masala.jpg', photoLabel: 'rai' },
  { id: 104, slug: 'garam-masala', name: 'Dhaniya', english: 'Coriander Powder', size: '200g', price: 3.49, image: '/images/products/garam_masala.jpg', photoLabel: 'dhaniya' },
  { id: 105, slug: 'garam-masala', name: 'Lal Mirch', english: 'Kashmiri Chilli', size: '200g', price: 4.49, image: '/images/products/garam_masala.jpg', photoLabel: 'mirch' },
  { id: 106, slug: 'garam-masala', name: 'Garam Masala', english: 'Royal 12-Spice', size: '100g', price: 5.49, image: '/images/products/garam_masala.jpg', photoLabel: 'garam masala' },
  { id: 107, slug: 'garam-masala', name: 'Hing', english: 'Asafoetida', size: '50g', price: 3.99, image: '/images/products/garam_masala.jpg', photoLabel: 'hing' },
];

const currentSlideIndex = ref(0);
const currentSlide = computed(() => bannerSlides[currentSlideIndex.value]);

let autoplayTimer = null;

const startAutoplay = () => {
  stopAutoplay();
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
  currentSlideIndex.value = index;
  startAutoplay();
};

const nextSlide = () => {
  currentSlideIndex.value = (currentSlideIndex.value + 1) % bannerSlides.length;
};

const prevSlide = () => {
  currentSlideIndex.value = (currentSlideIndex.value - 1 + bannerSlides.length) % bannerSlides.length;
};

// Drag & Swipe State for Hero Slider (Both Touch & Mouse)
const dragOffset = ref(0);
const isPointerDown = ref(false);
const isDragging = ref(false);
let startX = 0;
let dragThresholdPassed = false;

const handleDragStart = (e) => {
  if (e.type === 'mousedown' && e.button !== 0) return;
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
    if ((currentSlideIndex.value === 0 && diff > 0) || (currentSlideIndex.value === bannerSlides.length - 1 && diff < 0)) {
      dragOffset.value = diff * 0.3;
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

// Touch Handlers
const handleTouchStart = (e) => {
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
    if ((currentSlideIndex.value === 0 && diff > 0) || (currentSlideIndex.value === bannerSlides.length - 1 && diff < 0)) {
      dragOffset.value = diff * 0.3;
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
  if (props.products && props.products.length > 0) {
    store.setProducts(props.products);
  }
  startAutoplay();
});

onUnmounted(() => {
  stopAutoplay();
});

// Products for "Buy it again"
const buyAgainItems = computed(() => {
  const items = store.products.filter(p => p.buyAgain);
  return items.length > 0 ? items : store.products.slice(0, 5);
});

// Recipe Kits
const recipeKitItems = computed(() => {
  const items = store.products.filter(p => p.isRecipeKit);
  return items.length > 0 ? items : [
    {
      id: 6,
      slug: 'paneer-curry',
      name: 'Paneer Butter Masala Kit',
      price: 14.99,
      image: '/images/products/paneer_curry.jpg',
      servings: 'Serves 4',
      cookingTime: '25 mins',
      photoLabel: 'paneer kit',
    },
    {
      id: 7,
      slug: 'biryani',
      name: 'Royal Dum Biryani Kit',
      price: 19.99,
      image: '/images/products/biryani.jpg',
      servings: 'Serves 4',
      cookingTime: '45 mins',
      photoLabel: 'biryani kit',
    },
    {
      id: 8,
      slug: 'chana-masala',
      name: 'Punjabi Chana Masala Kit',
      price: 11.99,
      image: '/images/products/garam_masala.jpg',
      servings: 'Serves 4',
      cookingTime: '30 mins',
      photoLabel: 'chana kit',
    },
    {
      id: 9,
      slug: 'dal-tadka',
      name: 'Dal Tadka & Jeera Rice Kit',
      price: 10.99,
      image: '/images/products/toor_dal.jpg',
      servings: 'Serves 4',
      cookingTime: '20 mins',
      photoLabel: 'dal kit',
    },
  ];
});
</script>

<template>
  <Head title="Masala Mart — Indian Groceries & Fresh Click-and-Collect" />

  <StoreLayout>
    <div class="space-y-7 sm:space-y-10">

      <!-- HERO SECTION: Split Festival Banner (Screen 1a Specification) -->
      <section class="grid grid-cols-1 lg:grid-cols-12 gap-4">
        <!-- Main Festival Pre-Order Banner (Interactive Sliding Carousel with Drag & Swipe) -->
        <div class="lg:col-span-8 flex flex-col">
          <div 
            class="relative overflow-hidden rounded-3xl shadow-sm min-h-[180px] sm:min-h-[200px] group bg-[#1a1a1a] select-none cursor-grab active:cursor-grabbing"
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
                class="w-full shrink-0 h-full text-white grid grid-cols-12 overflow-hidden min-h-[180px] sm:min-h-[200px]"
                :class="slide.bg"
              >
                <Link 
                  :href="slide.link"
                  @click="handleSlideClick"
                  class="contents cursor-pointer"
                  draggable="false"
                >
                  <!-- Banner Copy -->
                  <div class="col-span-7 p-4 sm:p-7 flex flex-col justify-between select-none">
                    <div>
                      <div class="text-[9px] sm:text-[10px] font-semibold tracking-wider uppercase text-[#a47a3c] leading-tight mb-1">
                        {{ slide.tag }}
                      </div>
                      <h1 class="text-2xl sm:text-3xl lg:text-4xl font-serif font-medium leading-tight tracking-tight text-white whitespace-pre-line">
                        {{ slide.title }}
                      </h1>
                    </div>

                    <div>
                      <span class="inline-flex items-center gap-1 text-xs font-semibold text-white group-hover:text-white/90 mt-2 sm:mt-4">
                        <span>{{ slide.cta }}</span>
                        <ChevronRight class="w-4 h-4 group-hover:translate-x-1 transition-transform" />
                      </span>
                    </div>
                  </div>

                  <!-- Banner Visual Texture (Photo Area with Real Product & Label) -->
                  <div class="col-span-5 relative bg-stone-900 overflow-hidden img-zoom-container select-none">
                    <img 
                      :src="slide.image" 
                      :alt="slide.alt" 
                      class="w-full h-full object-cover object-center absolute inset-0 pointer-events-none select-none"
                      draggable="false"
                    />
                    <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent pointer-events-none"></div>
                    <div class="relative z-10 p-2.5 sm:p-3 h-full flex flex-col justify-end items-end pointer-events-none">
                      <span class="photo-label text-[10px] text-white/80 font-normal">
                        {{ slide.photoLabel }}
                      </span>
                    </div>
                  </div>
                </Link>
              </div>
            </div>

            <!-- Prev/Next desktop hover arrows -->
            <button 
              type="button"
              @click.stop.prevent="prevSlide"
              class="hidden sm:flex absolute left-2.5 top-1/2 -translate-y-1/2 w-8 h-8 rounded-full bg-black/40 hover:bg-black/75 text-white items-center justify-center backdrop-blur-xs opacity-0 group-hover:opacity-100 transition-opacity duration-200 cursor-pointer z-20 shadow-md"
              aria-label="Previous slide"
            >
              <ChevronLeft class="w-4 h-4" />
            </button>
            <button 
              type="button"
              @click.stop.prevent="nextSlide"
              class="hidden sm:flex absolute right-2.5 top-1/2 -translate-y-1/2 w-8 h-8 rounded-full bg-black/40 hover:bg-black/75 text-white items-center justify-center backdrop-blur-xs opacity-0 group-hover:opacity-100 transition-opacity duration-200 cursor-pointer z-20 shadow-md"
              aria-label="Next slide"
            >
              <ChevronRight class="w-4 h-4" />
            </button>
          </div>

          <!-- Banner Pagination Indicator Dots Tightly Docked Directly Below Banner -->
          <div class="flex items-center gap-1.5 mt-2.5 pl-1" role="tablist" aria-label="Hero banner pagination">
            <button
              v-for="(slide, idx) in bannerSlides"
              :key="slide.id"
              type="button"
              @click="setSlide(idx)"
              :aria-label="`Slide ${idx + 1}: ${slide.tag}`"
              :aria-selected="currentSlideIndex === idx"
              role="tab"
              class="h-1.5 rounded-full transition-all duration-300 cursor-pointer focus:outline-none focus-visible:ring-1 focus-visible:ring-[#1a1a1a]"
              :class="currentSlideIndex === idx ? 'w-5 bg-[#a47a3c]' : 'w-1.5 bg-[#e0d9cc] hover:bg-[#86868b]'"
            ></button>
          </div>
        </div>

        <!-- Desktop Click & Collect Guarantee Card -->
        <div class="hidden lg:flex lg:col-span-4 bg-[#f3efe7] rounded-3xl border border-[#e0d9cc] p-6 flex-col justify-between shadow-xs">
          <div>
            <div class="flex items-center justify-between text-[10px] font-semibold uppercase tracking-wider text-[#6e6e73] mb-2">
              <span>EXPRESS STORE PICKUP</span>
              <span class="text-[#7a5620] font-semibold">1 HR READY</span>
            </div>
            <h3 class="text-xl font-serif font-medium text-[#1d1d1f] tracking-tight leading-snug">
              Order online, pull up, pop the trunk.
            </h3>
            <p class="text-xs text-[#6e6e73] mt-2 font-normal leading-relaxed">
              We pick temperature-controlled produce, fresh dairy, and heavy pantry bags so you don't have to wait in line.
            </p>
          </div>

          <div class="mt-6 pt-4 border-t border-[#e0d9cc]/60 flex items-center justify-between">
            <div>
              <div class="text-xs font-bold text-[#1d1d1f]">{{ store.selectedStore }}</div>
              <div class="text-[11px] font-normal text-[#6e6e73]">Pickups ready in {{ store.readyTime }}</div>
            </div>
            <Link 
              href="/cart" 
              class="px-4 py-2 bg-[#1a1a1a] hover:bg-black text-xs font-semibold text-white rounded-full inline-flex items-center gap-1 transition-colors"
            >
              <span>Start Order</span>
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
            :href="'/products/' + (spice.slug || 'garam-masala')"
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
              <Link :href="'/products/' + kit.slug" class="block relative w-full aspect-[4/3] bg-white rounded-2xl overflow-hidden img-zoom-container border border-[#e0d9cc]/60">
                <img 
                  :src="kit.image || (kit.slug === 'veg-manchurian' ? '/images/products/sweets.jpg' : '/images/products/paneer_curry.jpg')" 
                  :alt="kit.name" 
                  class="w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-300"
                />
                <!-- Monospace tag (Screen 2: manchurian, butter masala) -->
                <span class="font-mono text-xs text-[#1d1d1f] bg-white/85 backdrop-blur-xs px-2 py-0.5 rounded-md absolute top-2.5 left-2.5 shadow-2xs">
                  {{ kit.photoLabel || kit.photo_label || 'recipe kit' }}
                </span>
                <!-- 6 items dark pill -->
                <span class="absolute top-2.5 right-2.5 bg-[#1a1a1a] text-white text-[11px] font-semibold px-2 py-0.5 rounded-md shadow-xs">
                  6 items
                </span>
              </Link>

              <!-- Kit Typography Below Image -->
              <div class="pt-3 text-left">
                <Link :href="'/products/' + kit.slug" class="block">
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

            <!-- Full-width CTA Button: "+ Add all 6 to cart" -->
            <button 
              type="button"
              @click.stop.prevent="store.addToCart(kit)"
              class="w-full mt-3.5 py-2.5 sm:py-3 bg-[#1a1a1a] hover:bg-black text-white font-semibold text-xs rounded-full flex items-center justify-center gap-1.5 transition-colors cursor-pointer active:scale-98 shadow-xs"
              :aria-label="'Add all 6 to cart for ' + kit.name"
            >
              <Plus class="w-3.5 h-3.5 stroke-[2.5]" />
              <span>Add all 6 to cart</span>
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
