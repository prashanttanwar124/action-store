<script setup>
import { ref, watch } from 'vue';
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
  ExternalLink
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
  product: {
    type: Object,
    required: true,
  },
  defaultCategories: {
    type: Object,
    default: () => ({}),
  },
});

const previewNewImages = ref([]);
const existingImages = ref([...(props.product.images || [])]);
const manualUrlInput = ref('');
const fileInputRef = ref(null);

const form = useForm({
  name: props.product.name || '',
  slug: props.product.slug || '',
  category: props.product.category || 'grocery',
  category_title: props.product.category_title || props.defaultCategories[props.product.category] || 'Pantry & Groceries',
  subtitle_tag: props.product.subtitle_tag || '',
  price: props.product.price !== undefined ? props.product.price : '',
  original_price: props.product.original_price || '',
  unit_price: props.product.unit_price || '',
  stock_badge: props.product.stock_badge || 'In Stock',
  photo_label: props.product.photo_label || '',
  size_main: props.product.size_main || '',
  size_sub: props.product.size_sub || '',
  freshness_line: props.product.freshness_line || '',
  description: props.product.description || '',
  buy_again: !!props.product.buy_again,
  has_subscription: !!props.product.has_subscription,
  existing_images: [...(props.product.images || [])],
  image_files: [],
  image_urls: [],
});

// Sync category title when category changes
watch(() => form.category, (newCat) => {
  if (props.defaultCategories[newCat]) {
    form.category_title = props.defaultCategories[newCat];
  }
});

const handleFilesSelected = (e) => {
  const files = Array.from(e.target.files || []);
  if (files.length === 0) return;

  for (const file of files) {
    form.image_files.push(file);

    const reader = new FileReader();
    reader.onload = (event) => {
      previewNewImages.value.push({
        type: 'file',
        file,
        url: event.target.result,
        name: file.name,
      });
    };
    reader.readAsDataURL(file);
  }

  if (fileInputRef.value) {
    fileInputRef.value.value = '';
  }
};

const addManualUrl = () => {
  const url = manualUrlInput.value.trim();
  if (!url) return;

  form.image_urls.push(url);
  previewNewImages.value.push({
    type: 'url',
    url,
    name: url.split('/').pop() || 'External URL',
  });

  manualUrlInput.value = '';
};

const removeExistingImage = (index) => {
  existingImages.value.splice(index, 1);
  form.existing_images = [...existingImages.value];
};

const removeNewPreviewImage = (index) => {
  const item = previewNewImages.value[index];
  if (!item) return;

  if (item.type === 'file') {
    const fileIndex = form.image_files.indexOf(item.file);
    if (fileIndex !== -1) {
      form.image_files.splice(fileIndex, 1);
    }
  } else if (item.type === 'url') {
    const urlIndex = form.image_urls.indexOf(item.url);
    if (urlIndex !== -1) {
      form.image_urls.splice(urlIndex, 1);
    }
  }

  previewNewImages.value.splice(index, 1);
};

const submit = () => {
  form.existing_images = [...existingImages.value];
  form.transform((data) => ({
    ...data,
    _method: 'PUT',
  })).post(route('admin.products.update', props.product.id), {
    forceFormData: true,
  });
};
</script>

