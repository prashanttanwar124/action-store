<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import StoreLayout from '../Layouts/StoreLayout.vue';
import { useStore } from '../stores/cart';
import { 
  Search as SearchIcon, 
  X, 
  ChevronLeft, 
  ChevronRight, 
  ChevronDown,
  ArrowUpDown, 
  Check, 
  Plus, 
  Minus, 
  Sparkles, 
  UtensilsCrossed, 
  Package, 
  RotateCcw,
  Flame,
  ShoppingBag,
  Wheat,
  Milk,
  Cookie,
  Apple,
  Leaf,
  Coffee,
  Tag,
  Layers
} from 'lucide-vue-next';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Tabs, TabsList, TabsTrigger } from '@/Components/ui/tabs';

const props = defineProps({
  products: {
    type: Object, // Laravel LengthAwarePaginator
    required: true,
  },
  recipeKits: {
    type: Object, // Laravel LengthAwarePaginator
    required: true,
  },
  categories: {
    type: Array,
    default: () => [],
  },
  filters: {
    type: Object,
    default: () => ({
      q: '',
      category: 'all',
      sort: 'featured',
      in_stock: false,
      has_sub: false,
      tab: 'products',
    }),
  },
});

const store = useStore();

// Local Filter States synced with Laravel props
const searchQuery = ref(props.filters.q || '');
const activeCategory = ref(props.filters.category || 'all');
const activeSort = ref(props.filters.sort || 'featured');
const inStockOnly = ref(Boolean(props.filters.in_stock));
const subscribeOnly = ref(Boolean(props.filters.has_sub));
const activeTab = ref(props.filters.tab || 'products');

// Keep Pinia cart store synced with incoming paginated products
watch(() => props.products?.data, (newProducts) => {
  if (newProducts && Array.isArray(newProducts) && newProducts.length > 0) {
    store.setProducts(newProducts);
  }
}, { immediate: true });

// Sync props if URL changes via browser back/forward
watch(() => props.filters, (newFilters) => {
  searchQuery.value = newFilters.q || '';
  activeCategory.value = newFilters.category || 'all';
  activeSort.value = newFilters.sort || 'featured';
  inStockOnly.value = Boolean(newFilters.in_stock);
  subscribeOnly.value = Boolean(newFilters.has_sub);
  activeTab.value = newFilters.tab || 'products';
}, { deep: true });

// Popular quick search suggestion tags
const popularSearches = [
  'Malai Paneer',
  'Desi Ghee',
  'Chakki Atta',
  'Basmati Rice',
  'Biryani Kit',
  'Garam Masala',
];

function selectQuickTag(tag) {
  searchQuery.value = tag;
  applyFilters(1);
}

const getCategoryIcon = (iconName) => {
  switch (iconName) {
    case 'Wheat': return Wheat;
    case 'Flame': return Flame;
    case 'Milk': return Milk;
    case 'Cookie': return Cookie;
    case 'Sparkles': return Sparkles;
    case 'Apple': return Apple;
    case 'UtensilsCrossed': return UtensilsCrossed;
    case 'Leaf': return Leaf;
    case 'Coffee': return Coffee;
    case 'Tag': return Tag;
    default: return ShoppingBag;
  }
};

// Category list formatting with Hindi titles and icons
const categoryList = computed(() => {
  const dynamicCategories = (props.categories || []).map(c => ({
    id: c.category,
    label: c.category_title?.split('·')[0]?.trim() || c.category,
    hindi: c.hindi_title || null,
    icon: c.icon || null,
  }));

  const allList = [{ id: 'all', label: 'All Items', hindi: 'सभी सामान', icon: 'ShoppingBag' }];
  dynamicCategories.forEach(cat => {
    if (!allList.some(item => item.id === cat.id)) {
      allList.push(cat);
    }
  });

  return allList;
});

// Has any active filter
const hasActiveFilters = computed(() => {
  return Boolean(
    searchQuery.value.trim() || 
    activeCategory.value !== 'all' || 
    activeSort.value !== 'featured' || 
    inStockOnly.value || 
    subscribeOnly.value
  );
});

