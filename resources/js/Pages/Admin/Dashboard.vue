<script setup>
import { ref } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { 
  Package, 
  Users, 
  Shield, 
  Key, 
  CheckCircle2, 
  Plus, 
  UserCheck, 
  Sparkles,
  Lock,
  Layers,
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
</script>

<template>
  <AdminLayout title="Dashboard">
    <div class="space-y-8">
      
      <!-- Welcome Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-[#f3efe7] border border-[#e0d9cc] rounded-3xl p-6 sm:p-8 shadow-2xs">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <span class="text-xs font-mono uppercase bg-[#1a1a1a] text-white px-2.5 py-0.5 rounded-full font-semibold">
              Authenticated Guard: admin
            </span>
            <span class="text-xs text-[#7a5620] bg-[#f5eee2] border border-[#e0d9cc] px-2.5 py-0.5 rounded-full font-medium">
              Spatie RBAC Active
            </span>
          </div>
          <h1 class="text-2xl sm:text-3xl font-serif font-medium text-[#1d1d1f] tracking-tight">
            Welcome back, {{ currentAdmin?.name }}
          </h1>
          <p class="text-xs sm:text-sm text-[#6e6e73] mt-1">
            Role-based authorization and store management isolated from customer sessions.
          </p>
        </div>

        <div class="flex items-center gap-2">
          <button 
            type="button"
            @click="showCreateRoleModal = true"
            class="px-4 py-2 bg-[#1a1a1a] hover:bg-black text-white text-xs font-semibold rounded-full inline-flex items-center gap-1.5 transition-colors cursor-pointer shadow-xs"
          >
            <Plus class="w-3.5 h-3.5" />
            <span>New Spatie Role</span>
          </button>
        </div>
      </div>

      <!-- Stats Grid -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Products -->
        <div class="bg-white border border-[#e0d9cc] rounded-2xl p-5 shadow-2xs flex flex-col justify-between">
          <div class="flex items-center justify-between text-[#6e6e73]">
            <span class="text-xs font-semibold uppercase tracking-wider">Catalog Products</span>
            <Package class="w-4 h-4 text-[#a47a3c]" />
          </div>
          <div class="mt-3">
            <div class="text-2xl sm:text-3xl font-serif font-medium text-[#1d1d1f]">
              {{ stats.total_products }}
            </div>
            <span class="text-[11px] text-[#6e6e73]">Active storefront items</span>
          </div>
        </div>

        <!-- Store Customers -->
        <div class="bg-white border border-[#e0d9cc] rounded-2xl p-5 shadow-2xs flex flex-col justify-between">
          <div class="flex items-center justify-between text-[#6e6e73]">
            <span class="text-xs font-semibold uppercase tracking-wider">Registered Users</span>
            <Users class="w-4 h-4 text-[#a47a3c]" />
          </div>
          <div class="mt-3">
            <div class="text-2xl sm:text-3xl font-serif font-medium text-[#1d1d1f]">
              {{ stats.total_users }}
            </div>
            <span class="text-[11px] text-[#6e6e73]">Web guard customer accounts</span>
          </div>
        </div>

        <!-- Staff / Admins -->
        <div class="bg-white border border-[#e0d9cc] rounded-2xl p-5 shadow-2xs flex flex-col justify-between">
          <div class="flex items-center justify-between text-[#6e6e73]">
            <span class="text-xs font-semibold uppercase tracking-wider">Active Admins</span>
            <UserCheck class="w-4 h-4 text-[#a47a3c]" />
          </div>
          <div class="mt-3">
            <div class="text-2xl sm:text-3xl font-serif font-medium text-[#1d1d1f]">
              {{ stats.total_admins }}
            </div>
            <span class="text-[11px] text-[#6e6e73]">Admin guard credentials</span>
          </div>
        </div>

        <!-- Roles & Permissions -->
        <div class="bg-white border border-[#e0d9cc] rounded-2xl p-5 shadow-2xs flex flex-col justify-between">
          <div class="flex items-center justify-between text-[#6e6e73]">
            <span class="text-xs font-semibold uppercase tracking-wider">Spatie RBAC</span>
            <Shield class="w-4 h-4 text-[#a47a3c]" />
          </div>
          <div class="mt-3">
            <div class="text-2xl sm:text-3xl font-serif font-medium text-[#1d1d1f]">
              {{ stats.total_roles }} <span class="text-base text-[#6e6e73] font-normal">roles</span>
            </div>
            <span class="text-[11px] text-[#6e6e73]">{{ stats.total_permissions }} granular permissions</span>
          </div>
        </div>
      </div>

      <!-- Spatie Roles & Permissions Section -->
      <section class="space-y-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <h2 class="text-xl font-serif font-medium text-[#1d1d1f] tracking-tight">
              Spatie Roles & Permissions
            </h2>
            <span class="text-[11px] font-mono text-[#7a5620] bg-[#f5eee2] px-2 py-0.5 rounded-full border border-[#e0d9cc]">
              guard: admin
            </span>
          </div>
          <span class="text-xs text-[#6e6e73]">Access rules defined in database</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div 
            v-for="role in roles" 
            :key="role.id"
            class="bg-white border border-[#e0d9cc] rounded-2xl p-5 shadow-2xs flex flex-col justify-between group hover:border-[#a47a3c]/60 transition-colors"
          >
            <div>
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                  <div class="w-3 h-3 rounded-full bg-[#a47a3c]"></div>
                  <h3 class="font-serif font-medium text-base text-[#1d1d1f]">
                    {{ role.name }}
                  </h3>
                </div>
                <span class="text-[11px] font-mono text-[#86868b]">
                  {{ role.users_count }} assigned
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

            <div class="mt-4 pt-3 border-t border-[#e0d9cc]/40 text-[11px] text-[#6e6e73] flex items-center gap-1">
              <CheckCircle2 class="w-3.5 h-3.5 text-emerald-600" />
              <span>Enforced via <code class="bg-[#f3efe7] px-1 py-0.5 rounded font-mono text-[10px]">role:{{ role.name }},admin</code></span>
            </div>
          </div>
        </div>
      </section>

      <!-- Administrators Table -->
      <section class="space-y-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <h2 class="text-xl font-serif font-medium text-[#1d1d1f] tracking-tight">
              Admin Users
            </h2>
            <span class="text-xs text-[#6e6e73]">Authenticating on separate 'admins' table</span>
          </div>
        </div>

        <div class="bg-white border border-[#e0d9cc] rounded-2xl overflow-hidden shadow-2xs">
          <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
              <thead class="bg-[#f3efe7] text-[#6e6e73] uppercase text-[10px] font-semibold tracking-wider border-b border-[#e0d9cc]">
                <tr>
                  <th class="py-3 px-4">Admin Name</th>
                  <th class="py-3 px-4">Email</th>
                  <th class="py-3 px-4">Assigned Roles</th>
                  <th class="py-3 px-4">Created Date</th>
                  <th class="py-3 px-4 text-right">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-[#e0d9cc]/60">
                <tr v-for="adm in admins" :key="adm.id" class="hover:bg-[#fbf9f5] transition-colors">
                  <td class="py-3.5 px-4 font-semibold text-[#1d1d1f]">
                    {{ adm.name }}
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
                      class="px-2.5 py-1 text-xs font-semibold text-[#1a1a1a] hover:text-[#a47a3c] bg-[#f3efe7] hover:bg-[#ece7de] rounded-md transition-colors cursor-pointer"
                    >
                      Manage Roles
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <!-- Architecture Card Explaining Isolation -->
      <section class="bg-[#1a1a1a] text-white rounded-3xl p-6 sm:p-8 shadow-sm border border-[#e0d9cc]/20 space-y-4">
        <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-[#a47a3c]">
          <Layers class="w-4 h-4" />
          <span>MULTI-GUARD ARCHITECTURE EXPLAINED</span>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-xs leading-relaxed text-stone-300">
          <div class="space-y-2">
            <h3 class="font-serif font-medium text-base text-white">1. Customer Guard (`web`)</h3>
            <p>
              Operates on the <code class="text-[#e4b97a]">users</code> database table via <code class="text-[#e4b97a]">App\Models\User</code>. 
              Users register and sign in at <code class="text-white">/login</code> to browse groceries, manage their cart, and review personal order history.
            </p>
          </div>
          <div class="space-y-2">
            <h3 class="font-serif font-medium text-base text-white">2. Admin Guard (`admin`)</h3>
            <p>
              Operates on the <code class="text-[#e4b97a]">admins</code> table via <code class="text-[#e4b97a]">App\Models\Admin</code>. 
              Protected by separate session cookies, throttle limits, and Spatie RBAC. Even if an admin and customer share the same browser, their sessions remain completely decoupled.
            </p>
          </div>
        </div>
      </section>

    </div>

    <!-- Create Role Modal -->
    <div 
      v-if="showCreateRoleModal"
      class="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4"
      @click.self="showCreateRoleModal = false"
    >
      <div class="bg-white rounded-3xl max-w-md w-full p-6 sm:p-7 shadow-xl border border-[#e0d9cc] space-y-4">
        <div class="flex items-center justify-between">
          <h3 class="font-serif font-medium text-lg text-[#1d1d1f]">Create New Spatie Role</h3>
          <button @click="showCreateRoleModal = false" class="text-stone-400 hover:text-stone-700 cursor-pointer text-lg">&times;</button>
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
            <div class="space-y-2 max-h-48 overflow-y-auto border border-[#e0d9cc] rounded-xl p-3">
              <label 
                v-for="perm in availablePermissions" 
                :key="perm"
                class="flex items-center gap-2 text-xs text-[#1d1d1f] cursor-pointer"
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
              class="px-4 py-2 bg-[#1a1a1a] hover:bg-black text-white text-xs font-semibold rounded-full cursor-pointer disabled:opacity-50"
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
      class="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4"
      @click.self="editingAdmin = null"
    >
      <div class="bg-white rounded-3xl max-w-md w-full p-6 sm:p-7 shadow-xl border border-[#e0d9cc] space-y-4">
        <div class="flex items-center justify-between">
          <div>
            <h3 class="font-serif font-medium text-lg text-[#1d1d1f]">Manage Roles</h3>
            <p class="text-xs text-[#6e6e73]">{{ editingAdmin.name }} ({{ editingAdmin.email }})</p>
          </div>
          <button @click="editingAdmin = null" class="text-stone-400 hover:text-stone-700 cursor-pointer text-lg">&times;</button>
        </div>

        <form @submit.prevent="submitSyncRoles" class="space-y-4">
          <div class="space-y-2 border border-[#e0d9cc] rounded-xl p-3">
            <label 
              v-for="r in roles" 
              :key="r.name"
              class="flex items-center gap-2 text-xs text-[#1d1d1f] cursor-pointer"
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
              class="px-4 py-2 bg-[#1a1a1a] hover:bg-black text-white text-xs font-semibold rounded-full cursor-pointer disabled:opacity-50"
            >
              Update Roles
            </button>
          </div>
        </form>
      </div>
    </div>

  </AdminLayout>
</template>