<template>
  <AdminLayout :title="`Edit ${product.name}`">
    <div class="max-w-4xl mx-auto space-y-6">

      <!-- Breadcrumbs & Back Bar -->
      <div class="flex items-center justify-between">
        <Link 
          :href="route('admin.products.index')"
          class="inline-flex items-center gap-1.5 text-xs font-semibold text-stone-600 hover:text-stone-900 transition-colors"
        >
          <ArrowLeft class="w-4 h-4" />
          <span>Back to Products</span>
        </Link>

        <!-- View on Storefront -->
        <a 
          :href="`/products/${product.slug}`" 
          target="_blank"
          class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium text-stone-700 hover:text-stone-950 bg-white border border-[#e0d9cc] shadow-2xs"
        >
          <span>View on Storefront</span>
          <ExternalLink class="w-3.5 h-3.5" />
        </a>
      </div>

      <!-- Page Title -->
      <div class="bg-white border border-[#e0d9cc] rounded-3xl p-6 sm:p-7 shadow-2xs">
        <div class="flex items-center gap-2 mb-1">
          <span class="text-[11px] font-mono uppercase bg-[#1a1a1a] text-white px-2.5 py-0.5 rounded-full font-semibold">
            Catalog Management
          </span>
          <span class="text-[11px] text-[#7a5620] bg-[#f5eee2] border border-[#e0d9cc] px-2.5 py-0.5 rounded-full font-medium">
            Product ID #{{ product.id }}
          </span>
        </div>
        <h1 class="text-2xl sm:text-3xl font-serif font-medium text-[#1d1d1f] tracking-tight">
          Edit {{ product.name }}
        </h1>
        <p class="text-xs sm:text-sm text-[#6e6e73] mt-0.5">
          Update prices, stock levels, packaging attributes, and manage multiple photo gallery images.
        </p>
      </div>

      <form @submit.prevent="submit" class="space-y-6">

        <!-- 1. Basic Information -->
        <div class="bg-white border border-[#e0d9cc] rounded-3xl p-6 sm:p-7 shadow-2xs space-y-5">
          <h2 class="text-base font-serif font-medium text-[#1d1d1f] border-b border-[#e0d9cc]/60 pb-3 flex items-center gap-2">
            <span>1. Basic Information</span>
          </h2>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Product Name -->
            <div class="sm:col-span-2">
              <Label class="block mb-1">
                Product Title <span class="text-red-500">*</span>
              </Label>
              <Input 
                v-model="form.name"
                type="text"
                required
                class="w-full bg-[#faf8f5] rounded-xl text-xs"
                :class="{ 'border-rose-400 focus-visible:ring-rose-500': form.errors.name }"
              />
              <p v-if="form.errors.name" class="text-xs text-red-600 mt-1">{{ form.errors.name }}</p>
            </div>

            <!-- Slug -->
            <div>
              <Label class="block mb-1">
                URL Slug <span class="text-red-500">*</span>
              </Label>
              <Input 
                v-model="form.slug"
                type="text"
                required
                class="w-full bg-[#faf8f5] rounded-xl text-xs font-mono"
                :class="{ 'border-rose-400 focus-visible:ring-rose-500': form.errors.slug }"
              />
              <p class="text-[10px] text-[#86868b] mt-1 font-mono">
                Storefront route: /products/{{ form.slug }}
              </p>
              <p v-if="form.errors.slug" class="text-xs text-red-600 mt-1">{{ form.errors.slug }}</p>
            </div>

            <!-- Subtitle Tag -->
            <div>
              <Label class="block mb-1">
                Subtitle Tag
              </Label>
              <Input 
                v-model="form.subtitle_tag"
                type="text"
                placeholder="e.g. Fresh Dairy · Rich & Soft"
                class="w-full bg-[#faf8f5] rounded-xl text-xs"
              />
              <p v-if="form.errors.subtitle_tag" class="text-xs text-red-600 mt-1">{{ form.errors.subtitle_tag }}</p>
            </div>

            <!-- Category -->
            <div>
              <Label class="block mb-1">
                Category <span class="text-red-500">*</span>
              </Label>
              <Select v-model="form.category">
                <SelectTrigger class="w-full h-10 bg-[#faf8f5] rounded-xl text-xs">
                  <SelectValue placeholder="Select category..." />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="grocery">Pantry & Groceries (grocery)</SelectItem>
                  <SelectItem value="spices">Fresh Spices & Masalas (spices)</SelectItem>
                  <SelectItem value="dairy">Dairy, Paneer & Ghee (dairy)</SelectItem>
                  <SelectItem value="sweets">Indian Sweets & Snacks (sweets)</SelectItem>
                  <SelectItem value="vegetables">Fresh Herbs & Produce (vegetables)</SelectItem>
                  <SelectItem value="recipe-kits">Artisanal Meal Kits (recipe-kits)</SelectItem>
                </SelectContent>
              </Select>
              <p v-if="form.errors.category" class="text-xs text-red-600 mt-1">{{ form.errors.category }}</p>
            </div>

            <!-- Category Title -->
            <div>
              <Label class="block mb-1">
                Category Display Title
              </Label>
              <Input 
                v-model="form.category_title"
                type="text"
                class="w-full bg-[#faf8f5] rounded-xl text-xs"
              />
            </div>
          </div>
        </div>

        <!-- 2. Multi-Image Gallery Management -->
        <div class="bg-white border border-[#e0d9cc] rounded-3xl p-6 sm:p-7 shadow-2xs space-y-5">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-[#e0d9cc]/60 pb-3">
            <div>
              <h2 class="text-base font-serif font-medium text-[#1d1d1f] flex items-center gap-2">
                <span>2. Multi-Image Gallery</span>
              </h2>
              <p class="text-xs text-[#6e6e73]">
                Manage current gallery photos or upload new high-resolution images.
              </p>
            </div>
            <span class="text-[11px] font-mono text-[#7a5620] bg-[#f5eee2] px-2.5 py-0.5 rounded-full border border-[#e0d9cc] self-start sm:self-auto">
              {{ existingImages.length + previewNewImages.length }} total images
            </span>
          </div>

          <!-- Existing Images Gallery -->
          <div v-if="existingImages.length > 0" class="space-y-2">
            <div class="text-[11px] font-semibold uppercase tracking-wider text-[#6e6e73]">
              Current Active Images:
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
              <div 
                v-for="(img, idx) in existingImages" 
                :key="idx"
                class="relative aspect-square rounded-2xl bg-stone-100 border border-[#e0d9cc] overflow-hidden group shadow-2xs"
              >
                <img 
                  :src="img" 
                  class="w-full h-full object-cover" 
                  @error="$event.target.src = '/images/products/atta.jpg'"
                />
                <span 
                  v-if="idx === 0" 
                  class="absolute top-2 left-2 text-[9px] font-mono uppercase bg-[#1a1a1a] text-white px-2 py-0.5 rounded-full font-semibold shadow-xs"
                >
                  Primary Cover
                </span>
                <button 
                  type="button"
                  @click="removeExistingImage(idx)"
                  class="absolute top-2 right-2 w-6 h-6 rounded-full bg-black/75 hover:bg-rose-600 text-white flex items-center justify-center transition-colors cursor-pointer"
                  title="Remove this photo"
                >
                  <X class="w-3.5 h-3.5" />
                </button>
                <div class="absolute bottom-0 inset-x-0 bg-black/60 backdrop-blur-xs text-white text-[9px] font-mono px-2 py-1 truncate">
                  {{ img }}
                </div>
              </div>
            </div>
          </div>

          <!-- Drag and Drop to Upload Additional Images -->
          <div 
            @click="fileInputRef.click()"
            class="border-2 border-dashed border-[#e0d9cc] hover:border-[#a47a3c] rounded-2xl p-6 text-center cursor-pointer transition-colors bg-[#faf8f5]/60 hover:bg-[#fbf9f5] group"
          >
            <input 
              ref="fileInputRef"
              type="file" 
              multiple 
              accept="image/*"
              class="hidden" 
              @change="handleFilesSelected"
            />
            <div class="w-12 h-12 rounded-full bg-[#f3efe7] text-[#a47a3c] flex items-center justify-center mx-auto mb-2 group-hover:scale-105 transition-transform">
              <Upload class="w-5 h-5" />
            </div>
            <div class="text-xs font-semibold text-[#1d1d1f]">
              Upload more images
            </div>
            <p class="text-[11px] text-[#86868b] mt-0.5">
              Select multiple files (JPEG, PNG, WEBP up to 10MB each)
            </p>
          </div>

          <!-- Add image by URL -->
          <div class="flex items-center gap-2 pt-1">
            <div class="relative flex-1">
              <LinkIcon class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-[#86868b] pointer-events-none" />
              <Input 
                v-model="manualUrlInput"
                type="text" 
                placeholder="Or paste an additional image URL / path..."
                class="w-full pl-8 pr-3 h-9 text-xs bg-[#faf8f5] rounded-xl"
                @keydown.enter.prevent="addManualUrl"
              />
            </div>
            <Button 
              type="button"
              variant="outline"
              size="sm"
              @click="addManualUrl"
              class="h-9 px-3.5 bg-[#f3efe7] hover:bg-[#ece7de] text-[#1d1d1f] text-xs font-medium rounded-xl border-[#e0d9cc] cursor-pointer shrink-0"
            >
              Add URL
            </Button>
          </div>

          <!-- New Images Previews (To be uploaded on save) -->
          <div v-if="previewNewImages.length > 0" class="pt-2 space-y-2">
            <div class="text-[11px] font-semibold uppercase tracking-wider text-emerald-700 flex items-center gap-1.5">
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
              <span>New Images to be added:</span>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
              <div 
                v-for="(item, index) in previewNewImages" 
                :key="index"
                class="relative aspect-square rounded-2xl bg-stone-100 border border-emerald-300 overflow-hidden group shadow-2xs"
              >
                <img 
                  :src="item.url" 
                  class="w-full h-full object-cover" 
                  :alt="item.name"
                />
                <button 
                  type="button"
                  @click="removeNewPreviewImage(index)"
                  class="absolute top-2 right-2 w-6 h-6 rounded-full bg-black/75 hover:bg-rose-600 text-white flex items-center justify-center transition-colors cursor-pointer"
                  title="Remove this new image"
                >
                  <X class="w-3.5 h-3.5" />
                </button>
                <div class="absolute bottom-0 inset-x-0 bg-emerald-950/80 text-emerald-100 text-[9px] font-mono px-2 py-1 truncate">
                  {{ item.name }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- 3. Pricing, Packaging & Badges -->
        <div class="bg-white border border-[#e0d9cc] rounded-3xl p-6 sm:p-7 shadow-2xs space-y-5">
          <h2 class="text-base font-serif font-medium text-[#1d1d1f] border-b border-[#e0d9cc]/60 pb-3 flex items-center gap-2">
            <span>3. Pricing & Packaging</span>
          </h2>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <!-- Price -->
            <div>
              <Label class="block mb-1">
                Selling Price ($) <span class="text-red-500">*</span>
              </Label>
              <Input 
                v-model="form.price"
                type="number"
                step="0.01"
                min="0"
                required
                class="w-full bg-[#faf8f5] rounded-xl text-xs"
                :class="{ 'border-rose-400 focus-visible:ring-rose-500': form.errors.price }"
              />
              <p v-if="form.errors.price" class="text-xs text-red-600 mt-1">{{ form.errors.price }}</p>
            </div>

            <!-- Original Price -->
            <div>
              <Label class="block mb-1">
                MSRP / Strike Price ($)
              </Label>
              <Input 
                v-model="form.original_price"
                type="number"
                step="0.01"
                min="0"
                class="w-full bg-[#faf8f5] rounded-xl text-xs"
              />
            </div>

            <!-- Unit Price -->
            <div>
              <Label class="block mb-1">
                Unit Price Tag
              </Label>
              <Input 
                v-model="form.unit_price"
                type="text"
                placeholder="e.g. ($0.45/oz)"
                class="w-full bg-[#faf8f5] rounded-xl text-xs"
              />
            </div>

            <!-- Size Main -->
            <div>
              <Label class="block mb-1">
                Main Size / Weight
              </Label>
              <Input 
                v-model="form.size_main"
                type="text"
                placeholder="e.g. 400 g or 10 lb"
                class="w-full bg-[#faf8f5] rounded-xl text-xs"
              />
            </div>

            <!-- Size Sub -->
            <div>
              <Label class="block mb-1">
                Size Subtitle
              </Label>
              <Input 
                v-model="form.size_sub"
                type="text"
                placeholder="e.g. Family Pack"
                class="w-full bg-[#faf8f5] rounded-xl text-xs"
              />
            </div>

            <!-- Stock Badge -->
            <div>
              <Label class="block mb-1">
                Stock Status Badge
              </Label>
              <Input 
                v-model="form.stock_badge"
                type="text"
                placeholder="e.g. In Stock or Limited Stock"
                class="w-full bg-[#faf8f5] rounded-xl text-xs"
              />
            </div>
          </div>
        </div>

        <!-- 4. Freshness, Descriptions & Recipe Kit Specs -->
        <div class="bg-white border border-[#e0d9cc] rounded-3xl p-6 sm:p-7 shadow-2xs space-y-5">
          <h2 class="text-base font-serif font-medium text-[#1d1d1f] border-b border-[#e0d9cc]/60 pb-3 flex items-center gap-2">
            <span>4. Descriptions & Highlights</span>
          </h2>

          <div class="space-y-4">
            <!-- Freshness Line -->
            <div>
              <Label class="block mb-1">
                Freshness / Sourcing Line
              </Label>
              <Input 
                v-model="form.freshness_line"
                type="text"
                class="w-full bg-[#faf8f5] rounded-xl text-xs"
              />
            </div>

            <!-- Description -->
            <div>
              <Label class="block mb-1">
                Full Product Description
              </Label>
              <Textarea 
                v-model="form.description"
                rows="4"
                class="w-full bg-[#faf8f5] rounded-xl text-xs"
              />
            </div>

            <!-- Toggles for Options -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
              <label class="flex items-center gap-2.5 p-3 rounded-2xl border border-[#e0d9cc] bg-[#faf8f5] cursor-pointer hover:bg-stone-50 select-none">
                <Checkbox 
                  :checked="form.buy_again" 
                  @update:checked="form.buy_again = $event"
                />
                <div>
                  <div class="text-xs font-semibold text-[#1d1d1f]">Buy It Again</div>
                  <div class="text-[10px] text-[#86868b]">Shows in customer reorder carousel</div>
                </div>
              </label>

              <label class="flex items-center gap-2.5 p-3 rounded-2xl border border-[#e0d9cc] bg-[#faf8f5] cursor-pointer hover:bg-stone-50 select-none">
                <Checkbox 
                  :checked="form.has_subscription" 
                  @update:checked="form.has_subscription = $event"
                />
                <div>
                  <div class="text-xs font-semibold text-[#1d1d1f]">Subscribe & Save</div>
                  <div class="text-[10px] text-[#86868b]">Enables 5% recurring discount</div>
                </div>
              </label>
            </div>
          </div>
        </div>

        <!-- Submit & Actions Footer -->
        <div class="flex items-center justify-end gap-3 pt-2">
          <Link 
            :href="route('admin.products.index')"
            class="px-5 py-2.5 text-xs font-semibold text-[#6e6e73] hover:text-[#1d1d1f] transition-colors"
          >
            Cancel
          </Link>
          <Button 
            type="submit"
            :disabled="form.processing"
            class="px-7 py-2.5 bg-[#1a1a1a] hover:bg-black text-white text-xs font-semibold rounded-full shadow-xs transition-all cursor-pointer disabled:opacity-50 inline-flex items-center gap-2"
          >
            <span>{{ form.processing ? 'Saving Changes...' : 'Update Product' }}</span>
          </Button>
        </div>

      </form>

    </div>
  </AdminLayout>
</template>
