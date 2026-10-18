<script setup>
import { ref, computed, onMounted } from 'vue';
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
  Clock,
  PanelLeftClose,
  PanelLeftOpen
} from 'lucide-vue-next';

defineProps({
  title: {
    type: String,
    default: 'Admin Console',
  },
  fullWidth: {
    type: Boolean,
    default: false,
  },
});

const page = usePage();
const admin = computed(() => page.props.auth?.admin);
const storeInfo = computed(() => page.props.storeInfo || {});
const { isPageLoading, isNavigating } = usePageLoading();
const logoutForm = useForm({});
const isSidebarOpen = ref(false);
const isDesktopSidebarOpen = ref(true);
const isRushMenuOpen = ref(false);

onMounted(() => {
  try {
    const saved = localStorage.getItem('admin_sidebar_open');
    if (saved !== null) {
      isDesktopSidebarOpen.value = saved !== 'false';
    }
  } catch {
    // ignore storage errors
  }
});

const toggleDesktopSidebar = () => {
  isDesktopSidebarOpen.value = !isDesktopSidebarOpen.value;
  try {
    localStorage.setItem('admin_sidebar_open', String(isDesktopSidebarOpen.value));
  } catch {
    // ignore storage errors
  }
};

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
  <div class="min-h-screen bg-zinc-50 text-zinc-900 font-sans antialiased selection:bg-zinc-900 selection:text-white flex">
    <Head :title="title + ' — Masala Mart Admin'" />

    <!-- ========================================== -->
    <!-- MOBILE SIDEBAR DRAWER OVERLAY              -->
    <!-- ========================================== -->
    <Transition
      enter-active-class="transition-opacity duration-200 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div 
        v-if="isSidebarOpen" 
        class="fixed inset-0 z-50 md:hidden bg-zinc-950/60 backdrop-blur-xs"
        @click="isSidebarOpen = false"
      ></div>
    </Transition>

    <aside 
      class="fixed inset-y-0 left-0 z-50 w-64 bg-zinc-950 text-zinc-300 flex flex-col justify-between border-r border-zinc-800 shadow-2xl transition-transform duration-200 ease-in-out md:hidden"
      :class="isSidebarOpen ? 'translate-x-0' : '-translate-x-full'"
    >
      <!-- Mobile Drawer Top -->
      <div class="flex flex-col flex-1 overflow-y-auto">
        <!-- Brand Header -->
        <div class="h-14 px-4 flex items-center justify-between border-b border-zinc-800/80">
          <Link :href="route('admin.dashboard')" class="flex items-center gap-2.5" @click="isSidebarOpen = false">
            <div class="w-7 h-7 rounded-md bg-white text-zinc-950 flex items-center justify-center font-bold text-xs shadow-2xs">
              M
            </div>
            <div class="flex items-center gap-2">
              <span class="font-semibold text-sm text-white tracking-tight">
                Masala Mart
              </span>
              <span class="text-[10px] font-mono uppercase px-1.5 py-0.5 rounded bg-zinc-800 text-zinc-300 border border-zinc-700 font-medium">
                Admin
              </span>
            </div>
          </Link>
          <button 
            type="button" 
            @click="isSidebarOpen = false"
            class="p-1.5 text-zinc-400 hover:text-white rounded-md hover:bg-zinc-800 transition-colors cursor-pointer"
            aria-label="Close sidebar"
          >
            <X class="w-4 h-4" />
          </button>
        </div>

        <!-- Navigation -->
        <div class="p-3 space-y-5">
          <div v-for="navGroup in navigationItems" :key="navGroup.group" class="space-y-0.5">
            <div class="text-[10px] font-mono uppercase tracking-wider text-zinc-500 font-semibold px-2.5 py-1">
              {{ navGroup.group }}
            </div>
            <component 
              :is="item.href.includes('#') ? 'a' : Link"
              v-for="item in navGroup.items" 
              :key="item.name"
              :href="item.href"
              @click="isSidebarOpen = false"
              class="flex items-center justify-between px-2.5 py-2 rounded-md text-xs font-medium transition-colors group"
              :class="item.isCurrent() 
                ? 'bg-zinc-800 text-white font-semibold' 
                : 'text-zinc-400 hover:text-zinc-100 hover:bg-zinc-900'"
            >
              <div class="flex items-center gap-2.5">
                <component 
                  :is="item.icon" 
                  class="w-4 h-4 transition-colors"
                  :class="item.isCurrent() ? 'text-white' : 'text-zinc-400 group-hover:text-zinc-200'"
                />
                <span>{{ item.name }}</span>
              </div>
              <span 
                v-if="item.badge" 
                class="text-[10px] font-mono uppercase px-1.5 py-0.5 rounded font-semibold"
                :class="item.badge === 'Live' ? 'bg-emerald-950 text-emerald-400 border border-emerald-800/60' : 'bg-zinc-800 text-zinc-300'"
              >
                {{ item.badge }}
              </span>
            </component>
          </div>

          <!-- Customer Storefront Shortcut -->
          <div class="pt-2 border-t border-zinc-800/80">
            <a 
              href="/" 
              target="_blank"
              class="flex items-center justify-between px-2.5 py-2 rounded-md text-xs font-medium text-zinc-400 hover:text-zinc-100 hover:bg-zinc-900 transition-colors group"
            >
              <div class="flex items-center gap-2.5">
                <Store class="w-4 h-4 text-zinc-400" />
                <span>Customer Storefront</span>
              </div>
              <ExternalLink class="w-3.5 h-3.5 text-zinc-500 group-hover:text-zinc-300" />
            </a>
          </div>
        </div>
      </div>

      <!-- Mobile Drawer Footer Profile -->
      <div class="p-3 border-t border-zinc-800/80 bg-zinc-950">
        <div class="flex items-center justify-between gap-2.5">
          <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-8 h-8 rounded-md bg-zinc-800 text-zinc-100 border border-zinc-700 flex items-center justify-center font-semibold text-xs shrink-0">
              {{ adminInitials }}
            </div>
            <div class="min-w-0">
              <div class="text-xs font-medium text-zinc-100 truncate">
                {{ admin?.name }}
              </div>
              <div class="text-[10px] font-mono text-zinc-400 truncate">
                {{ primaryRole }}
              </div>
            </div>
          </div>
          <button 
            type="button"
            @click="handleLogout"
            class="p-1.5 text-zinc-400 hover:text-rose-400 hover:bg-rose-950/40 rounded-md transition-colors cursor-pointer"
            title="Sign out of Admin Guard"
          >
            <LogOut class="w-4 h-4" />
          </button>
        </div>
      </div>
    </aside>

    <!-- ========================================== -->
    <!-- DESKTOP COLLAPSIBLE SIDEBAR                -->
    <!-- ========================================== -->
    <aside 
      class="hidden md:flex md:w-60 md:flex-col md:fixed md:inset-y-0 left-0 bg-zinc-950 text-zinc-300 border-r border-zinc-800 z-40 select-none transition-transform duration-200 ease-in-out"
      :class="isDesktopSidebarOpen ? 'md:translate-x-0' : 'md:-translate-x-full'"
    >
      
      <!-- Top Brand Header -->
      <div class="h-14 px-4 flex items-center justify-between border-b border-zinc-800/80">
        <Link :href="route('admin.dashboard')" class="flex items-center gap-2.5 group min-w-0">
          <div class="w-7 h-7 rounded-md bg-white text-zinc-950 flex items-center justify-center font-bold text-xs shadow-2xs shrink-0">
            M
          </div>
          <div class="flex items-center gap-2 truncate">
            <span class="font-semibold text-sm text-white tracking-tight truncate">
              Masala Mart
            </span>
            <span class="text-[10px] font-mono uppercase px-1.5 py-0.5 rounded bg-zinc-800 text-zinc-300 border border-zinc-700 font-medium shrink-0">
              Admin
            </span>
          </div>
        </Link>
        <button
          type="button"
          @click="toggleDesktopSidebar"
          class="p-1.5 text-zinc-400 hover:text-white rounded-md hover:bg-zinc-800 transition-colors cursor-pointer shrink-0"
          title="Collapse sidebar"
          aria-label="Collapse sidebar"
        >
          <PanelLeftClose class="w-4 h-4" />
        </button>
      </div>

      <!-- Navigation Content -->
      <div class="flex-1 overflow-y-auto px-3 py-4 space-y-5">
        <div v-for="navGroup in navigationItems" :key="navGroup.group" class="space-y-0.5">
          <div class="text-[10px] font-mono uppercase tracking-wider text-zinc-500 font-semibold px-2.5 py-1">
            {{ navGroup.group }}
          </div>
          <component
            :is="item.href.includes('#') ? 'a' : Link"
            v-for="item in navGroup.items" 
            :key="item.name"
            :href="item.href"
            class="flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs font-medium transition-colors group"
            :class="item.isCurrent() 
              ? 'bg-zinc-800 text-white font-semibold shadow-2xs' 
              : 'text-zinc-400 hover:text-zinc-100 hover:bg-zinc-900'"
          >
            <div class="flex items-center gap-2.5">
              <component 
                :is="item.icon" 
                class="w-4 h-4 transition-colors"
                :class="item.isCurrent() ? 'text-white' : 'text-zinc-400 group-hover:text-zinc-200'"
              />
              <span>{{ item.name }}</span>
            </div>
            <span 
              v-if="item.badge" 
              class="text-[10px] font-mono uppercase px-1.5 py-0.5 rounded font-semibold"
              :class="item.badge === 'Live' ? 'bg-emerald-950 text-emerald-400 border border-emerald-800/60' : 'bg-zinc-800 text-zinc-300'"
            >
              {{ item.badge }}
            </span>
          </component>
        </div>

        <!-- Storefront Shortcut Group -->
        <div class="pt-2 border-t border-zinc-800/80">
          <div class="text-[10px] font-mono uppercase tracking-wider text-zinc-500 font-semibold px-2.5 py-1">
            External
          </div>
          <a 
            href="/" 
            target="_blank"
            class="flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs font-medium text-zinc-400 hover:text-zinc-100 hover:bg-zinc-900 transition-colors group"
          >
            <div class="flex items-center gap-2.5">
              <Store class="w-4 h-4 text-zinc-400 group-hover:text-zinc-200 transition-colors" />
              <span>Storefront</span>
            </div>
            <ExternalLink class="w-3.5 h-3.5 text-zinc-500 group-hover:text-zinc-300" />
          </a>
        </div>
      </div>

      <!-- Desktop Footer / Admin Profile Card -->
      <div class="p-3 border-t border-zinc-800/80 bg-zinc-950">
        <div class="flex items-center justify-between gap-2 p-2 rounded-md bg-zinc-900/90 border border-zinc-800">
          <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-7 h-7 rounded-md bg-zinc-800 text-zinc-100 border border-zinc-700 flex items-center justify-center font-semibold text-xs shrink-0">
              {{ adminInitials }}
            </div>
            <div class="min-w-0">
              <div class="text-xs font-medium text-zinc-100 truncate leading-tight">
                {{ admin?.name }}
              </div>
              <div class="text-[10px] font-mono text-zinc-400 truncate">
                {{ primaryRole }}
              </div>
            </div>
          </div>
          <button 
            type="button"
            @click="handleLogout"
            class="p-1.5 text-zinc-400 hover:text-rose-400 hover:bg-rose-950/40 rounded-md transition-colors cursor-pointer shrink-0"
            title="Sign out of Admin Guard"
          >
            <LogOut class="w-3.5 h-3.5" />
          </button>
        </div>
      </div>
    </aside>

    <!-- ========================================== -->
    <!-- MAIN APPLICATION WRAPPER                   -->
    <!-- ========================================== -->
    <div 
      class="flex-1 flex flex-col min-h-screen w-full transition-[padding] duration-200 ease-in-out"
      :class="isDesktopSidebarOpen ? 'md:pl-60' : 'md:pl-0'"
    >
      
      <!-- Top Sticky Admin Header -->
      <header class="sticky top-0 z-30 bg-white/95 backdrop-blur-sm border-b border-zinc-200 h-14 flex items-center justify-between px-4 sm:px-6">
        
        <!-- Left Section: Sidebar Toggle & Breadcrumbs -->
        <div class="flex items-center gap-2.5">
          <!-- Mobile Hamburger Button -->
          <button 
            type="button"
            @click="isSidebarOpen = true"
            class="md:hidden p-1.5 text-zinc-600 hover:text-zinc-900 rounded-md hover:bg-zinc-100 border border-zinc-200/80 transition-colors cursor-pointer"
            aria-label="Open sidebar"
          >
            <Menu class="w-4 h-4" />
          </button>

          <!-- Desktop Sidebar Open/Close Toggle Button -->
          <button 
            type="button"
            @click="toggleDesktopSidebar"
            class="hidden md:inline-flex items-center justify-center p-1.5 text-zinc-600 hover:text-zinc-900 rounded-md hover:bg-zinc-100 border border-zinc-200/80 transition-colors cursor-pointer"
            :title="isDesktopSidebarOpen ? 'Hide sidebar' : 'Show sidebar'"
            :aria-label="isDesktopSidebarOpen ? 'Hide sidebar' : 'Show sidebar'"
          >
            <PanelLeftClose v-if="isDesktopSidebarOpen" class="w-4 h-4" />
            <PanelLeftOpen v-else class="w-4 h-4" />
          </button>

          <div class="h-4 w-px bg-zinc-200 hidden sm:block"></div>

          <!-- Breadcrumbs -->
          <div class="flex items-center gap-1.5 text-xs sm:text-sm">
            <span class="text-zinc-500 font-medium hidden sm:inline">Admin</span>
            <ChevronRight class="w-3.5 h-3.5 text-zinc-400 hidden sm:inline" />
            <h1 class="font-semibold text-zinc-900 tracking-tight text-sm">
              {{ title }}
            </h1>
          </div>

          <!-- Quick Rush / Busy Mode Bar (Desktop & Tablet) -->
          <div class="relative hidden sm:block ml-2">
            <button
              type="button"
              @click="isRushMenuOpen = !isRushMenuOpen"
              class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium transition-colors border cursor-pointer select-none"
              :class="storeInfo.is_busy 
                ? 'bg-amber-50 text-amber-900 border-amber-300 hover:bg-amber-100/70' 
                : 'bg-zinc-50 text-zinc-700 border-zinc-200 hover:bg-zinc-100'"
              title="Click to adjust store rush mode & prep buffer"
            >
              <span class="w-2 h-2 rounded-full" :class="storeInfo.is_busy ? 'bg-amber-500 animate-ping' : 'bg-emerald-500'"></span>
              <span class="font-semibold">{{ storeInfo.effective_pickup_time || 'Ready in 15 mins' }}</span>
              <span class="text-[10px] text-zinc-500 font-mono">({{ storeInfo.is_busy ? `+${storeInfo.busy_mode_extra_minutes}m Rush` : 'Normal' }})</span>
            </button>

            <!-- Popover dropdown -->
            <div 
              v-if="isRushMenuOpen" 
              class="absolute left-0 mt-1.5 w-64 bg-white rounded-lg shadow-lg border border-zinc-200 p-2.5 z-50 text-zinc-900 space-y-2 animate-in fade-in zoom-in-95 duration-100"
            >
              <div class="text-[11px] font-semibold uppercase tracking-wider text-zinc-500 px-1 flex items-center justify-between">
                <span>Store Prep Speed</span>
                <span class="text-[10px] text-zinc-400">Customer ETA</span>
              </div>

              <div class="space-y-1">
                <button
                  type="button"
                  @click="setRushMode(0)"
                  class="w-full text-left px-2.5 py-1.5 rounded-md text-xs flex items-center justify-between hover:bg-zinc-100 transition-colors cursor-pointer"
                  :class="(storeInfo.busy_mode_extra_minutes || 0) === 0 ? 'bg-emerald-50 font-semibold text-emerald-900 border border-emerald-200' : 'text-zinc-700'"
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
                  class="w-full text-left px-2.5 py-1.5 rounded-md text-xs flex items-center justify-between hover:bg-zinc-100 transition-colors cursor-pointer"
                  :class="(storeInfo.busy_mode_extra_minutes || 0) === 15 ? 'bg-amber-50 font-semibold text-amber-900 border border-amber-200' : 'text-zinc-700'"
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
                  class="w-full text-left px-2.5 py-1.5 rounded-md text-xs flex items-center justify-between hover:bg-zinc-100 transition-colors cursor-pointer"
                  :class="(storeInfo.busy_mode_extra_minutes || 0) === 30 ? 'bg-rose-50 font-semibold text-rose-900 border border-rose-200' : 'text-zinc-700'"
                >
                  <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                    <span>Heavy Rush (+30m · 45m total)</span>
                  </div>
                  <Check v-if="(storeInfo.busy_mode_extra_minutes || 0) === 30" class="w-3.5 h-3.5 text-rose-600" />
                </button>
              </div>

              <div class="pt-2 border-t border-zinc-100 text-[10px] text-zinc-500 flex items-center justify-between px-1">
                <span>Operations:</span>
                <Link href="/admin/store-info" @click="isRushMenuOpen = false" class="text-zinc-900 font-semibold hover:underline">
                  Store Settings ➔
                </Link>
              </div>
            </div>
          </div>
        </div>

        <!-- Right Section: Search & Actions -->
        <div class="flex items-center gap-2 sm:gap-2.5">
          
          <!-- Live Preview Button -->
          <a 
            href="/" 
            target="_blank"
            class="hidden sm:inline-flex items-center gap-1.5 px-2.5 py-1.5 bg-white hover:bg-zinc-50 text-zinc-700 border border-zinc-200 rounded-md text-xs font-medium transition-colors"
            title="Open customer storefront in a new tab"
          >
            <Store class="w-3.5 h-3.5 text-zinc-500" />
            <span>Storefront</span>
            <ExternalLink class="w-3 h-3 text-zinc-400" />
          </a>

          <!-- Admin Quick Profile Pill -->
          <div class="flex items-center gap-2 pl-2 border-l border-zinc-200">
            <div class="w-7 h-7 rounded-md bg-zinc-900 text-white flex items-center justify-center font-semibold text-xs">
              {{ adminInitials }}
            </div>
            <div class="hidden xl:flex flex-col text-left">
              <span class="text-xs font-semibold text-zinc-900 leading-none truncate max-w-[130px]">
                {{ admin?.name }}
              </span>
              <span class="text-[10px] font-mono text-zinc-500 mt-0.5 leading-none">
                {{ primaryRole }}
              </span>
            </div>
          </div>

        </div>
      </header>

      <!-- Subheader Notification Toast if session success -->
      <div 
        v-if="$page.props.flash?.success" 
        class="bg-emerald-900 text-emerald-50 text-xs px-4 py-2 text-center font-medium flex items-center justify-center gap-2 border-b border-emerald-800"
      >
        <ShieldCheck class="w-4 h-4 text-emerald-300" />
        <span>{{ $page.props.flash.success }}</span>
      </div>

      <!-- Top Navigating Progress Line -->
      <div 
        v-if="isNavigating" 
        class="fixed top-0 left-0 right-0 h-0.5 bg-zinc-900 z-50 animate-pulse"
      ></div>

      <!-- Main Slot Content -->
      <main :class="['flex-1 w-full mx-auto px-4 sm:px-6 py-5', fullWidth ? 'max-w-[1720px]' : 'max-w-7xl']">
        <PageSkeleton v-if="isPageLoading" type="admin" />
        <div v-show="!isPageLoading">
          <slot />
        </div>
      </main>

      <!-- Admin Backoffice Footer -->
      <footer class="border-t border-zinc-200 py-3.5 text-xs text-zinc-500 bg-white mt-auto">
        <div :class="['mx-auto px-4 sm:px-6 flex flex-col sm:flex-row items-center justify-between gap-2', fullWidth ? 'max-w-[1720px]' : 'max-w-7xl']">
          <div class="flex items-center gap-2">
            <span class="font-semibold text-zinc-800">Masala Mart</span>
            <span>·</span>
            <span>Admin Backoffice</span>
          </div>
          <div class="text-xs text-zinc-400">
            <span>© {{ new Date().getFullYear() }} Masala Mart</span>
          </div>
        </div>
      </footer>

    </div>
  </div>
</template>
