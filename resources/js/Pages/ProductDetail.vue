<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import StoreLayout from '../Layouts/StoreLayout.vue';
import { useStore } from '../stores/cart';
import { ChevronLeft, ChevronRight, Clock, Plus, Minus, Sparkles, RefreshCw, CheckCircle2, ShieldCheck, Snowflake, Check, ShoppingBag, X } from 'lucide-vue-next';

const props = defineProps({
  slug: {
    type: String,
    default: 'paneer',
  },
  product: {
    type: Object,
    default: null,
  },
  frequentlyBoughtTogether: {
    type: Array,
    default: () => [],
  },
});

const store = useStore();
const page = usePage();
const storeInfo = computed(() => page.props.storeInfo || {});

const product = computed(() => {
  let base;
  if (props.product) {
    base = {
      id: props.product.id,
      slug: props.product.slug,
      name: props.product.name,
      subtitleTag: props.product.subtitle_tag,
      category: props.product.category,
      categoryTitle: props.product.category_title,
      price: Number(props.product.price),
      originalPrice: Number(props.product.original_price || props.product.price),
      unitPrice: props.product.unit_price,
      stock: (props.product.stock !== undefined && props.product.stock !== null) ? Number(props.product.stock) : null,
      stockBadge: props.product.stock_badge,
      photoLabel: props.product.photo_label,
      image: props.product.image,
      images: props.product.images_list || props.product.images || (props.product.image ? [props.product.image] : []),
      sizeMain: props.product.size_main,
      sizeSub: props.product.size_sub,
      size: props.product.size_main,
      freshnessLine: props.product.freshness_line,
      description: props.product.description,
      servings: props.product.servings,
      cookingTime: props.product.cooking_time,
      isRecipeKit: !!props.product.is_recipe_kit,
      label: props.product.photo_label,
      frequentlyBoughtTogether: (props.frequentlyBoughtTogether && props.frequentlyBoughtTogether.length > 0)
        ? props.frequentlyBoughtTogether
        : (props.product.frequently_bought_together || []),
      recipeIngredients: props.product.recipe_ingredients || [],
    };
  } else {
    base = store.getProductBySlug(props.slug) || {};
  }

  // Provide dynamic pairings for "Goes with it" section
  let goesWithIt = [];
  if (base.frequentlyBoughtTogether && base.frequentlyBoughtTogether.length > 0) {
    goesWithIt = base.frequentlyBoughtTogether.slice(0, 2);
  } else {
    goesWithIt = store.products
      .filter(p => p.id !== base.id && p.slug !== base.slug)
      .slice(0, 2)
      .map(p => ({
        id: p.id,
        slug: p.slug,
        name: p.name,
        size: p.sizeMain || p.size,
        price: Number(p.price),
        label: p.photoLabel || p.name,
        image: p.image,
        stock: p.stock,
        stock_badge: p.stockBadge,
      }));
  }

  return {
    ...base,
    goesWithIt,
  };
});

const activeImage = ref('');
watch(() => product.value.image, (newImg) => {
  if (newImg) activeImage.value = newImg;
}, { immediate: true });

const quantity = ref(1);
const addedNotification = ref(false);
const notificationMessage = ref('');
const notificationSubtext = ref('');
const notificationImage = ref('');
const notificationKey = ref(0);
let notificationTimeoutId = null;

const availableStock = computed(() => store.getAvailableStock(product.value));
const isProductOutOfStock = computed(() => store.isOutOfStock(product.value));
const inCartQuantity = computed(() => store.getItemQuantity(product.value.id || product.value.slug));
const remainingStock = computed(() => {
  if (availableStock.value === Infinity) return Infinity;
  return Math.max(0, availableStock.value - inCartQuantity.value);
});
const canAddMainProduct = computed(() => !isProductOutOfStock.value && remainingStock.value >= quantity.value && remainingStock.value > 0);

watch(remainingStock, (rem) => {
  if (rem !== Infinity && rem > 0 && quantity.value > rem) {
    quantity.value = rem;
  }
});

function incrementQuantity() {
  if (remainingStock.value !== Infinity && quantity.value >= remainingStock.value) {
    store.showStockToast(
      'Maximum Stock Reached',
      `Only ${availableStock.value} of ${product.value.name} available in stock.`,
      'warning'
    );
    return;
  }
  quantity.value += 1;
}

function triggerAddedNotification({ title, subtext, image }) {
  if (notificationTimeoutId) {
    clearTimeout(notificationTimeoutId);
  }
  notificationMessage.value = title;
  notificationSubtext.value = subtext || `${store.totalItemCount} ${store.totalItemCount === 1 ? 'item' : 'items'} in cart · $${store.subtotal.toFixed(2)}`;
  notificationImage.value = image || activeImage.value || product.value.image || '/images/products/atta.jpg';
  notificationKey.value += 1;
  addedNotification.value = true;

  notificationTimeoutId = setTimeout(() => {
    addedNotification.value = false;
  }, 3800);
}

