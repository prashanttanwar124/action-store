<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import {
  FolderTree,
  Search,
  Plus,
  Edit2,
  Trash2,
  CheckCircle2,
  XCircle,
  Package,
  Layers,
  ArrowUpDown,
  X,
  ExternalLink,
  Sparkles,
  Flame,
  Milk,
  Cookie,
  Apple,
  ShoppingBag,
  UtensilsCrossed,
  Wheat,
  Coffee,
  Leaf,
  Tag,
  Eye,
  EyeOff,
  Image as ImageIcon,
  Upload,
  Link as LinkIcon
} from 'lucide-vue-next';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';

const props = defineProps({
  categories: {
    type: Object,
    required: true,
  },
  metrics: {
    type: Object,
    required: true,
  },
  filters: {
    type: Object,
    default: () => ({
      search: '',
      status: 'all',
      sort: 'sort_order',
    }),
  },
});

// Icon lookup dictionary
const iconComponents = {
  Wheat,
  Flame,
  Milk,
  Cookie,
  Sparkles,
  Apple,
  ShoppingBag,
  UtensilsCrossed,
  Coffee,
  Leaf,
  Tag,
  Package,
  Layers,
  FolderTree,
};

const availableIcons = [
  { name: 'Wheat', label: 'Staples & Grains', icon: Wheat },
  { name: 'Flame', label: 'Spices & Masalas', icon: Flame },
  { name: 'Milk', label: 'Dairy & Ghee', icon: Milk },
  { name: 'Cookie', label: 'Snacks & Savories', icon: Cookie },
  { name: 'Sparkles', label: 'Sweets & Mithai', icon: Sparkles },
  { name: 'Apple', label: 'Fresh Produce', icon: Apple },
  { name: 'ShoppingBag', label: 'Groceries & Pantry', icon: ShoppingBag },
  { name: 'UtensilsCrossed', label: 'Recipe Kits', icon: UtensilsCrossed },
  { name: 'Leaf', label: 'Organic & Herbs', icon: Leaf },
  { name: 'Coffee', label: 'Beverages & Chai', icon: Coffee },
  { name: 'Tag', label: 'Special Offers', icon: Tag },
  { name: 'Package', label: 'General Goods', icon: Package },
];

const getCategoryIconComponent = (iconName) => {
  return iconComponents[iconName] || FolderTree;
};

// Search, Filter & Sort State
const searchInput = ref(props.filters.search || '');
const currentStatus = ref(props.filters.status || 'all');
const currentSort = ref(props.filters.sort || 'sort_order');

let searchDebounce = null;
const handleSearchChange = () => {
  clearTimeout(searchDebounce);
  searchDebounce = setTimeout(() => {
    applyFilters();
  }, 350);
};

const setStatus = (statusKey) => {
  currentStatus.value = statusKey;
  applyFilters();
};

const setSort = (sortKey) => {
  currentSort.value = sortKey;
  applyFilters();
};

const resetFilters = () => {
  searchInput.value = '';
  currentStatus.value = 'all';
  currentSort.value = 'sort_order';
  applyFilters();
};

const applyFilters = () => {
  router.get(
    route('admin.categories.index'),
    {
      search: searchInput.value || undefined,
      status: currentStatus.value !== 'all' ? currentStatus.value : undefined,
      sort: currentSort.value !== 'sort_order' ? currentSort.value : undefined,
    },
    {
      preserveState: true,
      preserveScroll: true,
      replace: true,
    }
  );
};

// ==========================================
// MODAL: CREATE CATEGORY
// ==========================================
const showCreateModal = ref(false);
const createSlugManual = ref(false);
const createForm = useForm({
  name: '',
  slug: '',
  hindi_title: '',
  description: '',
  icon: 'Wheat',
  image_url: '',
  image_file: null,
  sort_order: 0,
  is_active: true,
});

const handleCreateNameInput = () => {
  if (!createSlugManual.value) {
    createForm.slug = createForm.name
      .toLowerCase()
      .trim()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '');
  }
};

const handleCreateImageSelect = (e) => {
  const file = e.target.files?.[0];
  if (file) {
    createForm.image_file = file;
  }
};

const submitCreate = () => {
  createForm.post(route('admin.categories.store'), {
    onSuccess: () => {
      showCreateModal.value = false;
      createForm.reset();
      createSlugManual.value = false;
    },
  });
};

// ==========================================
// MODAL: EDIT CATEGORY
// ==========================================
const showEditModal = ref(false);
const editCategoryItem = ref(null);
const editForm = useForm({
  name: '',
  slug: '',
  hindi_title: '',
  description: '',
  icon: 'Package',
  image_url: '',
  image_file: null,
  sort_order: 0,
  is_active: true,
});

const openEditModal = (category) => {
  editCategoryItem.value = category;
  editForm.name = category.name;
  editForm.slug = category.slug;
  editForm.hindi_title = category.hindi_title || '';
  editForm.description = category.description || '';
  editForm.icon = category.icon || 'Package';
  editForm.image_url = category.image || '';
  editForm.image_file = null;
  editForm.sort_order = category.sort_order || 0;
  editForm.is_active = category.is_active;
  editForm.clearErrors();
  showEditModal.value = true;
};

const handleEditImageSelect = (e) => {
  const file = e.target.files?.[0];
  if (file) {
    editForm.image_file = file;
  }
};

const submitEdit = () => {
  if (!editCategoryItem.value) return;

  // Use router.post with multipart form data
  editForm.post(route('admin.categories.update', editCategoryItem.value.id), {
    headers: {
      'X-HTTP-Method-Override': 'PATCH',
    },
    onSuccess: () => {
      showEditModal.value = false;
      editCategoryItem.value = null;
    },
  });
};

