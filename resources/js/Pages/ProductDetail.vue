<script setup>
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import StoreLayout from '../Layouts/StoreLayout.vue';
import { useStore } from '../stores/cart';
import { ChevronLeft, ChevronRight, Clock, Plus, Minus, Sparkles, RefreshCw, CheckCircle2, ShieldCheck, Snowflake } from 'lucide-vue-next';

const props = defineProps({
  slug: {
    type: String,
    default: 'paneer',
  },
  product: {
    type: Object,
    default: null,
  },
});

const store = useStore();

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
      hasSubscribeAndSave: !!props.product.has_subscription,
      hasSubscription: !!props.product.has_subscription,
      label: props.product.photo_label,
      frequentlyBoughtTogether: props.product.frequently_bought_together || [],
      recipeIngredients: props.product.recipe_ingredients || [],
    };
  } else {
    base = store.getProductBySlug(props.slug);
  }

  // Provide pairings for "Goes with it" section (Screen 3)
  const goesWithIt = [
    { id: 12, slug: 'toor-dal', name: 'Toor Dal', size: '4 lb', price: 7.99, label: 'toor dal', image: '/images/products/toor_dal.jpg' },
    { id: 2, slug: 'ghee', name: 'Desi Ghee', size: '500 ml', price: 9.99, label: 'ghee jar', image: '/images/products/ghee.jpg' },
  ];

  return {
    ...base,
    goesWithIt,
  };
});

const activeImage = ref('');
watch(() => product.value.image, (newImg) => {
  if (newImg) activeImage.value = newImg;
}, { immediate: true });

const isSubscribed = ref(false);
const quantity = ref(1);
const addedNotification = ref(false);

const fbtSelected = ref({
  'garam-masala': true,
  'garlic-naan': false,
  'kasuri-methi': false,
});

const fbtItems = computed(() => {
  if (product.value.frequentlyBoughtTogether?.length > 0) {
    return product.value.frequentlyBoughtTogether.map(item => ({
      id: item.id || item.slug,
      name: item.name,
      size: item.size || item.weight,
      price: Number(item.price),
      image: item.image || '/images/products/garam_masala.jpg',
    }));
  }
  return [
    { id: 'garam-masala', name: 'Garam Masala', size: '100 g', price: 3.49, image: '/images/products/garam_masala.jpg' },
    { id: 'garlic-naan', name: 'Garlic Naan', size: '4 pack', price: 3.99, image: '/images/products/sweets.jpg' },
    { id: 'kasuri-methi', name: 'Kasuri Methi', size: '50 g', price: 2.49, image: '/images/products/okra.jpg' },
  ];
});

function toggleFbtItem(id) {
  fbtSelected.value[id] = !fbtSelected.value[id];
}

const unitPrice = computed(() => {
  if (isSubscribed.value) {
    return Number((product.value.price * 0.95).toFixed(2));
  }
  return Number(product.value.price.toFixed(2));
});

const displayPrice = computed(() => {
  return (unitPrice.value * quantity.value).toFixed(2);
});

const totalFbtAll = computed(() => {
  const allFbtSum = fbtItems.value.reduce((acc, item) => acc + Number(item.price), 0);
  return (unitPrice.value + allFbtSum).toFixed(2);
});

function handleAction() {
  store.addToCart({
    id: product.value.id,
    name: product.value.name,
    weight: product.value.sizeMain,
    price: unitPrice.value,
    originalPrice: product.value.price,
    quantity: quantity.value,
    isSubscribed: isSubscribed.value,
    subscriptionInterval: isSubscribed.value ? 'Monthly' : undefined,
    nextDelivery: isSubscribed.value ? 'Oct 3' : undefined,
    image: product.value.image,
  });

  addedNotification.value = true;
  setTimeout(() => {
    addedNotification.value = false;
  }, 2500);
}

function handleAddAllFbt() {
  handleAction();
  fbtItems.value.forEach(item => {
    store.addToCart({
      id: item.id,
      name: item.name,
      weight: item.size,
      price: item.price,
      originalPrice: item.price,
      image: item.image,
    });
  });
}
</script>