const fbtSelected = ref({});

const fbtItems = computed(() => {
  const items = product.value.frequentlyBoughtTogether || [];
  const rawList = (Array.isArray(items) && items.length > 0)
    ? items
    : store.products
        .filter(p => !p.isRecipeKit && p.id !== product.value.id && p.slug !== product.value.slug)
        .slice(0, 3);

  return rawList.map(item => {
    const isOut = store.isOutOfStock(item);
    const avail = store.getAvailableStock(item);
    return {
      id: item.id || item.slug,
      slug: item.slug || String(item.id),
      name: item.name,
      size: item.size || item.size_main || item.sizeMain || item.weight || '',
      price: Number(item.price),
      originalPrice: Number(item.original_price || item.originalPrice || item.price),
      image: item.image || '/images/products/garam_masala.jpg',
      stock: item.stock,
      stock_badge: item.stock_badge || item.stockBadge,
      isOutOfStock: isOut,
      availableStock: avail,
    };
  });
});

// Default only in-stock companion items selected
watch(fbtItems, (items) => {
  if (items.length > 0) {
    const initial = {};
    items.forEach(item => {
      initial[item.id] = !item.isOutOfStock;
    });
    fbtSelected.value = initial;
  }
}, { immediate: true });

function toggleFbtItem(item) {
  if (item.isOutOfStock) {
    store.showStockToast('Out of Stock', `${item.name} is currently out of stock and cannot be added to the bundle.`, 'error');
    return;
  }
  fbtSelected.value[item.id] = !fbtSelected.value[item.id];
}

const selectedFbtItems = computed(() => {
  return fbtItems.value.filter(item => Boolean(fbtSelected.value[item.id]) && !item.isOutOfStock);
});

const selectedFbtCount = computed(() => {
  return selectedFbtItems.value.length;
});

const unitPrice = computed(() => {
  return Number(product.value.price.toFixed(2));
});

const displayPrice = computed(() => {
  return (unitPrice.value * quantity.value).toFixed(2);
});

const totalFbtAll = computed(() => {
  const mainSum = isProductOutOfStock.value ? 0 : (unitPrice.value * quantity.value);
  const companionSum = selectedFbtItems.value.reduce((acc, item) => acc + Number(item.price), 0);
  return (mainSum + companionSum).toFixed(2);
});

const canAddFbtBundle = computed(() => {
  return (!isProductOutOfStock.value && canAddMainProduct.value) || selectedFbtCount.value > 0;
});

const fbtButtonText = computed(() => {
  const count = selectedFbtCount.value;
  if (isProductOutOfStock.value) {
    return count > 0 ? `Add ${count} available pairing${count > 1 ? 's' : ''}` : 'All bundle items out of stock';
  }
  const availableFbtCount = fbtItems.value.filter(i => !i.isOutOfStock).length;
  if (count === availableFbtCount && count > 0) {
    return `Add ${product.value.name} + all ${count}`;
  } else if (count > 0) {
    return `Add ${product.value.name} + ${count} selected`;
  } else {
    return `Add ${product.value.name} only`;
  }
});

function handleAction() {
  if (isProductOutOfStock.value) {
    store.showStockToast('Out of Stock', `${product.value.name} is currently out of stock.`, 'error');
    return;
  }
  const added = store.addToCart({
    id: product.value.id,
    slug: product.value.slug,
    name: product.value.name,
    weight: product.value.sizeMain,
    price: unitPrice.value,
    originalPrice: product.value.price,
    quantity: quantity.value,
    image: product.value.image,
    stock: product.value.stock,
    stock_badge: product.value.stockBadge,
  });

  if (added) {
    triggerAddedNotification({
      title: `${quantity.value}× ${product.value.name}`,
      subtext: `${product.value.sizeMain ? product.value.sizeMain + ' · ' : ''}Cart subtotal $${store.subtotal.toFixed(2)}`,
      image: activeImage.value || product.value.image,
    });
  }
}

function handleAddPairingItem(item) {
  const added = store.addToCart({
    id: item.id,
    slug: item.slug,
    name: item.name,
    weight: item.size || item.weight,
    price: item.price,
    originalPrice: item.price,
    image: item.image,
    stock: item.stock,
    stock_badge: item.stock_badge || item.stockBadge,
  });

  if (added) {
    triggerAddedNotification({
      title: `1× ${item.name}`,
      subtext: `${item.size || item.weight ? (item.size || item.weight) + ' · ' : ''}Cart subtotal $${store.subtotal.toFixed(2)}`,
      image: item.image,
    });
  }
}

