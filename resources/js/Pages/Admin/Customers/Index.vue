<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { 
  Users, 
  Search, 
  ShoppingBag, 
  DollarSign, 
  Plus, 
  Edit, 
  Trash2, 
  CheckCircle2, 
  Clock, 
  ArrowUpDown, 
  X, 
  ExternalLink,
  Mail,
  ShieldCheck,
  AlertCircle,
  Package,
  Calendar,
  ChevronRight,
  TrendingUp,
  Receipt,
  UserCheck
} from 'lucide-vue-next';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';
import axios from 'axios';

const props = defineProps({
  customers: {
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
      filter: 'all',
      sort: 'latest',
    }),
  },
});

// Search, Filter, Sort state
const searchInput = ref(props.filters.search || '');
const currentFilter = ref(props.filters.filter || 'all');
const currentSort = ref(props.filters.sort || 'latest');

let searchDebounceTimeout = null;
const handleSearchChange = () => {
  clearTimeout(searchDebounceTimeout);
  searchDebounceTimeout = setTimeout(() => {
    applyFilters();
  }, 350);
};

const setFilter = (filterKey) => {
  currentFilter.value = filterKey;
  applyFilters();
};

const setSort = (sortKey) => {
  currentSort.value = sortKey;
  applyFilters();
};

const resetFilters = () => {
  searchInput.value = '';
  currentFilter.value = 'all';
  currentSort.value = 'latest';
  applyFilters();
};

const applyFilters = () => {
  router.get(
    route('admin.customers.index'),
    {
      search: searchInput.value || undefined,
      filter: currentFilter.value !== 'all' ? currentFilter.value : undefined,
      sort: currentSort.value !== 'latest' ? currentSort.value : undefined,
    },
    {
      preserveState: true,
      preserveScroll: true,
      replace: true,
    }
  );
};

// ==========================================
// DRAWER: CUSTOMER DETAILS & ORDER HISTORY
// ==========================================
const isDrawerOpen = ref(false);
const selectedCustomer = ref(null);
const customerOrderHistory = ref([]);
const isLoadingHistory = ref(false);
let closeDrawerTimer = null;

const openCustomerDrawer = async (customer) => {
  if (closeDrawerTimer) {
    clearTimeout(closeDrawerTimer);
    closeDrawerTimer = null;
  }
  selectedCustomer.value = customer;
  isDrawerOpen.value = true;
  customerOrderHistory.value = customer.recent_orders || [];
  isLoadingHistory.value = true;

  try {
    const response = await axios.get(route('admin.customers.show', customer.id), {
      headers: { Accept: 'application/json' },
    });
    if (response.data?.orders) {
      customerOrderHistory.value = response.data.orders;
    }
  } catch (error) {
    console.error('Failed to load customer order details:', error);
  } finally {
    isLoadingHistory.value = false;
  }
};

const closeCustomerDrawer = () => {
  isDrawerOpen.value = false;
  // Retain selectedCustomer during the 300ms slide-out animation so content does not flash or throw errors
  closeDrawerTimer = setTimeout(() => {
    if (!isDrawerOpen.value) {
      selectedCustomer.value = null;
      customerOrderHistory.value = [];
    }
    closeDrawerTimer = null;
  }, 320);
};

// ==========================================
// MODAL: CREATE CUSTOMER
// ==========================================
const showCreateModal = ref(false);
const createForm = useForm({
  name: '',
  email: '',
  password: '',
  email_verified: true,
});

const submitCreate = () => {
  createForm.post(route('admin.customers.store'), {
    preserveScroll: true,
    onSuccess: () => {
      showCreateModal.value = false;
      createForm.reset();
    },
  });
};

// ==========================================
// MODAL: EDIT CUSTOMER
// ==========================================
const editingCustomer = ref(null);
const editForm = useForm({
  name: '',
  email: '',
  password: '',
  email_verified: false,
});

const openEditModal = (customer) => {
  editingCustomer.value = customer;
  editForm.name = customer.name;
  editForm.email = customer.email;
  editForm.password = '';
  editForm.email_verified = customer.is_verified;
  editForm.clearErrors();
};

const submitEdit = () => {
  if (!editingCustomer.value) return;
  editForm.patch(route('admin.customers.update', editingCustomer.value.id), {
    preserveScroll: true,
    onSuccess: () => {
      // Update selected drawer customer if open
      if (selectedCustomer.value && selectedCustomer.value.id === editingCustomer.value.id) {
        selectedCustomer.value.name = editForm.name;
        selectedCustomer.value.email = editForm.email;
        selectedCustomer.value.is_verified = editForm.email_verified;
      }
      editingCustomer.value = null;
      editForm.reset();
    },
  });
};

// ==========================================
// MODAL: DELETE CONFIRMATION
// ==========================================
const deletingCustomer = ref(null);
const isDeleting = ref(false);

const confirmDelete = (customer) => {
  deletingCustomer.value = customer;
};

const submitDelete = () => {
  if (!deletingCustomer.value) return;
  isDeleting.value = true;
  router.delete(route('admin.customers.destroy', deletingCustomer.value.id), {
    preserveScroll: true,
    onFinish: () => {
      isDeleting.value = false;
      if (selectedCustomer.value?.id === deletingCustomer.value?.id) {
        isDrawerOpen.value = false;
        selectedCustomer.value = null;
      }
      deletingCustomer.value = null;
    },
  });
};

