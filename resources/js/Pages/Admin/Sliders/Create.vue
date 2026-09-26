<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { 
  ArrowLeft, 
  Upload, 
  ChevronRight, 
  Sparkles, 
  Image as ImageIcon,
  Check,
  Link as LinkIcon
} from 'lucide-vue-next';
import { Input } from '@/Components/ui/input';
import { Textarea } from '@/Components/ui/textarea';
import { Label } from '@/Components/ui/label';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';

const props = defineProps({
  products: {
    type: Array,
    default: () => [],
  },
  recipeKits: {
    type: Array,
    default: () => [],
  },
});

const imagePreview = ref('');
const fileInputRef = ref(null);

const bgPresets = [
  { label: 'Deep Charcoal', value: '#1a1a1a' },
  { label: 'Dark Coffee', value: '#221c17' },
  { label: 'Espresso', value: '#26201b' },
  { label: 'Midnight Navy', value: '#0f172a' },
  { label: 'Forest Green', value: '#064e3b' },
  { label: 'Royal Wine', value: '#4c0519' },
];

const form = useForm({
  title: 'Restaurant curry,\ncooked at\nhome.',
  tag: 'CHEF-CRAFTED · DINNER IN 20 MINS',
  cta_text: 'Order kit for $14.99',
  link_url: '/recipe-kits/paneer-curry',
  photo_label: 'paneer kit',
  bg_color: '#221c17',
  sort_order: 0,
  is_active: true,
  image_file: null,
  image_url: '/images/products/paneer_curry.jpg',
});

// Set default initial preview
imagePreview.value = form.image_url;

const handleFileSelected = (e) => {
  const file = e.target.files?.[0];
  if (!file) return;

  form.image_file = file;
  form.image_url = '';

  const reader = new FileReader();
  reader.onload = (event) => {
    imagePreview.value = event.target.result;
  };
  reader.readAsDataURL(file);
};

const handleUrlChanged = () => {
  if (form.image_url) {
    imagePreview.value = form.image_url;
    form.image_file = null;
    if (fileInputRef.value) fileInputRef.value.value = '';
  }
};

const setPresetBg = (color) => {
  form.bg_color = color;
};

const setQuickLink = (url) => {
  form.link_url = url;
};

const submit = () => {
  form.post(route('admin.sliders.store'), {
    forceFormData: true,
  });
};
</script>