<template>
  <Head :title="`${product.name} — Masala Mart`" />

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
        <span class="text-stone-600 font-semibold">{{ product.categoryTitle }}</span>
        <span>/</span>
        <span class="text-stone-900 font-bold truncate max-w-xs">{{ product.name }}</span>
      </nav>

      <!-- Main Product View: 2-Column Desktop Grid / Compact Mobile -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-10 items-start">
        
        <!-- LEFT COLUMN: Product Visual Stage (Screens 1b & 1c) -->
        <div class="lg:col-span-6 space-y-4">
          <div class="relative w-full aspect-square bg-[#F4F5F7] rounded-3xl overflow-hidden shadow-xs border border-stone-200/80 flex flex-col justify-between p-4 group img-zoom-container">
            
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
              <span class="bg-white/90 backdrop-blur-xs text-stone-800 border border-stone-200 px-3 py-1 text-xs font-semibold rounded-full shadow-xs">
                {{ product.stockBadge }}
              </span>
              <span class="photo-label text-[11px] text-white/90 bg-black/60 backdrop-blur-xs px-2.5 py-0.5 rounded-full font-normal">
                {{ product.photoLabel }}
              </span>
            </div>

            <!-- Bottom-Left Big Rounded Size Box -->
            <div class="relative z-10 self-start bg-[#1a1a1a] text-white p-3.5 sm:p-4 rounded-2xl shadow-lg border border-[#e0d9cc]/20">
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
              class="w-16 h-16 rounded-2xl overflow-hidden border-2 transition-all cursor-pointer shrink-0 bg-stone-100"
              :class="activeImage === img ? 'border-[#a47a3c] ring-2 ring-[#a47a3c]/30 scale-102' : 'border-stone-200 opacity-70 hover:opacity-100'"
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
          <div class="grid grid-cols-3 gap-2 text-center text-xs font-normal text-[#6e6e73] bg-[#f3efe7] border border-[#e0d9cc] rounded-2xl p-3 shadow-xs">
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
          <div class="bg-white rounded-3xl border border-[#e0d9cc] p-5 sm:p-7 space-y-4 shadow-xs">
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
                  ${{ isSubscribed ? (product.price * 0.95).toFixed(2) : product.price.toFixed(2) }}
                </span>
                <span v-if="product.unitPrice" class="text-xs text-[#6e6e73] font-normal">
                  {{ product.unitPrice }}
                </span>
                <span v-if="isSubscribed" class="text-[11px] font-bold text-[#7a5620] bg-[#f5eee2] border border-[#e0d9cc] px-2 py-0.5 rounded-full">
                  Save 5%
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

            <!-- Subscribe & Save 5% Toggle -->
            <div 
              v-if="product.hasSubscribeAndSave || product.hasSubscription"
              class="border border-[#e0d9cc] rounded-2xl p-4 bg-[#f3efe7] flex items-center justify-between select-none"
            >
              <div>
                <div class="font-bold text-sm text-[#1d1d1f] flex items-center gap-1.5">
                  <span>Subscribe & Save 5%</span>
                  <span class="text-xs font-semibold text-[#7a5620]">(${{ (product.price * 0.95).toFixed(2) }}/ea)</span>
                </div>
                <div class="text-xs text-[#6e6e73] font-normal mt-0.5">
                  Auto-reorder. Skip, pause or cancel anytime.
                </div>
              </div>

              <!-- Smooth Clean Toggle Switch -->
              <button
                type="button"
                @click="isSubscribed = !isSubscribed"
                :class="[
                  'w-12 h-7 rounded-full p-0.5 transition-colors relative cursor-pointer',
                  isSubscribed ? 'bg-[#1a1a1a]' : 'bg-[#e0d9cc]'
                ]"
                aria-label="Toggle Subscribe & Save"
              >
                <div 
                  :class="[
                    'w-6 h-6 bg-white rounded-full shadow-sm transition-transform duration-200',
                    isSubscribed ? 'translate-x-5' : 'translate-x-0'
                  ]"
                ></div>
              </button>
            </div>

            <!-- Frequently Bought Together (Screen 1 Specification) -->
            <div 
              v-if="fbtItems.length > 0" 
              class="border border-[#e0d9cc] rounded-3xl p-5 bg-[#f3efe7] space-y-3"
            >
              <div>
                <h2 class="font-serif font-medium text-base sm:text-lg text-[#1d1d1f]">Frequently bought together</h2>
                <p class="text-xs text-[#6e6e73] font-normal mt-0.5">68% of {{ product.name.toLowerCase().includes('paneer') ? 'paneer' : product.name }} shoppers add these</p>
              </div>

              <!-- FBT Items List with Dividers -->
              <div class="space-y-0 divide-y divide-[#e0d9cc]/70 pt-1">
                <div 
                  v-for="item in fbtItems"
                  :key="item.id"
                  class="py-3.5 first:pt-1 last:pb-1 flex items-center justify-between gap-3"
                >
                  <div class="flex items-center gap-3.5">
                    <div class="w-14 h-14 rounded-2xl bg-white/80 overflow-hidden shrink-0 border border-[#e0d9cc] shadow-2xs">
                      <img :src="item.image" :alt="item.name" class="w-full h-full object-cover" />
                    </div>
                    <div>
                      <div class="font-serif font-medium text-sm sm:text-base text-[#1d1d1f] leading-snug">{{ item.name }}</div>
                      <div class="text-xs text-[#6e6e73] font-normal mt-0.5">{{ item.size }}</div>
                    </div>
                  </div>

                  <div class="flex items-center gap-3">
                    <span class="font-serif font-medium text-base text-[#1d1d1f]">${{ Number(item.price).toFixed(2) }}</span>
                    <!-- Action Button: solid filled checkmark when selected, outline plus when unselected -->
                    <button 
                      type="button"
                      @click="toggleFbtItem(item.id)"
                      :class="[
                        'w-8 h-8 rounded-full flex items-center justify-center transition-all cursor-pointer active:scale-95',
                        fbtSelected[item.id] ? 'bg-[#1a1a1a] text-white shadow-xs' : 'border-2 border-[#1a1a1a] text-[#1a1a1a] bg-transparent'
                      ]"
                      :aria-label="'Toggle ' + item.name"
                    >
                      <Check v-if="fbtSelected[item.id]" class="w-4 h-4 stroke-[3]" />
                      <Plus v-else class="w-4 h-4 stroke-[2.5]" />
                    </button>
                  </div>
                </div>
              </div>

              <!-- Combined Add All Pill (Screen 1: Add paneer + all 3    $15.96) -->
              <button 
                type="button"
                @click="handleAddAllFbt"
                class="w-full py-4 px-6 bg-[#ece7de] hover:bg-[#e0d9cc] text-[#1d1d1f] font-semibold text-xs sm:text-sm rounded-full flex items-center justify-between cursor-pointer transition-colors shadow-2xs mt-3 active:scale-[0.99]"
              >
                <span>Add {{ product.name.toLowerCase().includes('paneer') ? 'paneer' : product.name }} + all {{ fbtItems.length }}</span>
                <span class="font-serif font-medium text-base sm:text-lg text-[#1d1d1f]">${{ totalFbtAll }}</span>
              </button>
            </div>

            <!-- What's in the kit (For Recipe Kits) -->
            <div 
              v-if="product.recipeIngredients?.length > 0" 
              class="border border-[#e0d9cc] rounded-2xl p-4 bg-[#f3efe7] space-y-3"
            >
              <div>
                <h2 class="font-serif font-medium text-sm text-[#1d1d1f]">What's included in this kit</h2>
                <p class="text-xs text-[#6e6e73] font-normal">Pre-portioned fresh ingredients & spices · Zero food waste</p>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                <div 
                  v-for="(ingredient, idx) in product.recipeIngredients" 
                  :key="idx" 
                  class="flex items-center gap-2.5 bg-white rounded-xl border border-[#e0d9cc] p-2.5 text-xs text-[#1d1d1f]"
                >
                  <div class="w-5 h-5 rounded-full bg-[#f5eee2] text-[#7a5620] flex items-center justify-center shrink-0">
                    <Check class="w-3.5 h-3.5 stroke-[3]" />
                  </div>
                  <span class="font-medium truncate">{{ ingredient }}</span>
                </div>
              </div>
            </div>

            <!-- Desktop Inline Action Bar -->
            <div class="hidden sm:block pt-4 border-t border-[#e0d9cc]/60 space-y-3">
              <div class="flex items-center gap-3">
                <!-- Stepper -->
                <div class="w-32 h-12 bg-[#ece7de] rounded-full flex items-center justify-between px-3 border border-[#e0d9cc]">
                  <button 
                    @click="quantity = Math.max(1, quantity - 1)" 
                    class="p-1 hover:text-[#1a1a1a] cursor-pointer flex items-center justify-center text-[#1d1d1f]"
                    aria-label="Decrease quantity"
                  >
                    <Minus class="w-4 h-4 stroke-[2.5]" />
                  </button>
                  <span class="text-sm font-bold text-[#1d1d1f]">{{ quantity }}</span>
                  <button 
                    @click="quantity = quantity + 1" 
                    class="p-1 hover:text-[#1a1a1a] cursor-pointer flex items-center justify-center text-[#1d1d1f]"
                    aria-label="Increase quantity"
                  >
                    <Plus class="w-4 h-4 stroke-[2.5]" />
                  </button>
                </div>

                <!-- Primary CTA Button -->
                <button 
                  @click="handleAction"
                  class="flex-1 h-12 bg-[#1a1a1a] hover:bg-black text-white font-semibold text-sm rounded-full flex items-center justify-between px-6 transition-all cursor-pointer active:scale-[0.99] shadow-md"
                >
                  <span>{{ isSubscribed ? 'Subscribe monthly' : (store.getItemQuantity(product.slug) > 0 ? 'Add another' : 'Add to cart') }}</span>
                  <span class="font-serif font-medium text-base">${{ displayPrice }}</span>
                </button>
              </div>

              <div 
                v-if="addedNotification"
                class="bg-[#f5eee2] border border-[#e0d9cc] text-[#7a5620] p-3 rounded-2xl text-xs font-semibold flex items-center justify-between"
              >
                <div class="flex items-center gap-2">
                  <CheckCircle2 class="w-4 h-4 text-[#7a5620]" />
                  <span>Added {{ quantity }}x {{ product.name }} to cart!</span>
                </div>
                <Link href="/cart" class="underline text-[#7a5620] font-semibold inline-flex items-center gap-1">
                  <span>View Cart</span>
                  <ChevronRight class="w-3.5 h-3.5" />
                </Link>
              </div>
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
            :href="'/products/' + (item.slug || (item.name.toLowerCase().includes('ghee') ? 'ghee' : 'toor-dal'))"
            class="relative block flex flex-col group text-left cursor-pointer transition-transform duration-200"
          >
            <!-- Rounded Photo Tile -->
            <div class="relative w-full aspect-[4/3] bg-[#f3efe7] rounded-[22px] sm:rounded-3xl overflow-hidden img-zoom-container shadow-2xs border border-[#e0d9cc]/60">
              <img 
                :src="item.image || (item.name.toLowerCase().includes('ghee') ? '/images/products/ghee.jpg' : '/images/products/toor_dal.jpg')" 
                :alt="item.name" 
                class="w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-300"
              />
              <span class="photo-label absolute top-2.5 left-2.5 text-[11px] text-[#1d1d1f] font-mono bg-white/80 backdrop-blur-xs px-2 py-0.5 rounded-lg z-10 shadow-2xs">{{ item.label || item.tag || 'item' }}</span>

              <!-- When item is in cart: show Primary [- qty +] stepper pill -->
              <div 
                v-if="store.getItemQuantity(item.id) > 0"
                class="absolute bottom-2.5 inset-x-2.5 bg-[#1a1a1a] text-white h-8 sm:h-9 rounded-full flex items-center justify-between px-2.5 shadow-md z-10"
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
                  @click.stop.prevent="store.addToCart({ id: item.id, name: item.name, weight: item.size || item.weight, price: item.price, originalPrice: item.price })" 
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
                @click.stop.prevent="store.addToCart({ id: item.id, name: item.name, weight: item.size || item.weight, price: item.price, originalPrice: item.price })"
                class="absolute bottom-2.5 right-2.5 w-8 h-8 sm:w-9 sm:h-9 bg-[#1a1a1a] hover:bg-black text-white rounded-full flex items-center justify-center cursor-pointer active:scale-95 shadow-md z-10 transition-transform group-hover:scale-105"
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
        <!-- Quantity Stepper -->
        <div class="w-28 h-11 bg-[#ece7de] rounded-full flex items-center justify-between px-3 border border-[#e0d9cc]">
          <button 
            @click="quantity = Math.max(1, quantity - 1)" 
            class="p-1 hover:text-[#1a1a1a] flex items-center justify-center text-[#1d1d1f]"
            aria-label="Decrease quantity"
          >
            <Minus class="w-4 h-4 stroke-[2.5]" />
          </button>
          <span class="text-xs font-bold text-[#1d1d1f]">{{ quantity }}</span>
          <button 
            @click="quantity = quantity + 1" 
            class="p-1 hover:text-[#1a1a1a] flex items-center justify-center text-[#1d1d1f]"
            aria-label="Increase quantity"
          >
            <Plus class="w-4 h-4 stroke-[2.5]" />
          </button>
        </div>

        <!-- Add to cart CTA -->
        <button 
          @click="handleAction"
          class="flex-1 h-11 bg-[#1a1a1a] active:bg-black text-white font-semibold text-xs rounded-full flex items-center justify-between px-5 shadow-md"
        >
          <span>{{ isSubscribed ? 'Subscribe' : (store.getItemQuantity(product.slug) > 0 ? 'Add another' : 'Add to cart') }}</span>
          <span class="font-serif font-medium text-sm">${{ displayPrice }}</span>
        </button>
      </div>

      <!-- Mobile Toast/Notification when added -->
      <div 
        v-if="addedNotification"
        class="sm:hidden fixed top-16 left-4 right-4 z-50 bg-[#1a1a1a] text-white p-3.5 rounded-2xl shadow-2xl flex items-center justify-between text-xs font-semibold border border-[#e0d9cc]/20"
      >
        <div class="flex items-center gap-2">
          <CheckCircle2 class="w-4 h-4 text-[#a47a3c]" />
          <span>Added to cart!</span>
        </div>
        <Link href="/cart" class="underline text-[#a47a3c] font-semibold inline-flex items-center gap-1">
          <span>View Cart</span>
          <ChevronRight class="w-3.5 h-3.5" />
        </Link>
      </div>

    </div>
  </StoreLayout>
</template>
