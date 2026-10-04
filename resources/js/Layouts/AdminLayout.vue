<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3';
import { usePageLoading } from '@/composables/usePageLoading';
import PageSkeleton from '@/Components/PageSkeleton.vue';
import { 
  ShieldCheck, 
  LogOut, 
  ExternalLink,
  LayoutDashboard,
  Package,
  ShoppingBag,
  Users,
  UserCheck,
  Layers,
  FolderTree,
  Menu,
  X,
  Search,
  Bell,
  Store,
  ChevronRight,
  Sparkles,
  BarChart3,
  Sliders,
  UtensilsCrossed,
  Truck,
  Flame,
  Check,
  Clock
} from 'lucide-vue-next';

defineProps({
  title: {
    type: String,
    default: 'Admin Console',
  },
});

const page = usePage();
const admin = computed(() => page.props.auth?.admin);
const storeInfo = computed(() => page.props.storeInfo || {});
const { isPageLoading, isNavigating } = usePageLoading();
const logoutForm = useForm({});
const isSidebarOpen = ref(false);
const isRushMenuOpen = ref(false);

const setRushMode = (extraMinutes, reason = null) => {
  isRushMenuOpen.value = false;
  router.post(route('admin.store-info.busy-mode'), {
    busy_mode_extra_minutes: extraMinutes,
    busy_mode_reason: reason,
  }, {
    preserveScroll: true,
  });
};

const adminInitials = computed(() => {
  if (!admin.value?.name) return 'AD';
  const parts = admin.value.name.trim().split(/\s+/);
  if (parts.length >= 2) {
    return (parts[0][0] + parts[1][0]).toUpperCase();
  }
  return parts[0].slice(0, 2).toUpperCase();
});

const primaryRole = computed(() => {
  if (admin.value?.roles && admin.value.roles.length > 0) {
    return admin.value.roles[0];
  }
  return 'Administrator';
});

const handleLogout = () => {
  logoutForm.post(route('admin.logout'));
};

const navigationItems = [
  {
    group: 'Overview',
    items: [
      {
        name: 'Dashboard',
        href: route('admin.dashboard'),
        icon: LayoutDashboard,
        isCurrent: () => route().current('admin.dashboard'),
        badge: null,
      },
    ],
  },
  {
    group: 'Store Management',
    items: [
      {
        name: 'Live Orders',
        href: route('admin.orders.index'),
        icon: ShoppingBag,
        isCurrent: () => route().current('admin.orders.*'),
        badge: 'Live',
      },
      {
        name: 'Products & Inventory',
        href: route('admin.products.index'),
        icon: Package,
        isCurrent: () => route().current('admin.products.*'),
        badge: null,
      },
      {
        name: 'Categories',
        href: route('admin.categories.index'),
        icon: FolderTree,
        isCurrent: () => route().current('admin.categories.*'),
        badge: null,
      },
      {
        name: 'Suppliers & Vendors',
        href: route('admin.suppliers.index'),
        icon: Truck,
        isCurrent: () => route().current('admin.suppliers.*'),
        badge: null,
      },
      {
        name: 'Recipe Kits',
        href: route('admin.recipe-kits.index'),
        icon: UtensilsCrossed,
        isCurrent: () => route().current('admin.recipe-kits.*'),
        badge: null,
      },
      {
        name: 'Promotional Banners',
        href: route('admin.sliders.index'),
        icon: Sliders,
        isCurrent: () => route().current('admin.sliders.*'),
        badge: null,
      },
      {
        name: 'Store Information',
        href: route('admin.store-info.edit'),
        icon: Store,
        isCurrent: () => route().current('admin.store-info.*'),
        badge: null,
      },
      {
        name: 'Pickup & Slot Settings',
        href: route('admin.pickup-settings.edit'),
        icon: Clock,
        isCurrent: () => route().current('admin.pickup-settings.*'),
        badge: 'Slots',
      },
      {
        name: 'Customer Accounts',
        href: route('admin.customers.index'),
        icon: Users,
        isCurrent: () => route().current('admin.customers.*'),
        badge: null,
      },
    ],
  },
  {
    group: 'Team & Security',
    items: [
      {
        name: 'Staff Roles & Permissions',
        href: route('admin.dashboard') + '#roles-section',
        icon: ShieldCheck,
        isCurrent: () => false,
        badge: null,
      },
      {
        name: 'Admin Team Members',
        href: route('admin.dashboard') + '#admins-section',
        icon: UserCheck,
        isCurrent: () => false,
        badge: null,
      },
    ],
  },
];
</script>

