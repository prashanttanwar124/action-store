<script setup>
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
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
  ShieldCheck
} from 'lucide-vue-next';

const props = defineProps({
  kit: {
    type: Object,
    required: true,
  },
});

const store = useStore();

const activeImage = ref(props.kit.image || (props.kit.images?.[0]) || '/images/products/paneer_curry.jpg');
const addedNotification = ref(false);

// State for which linked ingredients are checked for Group Buy
const checkedProducts = ref({});
if (props.kit.products && props.kit.products.length > 0) {
  props.kit.products.forEach(p => {
    checkedProducts.value[p.id] = true;
  });
}

function toggleProduct(id) {
  checkedProducts.value[id] = !checkedProducts.value[id];
}

const imagesList = computed(() => {
  if (Array.isArray(props.kit.images) && props.kit.images.length > 0) {
    return props.kit.images;
  }
  return [props.kit.image || '/images/products/paneer_curry.jpg'];
});

// Calculated pricing
const selectedProducts = computed(() => {
  return (props.kit.products || []).filter(p => checkedProducts.value[p.id]);
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

const kitPrice = computed(() => {
  if (allChecked.value) {
    return Number(props.kit.price);
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
  // Add all selected group buy ingredients into the cart
  selectedProducts.value.forEach(p => {
    const qty = p.pivot?.quantity || 1;
    store.addToCart({
      id: p.id,
      name: p.name,
      price: Number(p.price),
      weight: p.size_main,
      quantity: qty,
      image: p.image,
    });
  });

  addedNotification.value = true;
  setTimeout(() => {
    addedNotification.value = false;
  }, 2500);
}
</script>

<template>
  <Head :title="`${kit.name} — Recipe Kit Group Buy — Masala Mart`" />

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
        <Link href="/#recipe-kits" class="text-stone-600 font-semibold hover:text-stone-900">
          Recipe Kits
        </Link>
        <span>/</span>
        <span class="text-stone-900 font-bold truncate max-w-xs">{{ kit.name }}</span>
      </nav>

      <!-- Main Stage: Visuals & Overview -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-10 items-start">
        
        <!-- LEFT: Photo Gallery -->
        <div class="lg:col-span-6 space-y-4">
          <div class="relative w-full aspect-[4/3] bg-[#F4F5F7] rounded-3xl overflow-hidden shadow-xs border border-[#e0d9cc]/80 flex flex-col justify-between p-4 group img-zoom-container">
            <img 
              :src="activeImage" 
              :alt="kit.name" 
              class="w-full h-full object-cover object-center absolute inset-0 transition-opacity duration-200"
            />
            <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent pointer-events-none"></div>

            <!-- Top Badge Row -->
            <div class="relative z-10 flex items-center justify-between">
              <span class="bg-white/90 backdrop-blur-xs text-stone-800 border border-stone-200 px-3 py-1 text-xs font-semibold rounded-full shadow-xs">
                Group Buy Bundle
              </span>
              <span class="font-devanagari text-[11px] text-white/90 bg-black/60 backdrop-blur-xs px-2.5 py-0.5 rounded-full">
                एक टैप में रसोई
              </span>
            </div>

            <!-- Bottom Specs Overlay -->
            <div class="relative z-10 flex items-center gap-2 text-xs text-white">
              <span class="bg-black/60 backdrop-blur-xs px-2.5 py-1 rounded-full flex items-center gap-1">
                <Clock class="w-3.5 h-3.5 text-[#e4b97a]" />
                <span>{{ kit.cooking_time || '20 mins' }}</span>
              </span>
              <span class="bg-black/60 backdrop-blur-xs px-2.5 py-1 rounded-full flex items-center gap-1">
                <Users class="w-3.5 h-3.5 text-[#e4b97a]" />
                <span>{{ kit.servings || 'Serves 4' }}</span>
              </span>
              <span class="bg-black/60 backdrop-blur-xs px-2.5 py-1 rounded-full flex items-center gap-1">
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
              class="w-16 h-16 rounded-xl overflow-hidden border-2 transition-all shrink-0 cursor-pointer"
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
          <div class="bg-[#f3efe7] border border-[#e0d9cc] rounded-3xl p-5 sm:p-6 space-y-3">
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
                <span class="inline-flex items-center gap-1 text-xs font-bold text-[#7a5620] bg-[#f5eee2] border border-[#e0d9cc] px-2.5 py-1 rounded-full">
                  <TrendingDown class="w-3.5 h-3.5" />
                  <span>Save ${{ bundleSavings }} Bundle Discount</span>
                </span>
              </div>
            </div>

            <!-- 1-Click Group Buy Action Button -->
            <button
              type="button"
              @click="handleAddKitToCart"
              class="w-full py-3.5 bg-[#1a1a1a] hover:bg-black text-white font-semibold text-xs sm:text-sm rounded-full flex items-center justify-center gap-2 transition-all active:scale-98 shadow-md cursor-pointer"
            >
              <Plus class="w-4 h-4 stroke-[2.5]" />
              <span>Add All {{ selectedProducts.length }} Ingredients to Cart</span>
            </button>

            <!-- Notification Toast -->
            <div 
              v-if="addedNotification"
              class="p-2.5 rounded-xl bg-emerald-800 text-emerald-100 text-xs font-semibold text-center flex items-center justify-center gap-1.5 transition-all animate-fade-in"
            >
              <CheckCircle2 class="w-4 h-4 text-emerald-300" />
              <span>All {{ selectedProducts.length }} recipe kit ingredients added to cart!</span>
            </div>
          </div>
        </div>

      </div>

      <!-- SECTION: Interactive Linked Products for Group Buy -->
      <div class="bg-white border border-[#e0d9cc] rounded-3xl p-6 sm:p-8 shadow-2xs space-y-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-[#e0d9cc]/60 pb-4">
          <div>
            <div class="flex items-center gap-2">
              <span class="text-[11px] font-mono uppercase bg-[#1a1a1a] text-white px-2.5 py-0.5 rounded-full font-semibold">
                Included in Kit
              </span>
              <span class="text-xs font-semibold text-stone-700">
                {{ selectedProducts.length }} of {{ (kit.products || []).length }} Items Selected
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
            @click="toggleProduct(p.id)"
            class="p-3.5 rounded-2xl border transition-all cursor-pointer flex items-center justify-between gap-3 group select-none"
            :class="checkedProducts[p.id] ? 'bg-[#fbfaf8] border-[#a47a3c]/80 shadow-2xs' : 'bg-stone-50/50 border-stone-200 opacity-60'"
          >
            <div class="flex items-center gap-3 min-w-0">
              <img 
                :src="p.image || '/images/products/paneer_curry.jpg'" 
                :alt="p.name"
                class="w-12 h-12 rounded-xl object-cover border border-[#e0d9cc]/60 shrink-0" 
              />
              <div class="min-w-0">
                <div class="text-xs font-semibold text-[#1d1d1f] truncate group-hover:text-[#a47a3c] transition-colors">
                  {{ p.name }}
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
              :class="checkedProducts[p.id] ? 'bg-[#1a1a1a] text-white' : 'border border-stone-300 bg-white'"
            >
              <Check v-if="checkedProducts[p.id]" class="w-3.5 h-3.5 stroke-[3]" />
            </div>
          </div>
        </div>
      </div>

      <!-- SECTION: Step-by-Step Cooking Guide -->
      <div v-if="kit.recipe_steps && kit.recipe_steps.length > 0" class="bg-white border border-[#e0d9cc] rounded-3xl p-6 sm:p-8 shadow-2xs space-y-4">
        <div class="border-b border-[#e0d9cc]/60 pb-3">
          <span class="text-[11px] font-mono uppercase bg-[#f5eee2] text-[#7a5620] border border-[#e0d9cc] px-2.5 py-0.5 rounded-full font-semibold">
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
            class="flex items-start gap-3.5 p-3 rounded-2xl bg-[#fbfaf8] border border-[#e0d9cc]/60"
          >
            <span class="w-7 h-7 rounded-full bg-[#1a1a1a] text-white flex items-center justify-center font-mono font-bold text-xs shrink-0 mt-0.5 shadow-2xs">
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
