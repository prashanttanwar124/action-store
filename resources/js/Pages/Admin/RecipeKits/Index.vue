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
import { Input } from '@/Components/ui/input';

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
    preserveScroll: true,
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
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white border border-[#e4e4e7] rounded-lg p-6 sm:p-7 shadow-2xs">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <span class="text-[11px] font-mono uppercase bg-[#18181b] text-white px-2.5 py-0.5 rounded-md font-semibold">
              Chef Kits
            </span>
            <span class="text-[11px] text-[#27272a] bg-[#f4f4f5] border border-[#e4e4e7] px-2.5 py-0.5 rounded-md font-medium">
              Group Buy / Ingredient Bundling Active
            </span>
          </div>
          <h1 class="text-2xl sm:text-3xl font-semibold text-[#09090b] tracking-tight">
            Recipe Kits & Group Buy
          </h1>
          <p class="text-xs sm:text-sm text-[#52525b] font-normal mt-1">
            Curate dinner dish kits with pre-portioned ingredients linked directly from your catalog for 1-click group checkout and bundle discounts.
          </p>
        </div>

        <div class="flex items-center gap-3 shrink-0">
          <Link
            :href="route('admin.recipe-kits.create')"
            class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#18181b] hover:bg-black text-white text-xs font-semibold rounded-md shadow-sm hover:shadow transition-all active:scale-98 cursor-pointer"
          >
            <Plus class="w-4 h-4 stroke-[2.5]" />
            <span>Create Recipe Kit</span>
          </Link>
        </div>
      </div>

      <!-- Quick Metrics Cards -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4">
        <div class="bg-white border border-[#e4e4e7] rounded-lg p-4 shadow-2xs">
          <div class="text-[11px] font-mono text-[#71717a] uppercase tracking-wider">Total Kits</div>
          <div class="text-2xl font-semibold text-[#09090b] mt-1">{{ stats.total }}</div>
        </div>
        <div class="bg-white border border-[#e4e4e7] rounded-lg p-4 shadow-2xs">
          <div class="text-[11px] font-mono text-emerald-700 uppercase tracking-wider">Active Published</div>
          <div class="text-2xl font-semibold text-emerald-800 mt-1">{{ stats.active }}</div>
        </div>
        <div class="bg-white border border-[#e4e4e7] rounded-lg p-4 shadow-2xs">
          <div class="text-[11px] font-mono text-[#27272a] uppercase tracking-wider">Group Buy Bundles</div>
          <div class="text-2xl font-semibold text-[#27272a] mt-1">{{ stats.total }}</div>
        </div>
        <div class="bg-white border border-[#e4e4e7] rounded-lg p-4 shadow-2xs">
          <div class="text-[11px] font-mono text-[#71717a] uppercase tracking-wider">Product Linking</div>
          <div class="text-xs font-semibold text-[#09090b] mt-2 flex items-center gap-1.5">
            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
            <span>Live Catalog Sync</span>
          </div>
        </div>
      </div>

      <!-- Search & Filters -->
      <div class="bg-white border border-[#e4e4e7] rounded-lg p-3 shadow-2xs flex items-center gap-3">
        <div class="relative flex-1">
          <Search class="w-4 h-4 text-zinc-400 absolute left-3 top-3 pointer-events-none z-10" />
          <Input
            type="text"
            v-model="searchQuery"
            @input="handleSearchInput"
            placeholder="Search recipe kits by name, subtitle, or cooking tag..."
            class="w-full pl-9 pr-4 h-9 bg-[#fafafa] rounded-md text-xs"
          />
        </div>
      </div>

      <!-- Recipe Kits List -->
      <div v-if="recipeKits.data.length === 0" class="bg-white border border-[#e4e4e7] rounded-lg p-12 text-center shadow-2xs">
        <div class="w-16 h-16 rounded-md bg-[#f4f4f5] text-[#18181b] flex items-center justify-center mx-auto mb-4">
          <UtensilsCrossed class="w-8 h-8" />
        </div>
        <h3 class="text-lg font-semibold text-[#09090b]">No recipe kits found</h3>
        <p class="text-xs text-[#52525b] max-w-md mx-auto mt-1 mb-6">
          {{ searchQuery ? 'Try adjusting your search query.' : 'Create your first recipe kit with linked products for group buy.' }}
        </p>
        <Link
          :href="route('admin.recipe-kits.create')"
          class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#18181b] hover:bg-black text-white text-xs font-semibold rounded-md shadow-sm transition-all"
        >
          <Plus class="w-4 h-4 stroke-[2.5]" />
          <span>Add New Recipe Kit</span>
        </Link>
      </div>

      <div v-else class="space-y-4">
        <div 
          v-for="kit in recipeKits.data" 
          :key="kit.id"
          class="bg-white border border-[#e4e4e7] rounded-lg p-5 sm:p-6 shadow-2xs hover:border-[#18181b]/60 transition-all flex flex-col xl:flex-row xl:items-center justify-between gap-6"
        >
          <!-- Left: Thumbnail & Main Info -->
          <div class="flex items-start gap-4 sm:gap-5 min-w-0 flex-1">
            
            <!-- Thumbnail with Multi-Image count badge -->
            <div class="relative w-24 h-24 sm:w-28 sm:h-28 rounded-lg overflow-hidden bg-zinc-100 border border-[#e4e4e7]/60 shrink-0">
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
                <span class="text-[10px] font-mono uppercase bg-[#18181b] text-white px-2 py-0.5 rounded-md font-semibold">
                  {{ kit.difficulty || 'Easy' }}
                </span>
                <span 
                  class="text-[10px] font-semibold px-2 py-0.5 rounded-md"
                  :class="kit.is_active ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : 'bg-zinc-100 text-zinc-600 border border-zinc-200'"
                >
                  {{ kit.is_active ? '● Active' : '○ Draft' }}
                </span>
                <span v-if="kit.subtitle_tag" class="text-[10px] font-mono text-[#18181b] font-semibold truncate max-w-[200px]">
                  {{ kit.subtitle_tag }}
                </span>
              </div>

              <h3 class="font-semibold text-lg sm:text-xl text-[#09090b] truncate leading-tight">
                {{ kit.name }}
              </h3>

              <!-- Cooking Specs Row -->
              <div class="flex items-center gap-3 text-xs text-[#52525b] font-medium flex-wrap">
                <span class="inline-flex items-center gap-1">
                  <Clock class="w-3.5 h-3.5 text-[#18181b]" />
                  <span>{{ kit.cooking_time || '20 mins' }}</span>
                </span>
                <span>·</span>
                <span class="inline-flex items-center gap-1">
                  <Users class="w-3.5 h-3.5 text-[#18181b]" />
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
                <span class="text-[11px] text-[#71717a] mr-1">Group Buy:</span>
                <span 
                  v-for="p in kit.products" 
                  :key="p.id"
                  class="inline-flex items-center gap-1 text-[11px] px-2 py-0.5 rounded-lg bg-[#f4f4f5] text-[#27272a] border border-[#e4e4e7]/80"
                  :title="p.name + (p.unit_notes ? ' (' + p.unit_notes + ')' : '')"
                >
                  <span class="font-semibold">{{ p.name }}</span>
                  <span v-if="p.quantity > 1" class="font-mono text-[10px] bg-[#18181b] text-white px-1 rounded-sm">x{{ p.quantity }}</span>
                </span>
              </div>
            </div>

          </div>

          <!-- Middle / Right: Pricing & Group Buy Savings -->
          <div class="flex items-center justify-between xl:justify-end gap-6 pt-3 xl:pt-0 border-t xl:border-t-0 border-[#e4e4e7]/60 shrink-0">
            
            <!-- Pricing breakdown -->
            <div class="text-left xl:text-right">
              <div class="flex items-baseline xl:justify-end gap-1.5">
                <span class="font-semibold text-xl text-[#09090b]">
                  ${{ Number(kit.price).toFixed(2) }}
                </span>
                <span v-if="kit.original_price" class="text-xs text-[#71717a] line-through">
                  ${{ Number(kit.original_price).toFixed(2) }}
                </span>
              </div>

              <div v-if="kit.original_price && kit.original_price > kit.price" class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-800 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-md mt-1">
                <TrendingDown class="w-3 h-3" />
                <span>Save ${{ (Number(kit.original_price) - Number(kit.price)).toFixed(2) }} (Group Buy)</span>
              </div>
              <div v-else-if="kit.individual_total > kit.price" class="text-[11px] font-semibold text-[#27272a] mt-1">
                Individual sum: ${{ Number(kit.individual_total).toFixed(2) }}
              </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-2">
              <Link
                :href="route('admin.recipe-kits.edit', kit.id)"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-[#f4f4f5] hover:bg-[#e4e4e7] text-[#09090b] text-xs font-semibold rounded-md border border-[#e4e4e7] transition-colors cursor-pointer"
              >
                <Edit class="w-3.5 h-3.5 text-[#18181b]" />
                <span>Edit</span>
              </Link>

              <button
                type="button"
                @click="confirmDelete(kit)"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 text-rose-600 hover:text-rose-700 hover:bg-rose-50 rounded-md text-xs font-semibold transition-colors cursor-pointer border border-transparent hover:border-rose-200"
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
        class="bg-white border border-[#e4e4e7] rounded-lg p-4 flex items-center justify-between text-xs shadow-2xs"
      >
        <span class="text-[#52525b]">
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
              'bg-[#18181b] text-white': link.active,
              'text-zinc-600 hover:bg-zinc-100': !link.active && link.url,
              'text-zinc-300 cursor-not-allowed': !link.url
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
      <div class="bg-white rounded-lg max-w-md w-full p-6 sm:p-7 space-y-4 shadow-2xl border border-[#e4e4e7]">
        <div class="w-12 h-12 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center">
          <Trash2 class="w-6 h-6" />
        </div>
        <div>
          <h3 class="text-lg font-semibold text-[#09090b]">
            Delete Recipe Kit?
          </h3>
          <p class="text-xs text-[#52525b] mt-1">
            Are you sure you want to delete <strong class="text-[#09090b]">"{{ deletingKit.name }}"</strong>? This will remove the kit and bundle linkages. Catalog individual products will remain untouched.
          </p>
        </div>

        <div class="flex items-center justify-end gap-3 pt-3 border-t border-[#e4e4e7]/60">
          <button
            type="button"
            @click="deletingKit = null"
            class="px-4 py-2 text-xs font-medium text-zinc-600 hover:text-zinc-800 rounded-md hover:bg-zinc-100 transition-colors cursor-pointer"
          >
            Cancel
          </button>
          <button
            type="button"
            @click="handleDelete"
            :disabled="isDeleting"
            class="px-5 py-2 text-xs font-semibold text-white bg-rose-600 hover:bg-rose-700 rounded-md transition-colors cursor-pointer disabled:opacity-50"
          >
            {{ isDeleting ? 'Deleting...' : 'Confirm Delete' }}
          </button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