<template>
  <div class="min-h-screen bg-[#faf8f5] text-[#1d1d1f] font-sans antialiased selection:bg-[#a47a3c] selection:text-white flex">
    <Head :title="title + ' — Masala Mart Admin'" />

    <!-- ========================================== -->
    <!-- MOBILE SIDEBAR DRAWER OVERLAY              -->
    <!-- ========================================== -->
    <Transition
      enter-active-class="transition-opacity duration-300 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity duration-200 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div 
        v-if="isSidebarOpen" 
        class="fixed inset-0 z-50 md:hidden bg-black/60 backdrop-blur-xs"
        @click="isSidebarOpen = false"
      ></div>
    </Transition>

    <aside 
      class="fixed inset-y-0 left-0 z-50 w-72 bg-[#121214] text-stone-300 flex flex-col justify-between border-r border-[#242426] shadow-2xl transition-transform duration-300 ease-in-out md:hidden"
      :class="isSidebarOpen ? 'translate-x-0' : '-translate-x-full'"
    >
      <!-- Mobile Drawer Top -->
      <div class="flex flex-col flex-1 overflow-y-auto">
        <!-- Brand Header -->
        <div class="h-16 px-5 flex items-center justify-between border-b border-[#242426]">
          <Link :href="route('admin.dashboard')" class="flex items-center gap-3" @click="isSidebarOpen = false">
            <div class="w-8 h-8 rounded-xl bg-gradient-to-br from-[#c49856] to-[#8d662e] text-white flex items-center justify-center font-serif font-bold text-base shadow-sm">
              M
            </div>
            <div>
              <div class="font-serif font-medium text-base text-white tracking-tight leading-tight">
                Masala Mart
              </div>
              <div class="text-[10px] font-mono text-[#a47a3c] uppercase tracking-wider font-semibold">
                Admin Console
              </div>
            </div>
          </Link>
          <button 
            type="button" 
            @click="isSidebarOpen = false"
            class="p-2 text-stone-400 hover:text-white rounded-lg hover:bg-white/5 transition-colors cursor-pointer"
            aria-label="Close sidebar"
          >
            <X class="w-5 h-5" />
          </button>
        </div>

        <!-- Navigation -->
        <div class="p-4 space-y-6">
          <div v-for="navGroup in navigationItems" :key="navGroup.group" class="space-y-1">
            <div class="text-[10px] font-mono uppercase tracking-wider text-stone-500 font-semibold px-3 py-1">
              {{ navGroup.group }}
            </div>
            <component 
              :is="item.href.includes('#') ? 'a' : Link"
              v-for="item in navGroup.items" 
              :key="item.name"
              :href="item.href"
              @click="isSidebarOpen = false"
              class="flex items-center justify-between px-3 py-2.5 rounded-xl text-xs font-medium transition-all group"
              :class="item.isCurrent() 
                ? 'bg-[#a47a3c]/15 text-[#f5d5aa] font-semibold border border-[#a47a3c]/30' 
                : 'text-stone-300 hover:text-white hover:bg-white/5'"
            >
              <div class="flex items-center gap-2.5">
                <component 
                  :is="item.icon" 
                  class="w-4 h-4 transition-colors"
                  :class="item.isCurrent() ? 'text-[#e4b97a]' : 'text-stone-400 group-hover:text-stone-200'"
                />
                <span>{{ item.name }}</span>
              </div>
              <span 
                v-if="item.badge" 
                class="text-[9px] font-mono uppercase px-1.5 py-0.5 rounded-full font-semibold"
                :class="item.badge === 'Live' ? 'bg-emerald-950 text-emerald-300 border border-emerald-800/50' : 'bg-white/10 text-stone-400'"
              >
                {{ item.badge }}
              </span>
            </component>
          </div>

          <!-- Customer Storefront Shortcut -->
          <div class="pt-2 border-t border-[#242426]">
            <a 
              href="/" 
              target="_blank"
              class="flex items-center justify-between px-3 py-2.5 rounded-xl text-xs font-medium text-stone-300 hover:text-white hover:bg-white/5 transition-all group"
            >
              <div class="flex items-center gap-2.5">
                <Store class="w-4 h-4 text-[#a47a3c]" />
                <span>Customer Storefront</span>
              </div>
              <ExternalLink class="w-3.5 h-3.5 text-stone-500 group-hover:text-stone-300" />
            </a>
          </div>
        </div>
      </div>

      <!-- Mobile Drawer Footer Profile -->
      <div class="p-4 border-t border-[#242426] bg-[#0e0e10]">
        <div class="flex items-center justify-between gap-3">
          <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-9 h-9 rounded-full bg-gradient-to-br from-[#a47a3c] to-[#6d4b1a] text-white flex items-center justify-center font-bold text-xs shadow-sm ring-1 ring-white/10 shrink-0">
              {{ adminInitials }}
            </div>
            <div class="min-w-0">
              <div class="text-xs font-semibold text-white truncate">
                {{ admin?.name }}
              </div>
              <div class="text-[10px] font-mono text-[#a47a3c] truncate">
                {{ primaryRole }}
              </div>
            </div>
          </div>
          <button 
            type="button"
            @click="handleLogout"
            class="p-2 text-stone-400 hover:text-rose-400 hover:bg-rose-950/30 rounded-lg transition-colors cursor-pointer"
            title="Sign out of Admin Guard"
          >
            <LogOut class="w-4 h-4" />
          </button>
        </div>
      </div>
    </aside>

    <!-- ========================================== -->
    <!-- DESKTOP PINNED SIDEBAR                     -->
    <!-- ========================================== -->
    <aside class="hidden md:flex md:w-64 md:flex-col md:fixed md:inset-y-0 bg-[#121214] text-stone-300 border-r border-[#242426] z-40 select-none">
      
      <!-- Top Brand Header -->
      <div class="h-16 px-5 flex items-center justify-between border-b border-[#242426]">
        <Link :href="route('admin.dashboard')" class="flex items-center gap-3 group">
          <div class="w-8 h-8 rounded-xl bg-gradient-to-br from-[#c49856] to-[#8d662e] text-white flex items-center justify-center font-serif font-bold text-base shadow-sm group-hover:scale-105 transition-transform">
            M
          </div>
          <div>
            <div class="flex items-center gap-1.5">
              <span class="font-serif font-medium text-base text-white tracking-tight leading-tight group-hover:text-[#e4b97a] transition-colors">
                Masala Mart
              </span>
            </div>
            <div class="flex items-center gap-1.5 mt-0.5">
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
              <span class="text-[10px] font-mono text-[#a47a3c] uppercase tracking-wider font-semibold">
                Admin Console
              </span>
            </div>
          </div>
        </Link>
      </div>

      <!-- Navigation Content -->
      <div class="flex-1 overflow-y-auto px-3.5 py-5 space-y-6">
        <div v-for="navGroup in navigationItems" :key="navGroup.group" class="space-y-1">
          <div class="text-[10px] font-mono uppercase tracking-wider text-stone-500 font-semibold px-3 py-1">
            {{ navGroup.group }}
          </div>
          <component
            :is="item.href.includes('#') ? 'a' : Link"
            v-for="item in navGroup.items" 
            :key="item.name"
            :href="item.href"
            class="flex items-center justify-between px-3 py-2 rounded-xl text-xs font-medium transition-all group"
            :class="item.isCurrent() 
              ? 'bg-[#a47a3c]/15 text-[#f5d5aa] font-semibold border border-[#a47a3c]/30 shadow-xs' 
              : 'text-stone-400 hover:text-stone-100 hover:bg-white/5'"
          >
            <div class="flex items-center gap-2.5">
              <component 
                :is="item.icon" 
                class="w-4 h-4 transition-colors"
                :class="item.isCurrent() ? 'text-[#e4b97a]' : 'text-stone-400 group-hover:text-stone-200'"
              />
              <span>{{ item.name }}</span>
            </div>
            <span 
              v-if="item.badge" 
              class="text-[9px] font-mono uppercase px-1.5 py-0.5 rounded-full font-semibold"
              :class="item.badge === 'Live' ? 'bg-emerald-950 text-emerald-300 border border-emerald-800/50' : 'bg-white/10 text-stone-400'"
            >
              {{ item.badge }}
            </span>
          </component>
        </div>

        <!-- Storefront Shortcut Group -->
        <div class="pt-2 border-t border-[#242426]">
          <div class="text-[10px] font-mono uppercase tracking-wider text-stone-500 font-semibold px-3 py-1 mb-1">
            Public View
          </div>
          <a 
            href="/" 
            target="_blank"
            class="flex items-center justify-between px-3 py-2 rounded-xl text-xs font-medium text-stone-400 hover:text-stone-100 hover:bg-white/5 transition-all group"
          >
            <div class="flex items-center gap-2.5">
              <Store class="w-4 h-4 text-[#a47a3c] group-hover:text-[#c49856] transition-colors" />
              <span>Storefront</span>
            </div>
            <ExternalLink class="w-3.5 h-3.5 text-stone-500 group-hover:text-stone-300" />
          </a>
        </div>
      </div>

      <!-- Desktop Footer / Admin Profile Card -->
      <div class="p-3.5 border-t border-[#242426] bg-[#0d0d0f]">
        <div class="flex items-center justify-between gap-2.5 p-2 rounded-xl bg-white/5 border border-white/5">
          <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-[#c49856] to-[#8d662e] text-white flex items-center justify-center font-bold text-xs shadow-sm ring-1 ring-white/10 shrink-0">
              {{ adminInitials }}
            </div>
            <div class="min-w-0">
              <div class="text-xs font-semibold text-white truncate leading-tight">
                {{ admin?.name }}
              </div>
              <div class="text-[10px] font-mono text-[#a47a3c] truncate">
                {{ primaryRole }}
              </div>
            </div>
          </div>
          <button 
            type="button"
            @click="handleLogout"
            class="p-1.5 text-stone-400 hover:text-rose-400 hover:bg-rose-950/30 rounded-lg transition-colors cursor-pointer shrink-0"
            title="Sign out of Admin Guard"
          >
            <LogOut class="w-4 h-4" />
          </button>
        </div>

        <div class="mt-2 text-[10px] font-mono text-stone-500 text-center flex items-center justify-center gap-1.5">
          <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
          <span>Guard: admin · Spatie v8</span>
        </div>
      </div>
    </aside>

    <!-- ========================================== -->
    <!-- MAIN APPLICATION WRAPPER (Offset by w-64)   -->
    <!-- ========================================== -->
    <div class="flex-1 md:pl-64 flex flex-col min-h-screen w-full">
      
      <!-- Top Sticky Admin Header -->
      <header class="sticky top-0 z-30 bg-[#faf8f5]/90 backdrop-blur-md border-b border-[#e0d9cc]/80 h-16 flex items-center justify-between px-4 sm:px-6 lg:px-8">
        
        <!-- Left Section: Mobile Toggle & Breadcrumbs -->
        <div class="flex items-center gap-3">
          <!-- Mobile Hamburger Button -->
          <button 
            type="button"
            @click="isSidebarOpen = true"
            class="md:hidden p-2 text-stone-700 hover:text-stone-900 rounded-xl hover:bg-stone-200/50 transition-colors cursor-pointer"
            aria-label="Open sidebar"
          >
            <Menu class="w-5 h-5" />
          </button>

          <!-- Breadcrumbs -->
          <div class="flex items-center gap-2 text-xs sm:text-sm">
            <span class="text-[#86868b] font-medium hidden sm:inline">Admin Console</span>
            <ChevronRight class="w-3.5 h-3.5 text-[#b0a89a] hidden sm:inline" />
            <h1 class="font-medium text-[#1d1d1f] tracking-tight">
              {{ title }}
            </h1>
          </div>

          <!-- Quick Rush / Busy Mode Bar (Desktop & Tablet) -->
          <div class="relative hidden sm:block">
            <button
              type="button"
              @click="isRushMenuOpen = !isRushMenuOpen"
              class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium transition-all border cursor-pointer shadow-2xs select-none"
              :class="storeInfo.is_busy 
                ? 'bg-amber-500/15 text-amber-900 border-amber-300 hover:bg-amber-500/20' 
                : 'bg-emerald-50 text-emerald-800 border-emerald-200 hover:bg-emerald-100/70'"
              title="Click to adjust store rush mode & prep buffer"
            >
              <span class="w-2 h-2 rounded-full" :class="storeInfo.is_busy ? 'bg-amber-500 animate-ping' : 'bg-emerald-500'"></span>
              <span class="font-bold">{{ storeInfo.effective_pickup_time || 'Ready in 15 mins' }}</span>
              <span class="text-[10px] opacity-75 font-mono">({{ storeInfo.is_busy ? `+${storeInfo.busy_mode_extra_minutes}m Rush` : 'Normal' }})</span>
            </button>

            <!-- Popover dropdown -->
            <div 
              v-if="isRushMenuOpen" 
              class="absolute left-0 mt-2 w-64 bg-white rounded-2xl shadow-xl border border-stone-200 p-3 z-50 text-stone-900 space-y-2 animate-in fade-in zoom-in-95 duration-100"
            >
              <div class="text-[11px] font-bold uppercase tracking-wider text-stone-500 px-1 flex items-center justify-between">
                <span>Store Prep Speed</span>
                <span class="text-[10px] text-stone-400">Live Customer ETA</span>
              </div>

              <div class="space-y-1">
                <button
                  type="button"
                  @click="setRushMode(0)"
                  class="w-full text-left px-2.5 py-2 rounded-xl text-xs flex items-center justify-between hover:bg-stone-100 transition-colors cursor-pointer"
                  :class="(storeInfo.busy_mode_extra_minutes || 0) === 0 ? 'bg-emerald-50 font-bold text-emerald-900 border border-emerald-200' : 'text-stone-700'"
                >
                  <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    <span>Normal (15 mins)</span>
                  </div>
                  <Check v-if="(storeInfo.busy_mode_extra_minutes || 0) === 0" class="w-3.5 h-3.5 text-emerald-600" />
                </button>

                <button
                  type="button"
                  @click="setRushMode(15, 'Store Busy')"
                  class="w-full text-left px-2.5 py-2 rounded-xl text-xs flex items-center justify-between hover:bg-stone-100 transition-colors cursor-pointer"
                  :class="(storeInfo.busy_mode_extra_minutes || 0) === 15 ? 'bg-amber-50 font-bold text-amber-900 border border-amber-200' : 'text-stone-700'"
                >
                  <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                    <span>Busy (+15m · 30m total)</span>
                  </div>
                  <Check v-if="(storeInfo.busy_mode_extra_minutes || 0) === 15" class="w-3.5 h-3.5 text-amber-600" />
                </button>

                <button
                  type="button"
                  @click="setRushMode(30, 'Peak Rush')"
                  class="w-full text-left px-2.5 py-2 rounded-xl text-xs flex items-center justify-between hover:bg-stone-100 transition-colors cursor-pointer"
                  :class="(storeInfo.busy_mode_extra_minutes || 0) === 30 ? 'bg-rose-50 font-bold text-rose-900 border border-rose-200' : 'text-stone-700'"
                >
                  <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                    <span>Heavy Rush (+30m · 45m total)</span>
                  </div>
                  <Check v-if="(storeInfo.busy_mode_extra_minutes || 0) === 30" class="w-3.5 h-3.5 text-rose-600" />
                </button>
              </div>

              <div class="pt-2 border-t border-stone-100 text-[10px] text-stone-500 flex items-center justify-between px-1">
                <span>Manage operations:</span>
                <Link href="/admin/store-info" @click="isRushMenuOpen = false" class="text-[#a47a3c] font-semibold hover:underline">
                  Store Settings ➔
                </Link>
              </div>
            </div>
          </div>
        </div>

        <!-- Right Section: Search & Actions -->
        <div class="flex items-center gap-2 sm:gap-3">
          
          <!-- Live Preview Button -->
          <a 
            href="/" 
            target="_blank"
            class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#f3efe7] hover:bg-[#ece7de] text-[#1d1d1f] border border-[#e0d9cc] rounded-full text-xs font-medium transition-colors shadow-2xs"
            title="Open customer storefront in a new tab"
          >
            <Store class="w-3.5 h-3.5 text-[#a47a3c]" />
            <span>Storefront Preview</span>
            <ExternalLink class="w-3 h-3 text-[#86868b]" />
          </a>

          <!-- System Notification Bell -->
          <div class="relative">
            <button 
              type="button"
              class="p-2 rounded-full text-stone-600 hover:text-stone-900 hover:bg-[#f3efe7] transition-colors border border-[#e0d9cc]/60 cursor-pointer"
              title="System Alerts & Guard Status"
            >
              <Bell class="w-4 h-4" />
              <span class="absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-emerald-500 ring-2 ring-white"></span>
            </button>
          </div>

          <!-- Admin Quick Profile Pill -->
          <div class="flex items-center gap-2 pl-1 sm:pl-2">
            <div class="w-8 h-8 rounded-full bg-[#1a1a1a] text-white flex items-center justify-center font-bold text-xs shadow-2xs">
              {{ adminInitials }}
            </div>
            <div class="hidden xl:flex flex-col text-left">
              <span class="text-xs font-semibold text-[#1d1d1f] leading-none truncate max-w-[130px]">
                {{ admin?.name }}
              </span>
              <span class="text-[10px] font-mono text-[#7a5620] mt-0.5 leading-none">
                {{ primaryRole }}
              </span>
            </div>
          </div>

        </div>
      </header>

      <!-- Subheader Notification Toast if session success -->
      <div 
        v-if="$page.props.flash?.success" 
        class="bg-emerald-800 text-emerald-100 text-xs px-4 py-2.5 text-center font-medium shadow-inner flex items-center justify-center gap-2"
      >
        <ShieldCheck class="w-4 h-4 text-emerald-300" />
        <span>{{ $page.props.flash.success }}</span>
      </div>

      <!-- Top Navigating Progress Line -->
      <div 
        v-if="isNavigating" 
        class="fixed top-0 left-0 right-0 h-0.5 bg-[#a47a3c] z-50 animate-pulse shadow-xs"
      ></div>

      <!-- Main Slot Content -->
      <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
        <PageSkeleton v-if="isPageLoading" type="admin" />
        <div v-show="!isPageLoading">
          <slot />
        </div>
      </main>

      <!-- Admin Backoffice Footer -->
      <footer class="border-t border-[#e0d9cc]/60 py-6 text-xs text-[#6e6e73] bg-[#f3efe7]/40 mt-auto">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-3">
          <div class="flex items-center gap-2">
            <span class="font-serif font-medium text-[#1d1d1f]">Masala Mart</span>
            <span>·</span>
            <span>Store Management Portal</span>
          </div>
          <div class="text-xs text-[#86868b] flex items-center gap-2">
            <span>© {{ new Date().getFullYear() }} Masala Mart. All rights reserved.</span>
          </div>
        </div>
      </footer>

    </div>
  </div>
</template>
