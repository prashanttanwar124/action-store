<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { 
  Plus, 
  Search, 
  Package, 
  Edit, 
  Trash2, 
  ExternalLink, 
  Image as ImageIcon,
  ChevronLeft,
  ChevronRight,
  Filter,
  Sparkles
} from 'lucide-vue-next';
import { Input } from '@/Components/ui/input';

const props = defineProps({
  products: {
    type: Object,
    required: true,
  },
  filters: {
    type: Object,
    default: () => ({ search: '', category: '' }),
  },
  categories: {
    type: Array,
    default: () => [],
  },
  defaultCategories: {
    type: Object,
    default: () => ({}),
  },
  stats: {
    type: Object,
    default: () => ({ total: 0, recipe_kits: 0, categories_count: 0 }),
  },
});

const searchQuery = ref(props.filters.search || '');
const selectedCategory = ref(props.filters.category || 'all');
const deletingProduct = ref(null);
const isDeleting = ref(false);

let searchTimeout = null;
const handleSearchInput = () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    applyFilters();
  }, 350);
};

const filterByCategory = (cat) => {
  selectedCategory.value = cat;
  applyFilters();
};

const applyFilters = () => {
  router.get(
    route('admin.products.index'),
    {
      search: searchQuery.value || undefined,
      category: selectedCategory.value !== 'all' ? selectedCategory.value : undefined,
    },
    {
      preserveState: true,
      preserveScroll: true,
      replace: true,
    }
  );
};

const confirmDelete = (product) => {
  deletingProduct.value = product;
};

const handleDelete = () => {
  if (!deletingProduct.value) return;
  isDeleting.value = true;
  router.delete(route('admin.products.destroy', deletingProduct.value.id), {
    onFinish: () => {
      isDeleting.value = false;
      deletingProduct.value = null;
    },
  });
};

const getCategoryBadgeClass = (category) => {
  switch (category) {
    case 'spices':
      return 'bg-amber-100 text-amber-800 border-amber-200';
    case 'dairy':
      return 'bg-sky-100 text-sky-800 border-sky-200';
    case 'sweets':
      return 'bg-pink-100 text-pink-800 border-pink-200';
    case 'vegetables':
      return 'bg-emerald-100 text-emerald-800 border-emerald-200';
    case 'recipe-kits':
      return 'bg-purple-100 text-purple-800 border-purple-200';
    default:
      return 'bg-stone-100 text-stone-700 border-stone-200';
  }
};
</script>

