<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { 
  Plus, 
  Sliders, 
  Edit, 
  Trash2, 
  ExternalLink, 
  Eye, 
  EyeOff, 
  ArrowUpDown, 
  Sparkles,
  ChevronRight,
  Image as ImageIcon
} from 'lucide-vue-next';

const props = defineProps({
  sliders: {
    type: Array,
    required: true,
  },
});

const deletingSlider = ref(null);
const isDeleting = ref(false);

const confirmDelete = (slider) => {
  deletingSlider.value = slider;
};

const handleDelete = () => {
  if (!deletingSlider.value) return;
  isDeleting.value = true;
  router.delete(route('admin.sliders.destroy', deletingSlider.value.id), {
    onFinish: () => {
      isDeleting.value = false;
      deletingSlider.value = null;
    },
  });
};
</script>

<template>
  <AdminLayout title="Hero Banner Sliders">
    <div class="space-y-6">

      <!-- Header & Quick Actions -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white border border-[#e0d9cc] rounded-3xl p-6 sm:p-7 shadow-2xs">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <span class="text-[11px] font-mono uppercase bg-[#1a1a1a] text-white px-2.5 py-0.5 rounded-full font-semibold">
              Hero Section
            </span>
            <span class="text-[11px] text-[#7a5620] bg-[#f5eee2] border border-[#e0d9cc] px-2.5 py-0.5 rounded-full font-medium">
              Live Homepage Carousel
            </span>
          </div>
          <h1 class="text-2xl sm:text-3xl font-serif font-medium text-[#1d1d1f] tracking-tight">
            Homepage Hero Sliders
          </h1>
          <p class="text-xs sm:text-sm text-[#6e6e73] font-normal mt-1">
            Configure headline banners, seasonal announcements, recipe highlights, and call-to-actions shown on the customer storefront.
          </p>
        </div>

        <div class="flex items-center gap-3 shrink-0">
          <a
            href="/"
            target="_blank"
            class="inline-flex items-center gap-2 px-4 py-2.5 bg-[#f3efe7] hover:bg-[#ece7de] text-[#1d1d1f] border border-[#e0d9cc] rounded-full text-xs font-semibold transition-colors shadow-2xs"
          >
            <Eye class="w-4 h-4 text-[#a47a3c]" />
            <span>Preview Storefront</span>
            <ExternalLink class="w-3.5 h-3.5 text-[#86868b]" />
          </a>

          <Link
            :href="route('admin.sliders.create')"
            class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#1a1a1a] hover:bg-black text-white text-xs font-semibold rounded-full shadow-sm hover:shadow transition-all active:scale-98 cursor-pointer"
          >
            <Plus class="w-4 h-4 stroke-[2.5]" />
            <span>Add Slide</span>
          </Link>
        </div>
      </div>

      <!-- Slides List -->
      <div v-if="sliders.length === 0" class="bg-white border border-[#e0d9cc] rounded-3xl p-12 text-center shadow-2xs">
        <div class="w-16 h-16 rounded-full bg-[#f5eee2] text-[#a47a3c] flex items-center justify-center mx-auto mb-4">
          <Sliders class="w-8 h-8" />
        </div>
        <h3 class="text-lg font-serif font-medium text-[#1d1d1f]">No hero slides configured</h3>
        <p class="text-xs text-[#6e6e73] max-w-md mx-auto mt-1 mb-6">
          The storefront will display built-in default slides until you publish custom slides from this console.
        </p>
        <Link
          :href="route('admin.sliders.create')"
          class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#1a1a1a] hover:bg-black text-white text-xs font-semibold rounded-full shadow-sm transition-all"
        >
          <Plus class="w-4 h-4 stroke-[2.5]" />
          <span>Create First Slide</span>
        </Link>
      </div>

      <div v-else class="space-y-4">
        <div 
          v-for="(slide, index) in sliders" 
          :key="slide.id"
          class="bg-white border border-[#e0d9cc] rounded-3xl p-5 sm:p-6 shadow-2xs hover:border-[#a47a3c]/60 transition-all flex flex-col lg:flex-row lg:items-center justify-between gap-6"
        >
          <!-- Left: Visual Banner Miniature Preview -->
          <div class="w-full lg:w-[380px] shrink-0">
            <div 
              class="relative rounded-2xl overflow-hidden shadow-xs border border-white/10 grid grid-cols-12 min-h-[140px]"
              :style="{ backgroundColor: slide.bg_color || '#1a1a1a' }"
            >
              <!-- Copy Preview -->
              <div class="col-span-7 p-3.5 flex flex-col justify-between text-white select-none">
                <div>
                  <div class="text-[8px] font-semibold tracking-wider uppercase text-[#a47a3c] leading-tight line-clamp-1 mb-0.5">
                    {{ slide.tag || 'FEATURED' }}
                  </div>
                  <h4 class="text-sm font-serif font-medium leading-tight line-clamp-3 whitespace-pre-line">
                    {{ slide.title }}
                  </h4>
                </div>
                <div class="inline-flex items-center gap-1 text-[10px] font-semibold text-white/90 mt-2">
                  <span>{{ slide.cta_text }}</span>
                  <ChevronRight class="w-3 h-3" />
                </div>
              </div>

              <!-- Image Area -->
              <div class="col-span-5 relative bg-stone-900 overflow-hidden">
                <img 
                  :src="slide.image" 
                  :alt="slide.title" 
                  class="w-full h-full object-cover object-center absolute inset-0"
                />
                <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent"></div>
                <span v-if="slide.photo_label" class="absolute bottom-1.5 right-1.5 text-[8px] font-mono text-white/80 bg-black/50 px-1 rounded">
                  {{ slide.photo_label }}
                </span>
              </div>
            </div>
          </div>

          <!-- Middle: Metadata & Configuration -->
          <div class="flex-1 space-y-2 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="text-xs font-mono font-bold text-stone-500 bg-stone-100 px-2 py-0.5 rounded-md">
                Slide #{{ index + 1 }}
              </span>
              <span 
                class="text-[11px] font-semibold px-2.5 py-0.5 rounded-full"
                :class="slide.is_active ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : 'bg-stone-100 text-stone-600 border border-stone-200'"
              >
                {{ slide.is_active ? '● Active on Storefront' : '○ Disabled' }}
              </span>
              <span class="text-xs text-[#86868b] font-mono">
                Order: {{ slide.sort_order }}
              </span>
            </div>

            <div>
              <h3 class="font-serif font-medium text-base text-[#1d1d1f] truncate">
                {{ slide.title.replace(/\n/g, ' ') }}
              </h3>
              <p class="text-xs text-[#6e6e73] font-normal truncate mt-0.5">
                Target: <span class="font-mono text-stone-800">{{ slide.link_url }}</span>
              </p>
            </div>

            <div class="flex items-center gap-4 text-xs text-[#86868b] pt-1">
              <span class="inline-flex items-center gap-1.5">
                <span class="w-3 h-3 rounded-full border border-stone-300" :style="{ backgroundColor: slide.bg_color }"></span>
                <span>{{ slide.bg_color || '#1a1a1a' }}</span>
              </span>
              <span>·</span>
              <span>CTA: <strong class="text-stone-700">{{ slide.cta_text }}</strong></span>
            </div>
          </div>

          <!-- Right: Actions -->
          <div class="flex items-center gap-2 shrink-0 pt-2 lg:pt-0 border-t lg:border-t-0 border-[#e0d9cc]/60">
            <Link
              :href="route('admin.sliders.edit', slide.id)"
              class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-[#f3efe7] hover:bg-[#ece7de] text-[#1d1d1f] text-xs font-medium rounded-xl border border-[#e0d9cc] transition-colors cursor-pointer"
            >
              <Edit class="w-3.5 h-3.5 text-[#a47a3c]" />
              <span>Edit</span>
            </Link>

            <button
              type="button"
              @click="confirmDelete(slide)"
              class="inline-flex items-center gap-1.5 px-3.5 py-2 text-rose-600 hover:text-rose-700 hover:bg-rose-50 rounded-xl text-xs font-medium transition-colors cursor-pointer border border-transparent hover:border-rose-200"
              title="Delete Slide"
            >
              <Trash2 class="w-3.5 h-3.5" />
              <span>Delete</span>
            </button>
          </div>
        </div>
      </div>

    </div>

    <!-- Confirm Delete Modal -->
    <div 
      v-if="deletingSlider" 
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs"
    >
      <div class="bg-white rounded-3xl max-w-md w-full p-6 sm:p-7 space-y-4 shadow-2xl border border-[#e0d9cc]">
        <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center">
          <Trash2 class="w-6 h-6" />
        </div>
        <div>
          <h3 class="text-lg font-serif font-medium text-[#1d1d1f]">
            Delete Hero Slide?
          </h3>
          <p class="text-xs text-[#6e6e73] mt-1">
            Are you sure you want to remove slide <strong class="text-[#1d1d1f]">"{{ deletingSlider.title.replace(/\n/g, ' ') }}"</strong>? This slide will immediately be removed from the customer carousel.
          </p>
        </div>

        <div class="flex items-center justify-end gap-3 pt-3 border-t border-[#e0d9cc]/60">
          <button
            type="button"
            @click="deletingSlider = null"
            class="px-4 py-2 text-xs font-medium text-stone-600 hover:text-stone-800 rounded-full hover:bg-stone-100 transition-colors cursor-pointer"
          >
            Cancel
          </button>
          <button
            type="button"
            @click="handleDelete"
            :disabled="isDeleting"
            class="px-5 py-2 text-xs font-semibold text-white bg-rose-600 hover:bg-rose-700 rounded-full transition-colors cursor-pointer disabled:opacity-50"
          >
            {{ isDeleting ? 'Deleting...' : 'Confirm Delete' }}
          </button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
