<script setup>
import { ref, computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import StoreLayout from '../Layouts/StoreLayout.vue';
import { useStore } from '../stores/cart';
import { 
  ChevronLeft, 
  Clock, 
  Users, 
  UtensilsCrossed, 
  Sparkles, 
  CheckCircle2, 
  Plus, 
  Minus, 
  Check, 
  TrendingDown, 
  PackageCheck,
  ShieldCheck,
  ChevronRight,
  X,
  AlertTriangle
} from 'lucide-vue-next';

const props = defineProps({
  kit: {
    type: Object,
    required: true,
  },
});

const store = useStore();
const page = usePage();
const storeInfo = computed(() => page.props.storeInfo || {});


const activeImage = ref(props.kit.image || (props.kit.images?.[0]) || '/images/products/paneer_curry.jpg');
const addedNotification = ref(false);
const lastAddedSummary = ref('');
let addedToastTimeout = null;

function isIngredientOutOfStock(p) {
  if (!p) return false;
  if (store.isOutOfStock(p)) return true;
  const requiredQty = Number(p.pivot?.quantity || 1);
  return store.getAvailableStock(p) < requiredQty;
}

const outOfStockIngredients = computed(() => {
  return (props.kit.products || []).filter(p => isIngredientOutOfStock(p));
});

const availableIngredients = computed(() => {
  return (props.kit.products || []).filter(p => !isIngredientOutOfStock(p));
});

// State for which linked ingredients are checked for Group Buy
const checkedProducts = ref({});
if (props.kit.products && props.kit.products.length > 0) {
  props.kit.products.forEach(p => {
    checkedProducts.value[p.id] = !isIngredientOutOfStock(p);
  });
}

function toggleProduct(p) {
  if (isIngredientOutOfStock(p)) {
    store.showStockToast(`"${p.name}" is currently out of stock and cannot be added.`);
    return;
  }
  checkedProducts.value[p.id] = !checkedProducts.value[p.id];
}

const imagesList = computed(() => {
  if (Array.isArray(props.kit.images) && props.kit.images.length > 0) {
    return props.kit.images;
  }
  return [props.kit.image || '/images/products/paneer_curry.jpg'];
});

// Calculated pricing
const selectedProducts = computed(() => {
  return (props.kit.products || []).filter(p => checkedProducts.value[p.id] && !isIngredientOutOfStock(p));
});

const standaloneSum = computed(() => {
  return selectedProducts.value.reduce((acc, p) => {
    const qty = p.pivot?.quantity || 1;
    return acc + (Number(p.price) * qty);
  }, 0);
});

const allChecked = computed(() => {
  return (props.kit.products || []).length > 0 && 
         selectedProducts.value.length === (props.kit.products || []).length;
});

const isAllIngredientsOutOfStock = computed(() => {
  return (props.kit.products || []).length > 0 && availableIngredients.value.length === 0;
});

const kitPrice = computed(() => {
  if (allChecked.value) {
    return Number(props.kit.price);
  }
  if ((props.kit.products || []).length === 0) {
    return Number(props.kit.price || 0);
  }
  // If some products were unchecked, calculate prorated sum
  return Number(standaloneSum.value.toFixed(2));
});

const bundleSavings = computed(() => {
  const original = Number(props.kit.original_price || standaloneSum.value);
  if (original > kitPrice.value && allChecked.value) {
    return (original - kitPrice.value).toFixed(2);
  }
  return 0;
});

function handleAddKitToCart() {
  if ((props.kit.products || []).length > 0 && selectedProducts.value.length === 0) {
    store.showStockToast('Please select at least one available ingredient to add to cart.');
    return;
  }

  let addedAny = false;
  if (allChecked.value || (props.kit.products || []).length === 0) {
    addedAny = store.addToCart({
      id: `kit-${props.kit.id}`,
      slug: props.kit.slug,
      type: 'recipe-kit',
      isRecipeKit: true,
      name: `${props.kit.name} (Recipe Kit)`,
      price: Number(props.kit.price),
      weight: props.kit.servings || 'Recipe Kit',
      quantity: 1,
      image: activeImage.value,
      products: props.kit.products || [],
    });
    lastAddedSummary.value = `${props.kit.name} Kit`;
  } else {
    let addedCount = 0;
    selectedProducts.value.forEach(p => {
      const qty = p.pivot?.quantity || 1;
      const ok = store.addToCart({
        id: p.id,
        slug: p.slug,
        name: p.name,
        price: Number(p.price),
        weight: p.size_main,
        quantity: qty,
        image: p.image,
        stock: p.stock,
        stock_badge: p.stock_badge,
      });
      if (ok) addedCount++;
    });
    addedAny = addedCount > 0;
    lastAddedSummary.value = `${addedCount} Kit Ingredient${addedCount === 1 ? '' : 's'}`;
  }

  if (!addedAny) return;

  addedNotification.value = true;
  if (addedToastTimeout) clearTimeout(addedToastTimeout);
  addedToastTimeout = setTimeout(() => {
    addedNotification.value = false;
  }, 4000);
}
</script>

<template>
  <Head :title="`${kit.name} — Recipe Kit Group Buy — ${storeInfo.brand_name}`" />

  <StoreLayout 
    headerMode="pdp" 
    :headerTitle="'Recipe Kit · ' + (kit.difficulty || 'Chef Special')" 
    backUrl="/"
    :showCartBar="false" 
    :showBottomNav="false"
  >
    <div class="space-y-6 pb-20 sm:pb-8 max-w-5xl mx-auto">

      <!-- Breadcrumbs -->
      <nav class="hidden sm:flex items-center gap-2 text-xs font-normal text-stone-500">
        <Link href="/" class="hover:text-stone-950 flex items-center gap-1 font-semibold text-stone-700">
          <ChevronLeft class="w-4 h-4" />
          <span>Home</span>
        </Link>
        <span>/</span>
        <Link href="/recipe-kits" class="text-stone-600 font-semibold hover:text-stone-900">
          Recipe Kits
        </Link>
        <span>/</span>
        <span class="text-stone-900 font-bold truncate max-w-xs">{{ kit.name }}</span>
      </nav>

      <!-- Main Stage: Visuals & Overview -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-10 items-start">
        
        <!-- LEFT: Photo Gallery -->
        <div class="lg:col-span-6 space-y-4">
          <div class="relative w-full aspect-[4/3] bg-[#F4F5F7] rounded-lg overflow-hidden shadow-xs border border-[#e0d9cc]/80 flex flex-col justify-between p-4 group img-zoom-container">
            <img 
              :src="activeImage" 
              :alt="kit.name" 
              class="w-full h-full object-cover object-center absolute inset-0 transition-opacity duration-200"
            />
            <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent pointer-events-none"></div>

            <!-- Top Badge Row -->
            <div class="relative z-10 flex items-center justify-between">
              <span class="bg-white/90 backdrop-blur-xs text-stone-800 border border-stone-200 px-3 py-1 text-xs font-semibold rounded-md shadow-xs">
                Group Buy Bundle
              </span>
              <span class="font-devanagari text-[11px] text-white/90 bg-black/60 backdrop-blur-xs px-2.5 py-0.5 rounded-md">
                एक टैप में रसोई
              </span>
            </div>

            <!-- Bottom Specs Overlay -->
            <div class="relative z-10 flex items-center gap-2 text-xs text-white">
              <span class="bg-black/60 backdrop-blur-xs px-2.5 py-1 rounded-md flex items-center gap-1">
                <Clock class="w-3.5 h-3.5 text-[#e4b97a]" />
                <span>{{ kit.cooking_time || '20 mins' }}</span>
              </span>
              <span class="bg-black/60 backdrop-blur-xs px-2.5 py-1 rounded-md flex items-center gap-1">
                <Users class="w-3.5 h-3.5 text-[#e4b97a]" />
                <span>{{ kit.servings || 'Serves 4' }}</span>
              </span>
              <span class="bg-black/60 backdrop-blur-xs px-2.5 py-1 rounded-md flex items-center gap-1">
                <UtensilsCrossed class="w-3.5 h-3.5 text-[#e4b97a]" />
                <span>{{ kit.difficulty || 'Easy' }}</span>
              </span>
            </div>
          </div>

          <!-- Multi-Image Thumbnails -->
          <div v-if="imagesList.length > 1" class="flex items-center gap-2 overflow-x-auto pb-1 no-scrollbar">
            <button 
              v-for="(img, idx) in imagesList" 
              :key="idx"
              type="button"
              @click="activeImage = img"
              class="w-16 h-16 rounded-md overflow-hidden border-2 transition-all shrink-0 cursor-pointer"
              :class="activeImage === img ? 'border-[#a47a3c] ring-2 ring-[#a47a3c]/30' : 'border-transparent opacity-70 hover:opacity-100'"
            >
              <img :src="img" :alt="kit.name" class="w-full h-full object-cover" />
            </button>
          </div>
        </div>

        <!-- RIGHT: Kit Info, Pricing & 1-Click Group Buy CTA -->
        <div class="lg:col-span-6 space-y-6">
          <div>
            <div class="text-[11px] font-mono uppercase tracking-wider text-[#a47a3c] font-semibold mb-1">
              {{ kit.subtitle_tag || 'CHEF-CRAFTED · DINNER IN 20 MINS' }}
            </div>
            <h1 class="text-2xl sm:text-3xl lg:text-4xl font-serif font-medium text-[#1d1d1f] tracking-tight leading-tight">
              {{ kit.name }}
            </h1>
            <p class="text-xs sm:text-sm text-[#6e6e73] font-normal mt-2 leading-relaxed">
              {{ kit.description || 'Pre-portioned fresh ingredients & whole spices bundled together. Zero food waste with complete cooking instructions.' }}
            </p>
          </div>

          <!-- Pricing Row with Group Buy Savings -->
          <div class="bg-[#f3efe7] border border-[#e0d9cc] rounded-lg p-5 sm:p-6 space-y-3">
            <div class="flex items-baseline justify-between">
              <div>
                <div class="text-[10px] font-mono uppercase text-stone-500 font-semibold">
                  Group Buy Bundle Price
                </div>
                <div class="flex items-baseline gap-2 mt-0.5">
                  <span class="text-3xl font-serif font-medium text-[#1d1d1f]">
                    ${{ kitPrice.toFixed(2) }}
                  </span>
                  <span v-if="kit.original_price" class="text-sm text-[#86868b] line-through">
                    ${{ Number(kit.original_price).toFixed(2) }}
                  </span>
                </div>
              </div>

              <!-- Savings Pill -->
              <div v-if="bundleSavings > 0" class="text-right">
                <span class="inline-flex items-center gap-1 text-xs font-bold text-[#7a5620] bg-[#f5eee2] border border-[#e0d9cc] px-2.5 py-1 rounded-md">
                  <TrendingDown class="w-3.5 h-3.5" />
                  <span>Save ${{ bundleSavings }} Bundle Discount</span>
                </span>
              </div>
            </div>

            <!-- Out-of-stock Ingredients Alert Banner -->
            <div 
              v-if="outOfStockIngredients.length > 0" 
              class="p-3 rounded-md bg-amber-50 border border-amber-200/80 flex items-start gap-2.5 text-amber-950"
            >
              <AlertTriangle class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
              <div class="text-xs leading-snug">
                <span class="font-bold">{{ outOfStockIngredients.length }} ingredient{{ outOfStockIngredients.length === 1 ? ' is' : 's are' }} out of stock</span>
                ({{ outOfStockIngredients.map(i => i.name).join(', ') }}) and automatically unchecked. You can still buy the remaining {{ selectedProducts.length }} available item{{ selectedProducts.length === 1 ? '' : 's' }} at the adjusted price.
              </div>
            </div>

            <!-- 1-Click Group Buy Action Button -->
            <button
              type="button"
              :disabled="isAllIngredientsOutOfStock || ((kit.products || []).length > 0 && selectedProducts.length === 0)"
              @click="handleAddKitToCart"
              class="w-full py-3.5 font-semibold text-xs sm:text-sm rounded-md flex items-center justify-center gap-2 transition-all shadow-md"
              :class="(isAllIngredientsOutOfStock || ((kit.products || []).length > 0 && selectedProducts.length === 0))
                ? 'bg-stone-200 text-stone-500 border border-stone-300 cursor-not-allowed shadow-none'
                : 'bg-[#1a1a1a] hover:bg-black text-white active:scale-98 cursor-pointer'"
            >
              <Plus v-if="!isAllIngredientsOutOfStock && !((kit.products || []).length > 0 && selectedProducts.length === 0)" class="w-4 h-4 stroke-[2.5]" />
              <span v-if="isAllIngredientsOutOfStock">Kit Out of Stock</span>
              <span v-else-if="(kit.products || []).length > 0 && selectedProducts.length === 0">Select at least 1 ingredient</span>
              <span v-else>Add {{ allChecked ? 'All ' : '' }}{{ selectedProducts.length }} Ingredient{{ selectedProducts.length === 1 ? '' : 's' }} to Cart</span>
            </button>

            <!-- Notification Toast -->
            <transition
              enter-active-class="transition duration-200 ease-out"
              enter-from-class="opacity-0 -translate-y-1 scale-98"
              enter-to-class="opacity-100 translate-y-0 scale-100"
              leave-active-class="transition duration-150 ease-in"
              leave-from-class="opacity-100 translate-y-0 scale-100"
              leave-to-class="opacity-0 -translate-y-1 scale-98"
            >
              <div
                v-if="addedNotification"
                class="p-3 rounded-lg bg-[#141413] text-white border border-[#a47a3c]/40 shadow-md flex items-center justify-between gap-3"
              >
                <div class="flex items-center gap-2.5 min-w-0">
                  <div class="w-8 h-8 rounded-md bg-emerald-500/20 border border-emerald-400/30 flex items-center justify-center shrink-0">
                    <CheckCircle2 class="w-4 h-4 text-emerald-400" />
                  </div>
                  <div class="min-w-0">
                    <div class="text-xs font-bold text-white truncate">
                      Added {{ lastAddedSummary }}
                    </div>
                    <div class="text-[11px] text-stone-300 font-mono">
                      Cart ({{ store.totalItemCount }} items) · ${{ store.subtotal.toFixed(2) }}
                    </div>
                  </div>
                </div>
                <Link
                  href="/cart"
                  class="shrink-0 bg-[#e4b97a] hover:bg-[#d8a75e] text-[#1a1a1a] px-3.5 py-1.5 rounded-md text-xs font-bold flex items-center gap-1 transition-colors"
                >
                  <span>View Cart</span>
                  <ChevronRight class="w-3.5 h-3.5 stroke-[2.5]" />
                </Link>
              </div>
            </transition>
          </div>
        </div>

      </div>

      <!-- SECTION: Interactive Linked Products for Group Buy -->
      <div class="bg-white border border-[#e0d9cc] rounded-lg p-6 sm:p-8 shadow-2xs space-y-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-[#e0d9cc]/60 pb-4">
          <div>
            <div class="flex items-center gap-2">
              <span class="text-[11px] font-mono uppercase bg-[#1a1a1a] text-white px-2.5 py-0.5 rounded-md font-semibold">
                Included in Kit
              </span>
              <span class="text-xs font-semibold text-stone-700">
                {{ selectedProducts.length }} of {{ (kit.products || []).length }} Items Selected
              </span>
              <span v-if="outOfStockIngredients.length > 0" class="text-[11px] font-bold text-red-700 bg-red-50 border border-red-200 px-2 py-0.5 rounded-md">
                {{ outOfStockIngredients.length }} Out of Stock
              </span>
            </div>
            <h2 class="text-xl font-serif font-medium text-[#1d1d1f] mt-1.5">
              Fresh Ingredients & Pantry Staples
            </h2>
            <p class="text-xs text-[#6e6e73]">
              All items are pre-measured for this recipe. Already have a staple at home? Uncheck it to customize your group buy.
            </p>
          </div>
        </div>

        <!-- Products Checklist Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3 sm:gap-4">
          <div 
            v-for="p in kit.products"
            :key="p.id"
            @click="toggleProduct(p)"
            class="p-3.5 rounded-lg border transition-all flex items-center justify-between gap-3 group select-none"
            :class="isIngredientOutOfStock(p)
              ? 'bg-red-50/40 border-red-200/80 opacity-65 cursor-not-allowed'
              : (checkedProducts[p.id] ? 'bg-[#fbfaf8] border-[#a47a3c]/80 shadow-2xs cursor-pointer' : 'bg-stone-50/50 border-stone-200 opacity-60 cursor-pointer')"
          >
            <div class="flex items-center gap-3 min-w-0">
              <img 
                :src="p.image || '/images/products/paneer_curry.jpg'" 
                :alt="p.name"
                class="w-12 h-12 rounded-md object-cover border border-[#e0d9cc]/60 shrink-0"
                :class="{ 'grayscale': isIngredientOutOfStock(p) }"
              />
              <div class="min-w-0">
                <div class="flex items-center gap-1.5 flex-wrap">
                  <div class="text-xs font-semibold text-[#1d1d1f] truncate transition-colors" :class="{ 'line-through text-stone-500': isIngredientOutOfStock(p), 'group-hover:text-[#a47a3c]': !isIngredientOutOfStock(p) }">
                    {{ p.name }}
                  </div>
                  <span v-if="isIngredientOutOfStock(p)" class="text-[9.5px] font-bold uppercase tracking-wider bg-red-100 text-red-700 border border-red-200 px-1.5 py-0.2 rounded">
                    Out of Stock
                  </span>
                  <span v-else-if="store.getAvailableStock(p) <= 5" class="text-[9.5px] font-bold bg-amber-50 text-amber-800 border border-amber-200 px-1.5 py-0.2 rounded">
                    Only {{ store.getAvailableStock(p) }} left
                  </span>
                </div>
                <div class="text-[10px] text-[#6e6e73] truncate">
                  {{ p.pivot?.unit_notes || p.size_main || 'Standard Pack' }}
                </div>
                <div class="text-[11px] font-mono font-bold text-stone-800 mt-0.5">
                  ${{ Number(p.price).toFixed(2) }}
                  <span v-if="p.pivot?.quantity > 1" class="text-[9px] font-sans font-normal text-stone-500">
                    (x{{ p.pivot.quantity }})
                  </span>
                </div>
              </div>
            </div>

            <!-- Custom Checkbox -->
            <div 
              class="w-6 h-6 rounded-lg flex items-center justify-center transition-colors shrink-0"
              :class="isIngredientOutOfStock(p)
                ? 'border border-red-200 bg-red-50 text-red-400'
                : (checkedProducts[p.id] ? 'bg-[#1a1a1a] text-white' : 'border border-stone-300 bg-white')"
            >
              <X v-if="isIngredientOutOfStock(p)" class="w-3.5 h-3.5 stroke-[2.5]" />
              <Check v-else-if="checkedProducts[p.id]" class="w-3.5 h-3.5 stroke-[3]" />
            </div>
          </div>
        </div>
      </div>

      <!-- SECTION: Step-by-Step Cooking Guide -->
      <div v-if="kit.recipe_steps && kit.recipe_steps.length > 0" class="bg-white border border-[#e0d9cc] rounded-lg p-6 sm:p-8 shadow-2xs space-y-4">
        <div class="border-b border-[#e0d9cc]/60 pb-3">
          <span class="text-[11px] font-mono uppercase bg-[#f5eee2] text-[#7a5620] border border-[#e0d9cc] px-2.5 py-0.5 rounded-md font-semibold">
            Chef Recipe Card
          </span>
          <h2 class="text-xl font-serif font-medium text-[#1d1d1f] mt-1.5">
            Step-by-Step Instructions
          </h2>
        </div>

        <div class="space-y-3 pt-1">
          <div 
            v-for="(step, idx) in kit.recipe_steps" 
            :key="idx"
            class="flex items-start gap-3.5 p-3 rounded-lg bg-[#fbfaf8] border border-[#e0d9cc]/60"
          >
            <span class="w-7 h-7 rounded-md bg-[#1a1a1a] text-white flex items-center justify-center font-mono font-bold text-xs shrink-0 mt-0.5 shadow-2xs">
              {{ idx + 1 }}
            </span>
            <p class="text-xs sm:text-sm text-[#1d1d1f] font-normal leading-relaxed pt-0.5">
              {{ step }}
            </p>
          </div>
        </div>
      </div>

    </div>
  </StoreLayout>
</template>