// Helper: Initials
const getInitials = (name) => {
  if (!name) return 'CU';
  const parts = name.trim().split(/\s+/);
  if (parts.length >= 2) {
    return (parts[0][0] + parts[1][0]).toUpperCase();
  }
  return parts[0].slice(0, 2).toUpperCase();
};

// Helper: Status badge color
const getStatusClasses = (status) => {
  switch (status) {
    case 'confirmed':
      return 'bg-amber-50 text-amber-800 border-amber-300';
    case 'packing':
      return 'bg-sky-50 text-sky-800 border-sky-300';
    case 'ready_for_pickup':
    case 'ready':
      return 'bg-purple-50 text-purple-800 border-purple-300';
    case 'completed':
      return 'bg-emerald-50 text-emerald-800 border-emerald-300';
    case 'cancelled':
      return 'bg-rose-50 text-rose-800 border-rose-300';
    default:
      return 'bg-stone-50 text-stone-700 border-stone-300';
  }
};

const formatStatusText = (status) => {
  switch (status) {
    case 'confirmed':
      return 'New Order';
    case 'packing':
      return 'Packing Items';
    case 'ready_for_pickup':
    case 'ready':
      return 'Ready for Pickup';
    case 'completed':
      return 'Completed';
    case 'cancelled':
      return 'Cancelled';
    default:
      return status;
  }
};
</script>