function handleAddAllFbt() {
  if (!canAddFbtBundle.value) return;

  let addedMain = false;
  if (!isProductOutOfStock.value && remainingStock.value > 0) {
    addedMain = store.addToCart({
      id: product.value.id,
      slug: product.value.slug,
      name: product.value.name,
      weight: product.value.sizeMain,
      price: unitPrice.value,
      originalPrice: product.value.price,
      quantity: quantity.value,
      image: product.value.image,
      stock: product.value.stock,
      stock_badge: product.value.stockBadge,
    });
  }

  let addedPairings = 0;
  selectedFbtItems.value.forEach(item => {
    const ok = store.addToCart({
      id: item.id,
      slug: item.slug,
      name: item.name,
      weight: item.size,
      price: item.price,
      originalPrice: item.originalPrice || item.price,
      image: item.image,
      stock: item.stock,
      stock_badge: item.stock_badge,
      quantity: 1,
    });
    if (ok) addedPairings += 1;
  });

  if (addedMain || addedPairings > 0) {
    triggerAddedNotification({
      title: addedMain && addedPairings > 0
        ? `${product.value.name} + ${addedPairings} pairing${addedPairings > 1 ? 's' : ''}`
        : (addedMain ? `${quantity.value}× ${product.value.name}` : `${addedPairings} pairing${addedPairings > 1 ? 's' : ''}`),
      subtext: `${store.totalItemCount} items in cart · $${store.subtotal.toFixed(2)} subtotal`,
      image: activeImage.value || product.value.image,
    });
  }
}
</script>

