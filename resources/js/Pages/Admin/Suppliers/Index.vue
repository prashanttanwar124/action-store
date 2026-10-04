<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import {
  Truck,
  Search,
  Plus,
  Edit2,
  Trash2,
  CheckCircle2,
  XCircle,
  Package,
  Layers,
  Clock,
  MapPin,
  Phone,
  Mail,
  User,
  ArrowUpDown,
  X,
  ExternalLink,
  Sparkles,
  Building2,
  FileText,
  CreditCard,
  AlertCircle
} from 'lucide-vue-next';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';

const props = defineProps({
  suppliers: {
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
      sort: 'name',
    }),
  },
});

// Search, Filter & Sort State
const searchInput = ref(props.filters.search || '');
const activeStatus = ref(props.filters.status || 'all');
const activeSort = ref(props.filters.sort || 'name');
let debounceTimer = null;

const applyFilters = (page = 1) => {
  const params = {};
  if (searchInput.value.trim()) params.search = searchInput.value.trim();
  if (activeStatus.value !== 'all') params.status = activeStatus.value;
  if (activeSort.value !== 'name') params.sort = activeSort.value;
  if (page > 1) params.page = page;

  router.get(route('admin.suppliers.index'), params, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const handleSearchInput = () => {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    applyFilters(1);
  }, 350);
};

const clearSearch = () => {
  searchInput.value = '';
  applyFilters(1);
};

const handleStatusFilter = (status) => {
  activeStatus.value = status;
  applyFilters(1);
};

const handleSortChange = (val) => {
  if (val && typeof val === 'string') {
    activeSort.value = val;
    applyFilters(1);
  }
};

// Drawer Modal State for Add & Edit
const isDrawerOpen = ref(false);
const editingSupplier = ref(null);

const supplierForm = useForm({
  name: '',
  code: '',
  contact_person: '',
  email: '',
  phone: '',
  address: '',
  city: '',
  state: '',
  country: 'USA',
  postal_code: '',
  lead_time_days: 2,
  payment_terms: 'Net 30',
  status: 'active',
  notes: '',
});

const openCreateDrawer = () => {
  editingSupplier.value = null;
  supplierForm.reset();
  supplierForm.clearErrors();
  supplierForm.country = 'USA';
  supplierForm.lead_time_days = 2;
  supplierForm.payment_terms = 'Net 30';
  supplierForm.status = 'active';
  isDrawerOpen.value = true;
};

const openEditDrawer = (supplier) => {
  editingSupplier.value = supplier;
  supplierForm.clearErrors();
  supplierForm.name = supplier.name || '';
  supplierForm.code = supplier.code || '';
  supplierForm.contact_person = supplier.contact_person || '';
  supplierForm.email = supplier.email || '';
  supplierForm.phone = supplier.phone || '';
  supplierForm.address = supplier.address || '';
  supplierForm.city = supplier.city || '';
  supplierForm.state = supplier.state || '';
  supplierForm.country = supplier.country || 'USA';
  supplierForm.postal_code = supplier.postal_code || '';
  supplierForm.lead_time_days = supplier.lead_time_days ?? 2;
  supplierForm.payment_terms = supplier.payment_terms || 'Net 30';
  supplierForm.status = supplier.status || 'active';
  supplierForm.notes = supplier.notes || '';
  isDrawerOpen.value = true;
};

const closeDrawer = () => {
  isDrawerOpen.value = false;
  editingSupplier.value = null;
  supplierForm.reset();
  supplierForm.clearErrors();
};

const submitSupplier = () => {
  if (editingSupplier.value) {
    supplierForm.post(route('admin.suppliers.update', editingSupplier.value.id), {
      preserveScroll: true,
      onSuccess: () => {
        closeDrawer();
      },
    });
  } else {
    supplierForm.post(route('admin.suppliers.store'), {
      preserveScroll: true,
      onSuccess: () => {
        closeDrawer();
      },
    });
  }
};

// Toggle status directly from table
const toggleSupplierStatus = (supplier) => {
  router.post(route('admin.suppliers.toggle', supplier.id), {}, {
    preserveScroll: true,
  });
};

// Delete Confirmation Modal State
const isDeleteModalOpen = ref(false);
const supplierToDelete = ref(null);

const confirmDelete = (supplier) => {
  supplierToDelete.value = supplier;
  isDeleteModalOpen.value = true;
};

const executeDelete = () => {
  if (!supplierToDelete.value) return;
  router.delete(route('admin.suppliers.destroy', supplierToDelete.value.id), {
    preserveScroll: true,
    onSuccess: () => {
      isDeleteModalOpen.value = false;
      supplierToDelete.value = null;
    },
  });
};
</script>

