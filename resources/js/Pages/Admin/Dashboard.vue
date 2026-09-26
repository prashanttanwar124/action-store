<script setup>
import { ref, computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { 
  Package, 
  Users, 
  Shield, 
  CheckCircle2, 
  Plus, 
  UserCheck, 
  Layers,
  Search,
  Filter,
  ShieldCheck,
  Store,
  ExternalLink,
  Lock,
  Sparkles,
  ArrowUpRight
} from 'lucide-vue-next';

const props = defineProps({
  stats: {
    type: Object,
    required: true,
  },
  admins: {
    type: Array,
    required: true,
  },
  roles: {
    type: Array,
    required: true,
  },
  availablePermissions: {
    type: Array,
    required: true,
  },
});

const page = usePage();
const currentAdmin = page.props.auth?.admin;

// Filter & Search states
const searchAdminQuery = ref('');
const selectedRoleFilter = ref('ALL');
const searchRoleQuery = ref('');

// Computed filtered admins
const filteredAdmins = computed(() => {
  return props.admins.filter(adm => {
    const matchesSearch = !searchAdminQuery.value || 
      adm.name.toLowerCase().includes(searchAdminQuery.value.toLowerCase()) ||
      adm.email.toLowerCase().includes(searchAdminQuery.value.toLowerCase());

    const matchesRole = selectedRoleFilter.value === 'ALL' || 
      adm.roles.includes(selectedRoleFilter.value);

    return matchesSearch && matchesRole;
  });
});

// Computed filtered roles
const filteredRoles = computed(() => {
  if (!searchRoleQuery.value) return props.roles;
  const q = searchRoleQuery.value.toLowerCase();
  return props.roles.filter(role => 
    role.name.toLowerCase().includes(q) || 
    role.permissions.some(p => p.toLowerCase().includes(q))
  );
});

// Create New Role Form
const showCreateRoleModal = ref(false);
const newRoleForm = useForm({
  name: '',
  permissions: [],
});

const submitCreateRole = () => {
  newRoleForm.post(route('admin.roles.store'), {
    onSuccess: () => {
      newRoleForm.reset();
      showCreateRoleModal.value = false;
    },
  });
};

// Edit Admin Roles Form
const editingAdmin = ref(null);
const syncRoleForm = useForm({
  roles: [],
});

const openRoleEdit = (admin) => {
  editingAdmin.value = admin;
  syncRoleForm.roles = [...admin.roles];
};

const submitSyncRoles = () => {
  if (!editingAdmin.value) return;
  syncRoleForm.post(route('admin.admins.roles.sync', editingAdmin.value.id), {
    onSuccess: () => {
      editingAdmin.value = null;
    },
  });
};

const getInitials = (name) => {
  if (!name) return 'AD';
  const parts = name.trim().split(/\s+/);
  if (parts.length >= 2) {
    return (parts[0][0] + parts[1][0]).toUpperCase();
  }
  return parts[0].slice(0, 2).toUpperCase();
};
</script>

<template>
  <AdminLayout title="Dashboard Overview">
    <div class="space-y-8">
      
      <!-- Welcome & Status Header -->
      <div class="relative overflow-hidden bg-gradient-to-br from-[#f3efe7] via-[#f8f5ee] to-[#ece5d8] border border-[#e0d9cc] rounded-3xl p-6 sm:p-8 shadow-xs">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 relative z-10">
          <div class="space-y-1.5">
            <div class="flex flex-wrap items-center gap-2 mb-2">
              <span class="inline-flex items-center gap-1.5 text-[11px] font-mono uppercase bg-[#1a1a1a] text-white px-2.5 py-0.5 rounded-full font-semibold shadow-2xs">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                <span>Active Guard: admin</span>
              </span>
              <span class="text-[11px] text-[#7a5620] bg-white/80 border border-[#e0d9cc] px-2.5 py-0.5 rounded-full font-medium shadow-2xs">
                Spatie RBAC v8 Enforced
              </span>
              <span class="text-[11px] text-stone-600 bg-white/60 px-2 py-0.5 rounded-full font-sans">
                Storefront Isolated
              </span>
            </div>
            
            <h1 class="text-2xl sm:text-3xl lg:text-4xl font-serif font-medium text-[#1d1d1f] tracking-tight">
              Welcome back, {{ currentAdmin?.name }}
            </h1>
            <p class="text-xs sm:text-sm text-[#6e6e73] max-w-2xl leading-relaxed">
              Complete oversight of products, registered user accounts, admin credentials, and Spatie authorization rules.
            </p>
          </div>

          <div class="flex flex-wrap items-center gap-2.5 shrink-0">
            <a 
              href="/" 
              target="_blank"
              class="px-4 py-2 bg-white hover:bg-stone-50 text-[#1d1d1f] text-xs font-semibold rounded-full inline-flex items-center gap-1.5 border border-[#e0d9cc] transition-all shadow-2xs hover:shadow-xs"
            >
              <Store class="w-3.5 h-3.5 text-[#a47a3c]" />
              <span>Preview Storefront</span>
              <ExternalLink class="w-3 h-3 text-[#86868b]" />
            </a>

            <button 
              type="button"
              @click="showCreateRoleModal = true"
              class="px-4 py-2 bg-[#1a1a1a] hover:bg-black text-white text-xs font-semibold rounded-full inline-flex items-center gap-1.5 transition-all cursor-pointer shadow-xs hover:scale-[1.02]"
            >
              <Plus class="w-3.5 h-3.5" />
              <span>New Spatie Role</span>
            </button>
          </div>
        </div>

        <!-- Decorative subtle pattern overlay -->
        <div class="absolute -right-8 -bottom-8 w-48 h-48 rounded-full bg-[#a47a3c]/5 blur-2xl pointer-events-none"></div>
      </div>

      <!-- Quick Section Anchor Tabs -->
      <div class="flex items-center gap-1.5 overflow-x-auto pb-1 text-xs no-scrollbar">
        <a 
          href="#stats-section" 
          class="px-3.5 py-1.5 rounded-full bg-white hover:bg-[#f3efe7] border border-[#e0d9cc] text-[#1d1d1f] font-medium whitespace-nowrap transition-colors shadow-2xs"
        >
          Overview Stats
        </a>
        <a 
          href="#roles-section" 
          class="px-3.5 py-1.5 rounded-full bg-white hover:bg-[#f3efe7] border border-[#e0d9cc] text-[#1d1d1f] font-medium whitespace-nowrap transition-colors shadow-2xs"
        >
          Spatie Roles & Permissions ({{ roles.length }})
        </a>
        <a 
          href="#admins-section" 
          class="px-3.5 py-1.5 rounded-full bg-white hover:bg-[#f3efe7] border border-[#e0d9cc] text-[#1d1d1f] font-medium whitespace-nowrap transition-colors shadow-2xs"
        >
          Admin Staff Accounts ({{ admins.length }})
        </a>
        <a 
          href="#architecture-section" 
          class="px-3.5 py-1.5 rounded-full bg-white hover:bg-[#f3efe7] border border-[#e0d9cc] text-[#1d1d1f] font-medium whitespace-nowrap transition-colors shadow-2xs"
        >
          Multi-Guard Architecture
        </a>
      </div>

      <!-- Stats Grid -->
      <div id="stats-section" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Products -->
        <div class="bg-white border border-[#e0d9cc] rounded-2xl p-5 shadow-2xs flex flex-col justify-between hover:border-[#a47a3c]/50 transition-colors">
          <div class="flex items-center justify-between text-[#6e6e73]">
            <span class="text-xs font-semibold uppercase tracking-wider">Catalog Products</span>
            <div class="w-8 h-8 rounded-xl bg-[#f5eee2] flex items-center justify-center text-[#a47a3c]">
              <Package class="w-4 h-4" />
            </div>
          </div>
          <div class="mt-4">
            <div class="text-2xl sm:text-3xl font-serif font-medium text-[#1d1d1f]">
              {{ stats.total_products }}
            </div>
            <div class="flex items-center gap-1.5 mt-1 text-[11px] text-emerald-700">
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
              <span>Available in storefront</span>
            </div>
          </div>
        </div>

        <!-- Store Customers -->
        <div class="bg-white border border-[#e0d9cc] rounded-2xl p-5 shadow-2xs flex flex-col justify-between hover:border-[#a47a3c]/50 transition-colors">
          <div class="flex items-center justify-between text-[#6e6e73]">
            <span class="text-xs font-semibold uppercase tracking-wider">Registered Users</span>
            <div class="w-8 h-8 rounded-xl bg-[#f5eee2] flex items-center justify-center text-[#a47a3c]">
              <Users class="w-4 h-4" />
            </div>
          </div>
          <div class="mt-4">
            <div class="text-2xl sm:text-3xl font-serif font-medium text-[#1d1d1f]">
              {{ stats.total_users }}
            </div>
            <div class="flex items-center gap-1.5 mt-1 text-[11px] text-[#6e6e73]">
              <span>Customer guard (`web`)</span>
            </div>
          </div>
        </div>

        <!-- Staff / Admins -->
        <div class="bg-white border border-[#e0d9cc] rounded-2xl p-5 shadow-2xs flex flex-col justify-between hover:border-[#a47a3c]/50 transition-colors">
          <div class="flex items-center justify-between text-[#6e6e73]">
            <span class="text-xs font-semibold uppercase tracking-wider">Active Admins</span>
            <div class="w-8 h-8 rounded-xl bg-[#f5eee2] flex items-center justify-center text-[#a47a3c]">
              <UserCheck class="w-4 h-4" />
            </div>
          </div>
          <div class="mt-4">
            <div class="text-2xl sm:text-3xl font-serif font-medium text-[#1d1d1f]">
              {{ stats.total_admins }}
            </div>
            <div class="flex items-center gap-1.5 mt-1 text-[11px] text-[#6e6e73]">
              <span>Admin guard (`admins` table)</span>
            </div>
          </div>
        </div>

        <!-- Roles & Permissions -->
        <div class="bg-white border border-[#e0d9cc] rounded-2xl p-5 shadow-2xs flex flex-col justify-between hover:border-[#a47a3c]/50 transition-colors">
          <div class="flex items-center justify-between text-[#6e6e73]">
            <span class="text-xs font-semibold uppercase tracking-wider">Spatie RBAC</span>
            <div class="w-8 h-8 rounded-xl bg-[#f5eee2] flex items-center justify-center text-[#a47a3c]">
              <Shield class="w-4 h-4" />
            </div>
          </div>
          <div class="mt-4">
            <div class="text-2xl sm:text-3xl font-serif font-medium text-[#1d1d1f]">
              {{ stats.total_roles }} <span class="text-base text-[#6e6e73] font-normal">roles</span>
            </div>
            <div class="flex items-center gap-1.5 mt-1 text-[11px] text-[#6e6e73]">
              <span>{{ stats.total_permissions }} granular permissions</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Spatie Roles & Permissions Section -->
      <section id="roles-section" class="space-y-4 pt-2">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div class="flex items-center gap-2">
            <h2 class="text-xl font-serif font-medium text-[#1d1d1f] tracking-tight">
              Spatie Roles & Permissions
            </h2>
            <span class="text-[11px] font-mono text-[#7a5620] bg-[#f5eee2] px-2 py-0.5 rounded-full border border-[#e0d9cc]">
              guard: admin
            </span>
          </div>

          <!-- Role Search Input -->
          <div class="relative w-full sm:w-64">
            <Search class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-[#86868b]" />
            <input 
              v-model="searchRoleQuery"
              type="text"
              placeholder="Filter roles or permissions..."
              class="w-full pl-8 pr-3 py-1.5 text-xs bg-white border border-[#e0d9cc] rounded-full focus:outline-none focus:ring-2 focus:ring-[#1a1a1a]"
            />
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div 
            v-for="role in filteredRoles" 
            :key="role.id"
            class="bg-white border border-[#e0d9cc] rounded-2xl p-5 shadow-2xs flex flex-col justify-between group hover:border-[#a47a3c]/60 transition-colors"
          >
            <div>
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                  <div class="w-2.5 h-2.5 rounded-full bg-[#a47a3c]"></div>
                  <h3 class="font-serif font-medium text-base text-[#1d1d1f]">
                    {{ role.name }}
                  </h3>
                </div>
                <span class="text-[11px] font-mono text-[#86868b] bg-[#fbf9f5] px-2 py-0.5 rounded-full border border-[#e0d9cc]">
                  {{ role.users_count }} {{ role.users_count === 1 ? 'user' : 'users' }}
                </span>
              </div>

              <!-- Permission Badges -->
              <div class="mt-3 pt-3 border-t border-[#e0d9cc]/60">
                <div class="text-[10px] uppercase tracking-wider font-semibold text-[#86868b] mb-2">
                  Granted Permissions:
                </div>
                <div class="flex flex-wrap gap-1.5">
                  <span 
                    v-for="perm in role.permissions" 
                    :key="perm"
                    class="text-[10px] bg-[#f5eee2] text-[#7a5620] border border-[#e0d9cc] px-2 py-0.5 rounded-md font-mono"
                  >
                    {{ perm }}
                  </span>
                  <span v-if="role.permissions.length === 0" class="text-xs text-[#86868b] italic">
                    No permissions assigned
                  </span>
                </div>
              </div>
            </div>

            <div class="mt-4 pt-3 border-t border-[#e0d9cc]/40 text-[11px] text-[#6e6e73] flex items-center gap-1.5">
              <CheckCircle2 class="w-3.5 h-3.5 text-emerald-600 shrink-0" />
              <span class="truncate">Enforced via <code class="bg-[#f3efe7] px-1 py-0.5 rounded font-mono text-[10px]">role:{{ role.name }},admin</code></span>
            </div>
          </div>

          <div v-if="filteredRoles.length === 0" class="col-span-full py-8 text-center text-xs text-[#86868b] bg-white rounded-2xl border border-dashed border-[#e0d9cc]">
            No roles matching "{{ searchRoleQuery }}".
          </div>
        </div>
      </section>

      <!-- Administrators Table -->
      <section id="admins-section" class="space-y-4 pt-2">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div>
            <div class="flex items-center gap-2">
              <h2 class="text-xl font-serif font-medium text-[#1d1d1f] tracking-tight">
                Admin Staff Accounts
              </h2>
              <span class="text-xs text-[#86868b] bg-white px-2.5 py-0.5 rounded-full border border-[#e0d9cc]">
                {{ filteredAdmins.length }} showing
              </span>
            </div>
            <p class="text-xs text-[#6e6e73] mt-0.5">
              Administrators authenticating strictly against the separate <code class="font-mono text-[#7a5620]">admins</code> database table.
            </p>
          </div>

          <!-- Controls: Search & Role Filters -->
          <div class="flex flex-wrap items-center gap-2">
            <div class="relative w-full sm:w-56">
              <Search class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-[#86868b]" />
              <input 
                v-model="searchAdminQuery"
                type="text"
                placeholder="Search staff by name or email..."
                class="w-full pl-8 pr-3 py-1.5 text-xs bg-white border border-[#e0d9cc] rounded-full focus:outline-none focus:ring-2 focus:ring-[#1a1a1a]"
              />
            </div>

            <!-- Role Filter Pills -->
            <div class="flex items-center gap-1 bg-white p-1 rounded-full border border-[#e0d9cc] text-[11px]">
              <button 
                type="button"
                @click="selectedRoleFilter = 'ALL'"
                class="px-2.5 py-0.5 rounded-full font-medium transition-colors cursor-pointer"
                :class="selectedRoleFilter === 'ALL' ? 'bg-[#1a1a1a] text-white' : 'text-stone-600 hover:text-black'"
              >
                All
              </button>
              <button 
                v-for="r in roles" 
                :key="r.id"
                type="button"
                @click="selectedRoleFilter = r.name"
                class="px-2.5 py-0.5 rounded-full font-medium transition-colors cursor-pointer"
                :class="selectedRoleFilter === r.name ? 'bg-[#1a1a1a] text-white' : 'text-stone-600 hover:text-black'"
              >
                {{ r.name }}
              </button>
            </div>
          </div>
        </div>

        <div class="bg-white border border-[#e0d9cc] rounded-2xl overflow-hidden shadow-2xs">
          <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
              <thead class="bg-[#f3efe7] text-[#6e6e73] uppercase text-[10px] font-semibold tracking-wider border-b border-[#e0d9cc]">
                <tr>
                  <th class="py-3 px-4">Admin Profile</th>
                  <th class="py-3 px-4">Email Address</th>
                  <th class="py-3 px-4">Assigned Roles</th>
                  <th class="py-3 px-4">Created Date</th>
                  <th class="py-3 px-4 text-right">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-[#e0d9cc]/60">
                <tr v-for="adm in filteredAdmins" :key="adm.id" class="hover:bg-[#fbf9f5] transition-colors">
                  <td class="py-3.5 px-4 font-semibold text-[#1d1d1f]">
                    <div class="flex items-center gap-2.5">
                      <div class="w-7 h-7 rounded-full bg-[#1a1a1a] text-white flex items-center justify-center font-bold text-[10px] shadow-2xs">
                        {{ getInitials(adm.name) }}
                      </div>
                      <span>{{ adm.name }}</span>
                    </div>
                  </td>
                  <td class="py-3.5 px-4 font-mono text-[#6e6e73]">
                    {{ adm.email }}
                  </td>
                  <td class="py-3.5 px-4">
                    <div class="flex flex-wrap gap-1">
                      <span 
                        v-for="r in adm.roles" 
                        :key="r"
                        class="text-[10px] font-mono px-2 py-0.5 rounded-full font-semibold"
                        :class="r === 'Super Admin' ? 'bg-[#1a1a1a] text-white' : 'bg-[#f5eee2] text-[#7a5620] border border-[#e0d9cc]'"
                      >
                        {{ r }}
                      </span>
                    </div>
                  </td>
                  <td class="py-3.5 px-4 text-[#86868b]">
                    {{ adm.created_at }}
                  </td>
                  <td class="py-3.5 px-4 text-right">
                    <button 
                      type="button"
                      @click="openRoleEdit(adm)"
                      class="px-3 py-1 text-xs font-semibold text-[#1a1a1a] hover:text-[#a47a3c] bg-[#f3efe7] hover:bg-[#ece7de] rounded-lg transition-colors cursor-pointer border border-[#e0d9cc]/80"
                    >
                      Manage Roles
                    </button>
                  </td>
                </tr>

                <tr v-if="filteredAdmins.length === 0">
                  <td colspan="5" class="py-8 text-center text-xs text-[#86868b]">
                    No administrators found matching your search and filter criteria.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <!-- Architecture Card Explaining Isolation -->
      <section id="architecture-section" class="bg-[#121214] text-white rounded-3xl p-6 sm:p-8 shadow-sm border border-[#2e2e32] space-y-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-[#a47a3c]">
            <Layers class="w-4 h-4" />
            <span>MULTI-GUARD ARCHITECTURE & ISOLATION MATRIX</span>
          </div>
          <span class="text-[10px] font-mono text-emerald-400 bg-emerald-950/60 border border-emerald-800/40 px-2 py-0.5 rounded-full">
            Zero Session Collision
          </span>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-xs leading-relaxed text-stone-300 pt-2">
          <div class="space-y-2 p-4 rounded-2xl bg-white/5 border border-white/5">
            <div class="flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-sky-400"></span>
              <h3 class="font-serif font-medium text-base text-white">1. Customer Guard (`web`)</h3>
            </div>
            <p>
              Operates on the <code class="text-[#e4b97a] font-mono">users</code> database table via <code class="text-[#e4b97a] font-mono">App\Models\User</code>. 
              Users register and sign in at <code class="text-white font-mono">/login</code> to browse groceries, manage their cart, and review personal order history.
            </p>
          </div>
          
          <div class="space-y-2 p-4 rounded-2xl bg-white/5 border border-white/5">
            <div class="flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-[#a47a3c]"></span>
              <h3 class="font-serif font-medium text-base text-white">2. Admin Guard (`admin`)</h3>
            </div>
            <p>
              Operates on the <code class="text-[#e4b97a] font-mono">admins</code> table via <code class="text-[#e4b97a] font-mono">App\Models\Admin</code>. 
              Protected by separate session cookies, throttle limits, and Spatie RBAC. Even if an admin and customer share the same browser, their sessions remain completely decoupled.
            </p>
          </div>
        </div>
      </section>

    </div>

    <!-- Create Role Modal -->
    <div 
      v-if="showCreateRoleModal"
      class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4"
      @click.self="showCreateRoleModal = false"
    >
      <div class="bg-white rounded-3xl max-w-md w-full p-6 sm:p-7 shadow-2xl border border-[#e0d9cc] space-y-4">
        <div class="flex items-center justify-between">
          <div>
            <h3 class="font-serif font-medium text-lg text-[#1d1d1f]">Create New Spatie Role</h3>
            <p class="text-xs text-[#6e6e73]">Define an authorization role scoped to guard: admin</p>
          </div>
          <button @click="showCreateRoleModal = false" class="text-stone-400 hover:text-stone-700 cursor-pointer text-xl leading-none">&times;</button>
        </div>

        <form @submit.prevent="submitCreateRole" class="space-y-4">
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-[#6e6e73] mb-1">Role Name</label>
            <input 
              v-model="newRoleForm.name" 
              type="text" 
              placeholder="e.g. Catalog Specialist"
              required
              class="w-full px-3.5 py-2 border border-[#e0d9cc] rounded-xl text-xs focus:ring-2 focus:ring-[#1a1a1a] focus:outline-none"
            />
            <p v-if="newRoleForm.errors.name" class="text-xs text-red-600 mt-1">{{ newRoleForm.errors.name }}</p>
          </div>

          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-[#6e6e73] mb-2">Assign Permissions</label>
            <div class="space-y-2 max-h-48 overflow-y-auto border border-[#e0d9cc] rounded-xl p-3 bg-[#fbf9f5]">
              <label 
                v-for="perm in availablePermissions" 
                :key="perm"
                class="flex items-center gap-2 text-xs text-[#1d1d1f] cursor-pointer hover:bg-stone-100 p-1 rounded"
              >
                <input 
                  type="checkbox" 
                  :value="perm" 
                  v-model="newRoleForm.permissions"
                  class="rounded border-[#e0d9cc] text-[#1a1a1a] focus:ring-[#1a1a1a]"
                />
                <span class="font-mono">{{ perm }}</span>
              </label>
            </div>
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <button 
              type="button" 
              @click="showCreateRoleModal = false" 
              class="px-4 py-2 text-xs font-semibold text-[#6e6e73] hover:text-[#1d1d1f] cursor-pointer"
            >
              Cancel
            </button>
            <button 
              type="submit" 
              :disabled="newRoleForm.processing"
              class="px-5 py-2 bg-[#1a1a1a] hover:bg-black text-white text-xs font-semibold rounded-full cursor-pointer disabled:opacity-50 transition-colors"
            >
              Save Role
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Edit Admin Roles Modal -->
    <div 
      v-if="editingAdmin"
      class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4"
      @click.self="editingAdmin = null"
    >
      <div class="bg-white rounded-3xl max-w-md w-full p-6 sm:p-7 shadow-2xl border border-[#e0d9cc] space-y-4">
        <div class="flex items-center justify-between">
          <div>
            <h3 class="font-serif font-medium text-lg text-[#1d1d1f]">Manage Staff Roles</h3>
            <p class="text-xs text-[#6e6e73]">{{ editingAdmin.name }} ({{ editingAdmin.email }})</p>
          </div>
          <button @click="editingAdmin = null" class="text-stone-400 hover:text-stone-700 cursor-pointer text-xl leading-none">&times;</button>
        </div>

        <form @submit.prevent="submitSyncRoles" class="space-y-4">
          <div class="space-y-2 border border-[#e0d9cc] rounded-xl p-3 bg-[#fbf9f5]">
            <label 
              v-for="r in roles" 
              :key="r.name"
              class="flex items-center gap-2 text-xs text-[#1d1d1f] cursor-pointer hover:bg-stone-100 p-1 rounded"
            >
              <input 
                type="checkbox" 
                :value="r.name" 
                v-model="syncRoleForm.roles"
                class="rounded border-[#e0d9cc] text-[#1a1a1a] focus:ring-[#1a1a1a]"
              />
              <span class="font-semibold">{{ r.name }}</span>
            </label>
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <button 
              type="button" 
              @click="editingAdmin = null" 
              class="px-4 py-2 text-xs font-semibold text-[#6e6e73] hover:text-[#1d1d1f] cursor-pointer"
            >
              Cancel
            </button>
            <button 
              type="submit" 
              :disabled="syncRoleForm.processing"
              class="px-5 py-2 bg-[#1a1a1a] hover:bg-black text-white text-xs font-semibold rounded-full cursor-pointer disabled:opacity-50 transition-colors"
            >
              Update Roles
            </button>
          </div>
        </form>
      </div>
    </div>

  </AdminLayout>
</template>

