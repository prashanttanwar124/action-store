<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { 
  Plus, 
  Search, 
  Edit, 
  Trash2, 
  ExternalLink, 
  Image as ImageIcon,
  Clock,
  Users,
  UtensilsCrossed,
  PackageCheck,
  Sparkles,
  ChevronLeft,
  ChevronRight,
  TrendingDown
} from 'lucide-vue-next';

const props = defineProps({
  recipeKits: {
    type: Object,
    required: true,
  },
  filters: {
    type: Object,
    default: () => ({ search: '' }),
  },
  stats: {
    type: Object,
    default: () => ({ total: 0, active: 0 }),
  },
});

const searchQuery = ref(props.filters.search || '');
const deletingKit = ref(null);
const isDeleting = ref(false);

let searchTimeout = null;
const handleSearchInput = () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    router.get(
      route('admin.recipe-kits.index'),
      { search: searchQuery.value || undefined },
      { preserveState: true, preserveScroll: true, replace: true }
    );
  }, 350);
};

const confirmDelete = (kit) => {
  deletingKit.value = kit;
};

const handleDelete = () => {
  if (!deletingKit.value) return;
  isDeleting.value = true;
  router.delete(route('admin.recipe-kits.destroy', deletingKit.value.id), {
    onFinish: () => {
      isDeleting.value = false;
      deletingKit.value = null;
    },
  });
};
</script>

