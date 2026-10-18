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
    <div class="space-y-6">
      
      <!-- Compact Page Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white border border-zinc-200 rounded-lg p-5 shadow-2xs">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <span class="inline-flex items-center gap-1.5 text-[10px] font-mono uppercase bg-zinc-900 text-white px-2 py-0.5 rounded font-semibold">
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
              <span>Session Active</span>
            </span>
            <span class="text-[10px] font-mono uppercase text-zinc-600 bg-zinc-100 border border-zinc-200 px-2 py-0.5 rounded font-medium">
              RBAC Enabled
            </span>
          </div>
          
          <h1 class="text-xl sm:text-2xl font-semibold text-zinc-900 tracking-tight">
            System Overview
          </h1>
          <p class="text-xs text-zinc-500 mt-0.5">
            Logged in as <span class="font-medium text-zinc-700">{{ currentAdmin?.name }}</span> — Manage catalog metrics, staff roles, and access permissions.
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-2 shrink-0">
          <a 
            href="/" 
            target="_blank"
            class="px-3 py-1.5 bg-white hover:bg-zinc-50 text-zinc-700 text-xs font-medium rounded-md inline-flex items-center gap-1.5 border border-zinc-200 transition-colors"
          >
            <Store class="w-3.5 h-3.5 text-zinc-500" />
            <span>Storefront</span>
            <ExternalLink class="w-3 h-3 text-zinc-400" />
          </a>

          <button 
            type="button"
            @click="showCreateRoleModal = true"
            class="px-3.5 py-1.5 bg-zinc-900 hover:bg-zinc-800 text-white text-xs font-semibold rounded-md inline-flex items-center gap-1.5 transition-colors cursor-pointer"
          >
            <Plus class="w-3.5 h-3.5" />
            <span>New Staff Role</span>
          </button>
        </div>
      </div>

      <!-- Stats Grid -->
      <div id="stats-section" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
        <!-- Products -->
        <div class="bg-white border border-zinc-200 rounded-lg p-4 shadow-2xs flex flex-col justify-between">
          <div class="flex items-center justify-between text-zinc-500">
            <span class="text-xs font-medium">Catalog Products</span>
            <div class="w-7 h-7 rounded-md bg-zinc-100 flex items-center justify-center text-zinc-700">
              <Package class="w-4 h-4" />
            </div>
          </div>
          <div class="mt-3">
            <div class="text-2xl font-semibold text-zinc-900 tabular-nums">
              {{ stats.total_products }}
            </div>
            <div class="flex items-center gap-1.5 mt-1 text-[11px] text-emerald-700">
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
              <span>Active in catalog</span>
            </div>
          </div>
        </div>

        <!-- Store Customers -->
        <div class="bg-white border border-zinc-200 rounded-lg p-4 shadow-2xs flex flex-col justify-between">
          <div class="flex items-center justify-between text-zinc-500">
            <span class="text-xs font-medium">Registered Users</span>
            <div class="w-7 h-7 rounded-md bg-zinc-100 flex items-center justify-center text-zinc-700">
              <Users class="w-4 h-4" />
            </div>
          </div>
          <div class="mt-3">
            <div class="text-2xl font-semibold text-zinc-900 tabular-nums">
              {{ stats.total_users }}
            </div>
            <div class="flex items-center gap-1.5 mt-1 text-[11px] text-zinc-500">
              <span>Customer accounts</span>
            </div>
          </div>
        </div>

        <!-- Staff / Admins -->
        <div class="bg-white border border-zinc-200 rounded-lg p-4 shadow-2xs flex flex-col justify-between">
          <div class="flex items-center justify-between text-zinc-500">
            <span class="text-xs font-medium">Active Admins</span>
            <div class="w-7 h-7 rounded-md bg-zinc-100 flex items-center justify-center text-zinc-700">
              <UserCheck class="w-4 h-4" />
            </div>
          </div>
          <div class="mt-3">
            <div class="text-2xl font-semibold text-zinc-900 tabular-nums">
              {{ stats.total_admins }}
            </div>
            <div class="flex items-center gap-1.5 mt-1 text-[11px] text-zinc-500">
              <span>Store staff & managers</span>
            </div>
          </div>
        </div>

        <!-- Roles & Permissions -->
        <div class="bg-white border border-zinc-200 rounded-lg p-4 shadow-2xs flex flex-col justify-between">
          <div class="flex items-center justify-between text-zinc-500">
            <span class="text-xs font-medium">Staff Roles</span>
            <div class="w-7 h-7 rounded-md bg-zinc-100 flex items-center justify-center text-zinc-700">
              <Shield class="w-4 h-4" />
            </div>
          </div>
          <div class="mt-3">
            <div class="text-2xl font-semibold text-zinc-900 tabular-nums">
              {{ stats.total_roles }} <span class="text-xs text-zinc-500 font-normal">roles</span>
            </div>
            <div class="flex items-center gap-1.5 mt-1 text-[11px] text-zinc-500">
              <span>{{ stats.total_permissions }} permissions assigned</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Staff Roles & Permissions Section -->
      <section id="roles-section" class="space-y-3 pt-1">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div class="flex items-center gap-2">
            <h2 class="text-base font-semibold text-zinc-900 tracking-tight">
              Staff Roles & Permissions
            </h2>
            <span class="text-[10px] font-mono text-zinc-600 bg-zinc-100 px-2 py-0.5 rounded border border-zinc-200">
              Access Control
            </span>
          </div>

          <!-- Role Search Input -->
          <div class="relative w-full sm:w-64">
            <Search class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400" />
            <input 
              v-model="searchRoleQuery"
              type="text"
              placeholder="Filter roles or permissions..."
              class="w-full pl-8 pr-3 py-1.5 text-xs bg-white border border-zinc-200 rounded-md focus:outline-none focus:border-zinc-900"
            />
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-3.5">
          <div 
            v-for="role in filteredRoles" 
            :key="role.id"
            class="bg-white border border-zinc-200 rounded-lg p-4 shadow-2xs flex flex-col justify-between hover:border-zinc-300 transition-colors"
          >
            <div>
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                  <div class="w-2 h-2 rounded-full bg-zinc-900"></div>
                  <h3 class="font-semibold text-sm text-zinc-900">
                    {{ role.name }}
                  </h3>
                </div>
                <span class="text-[11px] font-mono text-zinc-600 bg-zinc-50 px-2 py-0.5 rounded border border-zinc-200">
                  {{ role.users_count }} {{ role.users_count === 1 ? 'user' : 'users' }}
                </span>
              </div>

              <!-- Permission Badges -->
              <div class="mt-3 pt-3 border-t border-zinc-100">
                <div class="text-[10px] uppercase tracking-wider font-semibold text-zinc-400 mb-2">
                  Granted Permissions
                </div>
                <div class="flex flex-wrap gap-1">
                  <span 
                    v-for="perm in role.permissions" 
                    :key="perm"
                    class="text-[10px] bg-zinc-100 text-zinc-700 border border-zinc-200 px-1.5 py-0.5 rounded font-mono"
                  >
                    {{ perm }}
                  </span>
                  <span v-if="role.permissions.length === 0" class="text-xs text-zinc-400 italic">
                    No permissions assigned
                  </span>
                </div>
              </div>
            </div>

            <div class="mt-3 pt-2.5 border-t border-zinc-100 text-[11px] text-zinc-500 flex items-center gap-1.5">
              <CheckCircle2 class="w-3.5 h-3.5 text-emerald-600 shrink-0" />
              <span class="truncate font-medium text-emerald-700">Active role configured</span>
            </div>
          </div>

          <div v-if="filteredRoles.length === 0" class="col-span-full py-8 text-center text-xs text-zinc-500 bg-white rounded-lg border border-dashed border-zinc-200">
            No roles matching "{{ searchRoleQuery }}".
          </div>
        </div>
      </section>

      <!-- Administrators Table -->
      <section id="admins-section" class="space-y-3 pt-1">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div>
            <div class="flex items-center gap-2">
              <h2 class="text-base font-semibold text-zinc-900 tracking-tight">
                Admin Staff Accounts
              </h2>
              <span class="text-[11px] font-mono text-zinc-600 bg-zinc-100 px-2 py-0.5 rounded border border-zinc-200">
                {{ filteredAdmins.length }} showing
              </span>
            </div>
            <p class="text-xs text-zinc-500 mt-0.5">
              Authorized staff members managing store catalog, customer orders, and operations.
            </p>
          </div>

          <!-- Controls: Search & Role Filters -->
          <div class="flex flex-wrap items-center gap-2">
            <div class="relative w-full sm:w-56">
              <Search class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400" />
              <input 
                v-model="searchAdminQuery"
                type="text"
                placeholder="Search staff..."
                class="w-full pl-8 pr-3 py-1.5 text-xs bg-white border border-zinc-200 rounded-md focus:outline-none focus:border-zinc-900"
              />
            </div>

            <!-- Role Filter Pills -->
            <div class="flex items-center gap-1 bg-white p-1 rounded-md border border-zinc-200 text-[11px]">
              <button 
                type="button"
                @click="selectedRoleFilter = 'ALL'"
                class="px-2.5 py-0.5 rounded font-medium transition-colors cursor-pointer"
                :class="selectedRoleFilter === 'ALL' ? 'bg-zinc-900 text-white' : 'text-zinc-600 hover:text-zinc-900'"
              >
                All
              </button>
              <button 
                v-for="r in roles" 
                :key="r.id"
                type="button"
                @click="selectedRoleFilter = r.name"
                class="px-2.5 py-0.5 rounded font-medium transition-colors cursor-pointer"
                :class="selectedRoleFilter === r.name ? 'bg-zinc-900 text-white' : 'text-zinc-600 hover:text-zinc-900'"
              >
                {{ r.name }}
              </button>
            </div>
          </div>
        </div>

        <div class="bg-white border border-zinc-200 rounded-lg overflow-hidden shadow-2xs">
          <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
              <thead class="bg-zinc-50 text-zinc-500 uppercase text-[10px] font-semibold tracking-wider border-b border-zinc-200">
                <tr>
                  <th class="py-2.5 px-4">Admin Profile</th>
                  <th class="py-2.5 px-4">Email Address</th>
                  <th class="py-2.5 px-4">Assigned Roles</th>
                  <th class="py-2.5 px-4">Created Date</th>
                  <th class="py-2.5 px-4 text-right">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-zinc-100">
                <tr v-for="adm in filteredAdmins" :key="adm.id" class="hover:bg-zinc-50/80 transition-colors">
                  <td class="py-3 px-4 font-semibold text-zinc-900">
                    <div class="flex items-center gap-2.5">
                      <div class="w-7 h-7 rounded-md bg-zinc-900 text-white flex items-center justify-center font-semibold text-[10px]">
                        {{ getInitials(adm.name) }}
                      </div>
                      <span>{{ adm.name }}</span>
                    </div>
                  </td>
                  <td class="py-3 px-4 font-mono text-zinc-600">
                    {{ adm.email }}
                  </td>
                  <td class="py-3 px-4">
                    <div class="flex flex-wrap gap-1">
                      <span 
                        v-for="r in adm.roles" 
                        :key="r"
                        class="text-[10px] font-mono px-2 py-0.5 rounded font-semibold"
                        :class="r === 'Super Admin' ? 'bg-zinc-900 text-white' : 'bg-zinc-100 text-zinc-700 border border-zinc-200'"
                      >
                        {{ r }}
                      </span>
                    </div>
                  </td>
                  <td class="py-3 px-4 text-zinc-500">
                    {{ adm.created_at }}
                  </td>
                  <td class="py-3 px-4 text-right">
                    <button 
                      type="button"
                      @click="openRoleEdit(adm)"
                      class="px-2.5 py-1 text-xs font-medium text-zinc-700 hover:text-zinc-900 bg-white hover:bg-zinc-50 rounded-md transition-colors cursor-pointer border border-zinc-200"
                    >
                      Manage Roles
                    </button>
                  </td>
                </tr>

                <tr v-if="filteredAdmins.length === 0">
                  <td colspan="5" class="py-8 text-center text-xs text-zinc-500">
                    No administrators found matching your search and filter criteria.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>

    </div>

    <!-- Create Role Modal -->
    <div 
      v-if="showCreateRoleModal"
      class="fixed inset-0 z-50 bg-zinc-950/60 backdrop-blur-xs flex items-center justify-center p-4"
      @click.self="showCreateRoleModal = false"
    >
      <div class="bg-white rounded-lg max-w-md w-full p-5 shadow-xl border border-zinc-200 space-y-4">
        <div class="flex items-center justify-between">
          <div>
            <h3 class="font-semibold text-base text-zinc-900">Create New Staff Role</h3>
            <p class="text-xs text-zinc-500">Define a role with customized permissions for your team</p>
          </div>
          <button @click="showCreateRoleModal = false" class="text-zinc-400 hover:text-zinc-700 cursor-pointer text-xl leading-none">&times;</button>
        </div>

        <form @submit.prevent="submitCreateRole" class="space-y-4">
          <div>
            <label class="block text-xs font-medium text-zinc-700 mb-1">Role Name</label>
            <input 
              v-model="newRoleForm.name" 
              type="text" 
              placeholder="e.g. Catalog Specialist"
              required
              class="w-full px-3 py-1.5 border border-zinc-200 rounded-md text-xs focus:border-zinc-900 focus:outline-none"
            />
            <p v-if="newRoleForm.errors.name" class="text-xs text-red-600 mt-1">{{ newRoleForm.errors.name }}</p>
          </div>

          <div>
            <label class="block text-xs font-medium text-zinc-700 mb-1.5">Assign Permissions</label>
            <div class="space-y-1.5 max-h-48 overflow-y-auto border border-zinc-200 rounded-md p-2.5 bg-zinc-50">
              <label 
                v-for="perm in availablePermissions" 
                :key="perm"
                class="flex items-center gap-2 text-xs text-zinc-800 cursor-pointer hover:bg-zinc-100 p-1 rounded"
              >
                <input 
                  type="checkbox" 
                  :value="perm" 
                  v-model="newRoleForm.permissions"
                  class="rounded border-zinc-300 text-zinc-900 focus:ring-zinc-900"
                />
                <span class="font-mono">{{ perm }}</span>
              </label>
            </div>
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <button 
              type="button" 
              @click="showCreateRoleModal = false" 
              class="px-3.5 py-1.5 text-xs font-medium text-zinc-600 hover:text-zinc-900 cursor-pointer"
            >
              Cancel
            </button>
            <button 
              type="submit" 
              :disabled="newRoleForm.processing"
              class="px-4 py-1.5 bg-zinc-900 hover:bg-zinc-800 text-white text-xs font-semibold rounded-md cursor-pointer disabled:opacity-50 transition-colors"
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
      class="fixed inset-0 z-50 bg-zinc-950/60 backdrop-blur-xs flex items-center justify-center p-4"
      @click.self="editingAdmin = null"
    >
      <div class="bg-white rounded-lg max-w-md w-full p-5 shadow-xl border border-zinc-200 space-y-4">
        <div class="flex items-center justify-between">
          <div>
            <h3 class="font-semibold text-base text-zinc-900">Manage Staff Roles</h3>
            <p class="text-xs text-zinc-500">{{ editingAdmin.name }} ({{ editingAdmin.email }})</p>
          </div>
          <button @click="editingAdmin = null" class="text-zinc-400 hover:text-zinc-700 cursor-pointer text-xl leading-none">&times;</button>
        </div>

        <form @submit.prevent="submitSyncRoles" class="space-y-4">
          <div class="space-y-1.5 border border-zinc-200 rounded-md p-2.5 bg-zinc-50">
            <label 
              v-for="r in roles" 
              :key="r.name"
              class="flex items-center gap-2 text-xs text-zinc-800 cursor-pointer hover:bg-zinc-100 p-1 rounded"
            >
              <input 
                type="checkbox" 
                :value="r.name" 
                v-model="syncRoleForm.roles"
                class="rounded border-zinc-300 text-zinc-900 focus:ring-zinc-900"
              />
              <span class="font-medium">{{ r.name }}</span>
            </label>
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <button 
              type="button" 
              @click="editingAdmin = null" 
              class="px-3.5 py-1.5 text-xs font-medium text-zinc-600 hover:text-zinc-900 cursor-pointer"
            >
              Cancel
            </button>
            <button 
              type="submit" 
              :disabled="syncRoleForm.processing"
              class="px-4 py-1.5 bg-zinc-900 hover:bg-zinc-800 text-white text-xs font-semibold rounded-md cursor-pointer disabled:opacity-50 transition-colors"
            >
              Update Roles
            </button>
          </div>
        </form>
      </div>
    </div>

  </AdminLayout>
</template>