<template>
  <AdminLayout title="Add Hero Slide">
    <div class="max-w-4xl mx-auto space-y-6">

      <!-- Breadcrumbs & Back Bar -->
      <div class="flex items-center justify-between">
        <Link 
          :href="route('admin.sliders.index')"
          class="inline-flex items-center gap-2 text-xs font-semibold text-stone-600 hover:text-stone-900 transition-colors"
        >
          <ArrowLeft class="w-4 h-4" />
          <span>Back to Sliders</span>
        </Link>
      </div>

      <!-- Live Interactive Visual Preview -->
      <div class="bg-white border border-[#e0d9cc] rounded-3xl p-6 sm:p-7 shadow-2xs space-y-3">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <span class="text-[11px] font-mono uppercase bg-[#a47a3c] text-white px-2.5 py-0.5 rounded-full font-semibold">
              Live Preview
            </span>
            <span class="text-xs text-[#6e6e73]">Exact appearance on the customer storefront hero carousel</span>
          </div>
        </div>

        <div 
          class="relative rounded-2xl sm:rounded-3xl overflow-hidden shadow-sm grid grid-cols-12 min-h-[180px] sm:min-h-[200px] transition-colors duration-300"
          :style="{ backgroundColor: form.bg_color || '#1a1a1a' }"
        >
          <!-- Copy Section -->
          <div class="col-span-7 p-4 sm:p-7 flex flex-col justify-between text-white select-none">
            <div>
              <div class="text-[9px] sm:text-[10px] font-semibold tracking-wider uppercase text-[#a47a3c] leading-tight mb-1">
                {{ form.tag || 'PROMOTION / TAGLINE' }}
              </div>
              <h1 class="text-xl sm:text-3xl lg:text-4xl font-serif font-medium leading-tight tracking-tight text-white whitespace-pre-line">
                {{ form.title || 'Headline Text Here' }}
              </h1>
            </div>

            <div>
              <span class="inline-flex items-center gap-1 text-xs font-semibold text-white mt-3">
                <span>{{ form.cta_text || 'Call to action' }}</span>
                <ChevronRight class="w-4 h-4" />
              </span>
            </div>
          </div>

          <!-- Photo Section -->
          <div class="col-span-5 relative bg-stone-900 overflow-hidden">
            <img 
              v-if="imagePreview"
              :src="imagePreview" 
              :alt="form.title" 
              class="w-full h-full object-cover object-center absolute inset-0"
            />
            <div v-else class="w-full h-full flex flex-col items-center justify-center text-stone-500 p-4 text-center">
              <ImageIcon class="w-8 h-8 mb-1" />
              <span class="text-[10px]">No image selected</span>
            </div>
            <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent pointer-events-none"></div>
            <div class="relative z-10 p-2.5 sm:p-3 h-full flex flex-col justify-end items-end pointer-events-none">
              <span v-if="form.photo_label" class="text-[10px] text-white/80 font-mono bg-black/40 px-1.5 py-0.5 rounded">
                {{ form.photo_label }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Main Form -->
      <form @submit.prevent="submit" class="bg-white border border-[#e0d9cc] rounded-3xl p-6 sm:p-8 shadow-2xs space-y-6">
        
        <!-- Header -->
        <div class="border-b border-[#e0d9cc]/60 pb-5">
          <h2 class="text-xl font-serif font-medium text-[#1d1d1f]">
            Slide Details & Settings
          </h2>
          <p class="text-xs text-[#6e6e73] mt-0.5">
            Configure headline copywriting, target URL, image, and appearance colors.
          </p>
        </div>

        <!-- Banner Content -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
          
          <!-- Title -->
          <div class="md:col-span-2">
            <Label class="mb-1.5 block">
              Headline Title <span class="text-rose-500">*</span>
            </Label>
            <Textarea
              v-model="form.title"
              rows="3"
              placeholder="e.g. Restaurant curry,&#10;cooked at&#10;home."
              class="font-serif text-sm"
              :class="{ 'border-rose-400 focus-visible:ring-rose-400': form.errors.title }"
              required
            />
            <div class="flex items-center justify-between mt-1 text-[11px] text-[#86868b]">
              <span>Tip: Hit Enter for line breaks to format multi-line editorial typography.</span>
              <span v-if="form.errors.title" class="text-rose-600 font-medium">{{ form.errors.title }}</span>
            </div>
          </div>

          <!-- Tagline -->
          <div>
            <Label class="mb-1.5 block">
              Eyebrow Tag / Subtitle
            </Label>
            <Input
              type="text"
              v-model="form.tag"
              placeholder="e.g. CHEF-CRAFTED · DINNER IN 20 MINS"
              :class="{ 'border-rose-400 focus-visible:ring-rose-400': form.errors.tag }"
            />
            <div v-if="form.errors.tag" class="text-rose-600 text-[11px] mt-1">{{ form.errors.tag }}</div>
          </div>

          <!-- CTA Text -->
          <div>
            <Label class="mb-1.5 block">
              Call to Action Button <span class="text-rose-500">*</span>
            </Label>
            <Input
              type="text"
              v-model="form.cta_text"
              placeholder="e.g. Order kit for $14.99"
              :class="{ 'border-rose-400 focus-visible:ring-rose-400': form.errors.cta_text }"
              required
            />
            <div v-if="form.errors.cta_text" class="text-rose-600 text-[11px] mt-1">{{ form.errors.cta_text }}</div>
          </div>

          <!-- Target Link URL -->
          <div class="md:col-span-2">
            <Label class="mb-1.5 block">
              Target Destination Link <span class="text-rose-500">*</span>
            </Label>
            <div class="relative">
              <Input
                type="text"
                v-model="form.link_url"
                placeholder="e.g. /recipe-kits/paneer-curry or /products/atta"
                class="pl-9 font-mono text-xs"
                :class="{ 'border-rose-400 focus-visible:ring-rose-400': form.errors.link_url }"
                required
              />
              <LinkIcon class="w-4 h-4 text-stone-400 absolute left-3 top-3" />
            </div>

            <!-- Quick link suggestions -->
            <div class="flex items-center gap-1.5 flex-wrap mt-2">
              <span class="text-[11px] text-[#86868b] mr-1">Quick pick:</span>
              <button
                type="button"
                v-for="kit in recipeKits.slice(0, 3)"
                :key="'kit-' + kit.id"
                @click="setQuickLink('/recipe-kits/' + kit.slug)"
                class="text-[10px] px-2 py-0.5 rounded-full bg-[#f5eee2] hover:bg-[#ebdcc8] text-[#7a5620] border border-[#e0d9cc] transition-colors cursor-pointer"
              >
                Recipe: {{ kit.name }}
              </button>
              <button
                type="button"
                v-for="p in products.slice(0, 3)"
                :key="'p-' + p.id"
                @click="setQuickLink('/products/' + p.slug)"
                class="text-[10px] px-2 py-0.5 rounded-full bg-stone-100 hover:bg-stone-200 text-stone-700 border border-stone-200 transition-colors cursor-pointer"
              >
                Product: {{ p.name }}
              </button>
            </div>
            <div v-if="form.errors.link_url" class="text-rose-600 text-[11px] mt-1">{{ form.errors.link_url }}</div>
          </div>

          <!-- Photo Label -->
          <div>
            <Label class="mb-1.5 block">
              Photo Corner Badge / Label
            </Label>
            <Input
              type="text"
              v-model="form.photo_label"
              placeholder="e.g. paneer kit, mithai box"
              :class="{ 'border-rose-400 focus-visible:ring-rose-400': form.errors.photo_label }"
            />
            <div v-if="form.errors.photo_label" class="text-rose-600 text-[11px] mt-1">{{ form.errors.photo_label }}</div>
          </div>

          <!-- Sort Order -->
          <div>
            <Label class="mb-1.5 block">
              Sort Order (0 = First Slide)
            </Label>
            <Input
              type="number"
              v-model.number="form.sort_order"
              min="0"
              :class="{ 'border-rose-400 focus-visible:ring-rose-400': form.errors.sort_order }"
            />
            <div v-if="form.errors.sort_order" class="text-rose-600 text-[11px] mt-1">{{ form.errors.sort_order }}</div>
          </div>

          <!-- Background Color -->
          <div class="md:col-span-2 space-y-2">
            <Label class="block">
              Slide Background Color
            </Label>
            <div class="flex items-center gap-2 flex-wrap">
              <button
                type="button"
                v-for="preset in bgPresets"
                :key="preset.value"
                @click="setPresetBg(preset.value)"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border text-xs font-medium transition-all cursor-pointer"
                :class="form.bg_color === preset.value ? 'ring-2 ring-[#a47a3c] border-stone-800 bg-stone-50' : 'border-[#e0d9cc] bg-white hover:bg-stone-50'"
              >
                <span class="w-3.5 h-3.5 rounded-full border border-stone-300" :style="{ backgroundColor: preset.value }"></span>
                <span>{{ preset.label }}</span>
              </button>
              <div class="flex items-center gap-1 ml-auto">
                <input 
                  type="color" 
                  v-model="form.bg_color" 
                  class="w-8 h-8 rounded-lg cursor-pointer border border-[#e0d9cc]" 
                />
                <Input
                  type="text"
                  v-model="form.bg_color"
                  placeholder="#1a1a1a"
                  class="w-24 font-mono text-xs h-8"
                />
              </div>
            </div>
          </div>

          <!-- Slide Image Uploader -->
          <div class="md:col-span-2 space-y-3 pt-2 border-t border-[#e0d9cc]/60">
            <div>
              <Label class="mb-1 block">
                Slide Image (Photo Section) <span class="text-rose-500">*</span>
              </Label>
              <p class="text-xs text-[#6e6e73]">
                Upload a high-resolution food photo or enter an existing image path.
              </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <!-- Upload Box -->
              <div 
                class="border-2 border-dashed border-[#e0d9cc] hover:border-[#a47a3c] rounded-2xl p-5 text-center flex flex-col items-center justify-center bg-[#fbfaf8] hover:bg-white transition-all cursor-pointer"
                @click="fileInputRef?.click()"
              >
                <Upload class="w-6 h-6 text-[#a47a3c] mb-2" />
                <span class="text-xs font-semibold text-stone-800">Upload Image File</span>
                <span class="text-[11px] text-stone-500 mt-0.5">JPG, PNG, WebP up to 10MB</span>
                <input
                  ref="fileInputRef"
                  type="file"
                  accept="image/jpeg,image/png,image/webp,image/gif"
                  class="hidden"
                  @change="handleFileSelected"
                />
              </div>

              <!-- Or Paste URL -->
              <div class="flex flex-col justify-center space-y-2 p-4 bg-[#fbfaf8] border border-[#e0d9cc] rounded-2xl">
                <span class="text-xs font-semibold text-stone-800">Or Image URL / Path:</span>
                <Input
                  type="text"
                  v-model="form.image_url"
                  @input="handleUrlChanged"
                  placeholder="/images/products/paneer_curry.jpg"
                  class="font-mono text-xs bg-white"
                />
                <span class="text-[10px] text-stone-500">You can also reference stored catalog assets.</span>
              </div>
            </div>
            <div v-if="form.errors.image_file || form.errors.image" class="text-rose-600 text-xs">
              {{ form.errors.image_file || form.errors.image }}
            </div>
          </div>

          <!-- Active Toggle -->
          <div class="md:col-span-2 pt-2 border-t border-[#e0d9cc]/60">
            <label class="flex items-center gap-3 cursor-pointer select-none">
              <Checkbox 
                :checked="form.is_active" 
                @update:checked="form.is_active = $event" 
              />
              <div>
                <span class="text-xs font-semibold text-stone-800">Active on Storefront</span>
                <p class="text-[11px] text-stone-500">When checked, this slide will rotate in the homepage hero carousel.</p>
              </div>
            </label>
          </div>

        </div>

        <!-- Form Actions -->
        <div class="pt-5 border-t border-[#e0d9cc]/60 flex items-center justify-end gap-3">
          <Link
            :href="route('admin.sliders.index')"
            class="px-5 py-2.5 text-xs font-semibold text-stone-600 hover:text-stone-800 rounded-full hover:bg-stone-100 transition-colors"
          >
            Cancel
          </Link>
          <Button
            type="submit"
            :disabled="form.processing"
            class="rounded-full px-6 py-2.5 text-xs font-semibold"
          >
            {{ form.processing ? 'Creating Slide...' : 'Save & Publish Slide' }}
          </Button>
        </div>

      </form>

    </div>
  </AdminLayout>
</template>