<template>
  <AdminLayout title="Customer Accounts">
    <div class="space-y-6">

      <!-- ========================================== -->
      <!-- TOP HEADER & PRIMARY ACTIONS               -->
      <!-- ========================================== -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white border border-[#e0d9cc] rounded-3xl p-6 sm:p-7 shadow-2xs">
        <div>
          <div class="flex items-center gap-2 mb-1.5">
            <span class="text-[11px] font-mono uppercase bg-[#1a1a1a] text-white px-2.5 py-0.5 rounded-full font-semibold">
              Directory
            </span>
            <span class="text-[11px] text-[#7a5620] bg-[#f5eee2] border border-[#e0d9cc] px-2.5 py-0.5 rounded-full font-medium">
              Registered Shoppers
            </span>
          </div>
          <h1 class="text-2xl sm:text-3xl font-serif font-medium text-[#1d1d1f] tracking-tight">
            Customer Accounts
          </h1>
          <p class="text-xs sm:text-sm text-[#6e6e73] font-normal mt-1 max-w-2xl leading-relaxed">
            Manage registered storefront accounts, review lifetime purchase history, inspect contact details, and update profiles.
          </p>
        </div>

        <div class="flex items-center gap-3 shrink-0">
          <button
            type="button"
            @click="showCreateModal = true"
            class="inline-flex items-center gap-2 px-4 py-2.5 bg-[#1a1a1a] hover:bg-black text-white rounded-full text-xs font-semibold transition-all shadow-xs hover:scale-[1.02] cursor-pointer"
          >
            <Plus class="w-4 h-4" />
            <span>Add Customer</span>
          </button>
        </div>
      </div>

      <!-- ========================================== -->
      <!-- EXECUTIVE KPI METRICS                      -->
      <!-- ========================================== -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        
        <!-- Total Registered Customers -->
        <div class="bg-white border border-[#e0d9cc] rounded-2xl p-5 shadow-2xs flex flex-col justify-between hover:border-[#a47a3c]/60 transition-colors">
          <div class="flex items-center justify-between text-[#6e6e73]">
            <span class="text-xs font-semibold uppercase tracking-wider">Total Customers</span>
            <div class="w-8 h-8 rounded-xl bg-[#f5eee2] flex items-center justify-center text-[#a47a3c]">
              <Users class="w-4 h-4" />
            </div>
          </div>
          <div class="mt-4">
            <div class="text-2xl sm:text-3xl font-serif font-medium text-[#1d1d1f]">
              {{ metrics.total_customers }}
            </div>
            <div class="flex items-center gap-1.5 mt-1 text-[11px] text-emerald-700">
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
              <span>Registered accounts</span>
            </div>
          </div>
        </div>

        <!-- Active Shoppers (with orders) -->
        <div class="bg-white border border-[#e0d9cc] rounded-2xl p-5 shadow-2xs flex flex-col justify-between hover:border-[#a47a3c]/60 transition-colors">
          <div class="flex items-center justify-between text-[#6e6e73]">
            <span class="text-xs font-semibold uppercase tracking-wider">Active Shoppers</span>
            <div class="w-8 h-8 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-700 border border-emerald-200">
              <UserCheck class="w-4 h-4" />
            </div>
          </div>
          <div class="mt-4">
            <div class="text-2xl sm:text-3xl font-serif font-medium text-[#1d1d1f]">
              {{ metrics.active_shoppers }}
            </div>
            <div class="flex items-center gap-1.5 mt-1 text-[11px] text-[#6e6e73]">
              <span>Placed 1 or more orders</span>
            </div>
          </div>
        </div>

        <!-- Total Orders Placed -->
        <div class="bg-white border border-[#e0d9cc] rounded-2xl p-5 shadow-2xs flex flex-col justify-between hover:border-[#a47a3c]/60 transition-colors">
          <div class="flex items-center justify-between text-[#6e6e73]">
            <span class="text-xs font-semibold uppercase tracking-wider">Customer Orders</span>
            <div class="w-8 h-8 rounded-xl bg-[#f5eee2] flex items-center justify-center text-[#a47a3c]">
              <ShoppingBag class="w-4 h-4" />
            </div>
          </div>
          <div class="mt-4">
            <div class="text-2xl sm:text-3xl font-serif font-medium text-[#1d1d1f]">
              {{ metrics.total_orders }}
            </div>
            <div class="flex items-center gap-1.5 mt-1 text-[11px] text-[#6e6e73]">
              <span>Total store checkout count</span>
            </div>
          </div>
        </div>

        <!-- Total Customer Revenue -->
        <div class="bg-white border border-[#e0d9cc] rounded-2xl p-5 shadow-2xs flex flex-col justify-between hover:border-[#a47a3c]/60 transition-colors">
          <div class="flex items-center justify-between text-[#6e6e73]">
            <span class="text-xs font-semibold uppercase tracking-wider">Total Revenue</span>
            <div class="w-8 h-8 rounded-xl bg-[#f5eee2] flex items-center justify-center text-[#a47a3c]">
              <DollarSign class="w-4 h-4" />
            </div>
          </div>
          <div class="mt-4">
            <div class="text-2xl sm:text-3xl font-serif font-medium text-[#1d1d1f]">
              ${{ metrics.total_revenue.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
            </div>
            <div class="flex items-center gap-1.5 mt-1 text-[11px] text-[#6e6e73]">
              <span>Avg ${{ metrics.average_spend }} per customer</span>
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
              placeholder="Search by customer name or email..."
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

          <!-- Sort Selector with Project's Custom UI Select Component -->
          <div class="flex items-center gap-2 self-end lg:self-center">
            <span class="text-xs text-[#86868b] whitespace-nowrap">Sort by:</span>
            <Select :model-value="currentSort" @update:model-value="setSort">
              <SelectTrigger class="w-44 h-8 rounded-full bg-[#fbf9f5] border-[#e0d9cc] text-xs font-medium focus:border-[#1a1a1a] focus:ring-3 focus:ring-[#1a1a1a]/10 shadow-2xs">
                <SelectValue placeholder="Sort by" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="latest">Newest Joined</SelectItem>
                <SelectItem value="oldest">Oldest Joined</SelectItem>
                <SelectItem value="highest_spend">Highest Total Spend</SelectItem>
                <SelectItem value="most_orders">Most Orders Placed</SelectItem>
                <SelectItem value="name">Name (A-Z)</SelectItem>
              </SelectContent>
            </Select>
          </div>

        </div>

        <!-- Filter Segment Pills -->
        <div class="flex items-center gap-1.5 overflow-x-auto pb-1 text-xs no-scrollbar pt-1 border-t border-[#e0d9cc]/40">
          <button 
            type="button"
            @click="setFilter('all')"
            class="px-3 py-1 rounded-full font-medium transition-colors cursor-pointer whitespace-nowrap"
            :class="currentFilter === 'all' ? 'bg-[#1a1a1a] text-white shadow-xs' : 'bg-[#fbf9f5] text-[#6e6e73] hover:text-[#1d1d1f] border border-[#e0d9cc]'"
          >
            All Customers
          </button>
          <button 
            type="button"
            @click="setFilter('with_orders')"
            class="px-3 py-1 rounded-full font-medium transition-colors cursor-pointer whitespace-nowrap"
            :class="currentFilter === 'with_orders' ? 'bg-[#1a1a1a] text-white shadow-xs' : 'bg-[#fbf9f5] text-[#6e6e73] hover:text-[#1d1d1f] border border-[#e0d9cc]'"
          >
            With Orders ({{ metrics.active_shoppers }})
          </button>
          <button 
            type="button"
            @click="setFilter('no_orders')"
            class="px-3 py-1 rounded-full font-medium transition-colors cursor-pointer whitespace-nowrap"
            :class="currentFilter === 'no_orders' ? 'bg-[#1a1a1a] text-white shadow-xs' : 'bg-[#fbf9f5] text-[#6e6e73] hover:text-[#1d1d1f] border border-[#e0d9cc]'"
          >
            No Orders Yet
          </button>
          <button 
            type="button"
            @click="setFilter('verified')"
            class="px-3 py-1 rounded-full font-medium transition-colors cursor-pointer whitespace-nowrap"
            :class="currentFilter === 'verified' ? 'bg-[#1a1a1a] text-white shadow-xs' : 'bg-[#fbf9f5] text-[#6e6e73] hover:text-[#1d1d1f] border border-[#e0d9cc]'"
          >
            Verified Email
          </button>
          <button 
            type="button"
            @click="setFilter('unverified')"
            class="px-3 py-1 rounded-full font-medium transition-colors cursor-pointer whitespace-nowrap"
            :class="currentFilter === 'unverified' ? 'bg-[#1a1a1a] text-white shadow-xs' : 'bg-[#fbf9f5] text-[#6e6e73] hover:text-[#1d1d1f] border border-[#e0d9cc]'"
          >
            Unverified
          </button>

          <button 
            v-if="searchInput || currentFilter !== 'all' || currentSort !== 'latest'"
            type="button"
            @click="resetFilters"
            class="ml-auto text-xs text-[#a47a3c] hover:underline font-medium cursor-pointer"
          >
            Reset Filters
          </button>
        </div>
      </div>

      <!-- ========================================== -->
      <!-- CUSTOMER ACCOUNTS TABLE                    -->
      <!-- ========================================== -->
      <div class="bg-white border border-[#e0d9cc] rounded-3xl overflow-hidden shadow-2xs">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead class="bg-[#f3efe7] text-[#6e6e73] uppercase text-[10px] font-semibold tracking-wider border-b border-[#e0d9cc]">
              <tr>
                <th class="py-3.5 px-4 sm:px-6">Customer Profile</th>
                <th class="py-3.5 px-4">Contact & Verification</th>
                <th class="py-3.5 px-4">Orders & Lifetime Spend</th>
                <th class="py-3.5 px-4">Latest Activity</th>
                <th class="py-3.5 px-4 sm:px-6 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#e0d9cc]/60">
              <tr 
                v-for="cust in customers.data" 
                :key="cust.id" 
                class="hover:bg-[#fbf9f5] transition-colors group cursor-pointer"
                @click="openCustomerDrawer(cust)"
              >
                <!-- Customer Profile -->
                <td class="py-4 px-4 sm:px-6">
                  <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full bg-[#1a1a1a] text-white flex items-center justify-center font-bold text-xs shadow-2xs shrink-0 group-hover:scale-105 transition-transform">
                      {{ getInitials(cust.name) }}
                    </div>
                    <div>
                      <div class="font-medium text-[#1d1d1f] text-sm group-hover:text-[#a47a3c] transition-colors flex items-center gap-1.5">
                        <span>{{ cust.name }}</span>
                        <span 
                          v-if="cust.total_spent > 50" 
                          class="text-[9px] font-mono uppercase bg-[#f5eee2] text-[#7a5620] border border-[#e0d9cc] px-1.5 py-0.2 rounded font-semibold"
                        >
                          VIP
                        </span>
                      </div>
                      <div class="text-[11px] text-[#86868b] flex items-center gap-1 mt-0.5">
                        <Calendar class="w-3 h-3 text-[#a47a3c]" />
                        <span>Joined {{ cust.created_at }}</span>
                      </div>
                    </div>
                  </div>
                </td>

                <!-- Email & Status -->
                <td class="py-4 px-4" @click.stop>
                  <div class="space-y-1">
                    <div class="font-mono text-[#1d1d1f] text-xs flex items-center gap-1.5">
                      <Mail class="w-3.5 h-3.5 text-[#86868b] shrink-0" />
                      <span>{{ cust.email }}</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                      <span 
                        v-if="cust.is_verified" 
                        class="inline-flex items-center gap-1 text-[10px] text-emerald-800 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-full font-medium"
                      >
                        <CheckCircle2 class="w-3 h-3 text-emerald-600" />
                        <span>Verified Account</span>
                      </span>
                      <span 
                        v-else 
                        class="inline-flex items-center gap-1 text-[10px] text-amber-800 bg-amber-50 border border-amber-200 px-2 py-0.5 rounded-full font-medium"
                      >
                        <Clock class="w-3 h-3 text-amber-600" />
                        <span>Unverified Email</span>
                      </span>
                    </div>
                  </div>
                </td>

                <!-- Orders & Total Spend -->
                <td class="py-4 px-4">
                  <div>
                    <div class="font-serif font-medium text-sm text-[#1d1d1f]">
                      ${{ cust.total_spent.toFixed(2) }}
                    </div>
                    <div class="text-[11px] text-[#6e6e73] flex items-center gap-1 mt-0.5">
                      <ShoppingBag class="w-3 h-3 text-[#86868b]" />
                      <span>{{ cust.orders_count }} {{ cust.orders_count === 1 ? 'order' : 'orders' }} placed</span>
                    </div>
                  </div>
                </td>

                <!-- Latest Order / Activity -->
                <td class="py-4 px-4">
                  <div v-if="cust.recent_orders && cust.recent_orders.length > 0">
                    <div class="flex items-center gap-1.5">
                      <span class="font-mono font-medium text-[#1d1d1f] text-xs">
                        {{ cust.recent_orders[0].order_number }}
                      </span>
                      <span 
                        class="text-[9px] font-semibold uppercase px-1.5 py-0.2 rounded-full border"
                        :class="getStatusClasses(cust.recent_orders[0].status)"
                      >
                        {{ formatStatusText(cust.recent_orders[0].status) }}
                      </span>
                    </div>
                    <div class="text-[11px] text-[#86868b] mt-0.5">
                      {{ cust.recent_orders[0].created_at }}
                    </div>
                  </div>
                  <div v-else class="text-xs text-[#86868b] italic">
                    No orders yet
                  </div>
                </td>

                <!-- Actions -->
                <td class="py-4 px-4 sm:px-6 text-right" @click.stop>
                  <div class="flex items-center justify-end gap-1.5">
                    <button 
                      type="button"
                      @click="openCustomerDrawer(cust)"
                      class="px-2.5 py-1 text-xs font-semibold text-[#1a1a1a] hover:text-[#a47a3c] bg-[#f3efe7] hover:bg-[#ece7de] rounded-lg transition-colors cursor-pointer border border-[#e0d9cc]/80 inline-flex items-center gap-1 shadow-2xs"
                      title="Inspect order history"
                    >
                      <Receipt class="w-3.5 h-3.5" />
                      <span>Orders</span>
                    </button>

                    <button 
                      type="button"
                      @click="openEditModal(cust)"
                      class="p-1.5 text-stone-600 hover:text-stone-900 bg-white hover:bg-stone-100 rounded-lg border border-[#e0d9cc] transition-colors cursor-pointer"
                      title="Edit Customer"
                    >
                      <Edit class="w-3.5 h-3.5" />
                    </button>

                    <button 
                      type="button"
                      @click="confirmDelete(cust)"
                      class="p-1.5 text-rose-600 hover:text-rose-900 bg-white hover:bg-rose-50 rounded-lg border border-rose-200 transition-colors cursor-pointer"
                      title="Delete Customer Account"
                    >
                      <Trash2 class="w-3.5 h-3.5" />
                    </button>
                  </div>
                </td>
              </tr>

              <!-- Empty Results -->
              <tr v-if="customers.data.length === 0">
                <td colspan="5" class="py-12 text-center text-xs text-[#86868b] bg-white">
                  <div class="w-12 h-12 rounded-full bg-[#f5eee2] text-[#a47a3c] flex items-center justify-center mx-auto mb-3">
                    <Users class="w-6 h-6" />
                  </div>
                  <p class="font-medium text-[#1d1d1f] text-sm">No customers found</p>
                  <p class="text-xs text-[#86868b] mt-1 max-w-sm mx-auto">
                    {{ searchInput ? `No accounts match "${searchInput}". Try clearing search or filters.` : 'No customer accounts registered in this segment.' }}
                  </p>
                  <button 
                    v-if="searchInput || currentFilter !== 'all'"
                    type="button" 
                    @click="resetFilters" 
                    class="mt-3 px-3 py-1.5 bg-[#f3efe7] hover:bg-[#ece7de] border border-[#e0d9cc] rounded-full text-xs font-semibold text-[#1d1d1f] cursor-pointer"
                  >
                    Clear Filter
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination Controls -->
        <div 
          v-if="customers.total > customers.per_page" 
          class="border-t border-[#e0d9cc] px-6 py-4 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-[#6e6e73]"
        >
          <div>
            Showing <strong class="text-[#1d1d1f]">{{ customers.from }}</strong> to <strong class="text-[#1d1d1f]">{{ customers.to }}</strong> of <strong class="text-[#1d1d1f]">{{ customers.total }}</strong> customers
          </div>
          <div class="flex items-center gap-1">
            <template v-for="(link, i) in customers.links" :key="i">
              <Link 
                v-if="link.url"
                :href="link.url"
                class="px-3 py-1.5 rounded-lg border font-medium transition-colors"
                :class="link.active ? 'bg-[#1a1a1a] text-white border-[#1a1a1a]' : 'bg-white text-[#1d1d1f] border-[#e0d9cc] hover:bg-[#f3efe7]'"
                v-html="link.label"
              />
              <span 
                v-else 
                class="px-3 py-1.5 rounded-lg border border-[#e0d9cc]/40 text-[#86868b]/50 bg-stone-50 cursor-not-allowed"
                v-html="link.label"
              />
            </template>
          </div>
        </div>
      </div>

    </div>

    <!-- ========================================== -->
    <!-- SLIDE-OVER DRAWER: CUSTOMER ORDER HISTORY  -->
    <!-- ========================================== -->
    <Teleport to="body">
      <div 
        v-if="isDrawerOpen || selectedCustomer" 
        class="fixed inset-0 z-50 overflow-hidden"
      >
        <!-- Backdrop: Smooth Fade Animation -->
        <Transition
          enter-active-class="transition-opacity duration-300 ease-out"
          enter-from-class="opacity-0"
          enter-to-class="opacity-100"
          leave-active-class="transition-opacity duration-300 ease-in"
          leave-from-class="opacity-100"
          leave-to-class="opacity-0"
        >
          <div 
            v-if="isDrawerOpen" 
            class="fixed inset-0 bg-black/60 backdrop-blur-xs"
            @click="closeCustomerDrawer"
          />
        </Transition>

        <!-- Slide-over Drawer Panel Container -->
        <div class="fixed inset-y-0 right-0 max-w-full flex pl-10 pointer-events-none">
          <Transition
            appear
            enter-active-class="transform transition ease-out duration-300"
            enter-from-class="translate-x-full"
            enter-to-class="translate-x-0"
            leave-active-class="transform transition ease-in duration-300"
            leave-from-class="translate-x-0"
            leave-to-class="translate-x-full"
          >
            <div 
              v-if="isDrawerOpen && selectedCustomer"
              class="w-screen max-w-xl pointer-events-auto bg-white shadow-2xl flex flex-col border-l border-[#e0d9cc]"
            >
              
              <!-- Drawer Header -->
              <div class="p-6 border-b border-[#e0d9cc] bg-[#fbf9f5] flex items-center justify-between shrink-0">
                <div class="flex items-center gap-3">
                  <div class="w-12 h-12 rounded-full bg-[#1a1a1a] text-white flex items-center justify-center font-bold text-base shadow-xs shrink-0">
                    {{ getInitials(selectedCustomer?.name) }}
                  </div>
                  <div>
                    <div class="flex items-center gap-2">
                      <h2 class="font-serif font-medium text-lg text-[#1d1d1f]">{{ selectedCustomer?.name }}</h2>
                      <span 
                        v-if="selectedCustomer?.is_verified" 
                        class="text-[10px] text-emerald-800 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-full font-medium"
                      >
                        Verified
                      </span>
                    </div>
                    <p class="text-xs text-[#6e6e73] font-mono">{{ selectedCustomer?.email }}</p>
                  </div>
                </div>
                <button 
                  type="button" 
                  @click="closeCustomerDrawer" 
                  class="text-[#86868b] hover:text-[#1d1d1f] p-1.5 rounded-full hover:bg-stone-200 transition-colors cursor-pointer"
                >
                  <X class="w-5 h-5" />
                </button>
              </div>

              <!-- Drawer Body -->
              <div class="flex-1 overflow-y-auto p-6 space-y-6">
                
                <!-- Summary Cards -->
                <div class="grid grid-cols-2 gap-3">
                  <div class="bg-[#f5eee2] border border-[#e0d9cc] rounded-2xl p-4">
                    <span class="text-[11px] font-semibold text-[#7a5620] uppercase tracking-wider block">Total Spent</span>
                    <span class="font-serif font-medium text-2xl text-[#1d1d1f] mt-1 block">
                      ${{ selectedCustomer?.total_spent?.toFixed(2) || '0.00' }}
                    </span>
                  </div>
                  <div class="bg-[#f5eee2] border border-[#e0d9cc] rounded-2xl p-4">
                    <span class="text-[11px] font-semibold text-[#7a5620] uppercase tracking-wider block">Orders Placed</span>
                    <span class="font-serif font-medium text-2xl text-[#1d1d1f] mt-1 block">
                      {{ selectedCustomer?.orders_count || 0 }}
                    </span>
                  </div>
                </div>

                <!-- Quick Actions Bar -->
                <div class="flex items-center gap-2">
                  <button 
                    type="button" 
                    @click="openEditModal(selectedCustomer)" 
                    class="flex-1 px-4 py-2 bg-[#f3efe7] hover:bg-[#ece7de] text-[#1d1d1f] text-xs font-semibold rounded-xl border border-[#e0d9cc] transition-colors cursor-pointer inline-flex items-center justify-center gap-1.5"
                  >
                    <Edit class="w-3.5 h-3.5 text-[#a47a3c]" />
                    <span>Edit Account</span>
                  </button>
                  <Link 
                    :href="route('admin.orders.index', { search: selectedCustomer?.email })"
                    class="flex-1 px-4 py-2 bg-[#1a1a1a] hover:bg-black text-white text-xs font-semibold rounded-xl transition-colors cursor-pointer inline-flex items-center justify-center gap-1.5"
                  >
                    <ShoppingBag class="w-3.5 h-3.5 text-white" />
                    <span>Open in Live Orders</span>
                  </Link>
                </div>

                <!-- Orders Section -->
                <div>
                  <div class="flex items-center justify-between mb-3">
                    <h3 class="font-serif font-medium text-base text-[#1d1d1f]">
                      Order History ({{ customerOrderHistory.length }})
                    </h3>
                    <span v-if="isLoadingHistory" class="text-xs text-[#a47a3c] animate-pulse">Loading orders...</span>
                  </div>

                  <div v-if="customerOrderHistory.length === 0" class="p-8 text-center bg-[#fbf9f5] border border-dashed border-[#e0d9cc] rounded-2xl text-xs text-[#86868b]">
                    <ShoppingBag class="w-6 h-6 text-[#a47a3c] mx-auto mb-2 opacity-60" />
                    <p class="font-medium text-[#1d1d1f]">No orders placed yet</p>
                    <p class="text-[11px] text-[#86868b] mt-0.5">This customer has not completed any purchases.</p>
                  </div>

                  <div v-else class="space-y-4">
                    <div 
                      v-for="order in customerOrderHistory" 
                      :key="order.id"
                      class="bg-white border border-[#e0d9cc] rounded-2xl p-4 shadow-2xs hover:border-[#a47a3c]/60 transition-colors space-y-3"
                    >
                      <!-- Order Header -->
                      <div class="flex items-center justify-between border-b border-[#e0d9cc]/60 pb-2.5">
                        <div class="flex items-center gap-2">
                          <span class="font-mono font-bold text-sm text-[#1d1d1f]">{{ order.order_number }}</span>
                          <span 
                            class="text-[10px] font-semibold uppercase px-2 py-0.5 rounded-full border"
                            :class="getStatusClasses(order.status)"
                          >
                            {{ formatStatusText(order.status) }}
                          </span>
                        </div>
                        <div class="font-serif font-medium text-base text-[#1d1d1f]">
                          ${{ Number(order.total).toFixed(2) }}
                        </div>
                      </div>

                      <!-- Fulfillment details -->
                      <div class="text-xs text-[#6e6e73] space-y-1">
                        <div class="flex items-center gap-1.5">
                          <Clock class="w-3.5 h-3.5 text-[#a47a3c]" />
                          <span>Pickup Slot: <strong class="text-[#1d1d1f]">{{ order.pickup_slot || 'Express Curbside' }}</strong></span>
                        </div>
                        <div class="flex items-center gap-1.5 text-[11px] text-[#86868b]">
                          <Calendar class="w-3.5 h-3.5" />
                          <span>Placed {{ order.created_at }}</span>
                        </div>
                      </div>

                      <!-- Items Purchased -->
                      <div v-if="order.items && order.items.length > 0" class="pt-2 border-t border-[#e0d9cc]/40 space-y-1.5">
                        <div class="text-[10px] uppercase font-semibold text-[#86868b] tracking-wider">
                          Purchased Items:
                        </div>
                        <div class="space-y-1">
                          <div 
                            v-for="item in order.items" 
                            :key="item.id" 
                            class="flex items-center justify-between text-xs py-1 px-2 rounded-lg bg-[#fbf9f5]"
                          >
                            <div class="flex items-center gap-2 truncate">
                              <img 
                                v-if="item.image" 
                                :src="item.image" 
                                :alt="item.name" 
                                class="w-6 h-6 rounded object-cover border border-[#e0d9cc]" 
                              />
                              <span class="font-medium text-[#1d1d1f] truncate">{{ item.name }}</span>
                              <span v-if="item.size" class="text-[10px] text-[#86868b]">({{ item.size }})</span>
                            </div>
                            <div class="text-right shrink-0 font-mono text-[11px] text-[#6e6e73]">
                              {{ item.quantity }} × ${{ Number(item.unit_price).toFixed(2) }}
                            </div>
                          </div>
                        </div>
                      </div>

                    </div>
                  </div>
                </div>

              </div>

            </div>
          </Transition>
        </div>
      </div>
    </Teleport>

    <!-- ========================================== -->
    <!-- MODAL: CREATE NEW CUSTOMER                 -->
    <!-- ========================================== -->
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
              class="bg-white rounded-3xl max-w-md w-full p-6 sm:p-7 shadow-2xl border border-[#e0d9cc] space-y-5"
            >
              <div class="flex items-center justify-between">
                <div>
                  <h3 class="font-serif font-medium text-xl text-[#1d1d1f]">Add New Customer</h3>
                  <p class="text-xs text-[#6e6e73] mt-0.5">Create a store customer account</p>
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
                <!-- Full Name -->
                <div>
                  <label class="block text-xs font-semibold text-[#1d1d1f] mb-1">Customer Full Name</label>
                  <input 
                    v-model="createForm.name" 
                    type="text" 
                    placeholder="e.g. Arjun Kapoor" 
                    class="w-full px-3.5 py-2 text-xs bg-[#fbf9f5] border border-[#e0d9cc] rounded-xl focus:bg-white focus:outline-none focus:ring-3 focus:ring-[#1a1a1a]/10 focus:border-[#1a1a1a]"
                    required 
                  />
                  <p v-if="createForm.errors.name" class="text-[11px] text-rose-600 mt-1">{{ createForm.errors.name }}</p>
                </div>

                <!-- Email Address -->
                <div>
                  <label class="block text-xs font-semibold text-[#1d1d1f] mb-1">Email Address</label>
                  <input 
                    v-model="createForm.email" 
                    type="email" 
                    placeholder="e.g. arjun@example.com" 
                    class="w-full px-3.5 py-2 text-xs bg-[#fbf9f5] border border-[#e0d9cc] rounded-xl focus:bg-white focus:outline-none focus:ring-3 focus:ring-[#1a1a1a]/10 focus:border-[#1a1a1a]"
                    required 
                  />
                  <p v-if="createForm.errors.email" class="text-[11px] text-rose-600 mt-1">{{ createForm.errors.email }}</p>
                </div>

                <!-- Password -->
                <div>
                  <label class="block text-xs font-semibold text-[#1d1d1f] mb-1">Temporary Password</label>
                  <input 
                    v-model="createForm.password" 
                    type="password" 
                    placeholder="Minimum 8 characters" 
                    class="w-full px-3.5 py-2 text-xs bg-[#fbf9f5] border border-[#e0d9cc] rounded-xl focus:bg-white focus:outline-none focus:ring-3 focus:ring-[#1a1a1a]/10 focus:border-[#1a1a1a]"
                    required 
                  />
                  <p v-if="createForm.errors.password" class="text-[11px] text-rose-600 mt-1">{{ createForm.errors.password }}</p>
                </div>

                <!-- Email Verified Toggle -->
                <div class="flex items-center gap-2 pt-1">
                  <input 
                    v-model="createForm.email_verified" 
                    type="checkbox" 
                    id="create_email_verified" 
                    class="w-4 h-4 rounded text-[#1a1a1a] focus:ring-[#1a1a1a] border-[#e0d9cc]" 
                  />
                  <label for="create_email_verified" class="text-xs text-[#1d1d1f] font-medium cursor-pointer">
                    Mark email as verified immediately
                  </label>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-2 pt-3 border-t border-[#e0d9cc]">
                  <button 
                    type="button" 
                    @click="showCreateModal = false" 
                    class="px-4 py-2 bg-stone-100 hover:bg-stone-200 text-stone-700 text-xs font-semibold rounded-xl cursor-pointer"
                  >
                    Cancel
                  </button>
                  <button 
                    type="submit" 
                    :disabled="createForm.processing"
                    class="px-5 py-2 bg-[#1a1a1a] hover:bg-black text-white text-xs font-semibold rounded-xl cursor-pointer disabled:opacity-50"
                  >
                    {{ createForm.processing ? 'Creating...' : 'Create Account' }}
                  </button>
                </div>
              </form>
            </div>
          </Transition>
        </div>
      </Transition>
    </Teleport>

    <!-- ========================================== -->
    <!-- MODAL: EDIT CUSTOMER DETAILS               -->
    <!-- ========================================== -->
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
          v-if="editingCustomer" 
          class="fixed inset-0 z-50 overflow-y-auto bg-black/60 backdrop-blur-xs flex items-center justify-center p-4"
          @click.self="editingCustomer = null"
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
              v-if="editingCustomer"
              class="bg-white rounded-3xl max-w-md w-full p-6 sm:p-7 shadow-2xl border border-[#e0d9cc] space-y-5"
            >
              <div class="flex items-center justify-between">
                <div>
                  <h3 class="font-serif font-medium text-xl text-[#1d1d1f]">Edit Customer</h3>
                  <p class="text-xs text-[#6e6e73] mt-0.5">Update name, email, or credentials</p>
                </div>
                <button 
                  type="button" 
                  @click="editingCustomer = null" 
                  class="text-[#86868b] hover:text-[#1d1d1f] cursor-pointer"
                >
                  <X class="w-5 h-5" />
                </button>
              </div>

              <form @submit.prevent="submitEdit" class="space-y-4">
                <!-- Full Name -->
                <div>
                  <label class="block text-xs font-semibold text-[#1d1d1f] mb-1">Customer Full Name</label>
                  <input 
                    v-model="editForm.name" 
                    type="text" 
                    class="w-full px-3.5 py-2 text-xs bg-[#fbf9f5] border border-[#e0d9cc] rounded-xl focus:bg-white focus:outline-none focus:ring-3 focus:ring-[#1a1a1a]/10 focus:border-[#1a1a1a]"
                    required 
                  />
                  <p v-if="editForm.errors.name" class="text-[11px] text-rose-600 mt-1">{{ editForm.errors.name }}</p>
                </div>

                <!-- Email Address -->
                <div>
                  <label class="block text-xs font-semibold text-[#1d1d1f] mb-1">Email Address</label>
                  <input 
                    v-model="editForm.email" 
                    type="email" 
                    class="w-full px-3.5 py-2 text-xs bg-[#fbf9f5] border border-[#e0d9cc] rounded-xl focus:bg-white focus:outline-none focus:ring-3 focus:ring-[#1a1a1a]/10 focus:border-[#1a1a1a]"
                    required 
                  />
                  <p v-if="editForm.errors.email" class="text-[11px] text-rose-600 mt-1">{{ editForm.errors.email }}</p>
                </div>

                <!-- Reset Password (optional) -->
                <div>
                  <label class="block text-xs font-semibold text-[#1d1d1f] mb-1">
                    Reset Password <span class="font-normal text-[#86868b]">(leave blank to keep unchanged)</span>
                  </label>
                  <input 
                    v-model="editForm.password" 
                    type="password" 
                    placeholder="New password (optional)" 
                    class="w-full px-3.5 py-2 text-xs bg-[#fbf9f5] border border-[#e0d9cc] rounded-xl focus:bg-white focus:outline-none focus:ring-3 focus:ring-[#1a1a1a]/10 focus:border-[#1a1a1a]"
                  />
                  <p v-if="editForm.errors.password" class="text-[11px] text-rose-600 mt-1">{{ editForm.errors.password }}</p>
                </div>

                <!-- Email Verified Toggle -->
                <div class="flex items-center gap-2 pt-1">
                  <input 
                    v-model="editForm.email_verified" 
                    type="checkbox" 
                    id="edit_email_verified" 
                    class="w-4 h-4 rounded text-[#1a1a1a] focus:ring-[#1a1a1a] border-[#e0d9cc]" 
                  />
                  <label for="edit_email_verified" class="text-xs text-[#1d1d1f] font-medium cursor-pointer">
                    Account email is verified
                  </label>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-2 pt-3 border-t border-[#e0d9cc]">
                  <button 
                    type="button" 
                    @click="editingCustomer = null" 
                    class="px-4 py-2 bg-stone-100 hover:bg-stone-200 text-stone-700 text-xs font-semibold rounded-xl cursor-pointer"
                  >
                    Cancel
                  </button>
                  <button 
                    type="submit" 
                    :disabled="editForm.processing"
                    class="px-5 py-2 bg-[#1a1a1a] hover:bg-black text-white text-xs font-semibold rounded-xl cursor-pointer disabled:opacity-50"
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
        enter-active-class="transition-opacity duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition-opacity duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div 
          v-if="deletingCustomer" 
          class="fixed inset-0 z-50 overflow-y-auto bg-black/60 backdrop-blur-xs flex items-center justify-center p-4"
          @click.self="deletingCustomer = null"
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
              v-if="deletingCustomer"
              class="bg-white rounded-3xl max-w-sm w-full p-6 shadow-2xl border border-[#e0d9cc] space-y-4"
            >
              <div class="w-12 h-12 rounded-full bg-rose-50 border border-rose-200 text-rose-600 flex items-center justify-center mx-auto">
                <AlertCircle class="w-6 h-6" />
              </div>
              <div class="text-center space-y-1">
                <h3 class="font-serif font-medium text-lg text-[#1d1d1f]">Remove Customer Account</h3>
                <p class="text-xs text-[#6e6e73] leading-relaxed">
                  Are you sure you want to delete <strong class="text-[#1d1d1f]">{{ deletingCustomer.name }}</strong> ({{ deletingCustomer.email }})?
                </p>
                <p class="text-[11px] text-[#86868b] bg-stone-50 p-2 rounded-xl border border-[#e0d9cc]/60 mt-2">
                  Historical sales orders placed by this customer will remain safely in store sales records.
                </p>
              </div>
              <div class="flex items-center gap-2 pt-2">
                <button 
                  type="button" 
                  @click="deletingCustomer = null" 
                  class="flex-1 py-2 bg-stone-100 hover:bg-stone-200 text-stone-700 text-xs font-semibold rounded-xl cursor-pointer"
                >
                  Cancel
                </button>
                <button 
                  type="button" 
                  @click="submitDelete" 
                  :disabled="isDeleting"
                  class="flex-1 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold rounded-xl cursor-pointer disabled:opacity-50"
                >
                  {{ isDeleting ? 'Deleting...' : 'Yes, Delete' }}
                </button>
              </div>
            </div>
          </Transition>
        </div>
      </Transition>
    </Teleport>

  </AdminLayout>
</template>
