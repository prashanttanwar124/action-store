<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { 
  ArrowLeft, 
  Upload, 
  X, 
  Image as ImageIcon, 
  Plus, 
  Check, 
  Sparkles,
  Link as LinkIcon,
  Trash2,
  Clock,
  Users,
  UtensilsCrossed,
  PackagePlus,
  TrendingDown,
  Info
} from 'lucide-vue-next';

const props = defineProps({
  availableProducts: {
    type: Array,
    default: () => [],
  },
});

const isSlugManuallyEdited = ref(false);
const previewImages = ref([]);
const manualUrlInput = ref('');
const fileInputRef = ref(null);
const selectedProductToAdd = ref('');

const form = useForm({
  name: '',
  slug: '',
  subtitle_tag: 'CHEF-CRAFTED · DINNER IN 20 MINS',
  description: '',
  servings: 'Serves 4',
  cooking_time: '25 mins',
  difficulty: 'Easy',
  price: '',
  original_price: '',
  is_active: true,
  image_files: [],
  image_urls: [],
  products: [],
  recipe_steps: [
    'Saute whole spices in hot ghee until fragrant (1 min).',
    'Add creamy tomato gravy base and simmer on medium heat (5 mins).',
    'Gently fold in fresh paneer cubes and crushed kasuri methi (3 mins).',
  ],
});

// Auto-slugify
watch(() => form.name, (newName) => {
  if (!isSlugManuallyEdited.value && newName) {
    form.slug = newName
      .toLowerCase()
      .trim()
      .replace(/[^\w\s-]/g, '')
      .replace(/[\s_-]+/g, '-')
      .replace(/^-+|-+$/g, '');
  }
});

// Image Uploads Handling
const handleFilesSelected = (e) => {
  const files = Array.from(e.target.files || []);
  if (files.length === 0) return;

  for (const file of files) {
    form.image_files.push(file);

    const reader = new FileReader();
    reader.onload = (event) => {
      previewImages.value.push({
        type: 'file',
        file,
        url: event.target.result,
        name: file.name,
      });
    };
    reader.readAsDataURL(file);
  }

  if (fileInputRef.value) fileInputRef.value.value = '';
};

const addManualUrl = () => {
  const url = manualUrlInput.value.trim();
  if (!url) return;

  form.image_urls.push(url);
  previewImages.value.push({
    type: 'url',
    url,
    name: url.split('/').pop() || 'External URL',
  });

  manualUrlInput.value = '';
};

const removePreviewImage = (index) => {
  const item = previewImages.value[index];
  if (!item) return;

  if (item.type === 'file') {
    const fileIndex = form.image_files.indexOf(item.file);
    if (fileIndex !== -1) form.image_files.splice(fileIndex, 1);
  } else if (item.type === 'url') {
    const urlIndex = form.image_urls.indexOf(item.url);
    if (urlIndex !== -1) form.image_urls.splice(urlIndex, 1);
  }

  previewImages.value.splice(index, 1);
};

// Linked Products Management for Group Buy
const addProductToKit = () => {
  if (!selectedProductToAdd.value) return;

  const product = props.availableProducts.find(p => p.id === Number(selectedProductToAdd.value));
  if (!product) return;

  // Check if already in list
  const existing = form.products.find(p => p.product_id === product.id);
  if (existing) {
    existing.quantity += 1;
    selectedProductToAdd.value = '';
    return;
  }

  form.products.push({
    product_id: product.id,
    name: product.name,
    price: Number(product.price),
    image: product.image,
    size_main: product.size_main,
    quantity: 1,
    unit_notes: '',
    is_optional: false,
  });

  selectedProductToAdd.value = '';
};

const removeProductFromKit = (index) => {
  form.products.splice(index, 1);
};

// Recipe Steps Management
const addRecipeStep = () => {
  form.recipe_steps.push('');
};

const removeRecipeStep = (index) => {
  form.recipe_steps.splice(index, 1);
};

// Calculated bundle metrics
const individualProductsTotal = computed(() => {
  return form.products.reduce((acc, item) => {
    return acc + (Number(item.price || 0) * Number(item.quantity || 1));
  }, 0);
});