<template>
  <AdminLayout title="Suppliers & Vendors — Masala Mart Admin">
    <Head title="Suppliers & Vendors — Masala Mart Admin" />

    <div class="space-y-6 max-w-7xl mx-auto pb-16">
      
      <!-- TOP BANNER / HEADER -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white border border-[#dfd6c8] p-5 sm:p-6 rounded-2xl sm:rounded-3xl shadow-2xs">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <span class="text-[11px] font-mono uppercase bg-stone-900 text-white px-2.5 py-0.5 rounded-full font-bold tracking-wider">
              Procurement & Supply Chain
            </span>
            <span class="text-[11px] text-amber-900 bg-amber-50 border border-amber-200/80 px-2.5 py-0.5 rounded-full font-bold">
              Direct Sourcing
            </span>
          </div>
          <h1 class="text-2xl sm:text-3xl font-serif font-bold text-stone-900 tracking-tight">
            Suppliers &amp; Vendors
          </h1>
          <p class="text-xs sm:text-sm text-stone-600 font-normal mt-1 max-w-2xl leading-relaxed">
            Manage farm-direct dairies, stone mills, spice cooperatives, and artisan producers supplying Masala Mart's inventory.
          </p>
        </div>

        <!-- Add Supplier Button -->
        <button
          type="button"
          @click="openCreateDrawer"
          class="inline-flex items-center gap-2 px-5 py-2.5 bg-stone-900 hover:bg-black text-white text-xs font-bold rounded-full transition-all shadow-xs active:scale-[0.98] cursor-pointer self-start md:self-center shrink-0"
        >
          <Plus class="w-4 h-4 stroke-[2.5]" />
          <span>Register Supplier</span>
        </button>
      </div>

      <!-- KPI METRIC CARDS -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3.5 sm:gap-4">
        
        <!-- Total Suppliers -->
        <div class="bg-white border border-[#dfd6c8] rounded-2xl p-4 sm:p-5 shadow-2xs flex flex-col justify-between">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-stone-500 uppercase tracking-wider">Total Suppliers</span>
            <div class="w-8 h-8 rounded-xl bg-stone-100 flex items-center justify-center text-stone-800">
              <Truck class="w-4 h-4" />
            </div>
          </div>
          <div class="mt-3">
            <div class="text-2xl sm:text-3xl font-serif font-bold text-stone-900">
              {{ metrics.total_suppliers }}
            </div>
            <p class="text-[11px] text-stone-500 font-medium mt-0.5">Active vendor accounts</p>
          </div>
        </div>

        <!-- Active Suppliers -->
        <div class="bg-white border border-[#dfd6c8] rounded-2xl p-4 sm:p-5 shadow-2xs flex flex-col justify-between">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-stone-500 uppercase tracking-wider">Active Suppliers</span>
            <div class="w-8 h-8 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-700">
              <CheckCircle2 class="w-4 h-4" />
            </div>
          </div>
          <div class="mt-3">
            <div class="text-2xl sm:text-3xl font-serif font-bold text-emerald-800">
              {{ metrics.active_suppliers }}
            </div>
            <p class="text-[11px] text-stone-500 font-medium mt-0.5">Fulfilling orders currently</p>
          </div>
        </div>

        <!-- Linked Products -->
        <div class="bg-white border border-[#dfd6c8] rounded-2xl p-4 sm:p-5 shadow-2xs flex flex-col justify-between">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-stone-500 uppercase tracking-wider">Sourced Products</span>
            <div class="w-8 h-8 rounded-xl bg-amber-50 flex items-center justify-center text-amber-800">
              <Package class="w-4 h-4" />
            </div>
          </div>
          <div class="mt-3">
            <div class="text-2xl sm:text-3xl font-serif font-bold text-stone-900">
              {{ metrics.total_supplied_products }}
            </div>
            <p class="text-[11px] text-stone-500 font-medium mt-0.5">Assigned inventory SKUs</p>
          </div>
        </div>

        <!-- Avg Lead Time -->
        <div class="bg-white border border-[#dfd6c8] rounded-2xl p-4 sm:p-5 shadow-2xs flex flex-col justify-between">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-stone-500 uppercase tracking-wider">Avg. Lead Time</span>
            <div class="w-8 h-8 rounded-xl bg-stone-100 flex items-center justify-center text-stone-800">
              <Clock class="w-4 h-4" />
            </div>
          </div>
          <div class="mt-3">
            <div class="text-2xl sm:text-3xl font-serif font-bold text-stone-900">
              {{ metrics.avg_lead_time }} <span class="text-sm font-sans font-medium text-stone-600">days</span>
            </div>
            <p class="text-[11px] text-stone-500 font-medium mt-0.5">Restock turnaround time</p>
          </div>
        </div>

      </div>

      <!-- SEARCH, FILTERS & SORT CONTROLS BAR -->
      <div class="bg-white border border-[#dfd6c8] rounded-2xl p-3 sm:p-4 shadow-2xs flex flex-col md:flex-row md:items-center justify-between gap-3">
        
        <!-- Omni-Search -->
        <div class="relative flex-1 max-w-md">
          <Search class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-stone-400 pointer-events-none" />
          <input
            v-model="searchInput"
            @input="handleSearchInput"
            type="text"
            placeholder="Search suppliers by name, code, contact person, city…"
            class="w-full h-10 pl-10 pr-9 bg-stone-50 focus:bg-white border border-[#e0d9cc] focus:border-[#1a1a1a] focus:ring-3 focus:ring-[#1a1a1a]/10 focus:outline-none rounded-xl text-xs font-medium text-stone-900 placeholder:text-stone-500 transition-all shadow-2xs"
          />
          <button
            v-if="searchInput"
            type="button"
            @click="clearSearch"
            class="absolute right-2.5 top-1/2 -translate-y-1/2 p-1 text-stone-400 hover:text-stone-800 rounded-full hover:bg-stone-200 transition-colors"
          >
            <X class="w-3.5 h-3.5" />
          </button>
        </div>

        <!-- Right Side: Status Filter Pills & Sort Dropdown -->
        <div class="flex items-center gap-2 overflow-x-auto no-scrollbar shrink-0">
          
          <!-- Status Pills -->
          <div class="inline-flex items-center p-0.5 bg-stone-100 border border-stone-200 rounded-full text-xs font-bold">
            <button
              type="button"
              @click="handleStatusFilter('all')"
              :class="[
                'px-3 py-1 rounded-full transition-colors cursor-pointer',
                activeStatus === 'all'
                  ? 'bg-stone-900 text-white shadow-xs'
                  : 'text-stone-600 hover:text-stone-900'
              ]"
            >
              All ({{ metrics.total_suppliers }})
            </button>
            <button
              type="button"
              @click="handleStatusFilter('active')"
              :class="[
                'px-3 py-1 rounded-full transition-colors cursor-pointer',
                activeStatus === 'active'
                  ? 'bg-stone-900 text-white shadow-xs'
                  : 'text-stone-600 hover:text-stone-900'
              ]"
            >
              Active ({{ metrics.active_suppliers }})
            </button>
            <button
              type="button"
              @click="handleStatusFilter('inactive')"
              :class="[
                'px-3 py-1 rounded-full transition-colors cursor-pointer',
                activeStatus === 'inactive'
                  ? 'bg-stone-900 text-white shadow-xs'
                  : 'text-stone-600 hover:text-stone-900'
              ]"
            >
              Inactive
            </button>
          </div>

          <!-- Sort Select -->
          <Select :model-value="activeSort" @update:model-value="handleSortChange">
            <SelectTrigger class="h-9 w-auto rounded-full border border-stone-300 bg-white hover:bg-stone-50 px-3 text-xs font-bold text-stone-900 shadow-2xs gap-1.5 focus:border-[#1a1a1a] focus:ring-3 focus:ring-[#1a1a1a]/10">
              <div class="flex items-center gap-1.5 mr-0.5">
                <ArrowUpDown class="w-3.5 h-3.5 text-stone-500 shrink-0" />
                <SelectValue placeholder="Sort" />
              </div>
            </SelectTrigger>
            <SelectContent class="rounded-2xl border border-stone-200 bg-white shadow-xl min-w-[200px] p-1.5 z-50">
              <SelectItem value="name" class="rounded-xl text-xs py-2 font-medium">Name (A to Z)</SelectItem>
              <SelectItem value="products_count" class="rounded-xl text-xs py-2 font-medium">Most Products Supplied</SelectItem>
              <SelectItem value="lead_time" class="rounded-xl text-xs py-2 font-medium">Fastest Lead Time</SelectItem>
              <SelectItem value="latest" class="rounded-xl text-xs py-2 font-medium">Recently Registered</SelectItem>
            </SelectContent>
          </Select>

        </div>

      </div>

      <!-- SUPPLIERS TABLE & LISTING -->
      <div class="bg-white border border-[#dfd6c8] rounded-2xl sm:rounded-3xl shadow-2xs overflow-hidden">
        
        <div v-if="suppliers.data && suppliers.data.length > 0" class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            
            <thead class="bg-stone-50 border-b border-stone-200 text-stone-600 uppercase font-mono text-[11px] tracking-wider">
              <tr>
                <th class="py-3.5 px-4 font-bold">Supplier Info</th>
                <th class="py-3.5 px-4 font-bold">Contact Person</th>
                <th class="py-3.5 px-4 font-bold">Location</th>
                <th class="py-3.5 px-4 font-bold">Lead Time &amp; Terms</th>
                <th class="py-3.5 px-4 font-bold">Supplied SKUs</th>
                <th class="py-3.5 px-4 font-bold">Status</th>
                <th class="py-3.5 px-4 font-bold text-right">Actions</th>
              </tr>
            </thead>

            <tbody class="divide-y divide-stone-100">
              <tr 
                v-for="s in suppliers.data" 
                :key="s.id"
                class="hover:bg-stone-50/70 transition-colors group"
              >
                <!-- Supplier Name & Code -->
                <td class="py-4 px-4">
                  <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-amber-50 border border-amber-200 flex items-center justify-center text-amber-900 font-bold font-serif shrink-0">
                      {{ s.name.charAt(0) }}
                    </div>
                    <div>
                      <div class="font-bold text-stone-900 text-sm group-hover:text-amber-900 transition-colors">
                        {{ s.name }}
                      </div>
                      <div class="flex items-center gap-1.5 mt-0.5">
                        <span class="font-mono text-[10.5px] bg-stone-100 text-stone-700 px-1.5 py-0.2 rounded border border-stone-200 font-semibold">
                          {{ s.code }}
                        </span>
                        <span v-if="s.notes" class="text-[11px] text-stone-500 max-w-[170px] truncate" :title="s.notes">
                          · {{ s.notes }}
                        </span>
                      </div>
                    </div>
                  </div>
                </td>

                <!-- Contact Details -->
                <td class="py-4 px-4">
                  <div class="font-semibold text-stone-900 flex items-center gap-1.5">
                    <User class="w-3.5 h-3.5 text-stone-400" />
                    <span>{{ s.contact_person || 'Direct Office' }}</span>
                  </div>
                  <div class="flex flex-col gap-0.5 mt-1 text-[11px] text-stone-600">
                    <a v-if="s.phone" :href="'tel:' + s.phone" class="hover:text-amber-900 hover:underline flex items-center gap-1">
                      <Phone class="w-3 h-3 text-stone-400" />
                      <span>{{ s.phone }}</span>
                    </a>
                    <a v-if="s.email" :href="'mailto:' + s.email" class="hover:text-amber-900 hover:underline flex items-center gap-1 truncate max-w-[160px]">
                      <Mail class="w-3 h-3 text-stone-400" />
                      <span>{{ s.email }}</span>
                    </a>
                  </div>
                </td>

                <!-- Location -->
                <td class="py-4 px-4">
                  <div class="flex items-center gap-1.5 font-semibold text-stone-900">
                    <MapPin class="w-3.5 h-3.5 text-stone-400" />
                    <span>{{ s.city || 'Standard' }}<span v-if="s.state">, {{ s.state }}</span></span>
                  </div>
                  <div class="text-[11px] text-stone-500 mt-0.5 truncate max-w-[140px]" :title="s.address">
                    {{ s.address || s.country || 'USA' }}
                  </div>
                </td>

                <!-- Lead Time & Payment Terms -->
                <td class="py-4 px-4">
                  <div class="inline-flex items-center gap-1 bg-stone-100 text-stone-800 border border-stone-200 px-2 py-0.5 rounded-md font-bold text-[11px]">
                    <Clock class="w-3 h-3 text-stone-500" />
                    <span>{{ s.lead_time_days }} {{ s.lead_time_days === 1 ? 'day' : 'days' }}</span>
                  </div>
                  <div class="text-[11px] font-medium text-stone-600 mt-1 flex items-center gap-1">
                    <CreditCard class="w-3 h-3 text-stone-400" />
                    <span>{{ s.payment_terms }}</span>
                  </div>
                </td>

                <!-- Supplied Products -->
                <td class="py-4 px-4">
                  <div class="flex items-center gap-1.5">
                    <span 
                      class="px-2 py-0.5 rounded-full text-[11px] font-bold border"
                      :class="s.products_count > 0 ? 'bg-amber-50 text-amber-900 border-amber-200' : 'bg-stone-100 text-stone-600 border-stone-200'"
                    >
                      {{ s.products_count }} {{ s.products_count === 1 ? 'product' : 'products' }}
                    </span>
                  </div>
                  <!-- Product Image Thumbnails -->
                  <div v-if="s.products && s.products.length > 0" class="flex -space-x-1.5 overflow-hidden mt-1.5">
                    <img
                      v-for="p in s.products.slice(0, 3)"
                      :key="p.id"
                      :src="p.image"
                      :alt="p.name"
                      class="inline-block h-6 w-6 rounded-full ring-2 ring-white object-cover bg-stone-100"
                      :title="p.name + ' · $' + p.price"
                    />
                    <span v-if="s.products_count > 3" class="inline-flex items-center justify-center h-6 w-6 rounded-full bg-stone-200 text-[9.5px] font-bold text-stone-700 ring-2 ring-white">
                      +{{ s.products_count - 3 }}
                    </span>
                  </div>
                </td>

                <!-- Status Badge (Interactive Toggle) -->
                <td class="py-4 px-4">
                  <button
                    type="button"
                    @click="toggleSupplierStatus(s)"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold border transition-all cursor-pointer shadow-2xs hover:scale-105 active:scale-95"
                    :class="s.status === 'active'
                      ? 'bg-emerald-50 text-emerald-800 border-emerald-200 hover:bg-emerald-100'
                      : 'bg-stone-100 text-stone-600 border-stone-300 hover:bg-stone-200'"
                    :title="'Click to mark ' + (s.status === 'active' ? 'inactive' : 'active')"
                  >
                    <span 
                      class="w-1.5 h-1.5 rounded-full" 
                      :class="s.status === 'active' ? 'bg-emerald-600' : 'bg-stone-400'"
                    ></span>
                    <span>{{ s.status === 'active' ? 'Active' : 'Inactive' }}</span>
                  </button>
                </td>

                <!-- Actions -->
                <td class="py-4 px-4 text-right whitespace-nowrap">
                  <div class="flex items-center justify-end gap-1.5">
                    <button
                      type="button"
                      @click="openEditDrawer(s)"
                      class="p-1.5 text-stone-600 hover:text-stone-900 hover:bg-stone-100 rounded-lg transition-colors cursor-pointer"
                      title="Edit Supplier"
                    >
                      <Edit2 class="w-4 h-4 stroke-[2]" />
                    </button>
                    <button
                      type="button"
                      @click="confirmDelete(s)"
                      class="p-1.5 text-red-600 hover:text-red-800 hover:bg-red-50 rounded-lg transition-colors cursor-pointer"
                      title="Delete Supplier"
                    >
                      <Trash2 class="w-4 h-4 stroke-[2]" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>

          </table>
        </div>

        <!-- Empty State -->
        <div v-else class="p-10 sm:p-16 text-center space-y-3">
          <div class="w-14 h-14 rounded-full bg-stone-100 flex items-center justify-center mx-auto text-stone-500">
            <Truck class="w-6 h-6 stroke-[1.8]" />
          </div>
          <h3 class="text-base sm:text-lg font-serif font-bold text-stone-900">
            No suppliers found
          </h3>
          <p class="text-xs text-stone-500 max-w-sm mx-auto leading-relaxed">
            We couldn't find any suppliers matching your search filters. Try clearing your filters or register a new vendor.
          </p>
          <div class="pt-2">
            <button
              type="button"
              @click="openCreateDrawer"
              class="px-5 py-2 bg-stone-900 text-white rounded-full text-xs font-bold hover:bg-black transition-colors shadow-xs cursor-pointer"
            >
              Register First Supplier
            </button>
          </div>
        </div>

      </div>

    </div>

    <!-- ======================================================== -->
    <!-- SLIDE-OVER DRAWER FOR CREATE & EDIT SUPPLIER             -->
    <!-- ======================================================== -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition-opacity duration-300 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition-opacity duration-200 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div 
          v-if="isDrawerOpen" 
          class="fixed inset-0 bg-black/40 backdrop-blur-xs z-50 transition-opacity" 
          @click="closeDrawer"
        />
      </Transition>

      <Transition
        enter-active-class="transition-transform duration-300 ease-out"
        enter-from-class="translate-x-full"
        enter-to-class="translate-x-0"
        leave-active-class="transition-transform duration-200 ease-in"
        leave-from-class="translate-x-0"
        leave-to-class="translate-x-full"
      >
        <div 
          v-if="isDrawerOpen" 
          class="fixed inset-y-0 right-0 w-full max-w-xl bg-white shadow-2xl z-50 flex flex-col border-l border-stone-200"
        >
          <!-- Drawer Header -->
          <div class="flex items-center justify-between px-6 py-5 border-b border-stone-200 bg-stone-50/70">
            <div>
              <span class="text-[10.5px] font-mono uppercase bg-amber-100 text-amber-900 px-2 py-0.5 rounded font-bold">
                {{ editingSupplier ? 'Update Vendor' : 'New Vendor Registration' }}
              </span>
              <h2 class="text-xl font-serif font-bold text-stone-900 mt-1">
                {{ editingSupplier ? editingSupplier.name : 'Register Supplier' }}
              </h2>
            </div>
            <button
              type="button"
              @click="closeDrawer"
              class="p-2 text-stone-500 hover:text-stone-900 rounded-full hover:bg-stone-200/60 transition-colors cursor-pointer"
            >
              <X class="w-5 h-5" />
            </button>
          </div>

          <!-- Drawer Form Body -->
          <form @submit.prevent="submitSupplier" class="flex-1 overflow-y-auto p-6 space-y-5">
            
            <!-- Section 1: Business Identity -->
            <div class="space-y-4">
              <h3 class="text-xs font-bold text-stone-500 uppercase tracking-wider border-b border-stone-200 pb-1.5">
                1. Supplier Information
              </h3>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <!-- Name -->
                <div class="sm:col-span-2">
                  <Label class="block mb-1 text-xs font-bold">
                    Company / Farm Name <span class="text-red-500">*</span>
                  </Label>
                  <Input 
                    v-model="supplierForm.name"
                    type="text"
                    required
                    placeholder="e.g. Amrit Farm Dairy or Punjab Heritage Mills"
                    class="bg-stone-50 rounded-xl text-xs font-medium"
                  />
                  <span v-if="supplierForm.errors.name" class="text-xs text-red-600 font-semibold mt-0.5 block">
                    {{ supplierForm.errors.name }}
                  </span>
                </div>

                <!-- Code -->
                <div>
                  <Label class="block mb-1 text-xs font-bold">
                    Supplier Code
                  </Label>
                  <Input 
                    v-model="supplierForm.code"
                    type="text"
                    placeholder="Auto-generated (e.g. SUP-006)"
                    class="bg-stone-50 rounded-xl text-xs font-mono font-medium"
                  />
                  <span class="text-[10.5px] text-stone-400 mt-0.5 block">Leave empty to auto-generate</span>
                  <span v-if="supplierForm.errors.code" class="text-xs text-red-600 font-semibold mt-0.5 block">
                    {{ supplierForm.errors.code }}
                  </span>
                </div>

                <!-- Status -->
                <div>
                  <Label class="block mb-1 text-xs font-bold">
                    Vendor Status <span class="text-red-500">*</span>
                  </Label>
                  <Select v-model="supplierForm.status">
                    <SelectTrigger class="bg-stone-50 rounded-xl text-xs font-semibold h-10">
                      <SelectValue placeholder="Select status" />
                    </SelectTrigger>
                    <SelectContent class="rounded-xl border border-stone-200 bg-white shadow-xl z-50">
                      <SelectItem value="active" class="text-xs py-2 font-semibold text-emerald-800">Active (Accepting Orders)</SelectItem>
                      <SelectItem value="inactive" class="text-xs py-2 font-semibold text-stone-600">Inactive (Paused)</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </div>
            </div>

            <!-- Section 2: Contact Person & Communication -->
            <div class="space-y-4 pt-2">
              <h3 class="text-xs font-bold text-stone-500 uppercase tracking-wider border-b border-stone-200 pb-1.5">
                2. Contact &amp; Representative
              </h3>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <!-- Contact Person -->
                <div class="sm:col-span-2">
                  <Label class="block mb-1 text-xs font-bold">
                    Contact Person Name
                  </Label>
                  <Input 
                    v-model="supplierForm.contact_person"
                    type="text"
                    placeholder="e.g. Harpreet Singh"
                    class="bg-stone-50 rounded-xl text-xs font-medium"
                  />
                </div>

                <!-- Email -->
                <div>
                  <Label class="block mb-1 text-xs font-bold">
                    Official Email
                  </Label>
                  <Input 
                    v-model="supplierForm.email"
                    type="email"
                    placeholder="orders@supplier.com"
                    class="bg-stone-50 rounded-xl text-xs font-medium"
                  />
                  <span v-if="supplierForm.errors.email" class="text-xs text-red-600 font-semibold mt-0.5 block">
                    {{ supplierForm.errors.email }}
                  </span>
                </div>

                <!-- Phone -->
                <div>
                  <Label class="block mb-1 text-xs font-bold">
                    Phone / WhatsApp
                  </Label>
                  <Input 
                    v-model="supplierForm.phone"
                    type="text"
                    placeholder="+1 (555) 234-5678"
                    class="bg-stone-50 rounded-xl text-xs font-medium"
                  />
                </div>
              </div>
            </div>

            <!-- Section 3: Physical Location -->
            <div class="space-y-4 pt-2">
              <h3 class="text-xs font-bold text-stone-500 uppercase tracking-wider border-b border-stone-200 pb-1.5">
                3. Address &amp; Warehouse Location
              </h3>

              <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <!-- Street Address -->
                <div class="sm:col-span-3">
                  <Label class="block mb-1 text-xs font-bold">
                    Street Address
                  </Label>
                  <Input 
                    v-model="supplierForm.address"
                    type="text"
                    placeholder="e.g. 84 Meadow Road, Suite 2"
                    class="bg-stone-50 rounded-xl text-xs font-medium"
                  />
                </div>

                <!-- City -->
                <div>
                  <Label class="block mb-1 text-xs font-bold">City</Label>
                  <Input 
                    v-model="supplierForm.city"
                    type="text"
                    placeholder="Edison"
                    class="bg-stone-50 rounded-xl text-xs font-medium"
                  />
                </div>

                <!-- State -->
                <div>
                  <Label class="block mb-1 text-xs font-bold">State</Label>
                  <Input 
                    v-model="supplierForm.state"
                    type="text"
                    placeholder="NJ"
                    class="bg-stone-50 rounded-xl text-xs font-medium"
                  />
                </div>

                <!-- Postal Code -->
                <div>
                  <Label class="block mb-1 text-xs font-bold">Postal Code</Label>
                  <Input 
                    v-model="supplierForm.postal_code"
                    type="text"
                    placeholder="08817"
                    class="bg-stone-50 rounded-xl text-xs font-medium"
                  />
                </div>
              </div>
            </div>

            <!-- Section 4: Commercial & Fulfillment Terms -->
            <div class="space-y-4 pt-2">
              <h3 class="text-xs font-bold text-stone-500 uppercase tracking-wider border-b border-stone-200 pb-1.5">
                4. Fulfillment &amp; Commercial Terms
              </h3>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <!-- Lead Time (Days) -->
                <div>
                  <Label class="block mb-1 text-xs font-bold">
                    Lead Time (Days)
                  </Label>
                  <Input 
                    v-model.number="supplierForm.lead_time_days"
                    type="number"
                    min="0"
                    placeholder="2"
                    class="bg-stone-50 rounded-xl text-xs font-medium"
                  />
                  <span class="text-[10.5px] text-stone-400 mt-0.5 block">Estimated days for delivery</span>
                </div>

                <!-- Payment Terms -->
                <div>
                  <Label class="block mb-1 text-xs font-bold">
                    Payment Terms
                  </Label>
                  <Select v-model="supplierForm.payment_terms">
                    <SelectTrigger class="bg-stone-50 rounded-xl text-xs font-semibold h-10">
                      <SelectValue placeholder="Payment Terms" />
                    </SelectTrigger>
                    <SelectContent class="rounded-xl border border-stone-200 bg-white shadow-xl z-50">
                      <SelectItem value="Net 15" class="text-xs py-2 font-medium">Net 15 Days</SelectItem>
                      <SelectItem value="Net 30" class="text-xs py-2 font-medium">Net 30 Days</SelectItem>
                      <SelectItem value="Net 60" class="text-xs py-2 font-medium">Net 60 Days</SelectItem>
                      <SelectItem value="Weekly" class="text-xs py-2 font-medium">Weekly Invoicing</SelectItem>
                      <SelectItem value="Cash on Delivery" class="text-xs py-2 font-medium">Cash on Delivery (COD)</SelectItem>
                      <SelectItem value="Prepaid / Advance" class="text-xs py-2 font-medium">Prepaid / Advance</SelectItem>
                    </SelectContent>
                  </Select>
                </div>

                <!-- Notes / Certifications -->
                <div class="sm:col-span-2">
                  <Label class="block mb-1 text-xs font-bold">
                    Sourcing Notes &amp; Quality Certifications
                  </Label>
                  <Textarea 
                    v-model="supplierForm.notes"
                    rows="3"
                    placeholder="e.g. Certified organic dairy, morning delivery route, FSSAI / FDA approved batch codes..."
                    class="bg-stone-50 rounded-xl text-xs font-medium"
                  />
                </div>
              </div>
            </div>

            <!-- Section 5: Associated Sourced Products (When Editing) -->
            <div v-if="editingSupplier && editingSupplier.products && editingSupplier.products.length > 0" class="space-y-2 pt-2">
              <h3 class="text-xs font-bold text-stone-500 uppercase tracking-wider border-b border-stone-200 pb-1.5 flex items-center justify-between">
                <span>Linked Inventory SKUs ({{ editingSupplier.products_count }})</span>
                <Link :href="route('admin.products.index')" class="text-amber-800 hover:underline normal-case flex items-center gap-1">
                  <span>Manage Products</span>
                  <ExternalLink class="w-3 h-3" />
                </Link>
              </h3>

              <div class="divide-y divide-stone-100 bg-stone-50 rounded-xl border border-stone-200 p-2 space-y-1">
                <div 
                  v-for="prod in editingSupplier.products" 
                  :key="prod.id"
                  class="flex items-center justify-between p-2 rounded-lg hover:bg-white transition-colors"
                >
                  <div class="flex items-center gap-2.5">
                    <img :src="prod.image" :alt="prod.name" class="w-8 h-8 rounded-lg object-cover border border-stone-200 bg-white" />
                    <div>
                      <div class="font-bold text-stone-900 text-xs">{{ prod.name }}</div>
                      <div class="text-[10.5px] text-stone-500 font-mono">${{ prod.price.toFixed(2) }}</div>
                    </div>
                  </div>
                  <span class="text-[11px] font-bold px-2 py-0.5 rounded-md bg-stone-200/80 text-stone-800">
                    {{ prod.stock }} in stock
                  </span>
                </div>
              </div>
            </div>

            <!-- Drawer Footer Buttons -->
            <div class="pt-4 border-t border-stone-200 flex items-center justify-end gap-2.5">
              <button
                type="button"
                @click="closeDrawer"
                class="px-5 py-2.5 rounded-full border border-stone-300 text-stone-700 hover:text-stone-900 font-bold text-xs hover:bg-stone-50 transition-colors cursor-pointer"
              >
                Cancel
              </button>
              <button
                type="submit"
                :disabled="supplierForm.processing"
                class="px-6 py-2.5 rounded-full bg-stone-900 hover:bg-black text-white font-bold text-xs transition-all shadow-xs active:scale-[0.98] disabled:opacity-50 cursor-pointer"
              >
                {{ supplierForm.processing ? 'Saving…' : (editingSupplier ? 'Save Changes' : 'Register Supplier') }}
              </button>
            </div>

          </form>

        </div>
      </Transition>
    </Teleport>

    <!-- ======================================================== -->
    <!-- DELETE CONFIRMATION MODAL                                -->
    <!-- ======================================================== -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition-opacity duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition-opacity duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div 
          v-if="isDeleteModalOpen" 
          class="fixed inset-0 bg-black/40 backdrop-blur-xs z-50 flex items-center justify-center p-4"
          @click="isDeleteModalOpen = false"
        >
          <div 
            class="bg-white rounded-3xl max-w-md w-full p-6 space-y-4 shadow-2xl border border-stone-200"
            @click.stop
          >
            <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center text-red-600 mx-auto">
              <AlertCircle class="w-6 h-6 stroke-[2]" />
            </div>

            <div class="text-center space-y-1">
              <h3 class="text-lg font-serif font-bold text-stone-900">
                Delete Supplier Account?
              </h3>
              <p class="text-xs text-stone-500 leading-relaxed">
                Are you sure you want to remove <strong class="text-stone-900">{{ supplierToDelete?.name }}</strong>? Products linked to this vendor will have their supplier association unlinked, but will not be deleted.
              </p>
            </div>

            <div class="flex items-center justify-center gap-3 pt-2">
              <button
                type="button"
                @click="isDeleteModalOpen = false"
                class="px-5 py-2.5 rounded-full border border-stone-300 text-stone-700 font-bold text-xs hover:bg-stone-50 cursor-pointer"
              >
                Cancel
              </button>
              <button
                type="button"
                @click="executeDelete"
                class="px-5 py-2.5 rounded-full bg-red-600 hover:bg-red-700 text-white font-bold text-xs shadow-xs cursor-pointer"
              >
                Confirm Delete
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

  </AdminLayout>
</template>
