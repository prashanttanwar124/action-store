<script setup>
import { ref, computed } from 'vue';
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
import { Input } from '@/Components/ui/input';
import { Textarea } from '@/Components/ui/textarea';
import { Label } from '@/Components/ui/label';
import { Checkbox } from '@/Components/ui/checkbox';
import { Button } from '@/Components/ui/button';
import { 
  Select, 
  SelectTrigger, 
  SelectValue, 
  SelectContent, 
  SelectItem 
} from '@/Components/ui/select';

const props = defineProps({
  recipeKit: {
    type: Object,
    required: true,
  },
  availableProducts: {
    type: Array,
    default: () => [],
  },
});

const previewImages = ref([]);
const manualUrlInput = ref('');
const fileInputRef = ref(null);
const selectedProductToAdd = ref('');

// Prepopulate previewImages with existing images
if (props.recipeKit.images && props.recipeKit.images.length > 0) {
  props.recipeKit.images.forEach(img => {
    previewImages.value.push({
      type: 'existing',
      url: img,
      name: img.split('/').pop() || 'Image',
    });
  });
} else if (props.recipeKit.image) {
  previewImages.value.push({
    type: 'existing',
    url: props.recipeKit.image,
    name: 'Cover Image',
  });
}

const form = useForm({
  _method: 'PUT',
  name: props.recipeKit.name || '',
  slug: props.recipeKit.slug || '',
  subtitle_tag: props.recipeKit.subtitle_tag || '',
  description: props.recipeKit.description || '',
  servings: props.recipeKit.servings || 'Serves 4',
  cooking_time: props.recipeKit.cooking_time || '20 mins',
  difficulty: props.recipeKit.difficulty || 'Easy',
  price: props.recipeKit.price ?? '',
  original_price: props.recipeKit.original_price ?? '',
  is_active: Boolean(props.recipeKit.is_active),
  existing_images: previewImages.value.filter(i => i.type === 'existing').map(i => i.url),
  image_files: [],
  image_urls: [],
  products: (props.recipeKit.products || []).map(p => ({
    product_id: p.product_id,
    name: p.name,
    price: Number(p.price),
    image: p.image,
    quantity: Number(p.quantity) || 1,
    unit_notes: p.unit_notes || '',
    is_optional: Boolean(p.is_optional),
  })),
  recipe_steps: props.recipeKit.recipe_steps && props.recipeKit.recipe_steps.length > 0 
    ? [...props.recipeKit.recipe_steps] 
    : ['Saute ingredients', 'Simmer gravy', 'Serve hot with basmati rice'],
});

// Sync existing_images when previewImages changes
const syncExistingImages = () => {
  form.existing_images = previewImages.value.filter(i => i.type === 'existing').map(i => i.url);
};

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
  syncExistingImages();
};