<template>
  <AdminLayout title="Product Catalog">
    <div class="space-y-6">

      <!-- Top Summary Header & Actions -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white border border-[#e0d9cc] rounded-3xl p-6 sm:p-7 shadow-2xs">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <span class="text-[11px] font-mono uppercase bg-[#1a1a1a] text-white px-2.5 py-0.5 rounded-full font-semibold">
              Catalog Management
            </span>
            <span class="text-[11px] text-[#7a5620] bg-[#f5eee2] border border-[#e0d9cc] px-2.5 py-0.5 rounded-full font-medium">
              Multi-Image Gallery Enabled
            </span>
          </div>
          <h1 class="text-2xl sm:text-3xl font-serif font-medium text-[#1d1d1f] tracking-tight">
            Storefront Products
          </h1>
          <p class="text-xs sm:text-sm text-[#6e6e73] mt-0.5">
            Add, update, manage multi-image galleries, and organize your store catalog.
          </p>
        </div>

        <div class="flex items-center gap-2.5 shrink-0">
          <Link 
            :href="route('admin.products.create')"
            class="px-4 py-2 bg-[#1a1a1a] hover:bg-black text-white text-xs font-semibold rounded-full inline-flex items-center gap-1.5 transition-colors cursor-pointer shadow-xs"
          >
            <Plus class="w-4 h-4" />
            <span>Add New Product</span>
          </Link>
        </div>
      </div>

      <!-- Quick Metrics Bar -->
      <div class="grid grid-cols-2 gap-3.5">
        <div class="bg-white border border-[#e0d9cc] rounded-2xl p-4 shadow-2xs flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-[#f5eee2] text-[#a47a3c] flex items-center justify-center shrink-0">
            <Package class="w-5 h-5" />
          </div>
          <div>
            <div class="text-xl sm:text-2xl font-serif font-medium text-[#1d1d1f] leading-none">
              {{ stats.total }}
            </div>
            <div class="text-[11px] text-[#6e6e73] mt-0.5">Total Store Products</div>
          </div>
        </div>

        <div class="bg-white border border-[#e0d9cc] rounded-2xl p-4 shadow-2xs flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0">
            <Filter class="w-5 h-5" />
          </div>
          <div>
            <div class="text-xl sm:text-2xl font-serif font-medium text-[#1d1d1f] leading-none">
              {{ stats.categories_count }}
            </div>
            <div class="text-[11px] text-[#6e6e73] mt-0.5">Active Categories</div>
          </div>
        </div>
      </div>

      <!-- Filters & Search Toolbar -->
      <div class="bg-white border border-[#e0d9cc] rounded-2xl p-4 shadow-2xs space-y-3">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <!-- Search Input -->
          <div class="relative w-full sm:w-80">
            <Search class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-[#86868b] pointer-events-none z-10" />
            <Input 
              v-model="searchQuery"
              @input="handleSearchInput"
              type="text"
              placeholder="Search products by title, slug, tag..."
              class="w-full pl-9 pr-4 h-9 text-xs bg-[#faf8f5] rounded-full"
            />
          </div>

          <!-- Total results count -->
          <div class="text-xs text-[#86868b] self-end sm:self-center font-medium">
            Showing {{ products.data.length }} of {{ products.total }} items
          </div>
        </div>

        <!-- Category Pills -->
        <div class="flex items-center gap-1.5 overflow-x-auto pt-1 no-scrollbar text-xs">
          <button 
            type="button"
            @click="filterByCategory('all')"
            class="px-3 py-1 rounded-full font-medium transition-colors cursor-pointer shrink-0"
            :class="selectedCategory === 'all' 
              ? 'bg-[#1a1a1a] text-white' 
              : 'bg-[#faf8f5] text-stone-600 hover:text-stone-900 border border-[#e0d9cc]'"
          >
            All Categories
          </button>
          <button 
            v-for="cat in categories" 
            :key="cat"
            type="button"
            @click="filterByCategory(cat)"
            class="px-3 py-1 rounded-full font-medium transition-colors cursor-pointer shrink-0 capitalize"
            :class="selectedCategory === cat 
              ? 'bg-[#1a1a1a] text-white' 
              : 'bg-[#faf8f5] text-stone-600 hover:text-stone-900 border border-[#e0d9cc]'"
          >
            {{ cat.replace('-', ' ') }}
          </button>
        </div>
      </div>

      <!-- Products Table -->
      <div class="bg-white border border-[#e0d9cc] rounded-3xl overflow-hidden shadow-2xs">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead class="bg-[#f3efe7] text-[#6e6e73] uppercase text-[10px] font-semibold tracking-wider border-b border-[#e0d9cc]">
              <tr>
                <th class="py-3.5 px-4">Product Details</th>
                <th class="py-3.5 px-4">Category</th>
                <th class="py-3.5 px-4">Price</th>
                <th class="py-3.5 px-4">Size & Stock</th>
                <th class="py-3.5 px-4">Gallery</th>
                <th class="py-3.5 px-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#e0d9cc]/60">
              <tr 
                v-for="prod in products.data" 
                :key="prod.id" 
                class="hover:bg-[#fbf9f5] transition-colors group"
              >
                <!-- Thumbnail & Name -->
                <td class="py-3.5 px-4">
                  <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-xl bg-stone-100 border border-stone-200 overflow-hidden relative shrink-0">
                      <img 
                        :src="prod.image" 
                        :alt="prod.name" 
                        class="w-full h-full object-cover"
                        @error="$event.target.src = '/images/products/atta.jpg'"
                      />
                      <span 
                        v-if="prod.images_count > 1" 
                        class="absolute bottom-0 right-0 bg-black/75 text-white text-[9px] font-mono px-1 rounded-tl"
                        title="Multiple gallery images"
                      >
                        {{ prod.images_count }}
                      </span>
                    </div>
                    <div class="min-w-0">
                      <div class="font-serif font-medium text-sm text-[#1d1d1f] truncate max-w-xs group-hover:text-[#a47a3c] transition-colors">
                        {{ prod.name }}
                      </div>
                      <div class="text-[11px] font-mono text-[#86868b] truncate max-w-xs">
                        /products/{{ prod.slug }}
                      </div>
                    </div>
                  </div>
                </td>

                <!-- Category -->
                <td class="py-3.5 px-4">
                  <span 
                    class="text-[10px] font-mono uppercase px-2 py-0.5 rounded-md font-semibold border"
                    :class="getCategoryBadgeClass(prod.category)"
                  >
                    {{ prod.category }}
                  </span>
                </td>

                <!-- Price -->
                <td class="py-3.5 px-4">
                  <div class="font-medium text-[#1d1d1f]">
                    ${{ Number(prod.price).toFixed(2) }}
                  </div>
                  <div 
                    v-if="prod.original_price && prod.original_price > prod.price" 
                    class="text-[10px] text-[#86868b] line-through"
                  >
                    ${{ Number(prod.original_price).toFixed(2) }}
                  </div>
                </td>

                <!-- Size & Stock -->
                <td class="py-3.5 px-4">
                  <div class="text-[#1d1d1f] font-medium">
                    {{ prod.size_main || 'Standard' }}
                  </div>
                  <div class="text-[10px] text-emerald-700 font-medium">
                    {{ prod.stock_badge || 'In Stock' }}
                  </div>
                </td>

                <!-- Multiple Gallery Images Badge -->
                <td class="py-3.5 px-4">
                  <div class="flex items-center gap-1.5">
                    <div class="flex -space-x-2 overflow-hidden">
                      <img 
                        v-for="(img, idx) in prod.images.slice(0, 3)" 
                        :key="idx" 
                        :src="img" 
                        class="inline-block h-6 w-6 rounded-full ring-2 ring-white object-cover bg-stone-100"
                        @error="$event.target.src = '/images/products/atta.jpg'"
                      />
                    </div>
                    <span class="text-[11px] font-mono text-[#86868b]">
                      {{ prod.images_count }} {{ prod.images_count === 1 ? 'img' : 'imgs' }}
                    </span>
                  </div>
                </td>

                <!-- Actions -->
                <td class="py-3.5 px-4 text-right">
                  <div class="flex items-center justify-end gap-1.5">
                    <!-- Preview on Storefront -->
                    <a 
                      :href="`/products/${prod.slug}`" 
                      target="_blank"
                      class="p-1.5 text-stone-500 hover:text-stone-900 hover:bg-[#ece7de] rounded-lg transition-colors cursor-pointer"
                      title="View on Customer Storefront"
                    >
                      <ExternalLink class="w-3.5 h-3.5" />
                    </a>

                    <!-- Edit Button -->
                    <Link 
                      :href="route('admin.products.edit', prod.id)"
                      class="p-1.5 text-stone-700 hover:text-[#a47a3c] hover:bg-[#ece7de] rounded-lg transition-colors cursor-pointer"
                      title="Edit Product Details & Gallery"
                    >
                      <Edit class="w-3.5 h-3.5" />
                    </Link>

                    <!-- Delete Button -->
                    <button 
                      type="button"
                      @click="confirmDelete(prod)"
                      class="p-1.5 text-stone-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors cursor-pointer"
                      title="Delete Product"
                    >
                      <Trash2 class="w-3.5 h-3.5" />
                    </button>
                  </div>
                </td>
              </tr>

              <!-- Empty State -->
              <tr v-if="products.data.length === 0">
                <td colspan="6" class="py-12 text-center text-xs text-[#86868b] space-y-2">
                  <Package class="w-8 h-8 mx-auto text-stone-300 stroke-1" />
                  <div class="font-medium text-stone-700">No products match your criteria.</div>
                  <p class="text-[11px] text-stone-400">Try adjusting your search query or category filter.</p>
                  <div class="pt-2">
                    <Link 
                      :href="route('admin.products.create')"
                      class="px-4 py-2 bg-[#1a1a1a] hover:bg-black text-white text-xs font-semibold rounded-full inline-flex items-center gap-1.5 transition-colors cursor-pointer shadow-xs"
                    >
                      <Plus class="w-3.5 h-3.5" />
                      <span>Add First Product</span>
                    </Link>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination Bar -->
        <div 
          v-if="products.links && products.links.length > 3" 
          class="border-t border-[#e0d9cc]/60 px-4 py-3 flex items-center justify-between text-xs bg-[#faf8f5]"
        >
          <div class="text-[#86868b]">
            Page {{ products.current_page }} of {{ products.last_page }}
          </div>

          <div class="flex items-center gap-1">
            <template v-for="(link, index) in products.links" :key="index">
              <Link 
                v-if="link.url"
                :href="link.url"
                class="px-3 py-1 rounded-md text-xs font-medium transition-colors"
                :class="link.active 
                  ? 'bg-[#1a1a1a] text-white font-semibold' 
                  : 'text-stone-700 hover:bg-[#ece7de]'"
                v-html="link.label"
              />
              <span 
                v-else 
                class="px-2 py-1 text-xs text-stone-300 cursor-not-allowed select-none"
                v-html="link.label"
              />
            </template>
          </div>
        </div>
      </div>

      <!-- Delete Confirmation Modal -->
      <div 
        v-if="deletingProduct"
        class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4"
        @click.self="deletingProduct = null"
      >
        <div class="bg-white rounded-3xl max-w-sm w-full p-6 shadow-2xl border border-[#e0d9cc] space-y-4">
          <div class="w-10 h-10 rounded-full bg-rose-50 text-rose-600 flex items-center justify-center mx-auto">
            <Trash2 class="w-5 h-5" />
          </div>

          <div class="text-center space-y-1">
            <h3 class="font-serif font-medium text-lg text-[#1d1d1f]">
              Delete Product?
            </h3>
            <p class="text-xs text-[#6e6e73]">
              Are you sure you want to permanently remove <strong>{{ deletingProduct.name }}</strong> from the store catalog?
            </p>
          </div>

          <div class="flex items-center justify-center gap-2 pt-2">
            <button 
              type="button"
              @click="deletingProduct = null"
              class="px-4 py-2 text-xs font-semibold text-[#6e6e73] hover:text-[#1d1d1f] rounded-full cursor-pointer"
            >
              Cancel
            </button>
            <button 
              type="button"
              @click="handleDelete"
              :disabled="isDeleting"
              class="px-5 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold rounded-full cursor-pointer transition-colors disabled:opacity-50"
            >
              {{ isDeleting ? 'Deleting...' : 'Delete Product' }}
            </button>
          </div>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>