<template>
  <Head :title="`${product.name} — ${storeInfo.brand_name}`" />

  <StoreLayout 
    headerMode="pdp" 
    :headerTitle="product.categoryTitle" 
    backUrl="/"
    :showCartBar="false" 
    :showBottomNav="false"
  >
    <div class="space-y-6 pb-20 sm:pb-4 max-w-5xl mx-auto">

      <!-- Desktop Breadcrumbs -->
      <nav class="hidden sm:flex items-center gap-2 text-xs font-normal text-stone-500">
        <Link href="/" class="hover:text-stone-950 flex items-center gap-1 font-semibold text-stone-700">
          <ChevronLeft class="w-4 h-4" />
          <span>Home</span>
        </Link>
        <span>/</span>
        <Link v-if="product.category" :href="'/categories/' + product.category" class="text-stone-600 font-semibold hover:text-stone-900">
          {{ product.categoryTitle }}
        </Link>
        <span v-else class="text-stone-600 font-semibold">{{ product.categoryTitle }}</span>
        <span>/</span>
        <span class="text-stone-900 font-bold truncate max-w-xs">{{ product.name }}</span>
      </nav>

      <!-- Main Product View: 2-Column Desktop Grid / Compact Mobile -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-10 items-start">
        
        <!-- LEFT COLUMN: Product Visual Stage (Screens 1b & 1c) -->
        <div class="lg:col-span-6 space-y-4">
          <div class="relative w-full aspect-square bg-[#F4F5F7] rounded-lg overflow-hidden shadow-xs border border-stone-200/80 flex flex-col justify-between p-4 group img-zoom-container">
            
            <!-- Real High-Res Product Image -->
            <img 
              :src="activeImage || product.image || ('/images/products/' + product.slug + '.jpg')" 
              :alt="product.name" 
              class="w-full h-full object-cover object-center absolute inset-0 transition-opacity duration-200"
            />

            <!-- Subtle vignette gradient -->
            <div class="absolute inset-0 bg-gradient-to-t from-black/50 via-transparent to-transparent pointer-events-none"></div>

            <!-- Top Row Badges (Screen 1b & 1c match) -->
            <div class="relative z-10 flex items-center justify-between">
              <span
                class="backdrop-blur-xs px-3 py-1 text-xs font-semibold rounded-md shadow-xs border"
                :class="isProductOutOfStock
                  ? 'bg-rose-600 text-white border-rose-700'
                  : 'bg-white/90 text-stone-800 border-stone-200'"
              >
                {{ isProductOutOfStock ? 'Out of Stock' : (product.stockBadge || (availableStock !== Infinity && availableStock <= 5 ? `Only ${availableStock} left` : 'In Stock')) }}
              </span>
              <span class="photo-label text-[11px] text-white/90 bg-black/60 backdrop-blur-xs px-2.5 py-0.5 rounded-md font-normal">
                {{ product.photoLabel }}
              </span>
            </div>

            <!-- Bottom-Left Big Rounded Size Box -->
            <div class="relative z-10 self-start bg-[#1a1a1a] text-white p-3.5 sm:p-4 rounded-lg shadow-lg border border-[#e0d9cc]/20">
              <div class="text-2xl sm:text-3xl font-serif font-medium leading-none tracking-tight">
                {{ product.sizeMain }}
              </div>
              <div class="text-[11px] sm:text-xs text-stone-300 font-normal mt-1">
                {{ product.sizeSub }}
              </div>
            </div>
          </div>

          <!-- Multi-Image Thumbnail Gallery (if product has > 1 image) -->
          <div v-if="product.images && product.images.length > 1" class="flex items-center gap-2 overflow-x-auto pb-1 no-scrollbar">
            <button 
              v-for="(img, idx) in product.images" 
              :key="idx"
              type="button"
              @click="activeImage = img"
              class="w-16 h-16 rounded-lg overflow-hidden border-2 transition-all cursor-pointer shrink-0 bg-stone-100"
              :class="activeImage === img ? 'border-[#a47a3c] scale-102' : 'border-stone-200 opacity-70 hover:opacity-100'"
            >
              <img 
                :src="img" 
                :alt="`${product.name} gallery ${idx + 1}`" 
                class="w-full h-full object-cover"
                @error="$event.target.src = '/images/products/atta.jpg'"
              />
            </button>
          </div>

          <!-- Product Assurance Strip -->
          <div class="grid grid-cols-3 gap-2 text-center text-xs font-normal text-[#6e6e73] bg-[#f3efe7] border border-[#e0d9cc] rounded-lg p-3 shadow-xs">
            <div class="flex flex-col items-center justify-center gap-1">
              <Snowflake class="w-4 h-4 text-sky-700 stroke-[2]" />
              <div>
                <span class="font-bold text-[#1d1d1f] block leading-tight">Cold Storage</span>
                <span class="text-[#86868b] text-[10px]">Temp Monitored</span>
              </div>
            </div>
            <div class="border-x border-[#e0d9cc] flex flex-col items-center justify-center gap-1 px-1">
              <Clock class="w-4 h-4 text-[#a47a3c] stroke-[2.2]" />
              <div>
                <span class="font-bold text-[#1d1d1f] block leading-tight">Same Day</span>
                <span class="text-[#86868b] text-[10px]">Ready in 1 Hr</span>
              </div>
            </div>
            <div class="flex flex-col items-center justify-center gap-1">
              <ShieldCheck class="w-4 h-4 text-[#7a5620] stroke-[2.2]" />
              <div>
                <span class="font-bold text-[#1d1d1f] block leading-tight">Fresh Guarantee</span>
                <span class="text-[#86868b] text-[10px]">100% Refundable</span>
              </div>
            </div>
          </div>
        </div>

        <!-- RIGHT COLUMN: Details, Pricing, Options & Actions -->
        <div class="lg:col-span-6 space-y-4 sm:space-y-5">
          
          <!-- Product Title & Price Header Card -->
          <div class="bg-white rounded-lg border border-[#e0d9cc] p-5 sm:p-7 space-y-4 shadow-xs">
            <div>
              <!-- Freshness Category Tag -->
              <div class="text-[11px] font-semibold uppercase text-[#7a5620] tracking-wider mb-1">
                {{ product.subtitleTag || product.freshnessLabel || 'FRESH · MADE LOCALLY' }}
              </div>
              
              <h1 class="text-2xl sm:text-3xl lg:text-4xl font-serif font-medium text-[#1d1d1f] tracking-tight leading-tight">
                {{ product.name }}
              </h1>

              <!-- Price & Unit -->
              <div class="flex items-baseline gap-2.5 mt-2">
                <span class="text-2xl sm:text-3xl font-serif font-medium text-[#1d1d1f]">
                  ${{ product.price.toFixed(2) }}
                </span>
                <span v-if="product.unitPrice" class="text-xs text-[#6e6e73] font-normal">
                  {{ product.unitPrice }}
                </span>
                <span
                  v-if="isProductOutOfStock"
                  class="px-2.5 py-0.5 rounded-md text-xs font-bold bg-rose-100 text-rose-800 border border-rose-200"
                >
                  Out of Stock
                </span>
                <span
                  v-else-if="availableStock !== Infinity && availableStock <= 5"
                  class="px-2.5 py-0.5 rounded-md text-xs font-semibold bg-[#f5eee2] text-[#7a5620] border border-[#e0d9cc]"
                >
                  Only {{ availableStock }} left in stock
                </span>
              </div>
            </div>

            <!-- Pickup & Freshness Meta -->
            <div class="flex items-center gap-1.5 text-xs text-[#6e6e73] font-normal pt-2 border-t border-[#e0d9cc]/60">
              <Clock class="w-3.5 h-3.5 text-[#86868b] shrink-0" />
              <span>{{ product.pickupNote || 'Pickup today from 4 pm' }}</span>
              <span>·</span>
              <span class="text-[#6e6e73]">{{ product.expiryNote || 'Best before Oct 2' }}</span>
            </div>

            <!-- Description -->
            <p class="text-xs sm:text-sm text-[#6e6e73] font-normal leading-relaxed pt-1">
              {{ product.description }}
            </p>

            <!-- Frequently Bought Together (Screen 1 Specification) -->
            <div 
              v-if="fbtItems.length > 0" 
              class="border border-[#e0d9cc] rounded-lg p-5 bg-[#f3efe7] space-y-3"
            >
              <div>
                <h2 class="font-serif font-medium text-base sm:text-lg text-[#1d1d1f]">Frequently bought together</h2>
                <p class="text-xs text-[#6e6e73] font-normal mt-0.5">Shoppers who bought {{ product.name }} frequently add these items</p>
              </div>

              <!-- FBT Items List with Dividers -->
              <div class="space-y-0 divide-y divide-[#e0d9cc]/70 pt-1">
                <div 
                  v-for="item in fbtItems"
                  :key="item.id"
                  class="py-3.5 first:pt-1 last:pb-1 flex items-center justify-between gap-3"
                  :class="item.isOutOfStock ? 'opacity-65' : ''"
                >
                  <Link :href="'/products/' + item.slug" class="flex items-center gap-3.5 flex-1 min-w-0 group cursor-pointer">
                    <div class="w-14 h-14 rounded-lg bg-white/80 overflow-hidden shrink-0 border border-[#e0d9cc] shadow-2xs group-hover:border-[#a47a3c] transition-colors">
                      <img
                        :src="item.image"
                        :alt="item.name"
                        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                        :class="item.isOutOfStock ? 'grayscale' : ''"
                      />
                    </div>
                    <div class="min-w-0">
                      <div class="flex flex-wrap items-center gap-1.5">
                        <div class="font-serif font-medium text-sm sm:text-base text-[#1d1d1f] leading-snug truncate group-hover:text-[#a47a3c] transition-colors" :class="item.isOutOfStock ? 'line-through text-stone-500' : ''">{{ item.name }}</div>
                        <span
                          v-if="item.isOutOfStock"
                          class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-rose-100 text-rose-800 border border-rose-200"
                        >
                          Out of Stock
                        </span>
                      </div>
                      <div class="text-xs text-[#6e6e73] font-normal mt-0.5 truncate">{{ item.size }}</div>
                    </div>
                  </Link>

                  <div class="flex items-center gap-3 shrink-0">
                    <span class="font-serif font-medium text-base text-[#1d1d1f]" :class="item.isOutOfStock ? 'line-through text-stone-400' : ''">${{ Number(item.price).toFixed(2) }}</span>
                    <!-- Action Button: solid filled checkmark when selected, outline plus when unselected, disabled when OOS -->
                    <button 
                      type="button"
                      :disabled="item.isOutOfStock"
                      @click="toggleFbtItem(item)"
                      :class="[
                        'w-8 h-8 rounded-md flex items-center justify-center transition-all',
                        item.isOutOfStock
                          ? 'bg-stone-200 text-stone-400 border border-stone-300 cursor-not-allowed'
                          : (fbtSelected[item.id]
                              ? 'bg-[#1a1a1a] text-white shadow-xs cursor-pointer active:scale-95'
                              : 'border-2 border-[#1a1a1a] text-[#1a1a1a] bg-transparent hover:bg-stone-200 cursor-pointer active:scale-95')
                      ]"
                      :aria-label="'Include ' + item.name + ' in bundle'"
                      :title="item.isOutOfStock ? 'Out of stock' : (fbtSelected[item.id] ? 'Included in bundle (click to uncheck)' : 'Click to include in bundle')"
                    >
                      <X v-if="item.isOutOfStock" class="w-4 h-4 stroke-[2.5]" />
                      <Check v-else-if="fbtSelected[item.id]" class="w-4 h-4 stroke-[3]" />
                      <Plus v-else class="w-4 h-4 stroke-[2.5]" />
                    </button>
                  </div>
                </div>
              </div>

              <!-- Combined Add All Pill -->
              <button 
                type="button"
                :disabled="!canAddFbtBundle"
                @click="handleAddAllFbt"
                class="w-full py-4 px-6 font-semibold text-xs sm:text-sm rounded-md flex items-center justify-between transition-colors shadow-2xs mt-3"
                :class="canAddFbtBundle
                  ? 'bg-[#ece7de] hover:bg-[#e0d9cc] text-[#1d1d1f] cursor-pointer active:scale-[0.99]'
                  : 'bg-stone-200 text-stone-500 cursor-not-allowed opacity-70'"
              >
                <span>{{ fbtButtonText }}</span>
                <span class="font-serif font-medium text-base sm:text-lg">{{ canAddFbtBundle ? `$${totalFbtAll}` : 'Unavailable' }}</span>
              </button>
            </div>

            <!-- What's in the kit (For Recipe Kits) -->
            <div 
              v-if="product.recipeIngredients?.length > 0" 
              class="border border-[#e0d9cc] rounded-lg p-4 bg-[#f3efe7] space-y-3"
            >
              <div>
                <h2 class="font-serif font-medium text-sm text-[#1d1d1f]">What's included in this kit</h2>
                <p class="text-xs text-[#6e6e73] font-normal">Pre-portioned fresh ingredients & spices · Zero food waste</p>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                <div 
                  v-for="(ingredient, idx) in product.recipeIngredients" 
                  :key="idx" 
                  class="flex items-center gap-2.5 bg-white rounded-md border border-[#e0d9cc] p-2.5 text-xs text-[#1d1d1f]"
                >
                  <div class="w-5 h-5 rounded-md bg-[#f5eee2] text-[#7a5620] flex items-center justify-center shrink-0">
                    <Check class="w-3.5 h-3.5 stroke-[3]" />
                  </div>
                  <span class="font-medium truncate">{{ ingredient }}</span>
                </div>
              </div>
            </div>

            <!-- Desktop Inline Action Bar -->
            <div class="hidden sm:block pt-4 border-t border-[#e0d9cc]/60 space-y-3">
              <div v-if="isProductOutOfStock" class="w-full h-12 bg-rose-50 border border-rose-200 text-rose-800 font-semibold text-sm rounded-md flex items-center justify-center gap-2 px-6">
                <span>Currently Out of Stock — Unavailable for Purchase</span>
              </div>
              <div v-else class="flex items-center gap-3">
                <!-- Stepper -->
                <div class="w-32 h-12 bg-[#ece7de] rounded-md flex items-center justify-between px-3 border border-[#e0d9cc]">
                  <button 
                    @click="quantity = Math.max(1, quantity - 1)" 
                    class="p-1 hover:text-[#1a1a1a] cursor-pointer flex items-center justify-center text-[#1d1d1f]"
                    aria-label="Decrease quantity"
                  >
                    <Minus class="w-4 h-4 stroke-[2.5]" />
                  </button>
                  <span class="text-sm font-bold text-[#1d1d1f]">{{ quantity }}</span>
                  <button 
                    @click="incrementQuantity" 
                    :disabled="remainingStock !== Infinity && quantity >= remainingStock"
                    class="p-1 flex items-center justify-center transition-opacity"
                    :class="(remainingStock !== Infinity && quantity >= remainingStock) ? 'opacity-35 cursor-not-allowed text-stone-400' : 'hover:text-[#1a1a1a] cursor-pointer text-[#1d1d1f]'"
                    aria-label="Increase quantity"
                  >
                    <Plus class="w-4 h-4 stroke-[2.5]" />
                  </button>
                </div>

                <!-- Primary CTA Button -->
                <button 
                  @click="handleAction"
                  :disabled="!canAddMainProduct"
                  class="flex-1 h-12 font-semibold text-sm rounded-md flex items-center justify-between px-6 transition-all shadow-md"
                  :class="canAddMainProduct
                    ? 'bg-[#1a1a1a] hover:bg-black text-white cursor-pointer active:scale-[0.99]'
                    : 'bg-stone-300 text-stone-600 cursor-not-allowed'"
                >
                  <span>
                    {{ !canAddMainProduct
                      ? `Max Stock in Cart (${availableStock})`
                      : (store.getItemQuantity(product.slug) > 0 ? 'Add another' : 'Add to cart')
                    }}
                  </span>
                  <span class="font-serif font-medium text-base">${{ displayPrice }}</span>
                </button>
              </div>

              <Transition
                enter-active-class="transition ease-out duration-200"
                enter-from-class="opacity-0 -translate-y-1"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition ease-in duration-150"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 -translate-y-1"
              >
                <div
                  v-if="addedNotification"
                  class="bg-[#fbf9f5] border border-[#a47a3c]/40 text-[#1d1d1f] p-3 rounded-lg text-xs flex items-center justify-between gap-3 shadow-2xs"
                >
                  <div class="flex items-center gap-2.5 min-w-0">
                    <div class="w-7 h-7 rounded-md bg-emerald-100 text-emerald-800 flex items-center justify-center shrink-0">
                      <Check class="w-4 h-4 stroke-[2.8]" />
                    </div>
                    <div class="min-w-0">
                      <div class="font-bold text-[#1d1d1f] truncate">
                        Added {{ notificationMessage || (`${quantity}× ${product.name}`) }}
                      </div>
                      <div class="text-[11px] text-stone-500 truncate">
                        {{ notificationSubtext }}
                      </div>
                    </div>
                  </div>
                  <Link
                    href="/cart"
                    class="px-3.5 py-1.5 rounded-md bg-[#1a1a1a] hover:bg-black text-white font-semibold text-xs inline-flex items-center gap-1 shrink-0 transition-colors shadow-2xs"
                  >
                    <ShoppingBag class="w-3.5 h-3.5 text-[#e4b97a]" />
                    <span>View Cart ({{ store.totalItemCount }})</span>
                    <ChevronRight class="w-3.5 h-3.5" />
                  </Link>
                </div>
              </Transition>
            </div>

          </div>

        </div>

      </div>

      <!-- SECTION: "Goes with it" Recommendations -->
      <section v-if="product.goesWithIt?.length > 0" class="space-y-3 pt-6 border-t border-[#e0d9cc]">
        <div>
          <h2 class="text-lg sm:text-xl font-serif font-medium text-[#1d1d1f] tracking-tight">Goes with it</h2>
          <p class="text-xs text-[#6e6e73] font-normal">Perfect pairings to complete your dish.</p>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3.5 sm:gap-5">
          <Link 
            v-for="item in product.goesWithIt" 
            :key="item.id"
            :href="'/products/' + (item.slug || String(item.id))"
            class="relative block flex flex-col group text-left cursor-pointer transition-transform duration-200"
          >
            <!-- Rounded Photo Tile -->
            <div class="relative w-full aspect-[4/3] bg-[#f3efe7] rounded-lg overflow-hidden img-zoom-container shadow-2xs border border-[#e0d9cc]/60">
              <img 
                :src="item.image || '/images/products/atta.jpg'" 
                :alt="item.name" 
                class="w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-300"
                :class="store.isOutOfStock(item) ? 'grayscale opacity-75' : ''"
              />
              <span class="photo-label absolute top-2.5 left-2.5 text-[11px] text-[#1d1d1f] font-mono bg-white/80 backdrop-blur-xs px-2 py-0.5 rounded-md z-10 shadow-2xs">{{ item.label || item.photoLabel || 'item' }}</span>

              <!-- When item is out of stock: show Out of Stock badge -->
              <div
                v-if="store.isOutOfStock(item)"
                class="absolute bottom-2.5 inset-x-2.5 bg-rose-700/95 text-white h-8 rounded-md flex items-center justify-center px-2.5 shadow-md z-10 text-[11px] font-bold"
                @click.stop.prevent
              >
                Out of Stock
              </div>

              <!-- When item is in cart: show Primary [- qty +] stepper pill -->
              <div 
                v-else-if="store.getItemQuantity(item.id) > 0"
                class="absolute bottom-2.5 inset-x-2.5 bg-[#1a1a1a] text-white h-8 sm:h-9 rounded-md flex items-center justify-between px-2.5 shadow-md z-10"
                @click.stop.prevent
              >
                <button 
                  type="button"
                  @click.stop.prevent="store.removeFromCart(item.id)" 
                  class="p-1 hover:opacity-80 cursor-pointer flex items-center justify-center"
                  aria-label="Decrease quantity"
                >
                  <Minus class="w-3.5 h-3.5 stroke-[2.5]" />
                </button>
                <span class="text-xs font-bold">{{ store.getItemQuantity(item.id) }}</span>
                <button 
                  type="button"
                  :disabled="!store.canAddMore(item, 1)"
                  @click.stop.prevent="handleAddPairingItem(item)" 
                  class="p-1 flex items-center justify-center"
                  :class="store.canAddMore(item, 1) ? 'hover:opacity-80 cursor-pointer' : 'opacity-35 cursor-not-allowed'"
                  aria-label="Increase quantity"
                >
                  <Plus class="w-3.5 h-3.5 stroke-[2.5]" />
                </button>
              </div>

              <!-- When item is not in cart: show round Primary + button -->
              <button 
                v-else
                type="button"
                @click.stop.prevent="handleAddPairingItem(item)"
                class="absolute bottom-2.5 right-2.5 w-8 h-8 sm:w-9 sm:h-9 bg-[#1a1a1a] hover:bg-black text-white rounded-md flex items-center justify-center cursor-pointer active:scale-95 shadow-md z-10 transition-transform group-hover:scale-105"
                aria-label="Add pairing item"
              >
                <Plus class="w-4 h-4 stroke-[2.5]" />
              </button>
            </div>

            <!-- Typography Below Tile (Screen 3: $7.99, Toor Dal · 4 lb) -->
            <div class="pt-2.5 text-left">
              <div class="font-serif font-medium text-base sm:text-lg text-[#1d1d1f] leading-none">${{ item.price.toFixed(2) }}</div>
              <div class="text-xs text-[#1d1d1f] font-normal mt-1 truncate">{{ item.name }} · {{ item.size || item.weight }}</div>
            </div>
          </Link>
        </div>
      </section>

      <!-- Mobile Sticky Bottom Action Bar -->
      <div class="sm:hidden fixed bottom-0 left-0 right-0 z-40 bg-white/95 backdrop-blur-md border-t border-[#e0d9cc] p-3 shadow-2xl flex items-center gap-3">
        <div
          v-if="isProductOutOfStock"
          class="w-full h-11 bg-rose-50 border border-rose-200 text-rose-800 font-semibold text-xs rounded-md flex items-center justify-center px-4"
        >
          Currently Out of Stock
        </div>
        <template v-else>
          <!-- Quantity Stepper -->
          <div class="w-28 h-11 bg-[#ece7de] rounded-md flex items-center justify-between px-3 border border-[#e0d9cc]">
            <button 
              @click="quantity = Math.max(1, quantity - 1)" 
              class="p-1 hover:text-[#1a1a1a] flex items-center justify-center text-[#1d1d1f]"
              aria-label="Decrease quantity"
            >
              <Minus class="w-4 h-4 stroke-[2.5]" />
            </button>
            <span class="text-xs font-bold text-[#1d1d1f]">{{ quantity }}</span>
            <button 
              @click="incrementQuantity" 
              :disabled="remainingStock !== Infinity && quantity >= remainingStock"
              class="p-1 flex items-center justify-center"
              :class="(remainingStock !== Infinity && quantity >= remainingStock) ? 'opacity-35 cursor-not-allowed text-stone-400' : 'hover:text-[#1a1a1a] text-[#1d1d1f]'"
              aria-label="Increase quantity"
            >
              <Plus class="w-4 h-4 stroke-[2.5]" />
            </button>
          </div>

          <!-- Add to cart CTA -->
          <button 
            @click="handleAction"
            :disabled="!canAddMainProduct"
            class="flex-1 h-11 font-semibold text-xs rounded-md flex items-center justify-between px-5 shadow-md"
            :class="canAddMainProduct ? 'bg-[#1a1a1a] active:bg-black text-white' : 'bg-stone-300 text-stone-600 cursor-not-allowed'"
          >
            <span>
              {{ !canAddMainProduct
                ? `Max Stock (${availableStock})`
                : (store.getItemQuantity(product.slug) > 0 ? 'Add another' : 'Add to cart')
              }}
            </span>
            <span class="font-serif font-medium text-sm">${{ displayPrice }}</span>
          </button>
        </template>
      </div>

      <!-- Rich Floating Add-to-Cart Toast (Docked cleanly above bottom bar on mobile, bottom-right on desktop; never covers product photo badges) -->
      <Transition
        enter-active-class="transform transition ease-out duration-250"
        enter-from-class="translate-y-4 opacity-0 scale-95"
        enter-to-class="translate-y-0 opacity-100 scale-100"
        leave-active-class="transform transition ease-in duration-150"
        leave-from-class="translate-y-0 opacity-100 scale-100"
        leave-to-class="translate-y-3 opacity-0 scale-95"
      >
        <div
          v-if="addedNotification"
          :key="notificationKey"
          class="fixed bottom-[74px] sm:bottom-6 left-3 right-3 sm:left-auto sm:right-6 z-50 sm:w-[390px] bg-[#1a1a1a]/95 backdrop-blur-md text-white rounded-lg shadow-2xl border border-[#a47a3c]/40 overflow-hidden"
        >
          <div class="p-3 sm:p-3.5 flex items-center justify-between gap-3">
            <!-- Left: Product Thumbnail + Check Badge + Item Info -->
            <div class="flex items-center gap-3 min-w-0">
              <div class="relative w-11 h-11 rounded-md bg-stone-800 border border-white/15 overflow-hidden shrink-0 shadow-sm">
                <img
                  :src="notificationImage || activeImage || product.image || '/images/products/atta.jpg'"
                  :alt="product.name"
                  class="w-full h-full object-cover object-center"
                />
                <span class="absolute -bottom-0.5 -right-0.5 w-4.5 h-4.5 rounded-md bg-emerald-500 text-stone-950 flex items-center justify-center border-2 border-[#1a1a1a]">
                  <Check class="w-2.5 h-2.5 stroke-[3.5]" />
                </span>
              </div>

              <div class="min-w-0">
                <div class="flex items-center gap-1.5">
                  <span class="text-[10px] font-bold uppercase tracking-wider text-[#e4b97a]">Added to Cart</span>
                </div>
                <div class="text-xs sm:text-sm font-bold text-white truncate leading-snug">
                  {{ notificationMessage || `${quantity}× ${product.name}` }}
                </div>
                <div class="text-[11px] text-stone-300 truncate mt-0.5">
                  {{ notificationSubtext }}
                </div>
              </div>
            </div>

            <!-- Right: Pill CTA + Dismiss -->
            <div class="flex items-center gap-1.5 shrink-0">
              <Link
                href="/cart"
                class="px-3.5 py-2 rounded-md bg-[#e4b97a] hover:bg-[#d6a55e] text-stone-950 font-bold text-xs inline-flex items-center gap-1 shadow-xs transition-colors active:scale-95"
              >
                <span>View Cart</span>
                <span class="px-1.5 py-0.2 rounded-md bg-stone-950/15 text-[10px] font-mono">{{ store.totalItemCount }}</span>
                <ChevronRight class="w-3.5 h-3.5 stroke-[2.5]" />
              </Link>
              <button
                type="button"
                @click="addedNotification = false"
                class="p-1.5 rounded-lg text-stone-400 hover:text-white hover:bg-white/10 transition-colors cursor-pointer"
                aria-label="Dismiss notification"
              >
                <X class="w-3.5 h-3.5" />
              </button>
            </div>
          </div>

          <!-- Bottom Brass Accent Hairline -->
          <div class="h-0.5 w-full bg-[#a47a3c]/25">
            <div class="h-full bg-gradient-to-r from-[#a47a3c] to-[#e4b97a] w-full"></div>
          </div>
        </div>
      </Transition>

    </div>
  </StoreLayout>
</template>