const bundleSavings = computed(() => {
  const kitPrice = Number(form.price) || 0;
  const comparePrice = Number(form.original_price) || individualProductsTotal.value;
  if (comparePrice > kitPrice && kitPrice > 0) {
    return comparePrice - kitPrice;
  }
  return 0;
});

// Auto set original price if empty
const copyIndividualTotalToOriginal = () => {
  if (individualProductsTotal.value > 0) {
    form.original_price = individualProductsTotal.value.toFixed(2);
  }
};

const submit = () => {
  form.post(route('admin.recipe-kits.store'), {
    forceFormData: true,
  });
};
</script>

<template>
  <AdminLayout title="Add Recipe Kit">
    <div class="max-w-5xl mx-auto space-y-6">

      <!-- Breadcrumbs & Back Bar -->
      <div class="flex items-center justify-between">
        <Link 
          :href="route('admin.recipe-kits.index')"
          class="inline-flex items-center gap-2 text-xs font-semibold text-stone-600 hover:text-stone-900 transition-colors"
        >
          <ArrowLeft class="w-4 h-4" />
          <span>Back to Recipe Kits</span>
        </Link>
      </div>

      <!-- Main Form -->
      <form @submit.prevent="submit" class="space-y-6">

        <!-- Card 1: Kit Identity & Headline -->
        <div class="bg-white border border-[#e0d9cc] rounded-3xl p-6 sm:p-8 shadow-2xs space-y-5">
          <div class="border-b border-[#e0d9cc]/60 pb-4">
            <span class="text-[11px] font-mono uppercase bg-[#1a1a1a] text-white px-2.5 py-0.5 rounded-full font-semibold">
              Step 1
            </span>
            <h2 class="text-xl font-serif font-medium text-[#1d1d1f] mt-2">
              Recipe Kit Information
            </h2>
            <p class="text-xs text-[#6e6e73] mt-0.5">
              Specify the dish name, banner tagline, cooking specs, and description.
            </p>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <!-- Kit Name -->
            <div class="md:col-span-2">
              <label class="block text-xs font-semibold uppercase tracking-wider text-stone-700 mb-1.5">
                Recipe Kit Name <span class="text-rose-500">*</span>
              </label>
              <input
                type="text"
                v-model="form.name"
                placeholder="e.g. Paneer Butter Masala Kit"
                class="w-full px-3.5 py-2.5 bg-[#fbfaf8] border rounded-2xl text-sm font-serif focus:bg-white focus:outline-none transition-all"
                :class="form.errors.name ? 'border-rose-400 focus:border-rose-500' : 'border-[#e0d9cc] focus:border-[#a47a3c]'"
                required
              />
              <div v-if="form.errors.name" class="text-rose-600 text-[11px] mt-1">{{ form.errors.name }}</div>
            </div>

            <!-- Slug -->
            <div>
              <label class="block text-xs font-semibold uppercase tracking-wider text-stone-700 mb-1.5">
                URL Slug
              </label>
              <input
                type="text"
                v-model="form.slug"
                @input="isSlugManuallyEdited = true"
                placeholder="paneer-curry"
                class="w-full px-3.5 py-2.5 bg-[#fbfaf8] border rounded-2xl text-xs font-mono focus:bg-white focus:outline-none transition-all"
                :class="form.errors.slug ? 'border-rose-400 focus:border-rose-500' : 'border-[#e0d9cc] focus:border-[#a47a3c]'"
              />
              <div v-if="form.errors.slug" class="text-rose-600 text-[11px] mt-1">{{ form.errors.slug }}</div>
            </div>

            <!-- Subtitle Tag -->
            <div>
              <label class="block text-xs font-semibold uppercase tracking-wider text-stone-700 mb-1.5">
                Eyebrow Subtitle Tag
              </label>
              <input
                type="text"
                v-model="form.subtitle_tag"
                placeholder="e.g. CHEF-CRAFTED · DINNER IN 20 MINS"
                class="w-full px-3.5 py-2.5 bg-[#fbfaf8] border rounded-2xl text-xs focus:bg-white focus:outline-none transition-all"
                :class="form.errors.subtitle_tag ? 'border-rose-400 focus:border-rose-500' : 'border-[#e0d9cc] focus:border-[#a47a3c]'"
              />
              <div v-if="form.errors.subtitle_tag" class="text-rose-600 text-[11px] mt-1">{{ form.errors.subtitle_tag }}</div>
            </div>

            <!-- Cooking Time -->
            <div>
              <label class="block text-xs font-semibold uppercase tracking-wider text-stone-700 mb-1.5">
                Cooking Time
              </label>
              <div class="relative">
                <input
                  type="text"
                  v-model="form.cooking_time"
                  placeholder="e.g. 20 mins, 35 mins"
                  class="w-full pl-9 pr-3.5 py-2.5 bg-[#fbfaf8] border rounded-2xl text-xs focus:bg-white focus:outline-none transition-all"
                  :class="form.errors.cooking_time ? 'border-rose-400 focus:border-rose-500' : 'border-[#e0d9cc] focus:border-[#a47a3c]'"
                />
                <Clock class="w-4 h-4 text-stone-400 absolute left-3 top-3" />
              </div>
            </div>

            <!-- Servings -->
            <div>
              <label class="block text-xs font-semibold uppercase tracking-wider text-stone-700 mb-1.5">
                Servings
              </label>
              <div class="relative">
                <input
                  type="text"
                  v-model="form.servings"
                  placeholder="e.g. Serves 4, Feast for 6"
                  class="w-full pl-9 pr-3.5 py-2.5 bg-[#fbfaf8] border rounded-2xl text-xs focus:bg-white focus:outline-none transition-all"
                  :class="form.errors.servings ? 'border-rose-400 focus:border-rose-500' : 'border-[#e0d9cc] focus:border-[#a47a3c]'"
                />
                <Users class="w-4 h-4 text-stone-400 absolute left-3 top-3" />
              </div>
            </div>

            <!-- Difficulty Level -->
            <div>
              <label class="block text-xs font-semibold uppercase tracking-wider text-stone-700 mb-1.5">
                Difficulty Level
              </label>
              <select
                v-model="form.difficulty"
                class="w-full px-3.5 py-2.5 bg-[#fbfaf8] border border-[#e0d9cc] rounded-2xl text-xs focus:bg-white focus:border-[#a47a3c] focus:outline-none"
              >
                <option value="Easy">Easy (Beginner friendly)</option>
                <option value="Medium">Medium (Weekend special)</option>
                <option value="Chef-Level">Chef-Level (Multi-step dum cooking)</option>
              </select>
            </div>

            <!-- Status Toggle -->
            <div class="flex items-center">
              <label class="flex items-center gap-3 cursor-pointer select-none">
                <input 
                  type="checkbox" 
                  v-model="form.is_active" 
                  class="w-4 h-4 rounded text-[#a47a3c] focus:ring-[#a47a3c] cursor-pointer"
                />
                <div>
                  <span class="text-xs font-semibold text-stone-800">Publish Kit Immediately</span>
                  <p class="text-[11px] text-stone-500">Visible on storefront recipe kits grid.</p>
                </div>
              </label>
            </div>

            <!-- Description -->
            <div class="md:col-span-2">
              <label class="block text-xs font-semibold uppercase tracking-wider text-stone-700 mb-1.5">
                Recipe Kit Story & Description
              </label>
              <textarea
                v-model="form.description"
                rows="3"
                placeholder="Detailed summary of the recipe, the flavor profile, and what is included..."
                class="w-full px-3.5 py-2.5 bg-[#fbfaf8] border border-[#e0d9cc] rounded-2xl text-xs focus:bg-white focus:border-[#a47a3c] focus:outline-none transition-all resize-y"
              ></textarea>
            </div>
          </div>
        </div>

        <!-- Card 2: Interactive Linked Products for Group Buy -->
        <div class="bg-white border border-[#e0d9cc] rounded-3xl p-6 sm:p-8 shadow-2xs space-y-5">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-[#e0d9cc]/60 pb-4">
            <div>
              <div class="flex items-center gap-2">
                <span class="text-[11px] font-mono uppercase bg-[#7a5620] text-white px-2.5 py-0.5 rounded-full font-semibold">
                  Step 2 · Core Feature
                </span>
                <span class="text-xs font-semibold text-emerald-800 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-full">
                  Group Buy / Bundle Linking
                </span>
              </div>
              <h2 class="text-xl font-serif font-medium text-[#1d1d1f] mt-1.5">
                Linked Catalog Ingredients (Group Buy)
              </h2>
              <p class="text-xs text-[#6e6e73]">
                Connect individual store products into this kit. When customers order this kit, all linked products are bundled in one tap with bundle discount.
              </p>
            </div>

            <!-- Add Product Selector -->
            <div class="flex items-center gap-2 shrink-0">
              <select
                v-model="selectedProductToAdd"
                class="px-3 py-2 bg-[#fbfaf8] border border-[#e0d9cc] rounded-xl text-xs focus:outline-none focus:border-[#a47a3c] max-w-[220px]"
              >
                <option value="" disabled>Select catalog product...</option>
                <option 
                  v-for="p in availableProducts" 
                  :key="p.id" 
                  :value="p.id"
                >
                  {{ p.name }} (${{ Number(p.price).toFixed(2) }})
                </option>
              </select>

              <button
                type="button"
                @click="addProductToKit"
                :disabled="!selectedProductToAdd"
                class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#1a1a1a] hover:bg-black text-white text-xs font-semibold rounded-xl disabled:opacity-40 transition-colors cursor-pointer"
              >
                <Plus class="w-3.5 h-3.5 stroke-[2.5]" />
                <span>Link Product</span>
              </button>
            </div>
          </div>

          <!-- Empty State -->
          <div 
            v-if="form.products.length === 0" 
            class="p-8 border-2 border-dashed border-[#e0d9cc] rounded-2xl text-center bg-[#fbfaf8]"
          >
            <PackagePlus class="w-8 h-8 text-stone-400 mx-auto mb-2" />
            <div class="text-xs font-semibold text-stone-800">No products linked yet</div>
            <p class="text-[11px] text-stone-500 max-w-sm mx-auto mt-0.5">
              Select products from the catalog above (e.g. Malai Paneer, Desi Ghee, Garam Masala) to link them to this recipe kit.
            </p>
          </div>

          <!-- Linked Products Table -->
          <div v-else class="space-y-3">
            <div 
              v-for="(item, idx) in form.products" 
              :key="item.product_id"
              class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-4 rounded-2xl bg-[#fbfaf8] border border-[#e0d9cc] hover:border-[#a47a3c]/60 transition-colors"
            >
              <!-- Left: Image & Name -->
              <div class="flex items-center gap-3 min-w-0 flex-1">
                <img 
                  :src="item.image || '/images/products/paneer_curry.jpg'" 
                  :alt="item.name"
                  class="w-12 h-12 rounded-xl object-cover border border-[#e0d9cc]/60 shrink-0" 
                />
                <div class="min-w-0">
                  <div class="text-xs font-semibold text-[#1d1d1f] truncate">
                    {{ item.name }}
                  </div>
                  <div class="text-[11px] text-[#86868b]">
                    Catalog Price: ${{ Number(item.price).toFixed(2) }} {{ item.size_main ? '· ' + item.size_main : '' }}
                  </div>
                </div>
              </div>

              <!-- Middle: Qty, Unit notes & Optional Checkbox -->
              <div class="flex items-center gap-3 flex-wrap">
                <!-- Qty -->
                <div class="flex items-center gap-1.5">
                  <span class="text-[10px] font-mono uppercase text-stone-500">Qty:</span>
                  <input
                    type="number"
                    v-model.number="item.quantity"
                    min="1"
                    max="99"
                    class="w-14 px-2 py-1 bg-white border border-[#e0d9cc] rounded-lg text-xs text-center font-bold focus:outline-none focus:border-[#a47a3c]"
                  />
                </div>

                <!-- Unit preparation notes -->
                <div class="flex-1 min-w-[160px]">
                  <input
                    type="text"
                    v-model="item.unit_notes"
                    placeholder="e.g. 400g cubed, for gravy"
                    class="w-full px-2.5 py-1 bg-white border border-[#e0d9cc] rounded-lg text-xs placeholder:text-stone-400 focus:outline-none focus:border-[#a47a3c]"
                  />
                </div>

                <!-- Optional toggle -->
                <label class="inline-flex items-center gap-1.5 text-[11px] text-stone-600 cursor-pointer select-none">
                  <input 
                    type="checkbox" 
                    v-model="item.is_optional" 
                    class="w-3.5 h-3.5 rounded text-[#a47a3c] focus:ring-[#a47a3c]"
                  />
                  <span>Optional</span>
                </label>

                <!-- Subtotal -->
                <div class="text-xs font-mono font-bold text-stone-800 min-w-[60px] text-right">
                  ${{ (Number(item.price) * Number(item.quantity)).toFixed(2) }}
                </div>

                <!-- Remove Button -->
                <button
                  type="button"
                  @click="removeProductFromKit(idx)"
                  class="p-1.5 text-stone-400 hover:text-rose-600 rounded-lg hover:bg-rose-50 transition-colors cursor-pointer"
                  title="Remove product from kit"
                >
                  <Trash2 class="w-4 h-4" />
                </button>
              </div>
            </div>

            <!-- Group Buy Bundle Summary Callout -->
            <div class="p-4 rounded-2xl bg-[#f5eee2] border border-[#e0d9cc] flex flex-col sm:flex-row sm:items-center justify-between gap-3">
              <div class="space-y-0.5">
                <div class="text-xs font-semibold text-[#7a5620] flex items-center gap-1.5">
                  <Sparkles class="w-4 h-4 text-[#a47a3c]" />
                  <span>Group Buy Bundle Calculation</span>
                </div>
                <div class="text-[11px] text-stone-600">
                  Total cost if {{ form.products.length }} ingredients are bought individually: 
                  <strong class="text-stone-900">${{ individualProductsTotal.toFixed(2) }}</strong>
                </div>
              </div>

              <button
                type="button"
                @click="copyIndividualTotalToOriginal"
                class="text-[11px] font-semibold text-[#7a5620] hover:text-[#523812] underline cursor-pointer shrink-0"
              >
                Use as Original Price ($)
              </button>
            </div>
          </div>
        </div>

        <!-- Card 3: Pricing & Bundle Discount -->
        <div class="bg-white border border-[#e0d9cc] rounded-3xl p-6 sm:p-8 shadow-2xs space-y-5">
          <div class="border-b border-[#e0d9cc]/60 pb-4">
            <span class="text-[11px] font-mono uppercase bg-[#1a1a1a] text-white px-2.5 py-0.5 rounded-full font-semibold">
              Step 3
            </span>
            <h2 class="text-xl font-serif font-medium text-[#1d1d1f] mt-2">
              Kit Bundle Pricing
            </h2>
            <p class="text-xs text-[#6e6e73] mt-0.5">
              Set the discounted bundle price customers pay for the complete recipe kit.
            </p>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
            <!-- Bundle Price -->
            <div>
              <label class="block text-xs font-semibold uppercase tracking-wider text-stone-700 mb-1.5">
                Bundle Price ($) <span class="text-rose-500">*</span>
              </label>
              <input
                type="number"
                step="0.01"
                v-model="form.price"
                placeholder="14.99"
                class="w-full px-3.5 py-2.5 bg-[#fbfaf8] border rounded-2xl text-base font-serif font-medium focus:bg-white focus:outline-none transition-all"
                :class="form.errors.price ? 'border-rose-400 focus:border-rose-500' : 'border-[#e0d9cc] focus:border-[#a47a3c]'"
                required
              />
              <div v-if="form.errors.price" class="text-rose-600 text-[11px] mt-1">{{ form.errors.price }}</div>
            </div>

            <!-- Original / Standalone Price -->
            <div>
              <label class="block text-xs font-semibold uppercase tracking-wider text-stone-700 mb-1.5">
                Original Standalone Price ($)
              </label>
              <input
                type="number"
                step="0.01"
                v-model="form.original_price"
                placeholder="18.99"
                class="w-full px-3.5 py-2.5 bg-[#fbfaf8] border rounded-2xl text-base font-serif font-medium focus:bg-white focus:outline-none transition-all"
                :class="form.errors.original_price ? 'border-rose-400 focus:border-rose-500' : 'border-[#e0d9cc] focus:border-[#a47a3c]'"
              />
              <div v-if="form.errors.original_price" class="text-rose-600 text-[11px] mt-1">{{ form.errors.original_price }}</div>
            </div>

            <!-- Live Savings Badge -->
            <div class="flex flex-col justify-center">
              <span class="text-xs font-semibold uppercase tracking-wider text-stone-700 mb-1.5">Customer Savings:</span>
              <div 
                v-if="bundleSavings > 0"
                class="p-2.5 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center gap-1.5"
              >
                <TrendingDown class="w-4 h-4" />
                <span>Save ${{ bundleSavings.toFixed(2) }} (Bundle Discount)</span>
              </div>
              <div v-else class="text-xs text-stone-400 italic">
                Set Bundle Price lower than Original Price to highlight savings badge.
              </div>
            </div>
          </div>
        </div>

        <!-- Card 4: Multi-Image Gallery -->
        <div class="bg-white border border-[#e0d9cc] rounded-3xl p-6 sm:p-8 shadow-2xs space-y-5">
          <div class="border-b border-[#e0d9cc]/60 pb-4">
            <span class="text-[11px] font-mono uppercase bg-[#1a1a1a] text-white px-2.5 py-0.5 rounded-full font-semibold">
              Step 4
            </span>
            <h2 class="text-xl font-serif font-medium text-[#1d1d1f] mt-2">
              Multi-Image Gallery
            </h2>
            <p class="text-xs text-[#6e6e73] mt-0.5">
              Upload multiple hero and detail photos for the dish and prepared meal.
            </p>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Upload Box -->
            <div 
              class="border-2 border-dashed border-[#e0d9cc] hover:border-[#a47a3c] rounded-2xl p-6 text-center flex flex-col items-center justify-center bg-[#fbfaf8] hover:bg-white transition-all cursor-pointer"
              @click="fileInputRef?.click()"
            >
              <Upload class="w-7 h-7 text-[#a47a3c] mb-2" />
              <span class="text-xs font-semibold text-stone-800">Upload Multiple Images</span>
              <span class="text-[11px] text-stone-500 mt-0.5">Select one or more JPG, PNG, WebP files</span>
              <input
                ref="fileInputRef"
                type="file"
                multiple
                accept="image/jpeg,image/png,image/webp,image/gif"
                class="hidden"
                @change="handleFilesSelected"
              />
            </div>

            <!-- Or Paste Image URL -->
            <div class="flex flex-col justify-center space-y-2 p-5 bg-[#fbfaf8] border border-[#e0d9cc] rounded-2xl">
              <span class="text-xs font-semibold text-stone-800">Add Image by URL or Path:</span>
              <div class="flex items-center gap-2">
                <input
                  type="text"
                  v-model="manualUrlInput"
                  @keydown.enter.prevent="addManualUrl"
                  placeholder="/images/products/paneer_curry.jpg"
                  class="flex-1 px-3 py-2 bg-white border border-[#e0d9cc] rounded-xl text-xs font-mono focus:outline-none focus:border-[#a47a3c]"
                />
                <button
                  type="button"
                  @click="addManualUrl"
                  class="px-3.5 py-2 bg-[#1a1a1a] hover:bg-black text-white text-xs font-semibold rounded-xl cursor-pointer"
                >
                  Add
                </button>
              </div>
              <span class="text-[10px] text-stone-500">Supports catalog paths or web URLs. First image serves as primary cover.</span>
            </div>
          </div>

          <!-- Preview Gallery Grid -->
          <div v-if="previewImages.length > 0" class="space-y-2 pt-2">
            <span class="text-xs font-semibold text-stone-700">Image Gallery Preview ({{ previewImages.length }}):</span>
            <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 gap-3">
              <div 
                v-for="(img, idx) in previewImages" 
                :key="idx"
                class="relative aspect-square rounded-xl overflow-hidden bg-stone-100 border border-[#e0d9cc] group shadow-2xs"
              >
                <img :src="img.url" :alt="img.name" class="w-full h-full object-cover" />
                <span v-if="idx === 0" class="absolute top-1.5 left-1.5 bg-[#a47a3c] text-white text-[9px] font-mono px-1.5 py-0.5 rounded shadow-xs">
                  Cover
                </span>
                <button
                  type="button"
                  @click="removePreviewImage(idx)"
                  class="absolute top-1.5 right-1.5 p-1 bg-black/70 hover:bg-rose-600 text-white rounded-lg transition-colors cursor-pointer"
                  title="Remove image"
                >
                  <X class="w-3.5 h-3.5" />
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Card 5: Recipe Steps -->
        <div class="bg-white border border-[#e0d9cc] rounded-3xl p-6 sm:p-8 shadow-2xs space-y-5">
          <div class="flex items-center justify-between border-b border-[#e0d9cc]/60 pb-4">
            <div>
              <span class="text-[11px] font-mono uppercase bg-[#1a1a1a] text-white px-2.5 py-0.5 rounded-full font-semibold">
                Step 5
              </span>
              <h2 class="text-xl font-serif font-medium text-[#1d1d1f] mt-2">
                Step-by-Step Cooking Instructions
              </h2>
              <p class="text-xs text-[#6e6e73] mt-0.5">
                Included in the digital recipe card on the kit page.
              </p>
            </div>

            <button
              type="button"
              @click="addRecipeStep"
              class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#f3efe7] hover:bg-[#ece7de] text-[#1d1d1f] text-xs font-semibold rounded-xl border border-[#e0d9cc] transition-colors cursor-pointer"
            >
              <Plus class="w-3.5 h-3.5 stroke-[2.5]" />
              <span>Add Step</span>
            </button>
          </div>

          <div class="space-y-3">
            <div 
              v-for="(step, idx) in form.recipe_steps" 
              :key="idx"
              class="flex items-center gap-3"
            >
              <span class="w-7 h-7 rounded-full bg-[#f5eee2] text-[#7a5620] border border-[#e0d9cc] flex items-center justify-center font-mono font-bold text-xs shrink-0">
                {{ idx + 1 }}
              </span>
              <input
                type="text"
                v-model="form.recipe_steps[idx]"
                placeholder="Describe this cooking step..."
                class="flex-1 px-3.5 py-2 bg-[#fbfaf8] border border-[#e0d9cc] rounded-xl text-xs focus:outline-none focus:bg-white focus:border-[#a47a3c]"
              />
              <button
                type="button"
                @click="removeRecipeStep(idx)"
                class="p-2 text-stone-400 hover:text-rose-600 transition-colors cursor-pointer"
                title="Remove step"
              >
                <X class="w-4 h-4" />
              </button>
            </div>
          </div>
        </div>

        <!-- Form Actions Bar -->
        <div class="bg-white border border-[#e0d9cc] rounded-3xl p-6 shadow-2xs flex items-center justify-between gap-4">
          <Link
            :href="route('admin.recipe-kits.index')"
            class="px-5 py-2.5 text-xs font-semibold text-stone-600 hover:text-stone-800 rounded-full hover:bg-stone-100 transition-colors"
          >
            Cancel
          </Link>

          <button
            type="submit"
            :disabled="form.processing"
            class="px-7 py-3 bg-[#1a1a1a] hover:bg-black text-white text-xs font-semibold rounded-full shadow-sm hover:shadow transition-all active:scale-98 cursor-pointer disabled:opacity-50"
          >
            {{ form.processing ? 'Publishing Kit...' : 'Publish Recipe Kit' }}
          </button>
        </div>

      </form>

    </div>
  </AdminLayout>
</template>