// Laravel Pagination Links for Numeric Page Buttons
const paginationLinks = computed(() => {
  const links = activeTab.value === 'products' ? props.products?.links : props.recipeKits?.links;
  if (!links || !Array.isArray(links)) return [];
  // Exclude the first (Previous) and last (Next) links
  return links.slice(1, -1);
});

// Inertia URL Update (Real Laravel Pagination & Query Strings)
let searchDebounceTimer = null;

function applyFilters(page = 1) {
  const params = {};
  const q = searchQuery.value.trim();
  if (q) params.q = q;
  if (activeCategory.value && activeCategory.value !== 'all') params.category = activeCategory.value;
  if (activeSort.value && activeSort.value !== 'featured') params.sort = activeSort.value;
  if (inStockOnly.value) params.in_stock = 1;
  if (subscribeOnly.value) params.has_sub = 1;
  if (activeTab.value && activeTab.value !== 'products') params.tab = activeTab.value;
  if (page && page > 1) params.page = page;

  router.get('/search', params, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
}

function onSearchInput() {
  clearTimeout(searchDebounceTimer);
  searchDebounceTimer = setTimeout(() => {
    applyFilters(1);
  }, 350);
}

function clearSearch() {
  searchQuery.value = '';
  applyFilters(1);
}

function onCategorySelect(catId) {
  activeCategory.value = catId;
  applyFilters(1);
}

function onSortChange(val) {
  if (val && typeof val === 'string') {
    activeSort.value = val;
  }
  applyFilters(1);
}

function toggleInStock() {
  inStockOnly.value = !inStockOnly.value;
  applyFilters(1);
}

function toggleSubscribe() {
  subscribeOnly.value = !subscribeOnly.value;
  applyFilters(1);
}

function onTabSelect(tab) {
  activeTab.value = tab;
  applyFilters(1);
}

function resetAllFilters() {
  searchQuery.value = '';
  activeCategory.value = 'all';
  activeSort.value = 'featured';
  inStockOnly.value = false;
  subscribeOnly.value = false;
  activeTab.value = 'products';
  router.get('/search', {}, {
    preserveState: true,
    replace: true,
  });
}

function handleAddKitToCart(kit) {
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
}

function scrollToTop(duration = 550, targetY = 0) {
  if (typeof window === 'undefined') return;

  const startY = window.pageYOffset || document.documentElement.scrollTop || document.body.scrollTop || 0;
  const difference = targetY - startY;

  if (Math.abs(difference) < 5) return;

  const startTime = 'now' in window.performance ? performance.now() : new Date().getTime();

  function step(currentTime) {
    const elapsed = currentTime - startTime;
    const progress = Math.min(elapsed / duration, 1);

    // Ease In-Out Cubic for a luxurious, silky-smooth glide
    const ease = progress < 0.5 
      ? 4 * progress * progress * progress 
      : 1 - Math.pow(-2 * progress + 2, 3) / 2;

    const currentY = Math.round(startY + difference * ease);
    window.scrollTo(0, currentY);

    if (progress < 1) {
      window.requestAnimationFrame(step);
    }
  }

  window.requestAnimationFrame(step);
}

function changePage(url) {
  if (!url) return;

  // 1. Smoothly glide to top of catalogue
  scrollToTop(550);

  // 2. Visit new page with preserveScroll to prevent Inertia from doing a jarring instant jump
  router.visit(url, {
    preserveState: true,
    preserveScroll: true,
  });
}

watch([() => props.products?.current_page, () => props.recipeKits?.current_page], (newVals, oldVals) => {
  if (oldVals && (newVals[0] !== oldVals[0] || newVals[1] !== oldVals[1])) {
    scrollToTop(550);
  }
});
</script>

<template>
  <Head title="Search Catalogue — Masala Mart" />

  <StoreLayout :showHeader="true" :showFooter="true" :showBottomNav="true" :showCartBar="true" headerMode="search">
    <div class="space-y-4 sm:space-y-6 max-w-7xl mx-auto pb-24 pt-1 sm:pt-3 px-2 sm:px-0">
      
      <!-- ========================================== -->
      <!-- UNIFIED COMMAND CENTER: SEARCH & AISLES    -->
      <!-- ========================================== -->
      <div class="bg-white border border-[#dfd6c8] rounded-2xl sm:rounded-3xl p-3 sm:p-6 shadow-2xs space-y-2.5 sm:space-y-4">
        
        <!-- ROW 1: HEADER & COMPACT SEGMENTED MODE SWITCHER -->
        <div class="flex items-center justify-between gap-3 sm:border-b sm:border-[#e0d9cc]/60 sm:pb-4">
          <div>
            <!-- Eyebrow Badges: Desktop only to prevent mobile bloat -->
            <div class="hidden sm:flex items-center gap-2 mb-1.5">
              <span class="text-[11px] font-mono uppercase bg-stone-900 text-white px-2.5 py-0.5 rounded-full font-bold tracking-wider">
                Storefront Catalogue
              </span>
              <span class="text-[11px] text-amber-900 bg-amber-50 border border-amber-200/80 px-2.5 py-0.5 rounded-full font-bold font-devanagari">
                {{ activeTab === 'products' ? 'किराना व रोज़मर्रा सामान' : 'कारीगरी रेसिपी किट' }}
              </span>
            </div>

            <h1 class="text-base sm:text-2xl md:text-3xl font-serif font-bold text-stone-900 tracking-tight leading-none">
              <span class="sm:hidden">Catalogue</span>
              <span class="hidden sm:inline">Search &amp; Catalogue</span>
            </h1>

            <p class="hidden sm:block text-xs sm:text-sm text-stone-600 font-normal mt-1 max-w-xl leading-relaxed">
              Explore farm-fresh artisanal dairy, stone-ground flours, authentic spices, and chef-curated recipe kits.
            </p>
          </div>

          <!-- Segmented Tab Switcher (Tight on mobile, rich on desktop) -->
          <div class="inline-flex items-center p-0.5 sm:p-1 bg-stone-100 border border-stone-200/90 rounded-full shadow-2xs shrink-0">
            <button
              type="button"
              @click="onTabSelect('products')"
              :class="[
                'inline-flex items-center gap-1.5 px-2.5 sm:px-4 py-1 sm:py-2 rounded-full text-xs font-bold transition-all cursor-pointer',
                activeTab === 'products'
                  ? 'bg-stone-900 text-white shadow-xs'
                  : 'text-stone-700 hover:text-stone-950 font-semibold'
              ]"
            >
              <Package class="w-3.5 h-3.5" />
              <span>Groceries ({{ products.total }})</span>
            </button>
            <button
              type="button"
              @click="onTabSelect('kits')"
              :class="[
                'inline-flex items-center gap-1.5 px-2.5 sm:px-4 py-1 sm:py-2 rounded-full text-xs font-bold transition-all cursor-pointer',
                activeTab === 'kits'
                  ? 'bg-stone-900 text-white shadow-xs'
                  : 'text-stone-700 hover:text-stone-950 font-semibold'
              ]"
            >
              <UtensilsCrossed class="w-3.5 h-3.5" />
              <span>Recipe Kits ({{ recipeKits.total }})</span>
            </button>
          </div>
        </div>

        <!-- ROW 2: OMNI-SEARCH INPUT -->
        <div class="space-y-2">
          <div class="relative flex items-center group">
            <div class="absolute left-3.5 sm:left-4 flex items-center pointer-events-none z-10">
              <SearchIcon class="w-4 h-4 text-stone-500 group-focus-within:text-stone-900 transition-colors stroke-[2.2]" />
            </div>

            <input 
              v-model="searchQuery"
              @input="onSearchInput"
              type="text" 
              placeholder="Search paneer, atta, bilona ghee, chai masala, recipe kits…"
              class="w-full h-10 sm:h-12 pl-10 sm:pl-11 pr-20 sm:pr-24 bg-stone-50 hover:bg-white focus:bg-white border border-[#e0d9cc] focus:border-[#1a1a1a] focus:ring-3 focus:ring-[#1a1a1a]/10 focus:outline-none rounded-xl sm:rounded-2xl text-xs sm:text-sm text-stone-900 placeholder:text-stone-500 shadow-2xs font-medium transition-all"
            />

            <!-- Right action: Clear Button or Keyboard Shortcut hint -->
            <div class="absolute right-2.5 sm:right-3 flex items-center gap-1.5">
              <button 
                v-if="searchQuery" 
                type="button"
                @click="clearSearch"
                class="p-1 text-stone-500 hover:text-stone-900 rounded-full hover:bg-stone-200 transition-colors cursor-pointer"
                title="Clear search"
              >
                <X class="w-4 h-4" />
              </button>
              <span v-else class="hidden sm:inline-flex text-[10px] font-mono text-stone-500 bg-white border border-stone-200 px-2 py-0.5 rounded-md shadow-2xs">
                ⌘K
              </span>
            </div>
          </div>

          <!-- Quick Search Tags: Hidden on mobile to preserve vertical product visibility -->
          <div class="hidden sm:flex items-center gap-1.5 overflow-x-auto no-scrollbar text-xs pt-0.5">
            <span class="text-[11px] font-bold text-stone-500 shrink-0 uppercase tracking-wider">Popular:</span>
            <button
              v-for="tag in popularSearches"
              :key="tag"
              type="button"
              @click="selectQuickTag(tag)"
              class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold bg-stone-100 hover:bg-stone-200 text-stone-800 border border-stone-200 transition-colors shrink-0 cursor-pointer"
            >
              {{ tag }}
            </button>
          </div>
        </div>

        <!-- ROW 3: BROWSE AISLES / CATEGORIES (Products tab only) -->
        <div v-if="activeTab === 'products'" class="space-y-1 sm:space-y-1.5 sm:pt-1 sm:border-t sm:border-[#e0d9cc]/40">
          <div class="hidden sm:flex items-center justify-between mb-1">
            <span class="text-xs font-bold text-stone-900 uppercase tracking-wider">Browse Aisles</span>
            <span class="text-[11px] text-stone-500 font-semibold">{{ categoryList.length }} departments</span>
          </div>
          
          <div class="flex items-center gap-1.5 sm:gap-2 overflow-x-auto no-scrollbar py-0.5 sm:py-1 -mx-1 px-1 sm:mx-0 sm:px-0">
            <button
              v-for="cat in categoryList"
              :key="cat.id"
              type="button"
              @click="onCategorySelect(cat.id)"
              :class="[
                'inline-flex items-center gap-1.5 sm:gap-2 px-3 sm:px-3.5 py-1.5 sm:py-2 rounded-xl text-xs whitespace-nowrap transition-all cursor-pointer border shrink-0 shadow-2xs',
                activeCategory === cat.id 
                  ? 'bg-stone-900 text-white border-stone-900 font-bold shadow-xs scale-[1.01]' 
                  : 'bg-white text-stone-900 hover:text-black font-semibold border-stone-300 hover:border-stone-900 hover:bg-stone-50'
              ]"
            >
              <component 
                :is="getCategoryIcon(cat.icon)" 
                class="w-3.5 h-3.5 shrink-0" 
                :class="activeCategory === cat.id ? 'text-amber-400' : 'text-stone-500'" 
              />
              <span>{{ cat.label }}</span>
              <span 
                v-if="cat.hindi" 
                :class="[
                  'text-[10.5px] font-devanagari px-1.5 py-0.5 rounded font-bold transition-colors leading-none',
                  activeCategory === cat.id 
                    ? 'bg-white text-stone-950 shadow-2xs' 
                    : 'bg-stone-100 text-stone-800 border border-stone-200'
                ]"
              >
                {{ cat.hindi }}
              </span>
            </button>
          </div>
        </div>

        <!-- ROW 4: UTILITY CONTROLS (Live Count, Active Filter Chips, Toggles & Sort) -->
        <div class="flex items-center justify-between gap-2 pt-2 sm:pt-3 border-t border-stone-200/80 overflow-x-auto no-scrollbar">
          
          <!-- Left: Live Count & Active Filter Chips -->
          <div class="flex items-center gap-2 text-xs shrink-0">
            <span class="text-stone-600 font-medium whitespace-nowrap">
              <strong class="text-stone-900 font-bold">{{ activeTab === 'products' ? products.total : recipeKits.total }}</strong>
              <span class="hidden sm:inline"> {{ activeTab === 'products' ? 'groceries found' : 'recipe kits found' }}</span>
              <span class="sm:hidden"> items</span>
            </span>

            <!-- Active Filter Badges (Desktop) -->
            <span 
              v-if="searchQuery" 
              class="hidden sm:inline-flex items-center gap-1 bg-stone-100 text-stone-900 border border-stone-300 px-2.5 py-0.5 rounded-full text-[11px] font-bold"
            >
              "{{ searchQuery }}"
              <button type="button" @click="clearSearch" class="hover:text-black cursor-pointer"><X class="w-3 h-3" /></button>
            </span>

            <span 
              v-if="activeCategory !== 'all'" 
              class="hidden sm:inline-flex items-center gap-1 bg-stone-100 text-stone-900 border border-stone-300 px-2.5 py-0.5 rounded-full text-[11px] font-bold"
            >
              Aisle: {{ activeCategory }}
              <button type="button" @click="onCategorySelect('all')" class="hover:text-black cursor-pointer"><X class="w-3 h-3" /></button>
            </span>

            <button 
              v-if="hasActiveFilters"
              type="button" 
              @click="resetAllFilters"
              class="inline-flex items-center gap-1 text-[11px] font-bold text-amber-800 hover:text-amber-950 underline decoration-amber-800/40 cursor-pointer ml-0.5 whitespace-nowrap"
            >
              <RotateCcw class="w-3 h-3" />
              <span>Reset</span>
            </button>
          </div>

          <!-- Right: Filters & Sort Controls in Single Compact Strip -->
          <div class="flex items-center gap-1.5 sm:gap-2 shrink-0">
            
            <!-- In Stock Toggle -->
            <button 
              type="button"
              @click="toggleInStock"
              :class="[
                'inline-flex items-center gap-1.5 px-2.5 sm:px-3 py-1 sm:py-1.5 rounded-full text-xs font-bold border transition-all cursor-pointer shadow-2xs whitespace-nowrap',
                inStockOnly 
                  ? 'bg-stone-900 text-white border-stone-900 shadow-xs' 
                  : 'bg-white text-stone-800 border-stone-300 hover:text-black hover:border-stone-400 hover:bg-stone-50'
              ]"
            >
              <span class="w-1.5 h-1.5 rounded-full" :class="inStockOnly ? 'bg-emerald-400' : 'bg-emerald-600'"></span>
              <span>In Stock</span>
            </button>

            <!-- Subscribe & Save Toggle -->
            <button 
              v-if="activeTab === 'products'"
              type="button"
              @click="toggleSubscribe"
              :class="[
                'inline-flex items-center gap-1.5 px-2.5 sm:px-3 py-1 sm:py-1.5 rounded-full text-xs font-bold border transition-all cursor-pointer shadow-2xs whitespace-nowrap',
                subscribeOnly 
                  ? 'bg-amber-900 text-white border-amber-900 shadow-xs' 
                  : 'bg-white text-stone-800 border-stone-300 hover:text-black hover:border-stone-400 hover:bg-stone-50'
              ]"
            >
              <Sparkles class="w-3 h-3 sm:w-3.5 sm:h-3.5" :class="subscribeOnly ? 'text-white' : 'text-amber-700'" />
              <span class="hidden sm:inline">Subscribe &amp; Save (5%)</span>
              <span class="sm:hidden">Sub &amp; Save</span>
            </button>

            <!-- Sort Dropdown -->
            <Select :model-value="activeSort" @update:model-value="onSortChange">
              <SelectTrigger class="h-7 sm:h-8 w-auto rounded-full border border-stone-300 bg-white hover:bg-stone-50 px-2.5 sm:px-3 text-xs font-bold text-stone-900 shadow-2xs gap-1 sm:gap-1.5 focus:border-[#1a1a1a] focus:ring-3 focus:ring-[#1a1a1a]/10 whitespace-nowrap">
                <div class="flex items-center gap-1 sm:gap-1.5 mr-0.5">
                  <ArrowUpDown class="w-3 h-3 sm:w-3.5 sm:h-3.5 text-stone-500 shrink-0" />
                  <SelectValue placeholder="Sort" />
                </div>
              </SelectTrigger>
              <SelectContent class="rounded-2xl border border-stone-200 bg-white shadow-xl min-w-[210px] p-1.5 z-50">
                <SelectItem value="featured" class="rounded-xl text-xs py-2 font-semibold text-stone-900">Featured / Recommended</SelectItem>
                <SelectItem value="price_asc" class="rounded-xl text-xs py-2 font-semibold text-stone-900">Price: Low to High</SelectItem>
                <SelectItem value="price_desc" class="rounded-xl text-xs py-2 font-semibold text-stone-900">Price: High to Low</SelectItem>
                <SelectItem value="name_asc" class="rounded-xl text-xs py-2 font-semibold text-stone-900">Name: A to Z</SelectItem>
                <SelectItem value="name_desc" class="rounded-xl text-xs py-2 font-semibold text-stone-900">Name: Z to A</SelectItem>
              </SelectContent>
            </Select>

          </div>

        </div>

      </div>

      <!-- SECTION: PRODUCTS GRID (When on Products Tab) -->
      <div v-if="activeTab === 'products'">
        <div v-if="products.data && products.data.length > 0" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3.5 sm:gap-5">
          <Link 
            v-for="item in products.data" 
            :key="item.slug || item.id"
            :href="'/products/' + item.slug"
            class="relative block flex flex-col group text-left cursor-pointer transition-transform duration-200"
          >
            <!-- Card Image Tile -->
            <div class="relative w-full aspect-square bg-[#f3efe7] rounded-[22px] sm:rounded-3xl overflow-hidden img-zoom-container shrink-0 border border-[#e0d9cc]/60 shadow-2xs group-hover:border-[#a47a3c]/60 transition-colors">
              <img 
                :src="item.image || '/images/products/atta.jpg'" 
                :alt="item.name" 
                class="w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-300"
              />
              
              <!-- Photo label badge -->
              <span class="photo-label absolute top-2.5 left-2.5 text-[11px] text-[#1d1d1f] font-mono bg-white/85 backdrop-blur-xs px-2 py-0.5 rounded-lg z-10 shadow-2xs">
                {{ item.photo_label || item.photoLabel || item.category || 'grocery' }}
              </span>

              <!-- Subscribe Badge if applicable -->
              <span 
                v-if="item.has_subscription"
                class="absolute top-2.5 right-2.5 bg-[#a47a3c] text-white text-[9.5px] font-bold px-1.5 py-0.5 rounded-md shadow-xs z-10"
              >
                -5% SUB
              </span>

              <!-- In-cart Stepper or Add to cart button -->
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

            <!-- Card Typography Below Image -->
            <div class="pt-2.5 text-left">
              <div class="text-lg sm:text-xl font-serif font-medium text-[#1d1d1f] leading-none group-hover:text-[#a47a3c] transition-colors">
                ${{ Number(item.price).toFixed(2) }}
              </div>
              <div class="text-sm sm:text-base font-semibold text-[#1d1d1f] mt-1 truncate">
                {{ item.name }}
              </div>
              <div class="text-xs text-[#6e6e73] font-normal mt-0.5 truncate">
                {{ item.size_main || item.sizeMain || item.unit_price || 'Standard pack' }}
              </div>
            </div>
          </Link>
        </div>

        <!-- Empty Products State -->
        <div v-else class="p-8 sm:p-14 rounded-3xl border border-[#e0d9cc] bg-white text-center space-y-3 shadow-2xs">
          <div class="w-14 h-14 rounded-full bg-[#f3efe7] border border-[#e0d9cc] flex items-center justify-center mx-auto text-[#7a5620]">
            <SearchIcon class="w-6 h-6 stroke-[2]" />
          </div>
          <h3 class="text-lg sm:text-xl font-serif font-medium text-[#1d1d1f]">
            No groceries found matching your search
          </h3>
          <p class="text-xs text-[#6e6e73] max-w-md mx-auto leading-relaxed">
            We couldn't find items matching "<strong class="text-[#1d1d1f]">{{ searchQuery }}</strong>". Try searching for popular Indian staples like Atta, Paneer, Rice, or Ghee.
          </p>
          <div class="pt-2">
            <button 
              type="button"
              @click="resetAllFilters"
              class="px-6 py-2.5 bg-[#1a1a1a] hover:bg-black text-white rounded-full text-xs font-semibold shadow-xs cursor-pointer"
            >
              Reset All Filters
            </button>
          </div>
        </div>
      </div>

      <!-- SECTION 6: RECIPE KITS GRID (When on Recipe Kits Tab) -->
      <div v-else>
        <div v-if="recipeKits.data && recipeKits.data.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <div 
            v-for="kit in recipeKits.data"
            :key="kit.slug || kit.id"
            class="bg-[#f3efe7] border border-[#e0d9cc] rounded-3xl p-4 flex flex-col justify-between hover:border-[#a47a3c]/60 transition-colors shadow-2xs group"
          >
            <div>
              <Link :href="'/recipe-kits/' + kit.slug" class="block relative w-full aspect-[4/3] bg-white rounded-2xl overflow-hidden img-zoom-container border border-[#e0d9cc]/60">
                <img 
                  :src="kit.image || '/images/products/paneer_curry.jpg'" 
                  :alt="kit.name" 
                  class="w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-300"
                />
                <span class="font-mono text-xs text-[#1d1d1f] bg-white/85 backdrop-blur-xs px-2 py-0.5 rounded-md absolute top-2.5 left-2.5 shadow-2xs">
                  {{ kit.subtitle_tag || 'recipe kit' }}
                </span>
                <span class="absolute top-2.5 right-2.5 bg-[#1a1a1a] text-white text-[11px] font-semibold px-2 py-0.5 rounded-md shadow-xs">
                  {{ kit.products ? kit.products.length : 3 }} items
                </span>
              </Link>

              <div class="pt-3 text-left">
                <Link :href="'/recipe-kits/' + kit.slug" class="block">
                  <h3 class="font-serif font-medium text-base sm:text-lg text-[#1d1d1f] leading-snug group-hover:text-[#a47a3c] transition-colors">
                    {{ kit.name }}
                  </h3>
                </Link>
                <p class="text-xs text-[#6e6e73] font-normal mt-1 leading-snug line-clamp-2 min-h-[32px]">
                  {{ kit.description || 'Pre-portioned fresh ingredients & spices · Zero food waste' }}
                </p>

                <div class="flex items-baseline gap-2 mt-2">
                  <span class="font-serif font-medium text-lg sm:text-xl text-[#1d1d1f]">
                    ${{ Number(kit.price).toFixed(2) }}
                  </span>
                  <span v-if="kit.original_price" class="text-xs text-[#86868b] line-through font-normal">
                    ${{ Number(kit.original_price).toFixed(2) }}
                  </span>
                  <span v-if="kit.original_price && kit.original_price > kit.price" class="text-[11px] font-bold text-[#7a5620] bg-[#f5eee2] border border-[#e0d9cc] px-2 py-0.5 rounded-full">
                    Save ${{ (Number(kit.original_price) - Number(kit.price)).toFixed(2) }}
                  </span>
                </div>
              </div>
            </div>

            <button 
              type="button"
              @click.stop.prevent="handleAddKitToCart(kit)"
              class="w-full mt-3.5 py-2.5 sm:py-3 bg-[#1a1a1a] hover:bg-black text-white font-semibold text-xs rounded-full flex items-center justify-center gap-1.5 transition-colors cursor-pointer active:scale-98 shadow-xs"
            >
              <Plus class="w-3.5 h-3.5 stroke-[2.5]" />
              <span>Add all {{ kit.products ? kit.products.length : 3 }} to cart</span>
            </button>
          </div>
        </div>

        <!-- Empty Kits State -->
        <div v-else class="p-8 sm:p-14 rounded-3xl border border-[#e0d9cc] bg-white text-center space-y-3 shadow-2xs">
          <div class="w-14 h-14 rounded-full bg-[#f3efe7] border border-[#e0d9cc] flex items-center justify-center mx-auto text-[#7a5620]">
            <UtensilsCrossed class="w-6 h-6 stroke-[2]" />
          </div>
          <h3 class="text-lg sm:text-xl font-serif font-medium text-[#1d1d1f]">
            No recipe kits found
          </h3>
          <p class="text-xs text-[#6e6e73] max-w-md mx-auto leading-relaxed">
            No recipe kits matched your search query. Try searching for "Paneer", "Biryani", or "Curry".
          </p>
          <div class="pt-2">
            <button 
              type="button"
              @click="resetAllFilters"
              class="px-6 py-2.5 bg-[#1a1a1a] hover:bg-black text-white rounded-full text-xs font-semibold shadow-xs cursor-pointer"
            >
              Reset Filters
            </button>
          </div>
        </div>
      </div>

      <!-- SECTION 7: LARAVEL PAGINATION CONTROLS WITH URL PERSISTENCE -->
      <div 
        v-if="(activeTab === 'products' ? products.last_page : recipeKits.last_page) > 1" 
        class="flex flex-wrap items-center justify-center gap-2 pt-6 pb-2"
      >
        <!-- Previous Page Link -->
        <Link 
          v-if="activeTab === 'products' ? products.prev_page_url : recipeKits.prev_page_url"
          :href="activeTab === 'products' ? products.prev_page_url : recipeKits.prev_page_url"
          @click.prevent="changePage(activeTab === 'products' ? products.prev_page_url : recipeKits.prev_page_url)"
          class="h-10 px-4 rounded-full border border-[#e0d9cc] bg-white text-xs font-semibold text-[#1d1d1f] hover:bg-[#f3efe7] transition-colors flex items-center gap-1.5 cursor-pointer shadow-2xs active:scale-[0.98]"
        >
          <ChevronLeft class="w-4 h-4 stroke-[2.2]" />
          <span>Previous</span>
        </Link>
        <button 
          v-else
          disabled
          class="h-10 px-4 rounded-full border border-[#e0d9cc] bg-stone-100 text-xs font-semibold text-[#86868b] opacity-40 cursor-not-allowed flex items-center gap-1.5"
        >
          <ChevronLeft class="w-4 h-4" />
          <span>Previous</span>
        </button>

        <!-- Numeric Page Buttons from Laravel LengthAwarePaginator -->
        <div class="flex items-center gap-1.5">
          <template v-for="(link, idx) in paginationLinks" :key="idx">
            <Link 
              v-if="link.url"
              :href="link.url"
              @click.prevent="changePage(link.url)"
              :class="[
                'w-10 h-10 rounded-full text-xs font-semibold transition-all cursor-pointer flex items-center justify-center',
                link.active 
                  ? 'bg-[#1a1a1a] text-white shadow-xs scale-105 pointer-events-none' 
                  : 'bg-white border border-[#e0d9cc] text-[#1d1d1f] hover:bg-[#f3efe7]'
              ]"
            >
              {{ link.label }}
            </Link>
            <span 
              v-else 
              class="w-8 text-center text-xs text-[#86868b] font-bold"
            >
              ...
            </span>
          </template>
        </div>

        <!-- Next Page Link -->
        <Link 
          v-if="activeTab === 'products' ? products.next_page_url : recipeKits.next_page_url"
          :href="activeTab === 'products' ? products.next_page_url : recipeKits.next_page_url"
          @click.prevent="changePage(activeTab === 'products' ? products.next_page_url : recipeKits.next_page_url)"
          class="h-10 px-4 rounded-full border border-[#e0d9cc] bg-white text-xs font-semibold text-[#1d1d1f] hover:bg-[#f3efe7] transition-colors flex items-center gap-1.5 cursor-pointer shadow-2xs active:scale-[0.98]"
        >
          <span>Next</span>
          <ChevronRight class="w-4 h-4 stroke-[2.2]" />
        </Link>
        <button 
          v-else
          disabled
          class="h-10 px-4 rounded-full border border-[#e0d9cc] bg-stone-100 text-xs font-semibold text-[#86868b] opacity-40 cursor-not-allowed flex items-center gap-1.5"
        >
          <span>Next</span>
          <ChevronRight class="w-4 h-4" />
        </button>
      </div>

    </div>
  </StoreLayout>
</template>