<template>
  <AdminLayout title="Recipe Kits & Group Buy">
    <div class="space-y-6">

      <!-- Top Summary Header & Actions -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white border border-[#e0d9cc] rounded-3xl p-6 sm:p-7 shadow-2xs">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <span class="text-[11px] font-mono uppercase bg-[#1a1a1a] text-white px-2.5 py-0.5 rounded-full font-semibold">
              Chef Kits
            </span>
            <span class="text-[11px] text-[#7a5620] bg-[#f5eee2] border border-[#e0d9cc] px-2.5 py-0.5 rounded-full font-medium">
              Group Buy / Ingredient Bundling Active
            </span>
          </div>
          <h1 class="text-2xl sm:text-3xl font-serif font-medium text-[#1d1d1f] tracking-tight">
            Recipe Kits & Group Buy
          </h1>
          <p class="text-xs sm:text-sm text-[#6e6e73] font-normal mt-1">
            Curate dinner dish kits with pre-portioned ingredients linked directly from your catalog for 1-click group checkout and bundle discounts.
          </p>
        </div>

        <div class="flex items-center gap-3 shrink-0">
          <Link
            :href="route('admin.recipe-kits.create')"
            class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#1a1a1a] hover:bg-black text-white text-xs font-semibold rounded-full shadow-sm hover:shadow transition-all active:scale-98 cursor-pointer"
          >
            <Plus class="w-4 h-4 stroke-[2.5]" />
            <span>Create Recipe Kit</span>
          </Link>
        </div>
      </div>

      <!-- Quick Metrics Cards -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4">
        <div class="bg-white border border-[#e0d9cc] rounded-2xl p-4 shadow-2xs">
          <div class="text-[11px] font-mono text-[#86868b] uppercase tracking-wider">Total Kits</div>
          <div class="text-2xl font-serif font-medium text-[#1d1d1f] mt-1">{{ stats.total }}</div>
        </div>
        <div class="bg-white border border-[#e0d9cc] rounded-2xl p-4 shadow-2xs">
          <div class="text-[11px] font-mono text-emerald-700 uppercase tracking-wider">Active Published</div>
          <div class="text-2xl font-serif font-medium text-emerald-800 mt-1">{{ stats.active }}</div>
        </div>
        <div class="bg-white border border-[#e0d9cc] rounded-2xl p-4 shadow-2xs">
          <div class="text-[11px] font-mono text-[#7a5620] uppercase tracking-wider">Group Buy Bundles</div>
          <div class="text-2xl font-serif font-medium text-[#7a5620] mt-1">{{ stats.total }}</div>
        </div>
        <div class="bg-white border border-[#e0d9cc] rounded-2xl p-4 shadow-2xs">
          <div class="text-[11px] font-mono text-[#86868b] uppercase tracking-wider">Product Linking</div>
          <div class="text-xs font-semibold text-[#1d1d1f] mt-2 flex items-center gap-1.5">
            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
            <span>Live Catalog Sync</span>
          </div>
        </div>
      </div>

      <!-- Search & Filters -->
      <div class="bg-white border border-[#e0d9cc] rounded-2xl p-3 shadow-2xs flex items-center gap-3">
        <div class="relative flex-1">
          <Search class="w-4 h-4 text-stone-400 absolute left-3 top-3" />
          <input
            type="text"
            v-model="searchQuery"
            @input="handleSearchInput"
            placeholder="Search recipe kits by name, subtitle, or cooking tag..."
            class="w-full pl-9 pr-4 py-2 bg-[#fbfaf8] border border-[#e0d9cc] rounded-xl text-xs focus:outline-none focus:bg-white focus:border-[#a47a3c] transition-all"
          />
        </div>
      </div>

      <!-- Recipe Kits List -->
      <div v-if="recipeKits.data.length === 0" class="bg-white border border-[#e0d9cc] rounded-3xl p-12 text-center shadow-2xs">
        <div class="w-16 h-16 rounded-full bg-[#f5eee2] text-[#a47a3c] flex items-center justify-center mx-auto mb-4">
          <UtensilsCrossed class="w-8 h-8" />
        </div>
        <h3 class="text-lg font-serif font-medium text-[#1d1d1f]">No recipe kits found</h3>
        <p class="text-xs text-[#6e6e73] max-w-md mx-auto mt-1 mb-6">
          {{ searchQuery ? 'Try adjusting your search query.' : 'Create your first recipe kit with linked products for group buy.' }}
        </p>
        <Link
          :href="route('admin.recipe-kits.create')"
          class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#1a1a1a] hover:bg-black text-white text-xs font-semibold rounded-full shadow-sm transition-all"
        >
          <Plus class="w-4 h-4 stroke-[2.5]" />
          <span>Add New Recipe Kit</span>
        </Link>
      </div>

      <div v-else class="space-y-4">
        <div 
          v-for="kit in recipeKits.data" 
          :key="kit.id"
          class="bg-white border border-[#e0d9cc] rounded-3xl p-5 sm:p-6 shadow-2xs hover:border-[#a47a3c]/60 transition-all flex flex-col xl:flex-row xl:items-center justify-between gap-6"
        >
          <!-- Left: Thumbnail & Main Info -->
          <div class="flex items-start gap-4 sm:gap-5 min-w-0 flex-1">
            
            <!-- Thumbnail with Multi-Image count badge -->
            <div class="relative w-24 h-24 sm:w-28 sm:h-28 rounded-2xl overflow-hidden bg-stone-100 border border-[#e0d9cc]/60 shrink-0">
              <img 
                :src="kit.image" 
                :alt="kit.name" 
                class="w-full h-full object-cover object-center"
              />
              <span 
                v-if="kit.images_count > 1"
                class="absolute bottom-1 right-1 bg-black/75 text-white text-[9px] font-mono font-semibold px-1.5 py-0.5 rounded-md backdrop-blur-xs"
              >
                +{{ kit.images_count - 1 }}
              </span>
            </div>

            <!-- Details -->
            <div class="space-y-1.5 min-w-0 flex-1">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="text-[10px] font-mono uppercase bg-[#1a1a1a] text-white px-2 py-0.5 rounded-md font-semibold">
                  {{ kit.difficulty || 'Easy' }}
                </span>
                <span 
                  class="text-[10px] font-semibold px-2 py-0.5 rounded-full"
                  :class="kit.is_active ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : 'bg-stone-100 text-stone-600 border border-stone-200'"
                >
                  {{ kit.is_active ? '● Active' : '○ Draft' }}
                </span>
                <span v-if="kit.subtitle_tag" class="text-[10px] font-mono text-[#a47a3c] font-semibold truncate max-w-[200px]">
                  {{ kit.subtitle_tag }}
                </span>
              </div>

              <h3 class="font-serif font-medium text-lg sm:text-xl text-[#1d1d1f] truncate leading-tight">
                {{ kit.name }}
              </h3>

              <!-- Cooking Specs Row -->
              <div class="flex items-center gap-3 text-xs text-[#6e6e73] font-medium flex-wrap">
                <span class="inline-flex items-center gap-1">
                  <Clock class="w-3.5 h-3.5 text-[#a47a3c]" />
                  <span>{{ kit.cooking_time || '20 mins' }}</span>
                </span>
                <span>·</span>
                <span class="inline-flex items-center gap-1">
                  <Users class="w-3.5 h-3.5 text-[#a47a3c]" />
                  <span>{{ kit.servings || 'Serves 4' }}</span>
                </span>
                <span>·</span>
                <span class="inline-flex items-center gap-1 text-emerald-700 font-semibold">
                  <PackageCheck class="w-3.5 h-3.5" />
                  <span>{{ kit.products_count }} Linked Ingredients</span>
                </span>
              </div>

              <!-- Linked Products Group Buy Badges -->
              <div v-if="kit.products && kit.products.length > 0" class="flex items-center gap-1.5 flex-wrap pt-1">
                <span class="text-[11px] text-[#86868b] mr-1">Group Buy:</span>
                <span 
                  v-for="p in kit.products" 
                  :key="p.id"
                  class="inline-flex items-center gap-1 text-[11px] px-2 py-0.5 rounded-lg bg-[#f5eee2] text-[#7a5620] border border-[#e0d9cc]/80"
                  :title="p.name + (p.unit_notes ? ' (' + p.unit_notes + ')' : '')"
                >
                  <span class="font-semibold">{{ p.name }}</span>
                  <span v-if="p.quantity > 1" class="font-mono text-[10px] bg-[#a47a3c] text-white px-1 rounded-sm">x{{ p.quantity }}</span>
                </span>
              </div>
            </div>

          </div>

          <!-- Middle / Right: Pricing & Group Buy Savings -->
          <div class="flex items-center justify-between xl:justify-end gap-6 pt-3 xl:pt-0 border-t xl:border-t-0 border-[#e0d9cc]/60 shrink-0">
            
            <!-- Pricing breakdown -->
            <div class="text-left xl:text-right">
              <div class="flex items-baseline xl:justify-end gap-1.5">
                <span class="font-serif font-medium text-xl text-[#1d1d1f]">
                  ${{ Number(kit.price).toFixed(2) }}
                </span>
                <span v-if="kit.original_price" class="text-xs text-[#86868b] line-through">
                  ${{ Number(kit.original_price).toFixed(2) }}
                </span>
              </div>

              <div v-if="kit.original_price && kit.original_price > kit.price" class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-800 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-full mt-1">
                <TrendingDown class="w-3 h-3" />
                <span>Save ${{ (Number(kit.original_price) - Number(kit.price)).toFixed(2) }} (Group Buy)</span>
              </div>
              <div v-else-if="kit.individual_total > kit.price" class="text-[11px] font-semibold text-[#7a5620] mt-1">
                Individual sum: ${{ Number(kit.individual_total).toFixed(2) }}
              </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-2">
              <Link
                :href="route('admin.recipe-kits.edit', kit.id)"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-[#f3efe7] hover:bg-[#ece7de] text-[#1d1d1f] text-xs font-semibold rounded-xl border border-[#e0d9cc] transition-colors cursor-pointer"
              >
                <Edit class="w-3.5 h-3.5 text-[#a47a3c]" />
                <span>Edit</span>
              </Link>

              <button
                type="button"
                @click="confirmDelete(kit)"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 text-rose-600 hover:text-rose-700 hover:bg-rose-50 rounded-xl text-xs font-semibold transition-colors cursor-pointer border border-transparent hover:border-rose-200"
                title="Delete Recipe Kit"
              >
                <Trash2 class="w-3.5 h-3.5" />
                <span>Delete</span>
              </button>
            </div>

          </div>

        </div>
      </div>

      <!-- Pagination -->
      <div 
        v-if="recipeKits.links && recipeKits.links.length > 3" 
        class="bg-white border border-[#e0d9cc] rounded-2xl p-4 flex items-center justify-between text-xs shadow-2xs"
      >
        <span class="text-[#6e6e73]">
          Showing {{ recipeKits.from || 0 }} to {{ recipeKits.to || 0 }} of {{ recipeKits.total }} kits
        </span>

        <div class="flex items-center gap-1">
          <component
            :is="link.url ? Link : 'span'"
            v-for="(link, i) in recipeKits.links"
            :key="i"
            :href="link.url"
            v-html="link.label"
            class="px-3 py-1.5 rounded-lg font-medium transition-colors"
            :class="{
              'bg-[#1a1a1a] text-white': link.active,
              'text-stone-600 hover:bg-stone-100': !link.active && link.url,
              'text-stone-300 cursor-not-allowed': !link.url
            }"
          />
        </div>
      </div>

    </div>

    <!-- Confirm Delete Modal -->
    <div 
      v-if="deletingKit" 
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs"
    >
      <div class="bg-white rounded-3xl max-w-md w-full p-6 sm:p-7 space-y-4 shadow-2xl border border-[#e0d9cc]">
        <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center">
          <Trash2 class="w-6 h-6" />
        </div>
        <div>
          <h3 class="text-lg font-serif font-medium text-[#1d1d1f]">
            Delete Recipe Kit?
          </h3>
          <p class="text-xs text-[#6e6e73] mt-1">
            Are you sure you want to delete <strong class="text-[#1d1d1f]">"{{ deletingKit.name }}"</strong>? This will remove the kit and bundle linkages. Catalog individual products will remain untouched.
          </p>
        </div>

        <div class="flex items-center justify-end gap-3 pt-3 border-t border-[#e0d9cc]/60">
          <button
            type="button"
            @click="deletingKit = null"
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