// Linked Products Management for Group Buy
const addProductToKit = () => {
  if (!selectedProductToAdd.value) return;

  const product = props.availableProducts.find(p => p.id === Number(selectedProductToAdd.value));
  if (!product) return;

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

const copyIndividualTotalToOriginal = () => {
  if (individualProductsTotal.value > 0) {
    form.original_price = individualProductsTotal.value.toFixed(2);
  }
};

const submit = () => {
  syncExistingImages();
  form.post(route('admin.recipe-kits.update', props.recipeKit.id), {
    forceFormData: true,
  });
};
</script>

<template>
  <AdminLayout :title="'Edit Recipe Kit: ' + recipeKit.name">
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
              Edit Recipe Kit Information
            </h2>
            <p class="text-xs text-[#6e6e73] mt-0.5">
              Specify the dish name, banner tagline, cooking specs, and description.
            </p>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <!-- Kit Name -->
            <div class="md:col-span-2">
              <Label class="block mb-1.5">
                Recipe Kit Name <span class="text-rose-500">*</span>
              </Label>
              <Input
                type="text"
                v-model="form.name"
                class="w-full h-11 bg-[#fbfaf8] rounded-2xl text-sm font-serif"
                :class="{ 'border-rose-400 focus-visible:ring-rose-500': form.errors.name }"
                required
              />
              <div v-if="form.errors.name" class="text-rose-600 text-[11px] mt-1">{{ form.errors.name }}</div>
            </div>

            <!-- Slug -->
            <div>
              <Label class="block mb-1.5">
                URL Slug
              </Label>
              <Input
                type="text"
                v-model="form.slug"
                class="w-full h-10 bg-[#fbfaf8] rounded-2xl text-xs font-mono"
                :class="{ 'border-rose-400 focus-visible:ring-rose-500': form.errors.slug }"
              />
              <div v-if="form.errors.slug" class="text-rose-600 text-[11px] mt-1">{{ form.errors.slug }}</div>
            </div>

            <!-- Subtitle Tag -->
            <div>
              <Label class="block mb-1.5">
                Eyebrow Subtitle Tag
              </Label>
              <Input
                type="text"
                v-model="form.subtitle_tag"
                class="w-full h-10 bg-[#fbfaf8] rounded-2xl text-xs"
                :class="{ 'border-rose-400 focus-visible:ring-rose-500': form.errors.subtitle_tag }"
              />
              <div v-if="form.errors.subtitle_tag" class="text-rose-600 text-[11px] mt-1">{{ form.errors.subtitle_tag }}</div>
            </div>

            <!-- Cooking Time -->
            <div>
              <Label class="block mb-1.5">
                Cooking Time
              </Label>
              <div class="relative">
                <Input
                  type="text"
                  v-model="form.cooking_time"
                  placeholder="e.g. 25 mins"
                  class="w-full h-10 pl-9 bg-[#fbfaf8] rounded-2xl text-xs"
                  :class="{ 'border-rose-400 focus-visible:ring-rose-500': form.errors.cooking_time }"
                />
                <Clock class="w-4 h-4 text-stone-400 absolute left-3 top-3 pointer-events-none" />
              </div>
            </div>

            <!-- Servings -->
            <div>
              <Label class="block mb-1.5">
                Servings
              </Label>
              <div class="relative">
                <Input
                  type="text"
                  v-model="form.servings"
                  placeholder="e.g. Serves 4"
                  class="w-full h-10 pl-9 bg-[#fbfaf8] rounded-2xl text-xs"
                  :class="{ 'border-rose-400 focus-visible:ring-rose-500': form.errors.servings }"
                />
                <Users class="w-4 h-4 text-stone-400 absolute left-3 top-3 pointer-events-none" />
              </div>
            </div>

            <!-- Difficulty Level -->
            <div>
              <Label class="block mb-1.5">
                Difficulty Level
              </Label>
              <Select v-model="form.difficulty">
                <SelectTrigger class="w-full h-10 bg-[#fbfaf8] rounded-2xl">
                  <SelectValue placeholder="Select difficulty..." />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="Easy">Easy (Beginner friendly)</SelectItem>
                  <SelectItem value="Medium">Medium (Weekend special)</SelectItem>
                  <SelectItem value="Chef-Level">Chef-Level (Multi-step dum cooking)</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <!-- Status Toggle -->
            <div class="flex items-center pt-2">
              <label class="flex items-center gap-3 cursor-pointer select-none">
                <Checkbox
                  :checked="form.is_active"
                  @update:checked="form.is_active = $event"
                />
                <div>
                  <span class="text-xs font-semibold text-stone-800">Publish Kit Immediately</span>
                  <p class="text-[11px] text-stone-500">Visible on storefront recipe kits grid.</p>
                </div>
              </label>
            </div>

            <!-- Description -->
            <div class="md:col-span-2">
              <Label class="block mb-1.5">
                Recipe Kit Story & Description
              </Label>
              <Textarea
                v-model="form.description"
                rows="3"
                class="w-full bg-[#fbfaf8] rounded-2xl text-xs resize-y"
              />
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
              <Select v-model="selectedProductToAdd">
                <SelectTrigger class="w-[230px] h-9 bg-[#fbfaf8] rounded-xl text-xs">
                  <SelectValue placeholder="Select catalog product..." />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem 
                    v-for="p in availableProducts" 
                    :key="p.id" 
                    :value="String(p.id)"
                  >
                    {{ p.name }} (${{ Number(p.price).toFixed(2) }})
                  </SelectItem>
                </SelectContent>
              </Select>

              <Button
                type="button"
                @click="addProductToKit"
                :disabled="!selectedProductToAdd"
                size="sm"
                class="rounded-xl h-9 bg-[#1a1a1a] hover:bg-black text-white text-xs font-semibold gap-1.5 cursor-pointer disabled:opacity-40"
              >
                <Plus class="w-3.5 h-3.5 stroke-[2.5]" />
                <span>Link Product</span>
              </Button>
            </div>
          </div>

          <!-- Linked Products Table -->
          <div class="space-y-3">
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
                    Catalog Price: ${{ Number(item.price).toFixed(2) }}
                  </div>
                </div>
              </div>

              <!-- Middle: Qty, Unit notes & Optional Checkbox -->
              <div class="flex items-center gap-3 flex-wrap">
                <!-- Qty -->
                <div class="flex items-center gap-1.5">
                  <span class="text-[10px] font-mono uppercase text-stone-500">Qty:</span>
                  <Input
                    type="number"
                    v-model.number="item.quantity"
                    min="1"
                    max="99"
                    class="w-16 h-8 bg-white rounded-lg text-xs text-center font-bold px-1"
                  />
                </div>

                <!-- Unit preparation notes -->
                <div class="flex-1 min-w-[160px]">
                  <Input
                    type="text"
                    v-model="item.unit_notes"
                    placeholder="e.g. 400g cubed, for gravy"
                    class="w-full h-8 bg-white rounded-lg text-xs placeholder:text-stone-400"
                  />
                </div>

                <!-- Optional toggle -->
                <label class="inline-flex items-center gap-1.5 text-[11px] text-stone-600 cursor-pointer select-none">
                  <Checkbox 
                    :checked="item.is_optional" 
                    @update:checked="item.is_optional = $event"
                    class="h-3.5 w-3.5"
                  />
                  <span>Optional</span>
                </label>

                <!-- Subtotal -->
                <div class="text-xs font-mono font-bold text-stone-800 min-w-[60px] text-right">
                  ${{ (Number(item.price) * Number(item.quantity)).toFixed(2) }}
                </div>

                <!-- Remove Button -->
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  @click="removeProductFromKit(idx)"
                  class="h-8 w-8 text-stone-400 hover:text-rose-600 hover:bg-rose-50 cursor-pointer"
                  title="Remove product from kit"
                >
                  <Trash2 class="w-4 h-4" />
                </Button>
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
              <Label class="block mb-1.5">
                Bundle Price ($) <span class="text-rose-500">*</span>
              </Label>
              <Input
                type="number"
                step="0.01"
                v-model="form.price"
                class="w-full h-11 bg-[#fbfaf8] rounded-2xl text-base font-serif font-medium"
                :class="{ 'border-rose-400 focus-visible:ring-rose-500': form.errors.price }"
                required
              />
              <div v-if="form.errors.price" class="text-rose-600 text-[11px] mt-1">{{ form.errors.price }}</div>
            </div>

            <!-- Original / Standalone Price -->
            <div>
              <Label class="block mb-1.5">
                Original Standalone Price ($)
              </Label>
              <Input
                type="number"
                step="0.01"
                v-model="form.original_price"
                class="w-full h-11 bg-[#fbfaf8] rounded-2xl text-base font-serif font-medium"
                :class="{ 'border-rose-400 focus-visible:ring-rose-500': form.errors.original_price }"
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
              <span class="text-xs font-semibold text-stone-800">Upload Additional Images</span>
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
                <Input
                  type="text"
                  v-model="manualUrlInput"
                  @keydown.enter.prevent="addManualUrl"
                  placeholder="/images/products/paneer_curry.jpg"
                  class="flex-1 h-9 bg-white rounded-xl text-xs font-mono"
                />
                <Button
                  type="button"
                  @click="addManualUrl"
                  size="sm"
                  class="h-9 px-3.5 bg-[#1a1a1a] hover:bg-black text-white text-xs font-semibold rounded-xl cursor-pointer"
                >
                  Add
                </Button>
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

            <Button
              type="button"
              variant="outline"
              size="sm"
              @click="addRecipeStep"
              class="h-8 rounded-xl gap-1.5 bg-[#f3efe7] hover:bg-[#ece7de] text-[#1d1d1f] text-xs font-semibold border-[#e0d9cc] cursor-pointer"
            >
              <Plus class="w-3.5 h-3.5 stroke-[2.5]" />
              <span>Add Step</span>
            </Button>
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
              <Input
                type="text"
                v-model="form.recipe_steps[idx]"
                placeholder="Describe this cooking step..."
                class="flex-1 h-9 bg-[#fbfaf8] rounded-xl text-xs"
              />
              <Button
                type="button"
                variant="ghost"
                size="icon"
                @click="removeRecipeStep(idx)"
                class="h-8 w-8 text-stone-400 hover:text-rose-600 cursor-pointer"
                title="Remove step"
              >
                <X class="w-4 h-4" />
              </Button>
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

          <Button
            type="submit"
            :disabled="form.processing"
            class="h-11 px-7 bg-[#1a1a1a] hover:bg-black text-white text-xs font-semibold rounded-full shadow-sm hover:shadow transition-all active:scale-98 cursor-pointer disabled:opacity-50"
          >
            {{ form.processing ? 'Updating Kit...' : 'Save Changes' }}
          </Button>
        </div>

      </form>

    </div>
  </AdminLayout>
</template>