// ==========================================
// MODAL: DELETE CATEGORY
// ==========================================
const showDeleteModal = ref(false);
const categoryToDelete = ref(null);
const isDeleting = ref(false);

const openDeleteModal = (category) => {
  categoryToDelete.value = category;
  showDeleteModal.value = true;
};

const confirmDelete = () => {
  if (!categoryToDelete.value) return;
  isDeleting.value = true;
  router.delete(route('admin.categories.destroy', categoryToDelete.value.id), {
    preserveScroll: true,
    onFinish: () => {
      isDeleting.value = false;
      showDeleteModal.value = false;
      categoryToDelete.value = null;
    },
  });
};

// ==========================================
// FAST TOGGLE ACTIVE STATUS
// ==========================================
const toggleCategoryActive = (category) => {
  // Optimistic update
  category.is_active = !category.is_active;

  router.post(
    route('admin.categories.toggle', category.id),
    {},
    {
      preserveScroll: true,
      preserveState: true,
      onError: () => {
        // Rollback on error
        category.is_active = !category.is_active;
      },
    }
  );
};
</script>

<template>
  <AdminLayout title="Product Categories">
    <div class="space-y-6">

      <!-- ========================================== -->
      <!-- TOP HEADER & PRIMARY ACTIONS               -->
      <!-- ========================================== -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white border border-[#e0d9cc] rounded-3xl p-6 sm:p-7 shadow-2xs">
        <div>
          <div class="flex items-center gap-2 mb-1.5">
            <span class="text-[11px] font-mono uppercase bg-[#1a1a1a] text-white px-2.5 py-0.5 rounded-full font-semibold">
              Store Catalog
            </span>
            <span class="text-[11px] text-[#7a5620] bg-[#f5eee2] border border-[#e0d9cc] px-2.5 py-0.5 rounded-full font-medium">
              Taxonomy & Collections
            </span>
          </div>
          <h1 class="text-2xl sm:text-3xl font-serif font-medium text-[#1d1d1f] tracking-tight">
            Product Categories
          </h1>
          <p class="text-xs sm:text-sm text-[#6e6e73] font-normal mt-1 max-w-2xl leading-relaxed">
            Manage your store's department aisles, Hindi labels, aisle sort priority, category banners, and storefront navigation.
          </p>
        </div>

        <div class="flex items-center gap-3 shrink-0">
          <button
            type="button"
            @click="showCreateModal = true"
            class="inline-flex items-center gap-2 px-4 py-2.5 bg-[#1a1a1a] hover:bg-black text-white rounded-full text-xs font-semibold transition-all shadow-xs hover:scale-[1.02] cursor-pointer"
          >
            <Plus class="w-4 h-4" />
            <span>Add Category</span>
          </button>
        </div>
      </div>

      <!-- ========================================== -->
      <!-- EXECUTIVE KPI METRICS                      -->
      <!-- ========================================== -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        
        <!-- Total Categories -->
        <div class="bg-white border border-[#e0d9cc] rounded-2xl p-5 shadow-2xs flex flex-col justify-between hover:border-[#a47a3c]/60 transition-colors">
          <div class="flex items-center justify-between text-[#6e6e73]">
            <span class="text-xs font-semibold uppercase tracking-wider">Total Categories</span>
            <div class="w-8 h-8 rounded-xl bg-[#f5eee2] flex items-center justify-center text-[#a47a3c]">
              <FolderTree class="w-4 h-4" />
            </div>
          </div>
          <div class="mt-4">
            <div class="text-2xl sm:text-3xl font-serif font-medium text-[#1d1d1f]">
              {{ metrics.total_categories }}
            </div>
            <div class="flex items-center gap-1.5 mt-1 text-[11px] text-emerald-700">
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
              <span>Organized store catalog</span>
            </div>
          </div>
        </div>

        <!-- Active in Store -->
        <div class="bg-white border border-[#e0d9cc] rounded-2xl p-5 shadow-2xs flex flex-col justify-between hover:border-[#a47a3c]/60 transition-colors">
          <div class="flex items-center justify-between text-[#6e6e73]">
            <span class="text-xs font-semibold uppercase tracking-wider">Active In Storefront</span>
            <div class="w-8 h-8 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-600">
              <CheckCircle2 class="w-4 h-4" />
            </div>
          </div>
          <div class="mt-4">
            <div class="text-2xl sm:text-3xl font-serif font-medium text-[#1d1d1f]">
              {{ metrics.active_categories }}
            </div>
            <div class="flex items-center gap-1.5 mt-1 text-[11px] text-[#6e6e73]">
              <span>{{ metrics.total_categories - metrics.active_categories }} hidden from navigation</span>
            </div>
          </div>
        </div>

        <!-- Total Products Categorized -->
        <div class="bg-white border border-[#e0d9cc] rounded-2xl p-5 shadow-2xs flex flex-col justify-between hover:border-[#a47a3c]/60 transition-colors">
          <div class="flex items-center justify-between text-[#6e6e73]">
            <span class="text-xs font-semibold uppercase tracking-wider">Total Products</span>
            <div class="w-8 h-8 rounded-xl bg-[#f5eee2] flex items-center justify-center text-[#a47a3c]">
              <Package class="w-4 h-4" />
            </div>
          </div>
          <div class="mt-4">
            <div class="text-2xl sm:text-3xl font-serif font-medium text-[#1d1d1f]">
              {{ metrics.total_products }}
            </div>
            <div class="flex items-center gap-1.5 mt-1 text-[11px] text-[#6e6e73]">
              <span>Assigned across catalog aisles</span>
            </div>
          </div>
        </div>

      </div>

      <!-- ========================================== -->
      <!-- SEARCH, FILTERS & SORT CONTROLS            -->
      <!-- ========================================== -->
      <div class="bg-white border border-[#e0d9cc] rounded-2xl p-4 shadow-2xs space-y-3">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
          
          <!-- Search Bar -->
          <div class="relative flex-1 max-w-md">
            <Search class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-[#86868b]" />
            <input 
              v-model="searchInput"
              @input="handleSearchChange"
              type="text"
              placeholder="Search by category name, slug, or Hindi title..."
              class="w-full pl-9 pr-9 py-2 text-xs bg-[#fbf9f5] border border-[#e0d9cc] rounded-full focus:outline-none focus:ring-3 focus:ring-[#1a1a1a]/10 focus:border-[#1a1a1a] focus:bg-white transition-all"
            />
            <button 
              v-if="searchInput" 
              type="button" 
              @click="searchInput = ''; applyFilters()" 
              class="absolute right-3 top-1/2 -translate-y-1/2 text-[#86868b] hover:text-[#1d1d1f] cursor-pointer"
            >
              <X class="w-3.5 h-3.5" />
            </button>
          </div>

          <!-- Controls: Status Filter & Sort Selector -->
          <div class="flex items-center flex-wrap gap-3 self-end lg:self-center">
            
            <!-- Status Select -->
            <div class="flex items-center gap-2">
              <span class="text-xs text-[#86868b] whitespace-nowrap">Status:</span>
              <Select :model-value="currentStatus" @update:model-value="setStatus">
                <SelectTrigger class="w-36 h-8 rounded-full bg-[#fbf9f5] border-[#e0d9cc] text-xs font-medium focus:border-[#1a1a1a] focus:ring-3 focus:ring-[#1a1a1a]/10 shadow-2xs">
                  <SelectValue placeholder="All Status" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All Statuses</SelectItem>
                  <SelectItem value="active">Active Only</SelectItem>
                  <SelectItem value="inactive">Hidden Only</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <!-- Sort Select -->
            <div class="flex items-center gap-2">
              <span class="text-xs text-[#86868b] whitespace-nowrap">Sort:</span>
              <Select :model-value="currentSort" @update:model-value="setSort">
                <SelectTrigger class="w-44 h-8 rounded-full bg-[#fbf9f5] border-[#e0d9cc] text-xs font-medium focus:border-[#1a1a1a] focus:ring-3 focus:ring-[#1a1a1a]/10 shadow-2xs">
                  <SelectValue placeholder="Sort Order" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="sort_order">Display Priority</SelectItem>
                  <SelectItem value="name">Name (A-Z)</SelectItem>
                  <SelectItem value="products_count">Most Products</SelectItem>
                  <SelectItem value="latest">Newest Created</SelectItem>
                </SelectContent>
              </Select>
            </div>

          </div>

        </div>

        <!-- Filter Segment Pills -->
        <div class="flex items-center gap-1.5 overflow-x-auto pb-1 text-xs no-scrollbar pt-1 border-t border-[#e0d9cc]/40">
          <button 
            type="button"
            @click="setStatus('all')"
            :class="[
              'px-3 py-1 rounded-full font-medium transition-colors cursor-pointer',
              currentStatus === 'all'
                ? 'bg-[#1a1a1a] text-white shadow-2xs'
                : 'bg-[#fbf9f5] text-[#6e6e73] hover:text-[#1d1d1f] hover:bg-[#f0ebe1] border border-[#e0d9cc]/60'
            ]"
          >
            All Categories ({{ metrics.total_categories }})
          </button>
          <button 
            type="button"
            @click="setStatus('active')"
            :class="[
              'px-3 py-1 rounded-full font-medium transition-colors cursor-pointer flex items-center gap-1.5',
              currentStatus === 'active'
                ? 'bg-[#1a1a1a] text-white shadow-2xs'
                : 'bg-[#fbf9f5] text-[#6e6e73] hover:text-[#1d1d1f] hover:bg-[#f0ebe1] border border-[#e0d9cc]/60'
            ]"
          >
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
            Active in Storefront ({{ metrics.active_categories }})
          </button>
          <button 
            type="button"
            @click="setStatus('inactive')"
            :class="[
              'px-3 py-1 rounded-full font-medium transition-colors cursor-pointer flex items-center gap-1.5',
              currentStatus === 'inactive'
                ? 'bg-[#1a1a1a] text-white shadow-2xs'
                : 'bg-[#fbf9f5] text-[#6e6e73] hover:text-[#1d1d1f] hover:bg-[#f0ebe1] border border-[#e0d9cc]/60'
            ]"
          >
            <span class="w-1.5 h-1.5 rounded-full bg-zinc-400"></span>
            Hidden / Inactive ({{ metrics.total_categories - metrics.active_categories }})
          </button>

          <button 
            v-if="searchInput || currentStatus !== 'all' || currentSort !== 'sort_order'"
            type="button" 
            @click="resetFilters" 
            class="ml-auto text-xs text-[#a47a3c] hover:underline font-medium cursor-pointer"
          >
            Reset Filters
          </button>
        </div>
      </div>

      <!-- ========================================== -->
      <!-- CATEGORIES TABLE                           -->
      <!-- ========================================== -->
      <div class="bg-white border border-[#e0d9cc] rounded-3xl overflow-hidden shadow-2xs">
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="border-b border-[#e0d9cc] bg-[#fbf9f5]/80 text-[11px] font-mono uppercase tracking-wider text-[#86868b]">
                <th class="py-3.5 pl-6 pr-3">Category</th>
                <th class="py-3.5 px-3">Slug & Route</th>
                <th class="py-3.5 px-3 text-center">Priority</th>
                <th class="py-3.5 px-3 text-center">Products</th>
                <th class="py-3.5 px-3 text-center">Status</th>
                <th class="py-3.5 pr-6 pl-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#e0d9cc]/60 text-xs">
              <tr 
                v-for="cat in categories.data" 
                :key="cat.id" 
                class="hover:bg-[#fbf9f5] transition-colors group"
              >
                <!-- Category Details with Thumbnail & Icon -->
                <td class="py-4 pl-6 pr-3">
                  <div class="flex items-center gap-3.5">
                    <!-- Thumbnail or Icon Fallback -->
                    <div class="w-12 h-12 rounded-2xl border border-[#e0d9cc] bg-[#f5eee2] overflow-hidden shrink-0 flex items-center justify-center relative shadow-2xs group-hover:border-[#a47a3c]/60 transition-colors">
                      <img 
                        v-if="cat.image" 
                        :src="cat.image" 
                        :alt="cat.name" 
                        class="w-full h-full object-cover"
                        @error="(e) => e.target.style.display = 'none'"
                      />
                      <component 
                        :is="getCategoryIconComponent(cat.icon)" 
                        class="w-5 h-5 text-[#a47a3c]"
                      />
                    </div>

                    <div>
                      <div class="flex items-center gap-2">
                        <span class="font-serif font-medium text-sm text-[#1d1d1f] group-hover:text-[#a47a3c] transition-colors">
                          {{ cat.name }}
                        </span>
                        <span 
                          v-if="cat.hindi_title" 
                          class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-[#f5eee2] text-[#7a5620] border border-[#e0d9cc]/80"
                        >
                          {{ cat.hindi_title }}
                        </span>
                      </div>
                      <p v-if="cat.description" class="text-[11px] text-[#6e6e73] line-clamp-1 max-w-sm mt-0.5 font-normal">
                        {{ cat.description }}
                      </p>
                    </div>
                  </div>
                </td>

                <!-- Slug & Storefront Link -->
                <td class="py-4 px-3">
                  <div class="flex items-center gap-1.5">
                    <span class="font-mono text-[11px] text-[#6e6e73] bg-[#f5eee2] px-2 py-0.5 rounded-md border border-[#e0d9cc]">
                      /categories/{{ cat.slug }}
                    </span>
                    <a 
                      :href="`/categories/${cat.slug}`" 
                      target="_blank" 
                      title="View category page in storefront"
                      class="text-[#86868b] hover:text-[#1a1a1a] transition-colors p-1"
                    >
                      <ExternalLink class="w-3.5 h-3.5" />
                    </a>
                  </div>
                </td>

                <!-- Priority (Sort Order) -->
                <td class="py-4 px-3 text-center">
                  <span class="inline-flex items-center justify-center font-mono text-xs px-2.5 py-0.5 rounded-full bg-[#fbf9f5] border border-[#e0d9cc] text-[#1d1d1f] font-semibold">
                    #{{ cat.sort_order }}
                  </span>
                </td>

                <!-- Products Count -->
                <td class="py-4 px-3 text-center">
                  <Link 
                    :href="route('admin.products.index', { category: cat.slug })"
                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-[#f5eee2] hover:bg-[#e8decb] text-[#7a5620] text-xs font-medium border border-[#e0d9cc] transition-colors"
                  >
                    <Package class="w-3 h-3 text-[#a47a3c]" />
                    <span>{{ cat.products_count }} {{ cat.products_count === 1 ? 'item' : 'items' }}</span>
                  </Link>
                </td>

                <!-- Status Toggle -->
                <td class="py-4 px-3 text-center">
                  <button
                    type="button"
                    @click="toggleCategoryActive(cat)"
                    :title="cat.is_active ? 'Click to hide category from store' : 'Click to make category active'"
                    :class="[
                      'inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium transition-all cursor-pointer shadow-2xs',
                      cat.is_active 
                        ? 'bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100' 
                        : 'bg-zinc-100 text-zinc-600 border border-zinc-200 hover:bg-zinc-200'
                    ]"
                  >
                    <span 
                      :class="[
                        'w-1.5 h-1.5 rounded-full transition-colors',
                        cat.is_active ? 'bg-emerald-500' : 'bg-zinc-400'
                      ]"
                    ></span>
                    <span>{{ cat.is_active ? 'Active' : 'Hidden' }}</span>
                  </button>
                </td>

                <!-- Actions -->
                <td class="py-4 pr-6 pl-3 text-right">
                  <div class="inline-flex items-center gap-1.5">
                    <button
                      type="button"
                      @click="openEditModal(cat)"
                      class="p-1.5 text-[#6e6e73] hover:text-[#1d1d1f] hover:bg-[#f0ebe1] rounded-lg transition-colors cursor-pointer"
                      title="Edit Category Details"
                    >
                      <Edit2 class="w-4 h-4" />
                    </button>
                    <button
                      type="button"
                      @click="openDeleteModal(cat)"
                      class="p-1.5 text-[#86868b] hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors cursor-pointer"
                      title="Delete Category"
                    >
                      <Trash2 class="w-4 h-4" />
                    </button>
                  </div>
                </td>
              </tr>

              <!-- Empty State -->
              <tr v-if="categories.data.length === 0">
                <td colspan="6" class="py-12 text-center">
                  <div class="w-12 h-12 rounded-full bg-[#f5eee2] text-[#a47a3c] flex items-center justify-center mx-auto mb-3">
                    <FolderTree class="w-6 h-6" />
                  </div>
                  <h3 class="font-serif font-medium text-base text-[#1d1d1f]">No categories found</h3>
                  <p class="text-xs text-[#6e6e73] mt-1 max-w-sm mx-auto">
                    Try adjusting your search criteria, clearing active filters, or create a brand new store category.
                  </p>
                  <button 
                    type="button" 
                    @click="resetFilters" 
                    class="mt-4 text-xs font-semibold text-[#a47a3c] hover:underline"
                  >
                    Clear All Filters
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination Footer -->
        <div 
          v-if="categories.links && categories.links.length > 3" 
          class="border-t border-[#e0d9cc] px-6 py-4 flex flex-col sm:flex-row items-center justify-between gap-3 bg-[#fbf9f5]/50"
        >
          <div class="text-xs text-[#6e6e73]">
            Showing <span class="font-medium text-[#1d1d1f]">{{ categories.from || 0 }}</span> to <span class="font-medium text-[#1d1d1f]">{{ categories.to || 0 }}</span> of <span class="font-medium text-[#1d1d1f]">{{ categories.total }}</span> categories
          </div>
          <div class="flex items-center gap-1">
            <template v-for="(link, i) in categories.links" :key="i">
              <Link
                v-if="link.url"
                :href="link.url"
                v-html="link.label"
                :class="[
                  'px-3 py-1 rounded-full text-xs transition-colors',
                  link.active
                    ? 'bg-[#1a1a1a] text-white font-semibold'
                    : 'text-[#6e6e73] hover:text-[#1d1d1f] hover:bg-[#f0ebe1]'
                ]"
              />
              <span
                v-else
                v-html="link.label"
                class="px-3 py-1 text-xs text-[#86868b] opacity-50 cursor-not-allowed"
              />
            </template>
          </div>
        </div>
      </div>

    </div>

    <!-- ========================================== -->
    <!-- MODAL: ADD NEW CATEGORY                    -->
    <!-- ========================================== -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div 
          v-if="showCreateModal" 
          class="fixed inset-0 z-50 overflow-y-auto bg-black/60 backdrop-blur-xs flex items-center justify-center p-4"
          @click.self="showCreateModal = false"
        >
          <Transition
            appear
            enter-active-class="transform transition ease-out duration-200"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transform transition ease-in duration-150"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
          >
            <div 
              v-if="showCreateModal"
              class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-7 shadow-2xl border border-[#e0d9cc] space-y-5"
            >
              <div class="flex items-center justify-between">
                <div>
                  <h3 class="font-serif font-medium text-xl text-[#1d1d1f]">Add Product Category</h3>
                  <p class="text-xs text-[#6e6e73] mt-0.5">Create a store aisle and storefront collection</p>
                </div>
                <button 
                  type="button" 
                  @click="showCreateModal = false" 
                  class="text-[#86868b] hover:text-[#1d1d1f] cursor-pointer"
                >
                  <X class="w-5 h-5" />
                </button>
              </div>

              <form @submit.prevent="submitCreate" class="space-y-4">
                <!-- Name -->
                <div>
                  <label class="block text-xs font-semibold text-[#1d1d1f] mb-1">
                    Category Name <span class="text-rose-500">*</span>
                  </label>
                  <input 
                    v-model="createForm.name" 
                    @input="handleCreateNameInput"
                    type="text" 
                    placeholder="e.g. Organic Ghee & Oils" 
                    class="w-full px-3.5 py-2 text-xs bg-[#fbf9f5] border border-[#e0d9cc] rounded-xl focus:bg-white focus:outline-none focus:ring-3 focus:ring-[#1a1a1a]/10 focus:border-[#1a1a1a]"
                    required 
                  />
                  <p v-if="createForm.errors.name" class="text-[11px] text-rose-600 mt-1">{{ createForm.errors.name }}</p>
                </div>

                <!-- Slug & Hindi Title in 2 Cols -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div>
                    <label class="block text-xs font-semibold text-[#1d1d1f] mb-1">
                      URL Slug
                    </label>
                    <input 
                      v-model="createForm.slug" 
                      @input="createSlugManual = true"
                      type="text" 
                      placeholder="e.g. organic-ghee" 
                      class="w-full px-3.5 py-2 text-xs font-mono bg-[#fbf9f5] border border-[#e0d9cc] rounded-xl focus:bg-white focus:outline-none focus:ring-3 focus:ring-[#1a1a1a]/10 focus:border-[#1a1a1a]"
                    />
                    <p v-if="createForm.errors.slug" class="text-[11px] text-rose-600 mt-1">{{ createForm.errors.slug }}</p>
                  </div>

                  <div>
                    <label class="block text-xs font-semibold text-[#1d1d1f] mb-1">
                      Hindi Label
                    </label>
                    <input 
                      v-model="createForm.hindi_title" 
                      type="text" 
                      placeholder="e.g. देसी घी व तेल" 
                      class="w-full px-3.5 py-2 text-xs bg-[#fbf9f5] border border-[#e0d9cc] rounded-xl focus:bg-white focus:outline-none focus:ring-3 focus:ring-[#1a1a1a]/10 focus:border-[#1a1a1a]"
                    />
                    <p v-if="createForm.errors.hindi_title" class="text-[11px] text-rose-600 mt-1">{{ createForm.errors.hindi_title }}</p>
                  </div>
                </div>

                <!-- Description -->
                <div>
                  <label class="block text-xs font-semibold text-[#1d1d1f] mb-1">
                    Storefront Description
                  </label>
                  <textarea 
                    v-model="createForm.description" 
                    rows="2"
                    placeholder="Short summary displayed on category header and SEO..." 
                    class="w-full px-3.5 py-2 text-xs bg-[#fbf9f5] border border-[#e0d9cc] rounded-xl focus:bg-white focus:outline-none focus:ring-3 focus:ring-[#1a1a1a]/10 focus:border-[#1a1a1a] resize-none"
                  ></textarea>
                  <p v-if="createForm.errors.description" class="text-[11px] text-rose-600 mt-1">{{ createForm.errors.description }}</p>
                </div>

                <!-- Icon Selector -->
                <div>
                  <label class="block text-xs font-semibold text-[#1d1d1f] mb-1.5">
                    Category Icon
                  </label>
                  <div class="grid grid-cols-4 sm:grid-cols-6 gap-2">
                    <button
                      v-for="item in availableIcons"
                      :key="item.name"
                      type="button"
                      @click="createForm.icon = item.name"
                      :class="[
                        'p-2 rounded-xl border flex flex-col items-center justify-center gap-1 transition-all cursor-pointer text-center',
                        createForm.icon === item.name
                          ? 'border-[#1a1a1a] bg-[#1a1a1a] text-white shadow-2xs'
                          : 'border-[#e0d9cc] bg-[#fbf9f5] text-[#6e6e73] hover:border-[#a47a3c] hover:bg-white'
                      ]"
                    >
                      <component :is="item.icon" class="w-4 h-4" />
                      <span class="text-[9px] font-medium leading-tight truncate w-full">{{ item.name }}</span>
                    </button>
                  </div>
                </div>

                <!-- Image URL or File Upload -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                  <div>
                    <label class="block text-xs font-semibold text-[#1d1d1f] mb-1">
                      Banner Image URL
                    </label>
                    <div class="relative">
                      <LinkIcon class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-[#86868b]" />
                      <input 
                        v-model="createForm.image_url" 
                        type="text" 
                        placeholder="/images/products/ghee.jpg" 
                        class="w-full pl-8 pr-3 py-2 text-xs bg-[#fbf9f5] border border-[#e0d9cc] rounded-xl focus:bg-white focus:outline-none focus:ring-3 focus:ring-[#1a1a1a]/10 focus:border-[#1a1a1a]"
                      />
                    </div>
                  </div>

                  <div>
                    <label class="block text-xs font-semibold text-[#1d1d1f] mb-1">
                      Or Upload Image File
                    </label>
                    <label class="flex items-center gap-2 px-3 py-2 text-xs bg-[#fbf9f5] border border-[#e0d9cc] rounded-xl cursor-pointer hover:bg-white transition-colors truncate">
                      <Upload class="w-3.5 h-3.5 text-[#86868b] shrink-0" />
                      <span class="truncate text-[#6e6e73]">
                        {{ createForm.image_file ? createForm.image_file.name : 'Choose file...' }}
                      </span>
                      <input 
                        type="file" 
                        accept="image/*" 
                        class="hidden" 
                        @change="handleCreateImageSelect" 
                      />
                    </label>
                  </div>
                </div>

                <!-- Priority & Active Checkbox -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2 border-t border-[#e0d9cc]/60 items-center">
                  <div>
                    <label class="block text-xs font-semibold text-[#1d1d1f] mb-1">
                      Display Priority (Sort Order)
                    </label>
                    <input 
                      v-model="createForm.sort_order" 
                      type="number" 
                      min="0"
                      class="w-full px-3.5 py-1.5 text-xs bg-[#fbf9f5] border border-[#e0d9cc] rounded-xl focus:bg-white focus:outline-none focus:ring-3 focus:ring-[#1a1a1a]/10 focus:border-[#1a1a1a]"
                    />
                  </div>

                  <div class="flex items-center gap-2 pt-4">
                    <input 
                      v-model="createForm.is_active" 
                      type="checkbox" 
                      id="create_is_active" 
                      class="w-4 h-4 rounded text-[#1a1a1a] focus:ring-[#1a1a1a] border-[#e0d9cc]" 
                    />
                    <label for="create_is_active" class="text-xs text-[#1d1d1f] font-medium cursor-pointer">
                      Visible in Storefront navigation
                    </label>
                  </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-2 pt-3 border-t border-[#e0d9cc]">
                  <button 
                    type="button" 
                    @click="showCreateModal = false" 
                    class="px-4 py-2 text-xs font-medium text-[#6e6e73] hover:text-[#1d1d1f] hover:bg-[#f0ebe1] rounded-full transition-colors cursor-pointer"
                  >
                    Cancel
                  </button>
                  <button 
                    type="submit" 
                    :disabled="createForm.processing"
                    class="px-5 py-2 text-xs font-semibold bg-[#1a1a1a] hover:bg-black text-white rounded-full transition-all shadow-2xs hover:scale-[1.02] cursor-pointer disabled:opacity-50"
                  >
                    {{ createForm.processing ? 'Creating...' : 'Create Category' }}
                  </button>
                </div>
              </form>
            </div>
          </Transition>
        </div>
      </Transition>
    </Teleport>

    <!-- ========================================== -->
    <!-- MODAL: EDIT CATEGORY                       -->
    <!-- ========================================== -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div 
          v-if="showEditModal" 
          class="fixed inset-0 z-50 overflow-y-auto bg-black/60 backdrop-blur-xs flex items-center justify-center p-4"
          @click.self="showEditModal = false"
        >
          <Transition
            appear
            enter-active-class="transform transition ease-out duration-200"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transform transition ease-in duration-150"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
          >
            <div 
              v-if="showEditModal"
              class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-7 shadow-2xl border border-[#e0d9cc] space-y-5"
            >
              <div class="flex items-center justify-between">
                <div>
                  <h3 class="font-serif font-medium text-xl text-[#1d1d1f]">Edit Category</h3>
                  <p class="text-xs text-[#6e6e73] mt-0.5">Modify aisle parameters and store settings</p>
                </div>
                <button 
                  type="button" 
                  @click="showEditModal = false" 
                  class="text-[#86868b] hover:text-[#1d1d1f] cursor-pointer"
                >
                  <X class="w-5 h-5" />
                </button>
              </div>

              <form @submit.prevent="submitEdit" class="space-y-4">
                <!-- Name -->
                <div>
                  <label class="block text-xs font-semibold text-[#1d1d1f] mb-1">
                    Category Name <span class="text-rose-500">*</span>
                  </label>
                  <input 
                    v-model="editForm.name" 
                    type="text" 
                    class="w-full px-3.5 py-2 text-xs bg-[#fbf9f5] border border-[#e0d9cc] rounded-xl focus:bg-white focus:outline-none focus:ring-3 focus:ring-[#1a1a1a]/10 focus:border-[#1a1a1a]"
                    required 
                  />
                  <p v-if="editForm.errors.name" class="text-[11px] text-rose-600 mt-1">{{ editForm.errors.name }}</p>
                </div>

                <!-- Slug & Hindi Title in 2 Cols -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div>
                    <label class="block text-xs font-semibold text-[#1d1d1f] mb-1">
                      URL Slug <span class="text-rose-500">*</span>
                    </label>
                    <input 
                      v-model="editForm.slug" 
                      type="text" 
                      class="w-full px-3.5 py-2 text-xs font-mono bg-[#fbf9f5] border border-[#e0d9cc] rounded-xl focus:bg-white focus:outline-none focus:ring-3 focus:ring-[#1a1a1a]/10 focus:border-[#1a1a1a]"
                      required
                    />
                    <p v-if="editForm.errors.slug" class="text-[11px] text-rose-600 mt-1">{{ editForm.errors.slug }}</p>
                  </div>

                  <div>
                    <label class="block text-xs font-semibold text-[#1d1d1f] mb-1">
                      Hindi Label
                    </label>
                    <input 
                      v-model="editForm.hindi_title" 
                      type="text" 
                      placeholder="e.g. देसी घी" 
                      class="w-full px-3.5 py-2 text-xs bg-[#fbf9f5] border border-[#e0d9cc] rounded-xl focus:bg-white focus:outline-none focus:ring-3 focus:ring-[#1a1a1a]/10 focus:border-[#1a1a1a]"
                    />
                    <p v-if="editForm.errors.hindi_title" class="text-[11px] text-rose-600 mt-1">{{ editForm.errors.hindi_title }}</p>
                  </div>
                </div>

                <!-- Description -->
                <div>
                  <label class="block text-xs font-semibold text-[#1d1d1f] mb-1">
                    Storefront Description
                  </label>
                  <textarea 
                    v-model="editForm.description" 
                    rows="2"
                    placeholder="Short summary displayed on category header and SEO..." 
                    class="w-full px-3.5 py-2 text-xs bg-[#fbf9f5] border border-[#e0d9cc] rounded-xl focus:bg-white focus:outline-none focus:ring-3 focus:ring-[#1a1a1a]/10 focus:border-[#1a1a1a] resize-none"
                  ></textarea>
                  <p v-if="editForm.errors.description" class="text-[11px] text-rose-600 mt-1">{{ editForm.errors.description }}</p>
                </div>

                <!-- Icon Selector -->
                <div>
                  <label class="block text-xs font-semibold text-[#1d1d1f] mb-1.5">
                    Category Icon
                  </label>
                  <div class="grid grid-cols-4 sm:grid-cols-6 gap-2">
                    <button
                      v-for="item in availableIcons"
                      :key="item.name"
                      type="button"
                      @click="editForm.icon = item.name"
                      :class="[
                        'p-2 rounded-xl border flex flex-col items-center justify-center gap-1 transition-all cursor-pointer text-center',
                        editForm.icon === item.name
                          ? 'border-[#1a1a1a] bg-[#1a1a1a] text-white shadow-2xs'
                          : 'border-[#e0d9cc] bg-[#fbf9f5] text-[#6e6e73] hover:border-[#a47a3c] hover:bg-white'
                      ]"
                    >
                      <component :is="item.icon" class="w-4 h-4" />
                      <span class="text-[9px] font-medium leading-tight truncate w-full">{{ item.name }}</span>
                    </button>
                  </div>
                </div>

                <!-- Current Thumbnail & Image Upload / URL -->
                <div class="space-y-2 pt-1">
                  <div class="flex items-center gap-3">
                    <div 
                      v-if="editForm.image_url" 
                      class="w-12 h-12 rounded-xl border border-[#e0d9cc] overflow-hidden bg-[#f5eee2] shrink-0"
                    >
                      <img :src="editForm.image_url" alt="Current image" class="w-full h-full object-cover" />
                    </div>
                    <div class="flex-1">
                      <label class="block text-xs font-semibold text-[#1d1d1f] mb-1">
                        Image URL
                      </label>
                      <input 
                        v-model="editForm.image_url" 
                        type="text" 
                        class="w-full px-3.5 py-1.5 text-xs bg-[#fbf9f5] border border-[#e0d9cc] rounded-xl focus:bg-white focus:outline-none focus:ring-3 focus:ring-[#1a1a1a]/10 focus:border-[#1a1a1a]"
                      />
                    </div>
                  </div>

                  <div>
                    <label class="block text-xs font-semibold text-[#1d1d1f] mb-1">
                      Or Upload New File
                    </label>
                    <label class="flex items-center gap-2 px-3 py-2 text-xs bg-[#fbf9f5] border border-[#e0d9cc] rounded-xl cursor-pointer hover:bg-white transition-colors truncate">
                      <Upload class="w-3.5 h-3.5 text-[#86868b] shrink-0" />
                      <span class="truncate text-[#6e6e73]">
                        {{ editForm.image_file ? editForm.image_file.name : 'Choose replacement image...' }}
                      </span>
                      <input 
                        type="file" 
                        accept="image/*" 
                        class="hidden" 
                        @change="handleEditImageSelect" 
                      />
                    </label>
                  </div>
                </div>

                <!-- Priority & Active Checkbox -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2 border-t border-[#e0d9cc]/60 items-center">
                  <div>
                    <label class="block text-xs font-semibold text-[#1d1d1f] mb-1">
                      Display Priority (Sort Order)
                    </label>
                    <input 
                      v-model="editForm.sort_order" 
                      type="number" 
                      min="0"
                      class="w-full px-3.5 py-1.5 text-xs bg-[#fbf9f5] border border-[#e0d9cc] rounded-xl focus:bg-white focus:outline-none focus:ring-3 focus:ring-[#1a1a1a]/10 focus:border-[#1a1a1a]"
                    />
                  </div>

                  <div class="flex items-center gap-2 pt-4">
                    <input 
                      v-model="editForm.is_active" 
                      type="checkbox" 
                      id="edit_is_active" 
                      class="w-4 h-4 rounded text-[#1a1a1a] focus:ring-[#1a1a1a] border-[#e0d9cc]" 
                    />
                    <label for="edit_is_active" class="text-xs text-[#1d1d1f] font-medium cursor-pointer">
                      Visible in Storefront navigation
                    </label>
                  </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-2 pt-3 border-t border-[#e0d9cc]">
                  <button 
                    type="button" 
                    @click="showEditModal = false" 
                    class="px-4 py-2 text-xs font-medium text-[#6e6e73] hover:text-[#1d1d1f] hover:bg-[#f0ebe1] rounded-full transition-colors cursor-pointer"
                  >
                    Cancel
                  </button>
                  <button 
                    type="submit" 
                    :disabled="editForm.processing"
                    class="px-5 py-2 text-xs font-semibold bg-[#1a1a1a] hover:bg-black text-white rounded-full transition-all shadow-2xs hover:scale-[1.02] cursor-pointer disabled:opacity-50"
                  >
                    {{ editForm.processing ? 'Saving...' : 'Save Changes' }}
                  </button>
                </div>
              </form>
            </div>
          </Transition>
        </div>
      </Transition>
    </Teleport>

    <!-- ========================================== -->
    <!-- MODAL: DELETE CONFIRMATION                 -->
    <!-- ========================================== -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div 
          v-if="showDeleteModal" 
          class="fixed inset-0 z-50 overflow-y-auto bg-black/60 backdrop-blur-xs flex items-center justify-center p-4"
          @click.self="showDeleteModal = false"
        >
          <Transition
            appear
            enter-active-class="transform transition ease-out duration-200"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transform transition ease-in duration-150"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
          >
            <div 
              v-if="showDeleteModal && categoryToDelete"
              class="bg-white rounded-3xl max-w-sm w-full p-6 shadow-2xl border border-[#e0d9cc] space-y-4"
            >
              <div class="w-12 h-12 rounded-full bg-rose-50 text-rose-600 flex items-center justify-center mx-auto">
                <Trash2 class="w-6 h-6" />
              </div>

              <div class="text-center">
                <h3 class="font-serif font-medium text-lg text-[#1d1d1f]">
                  Delete Category?
                </h3>
                <p class="text-xs text-[#6e6e73] mt-1.5 leading-relaxed">
                  Are you sure you want to delete <strong class="text-[#1d1d1f]">{{ categoryToDelete.name }}</strong>?
                </p>
                <div class="mt-3 p-3 bg-[#fbf9f5] border border-[#e0d9cc] rounded-xl text-left text-[11px] text-[#6e6e73] space-y-1">
                  <p class="font-medium text-[#1d1d1f]">Automatic Safety Protection:</p>
                  <p>Any items in this category will be smoothly reassigned to the default <strong class="text-[#1a1a1a]">Pantry & Groceries</strong> collection so no products become orphaned.</p>
                </div>
              </div>

              <div class="flex items-center gap-2 pt-2">
                <button 
                  type="button" 
                  @click="showDeleteModal = false" 
                  class="flex-1 py-2 text-xs font-medium text-[#6e6e73] hover:text-[#1d1d1f] hover:bg-[#f0ebe1] rounded-full transition-colors cursor-pointer"
                >
                  Cancel
                </button>
                <button 
                  type="button" 
                  @click="confirmDelete" 
                  :disabled="isDeleting"
                  class="flex-1 py-2 text-xs font-semibold bg-rose-600 hover:bg-rose-700 text-white rounded-full transition-all shadow-2xs cursor-pointer disabled:opacity-50"
                >
                  {{ isDeleting ? 'Deleting...' : 'Confirm Delete' }}
                </button>
              </div>
            </div>
          </Transition>
        </div>
      </Transition>
    </Teleport>

  </AdminLayout>
</template>
