<script setup>
import { computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { usePageLoading } from '@/composables/usePageLoading';
import PageSkeleton from '@/Components/PageSkeleton.vue';
import { 
  ShieldCheck, 
  LogOut, 
  ExternalLink 
} from 'lucide-vue-next';

defineProps({
  title: {
    type: String,
    default: 'Admin Console',
  },
});

const page = usePage();
const admin = computed(() => page.props.auth?.admin);
const { isPageLoading } = usePageLoading();
const logoutForm = useForm({});

const handleLogout = () => {
  logoutForm.post(route('admin.logout'));
};
</script>

<template>
  <div class="min-h-screen bg-[#fbf9f5] text-[#1d1d1f] flex flex-col font-sans selection:bg-[#a47a3c] selection:text-white">
    <Head :title="title + ' — Masala Mart Admin'" />

    <!-- Top Admin Bar -->
    <header class="bg-[#1a1a1a] text-white border-b border-[#2e2e2e] sticky top-0 z-40">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
        
        <!-- Brand & Guard Identity -->
        <div class="flex items-center gap-3">
          <Link :href="route('admin.dashboard')" class="flex items-center gap-2.5 group">
            <div class="w-8 h-8 rounded-xl bg-[#a47a3c] flex items-center justify-center text-white font-serif font-bold text-base shadow-sm">
              M
            </div>
            <div>
              <div class="flex items-center gap-2">
                <span class="font-serif font-medium text-base sm:text-lg tracking-tight text-white group-hover:text-[#a47a3c] transition-colors">
                  Masala Mart
                </span>
                <span class="text-[10px] font-mono uppercase bg-[#2e261f] text-[#a47a3c] border border-[#a47a3c]/30 px-2 py-0.5 rounded-full font-semibold">
                  Admin Guard
                </span>
              </div>
            </div>
          </Link>
        </div>

        <!-- Center Quick Links -->
        <nav class="hidden md:flex items-center gap-1 bg-[#242424] px-1.5 py-1 rounded-full border border-white/10 text-xs">
          <Link 
            :href="route('admin.dashboard')"
            class="px-3.5 py-1.5 rounded-full font-medium transition-colors"
            :class="route().current('admin.dashboard') ? 'bg-[#a47a3c] text-white' : 'text-stone-300 hover:text-white'"
          >
            Dashboard
          </Link>
          <a 
            href="/" 
            target="_blank" 
            class="px-3.5 py-1.5 rounded-full text-stone-300 hover:text-white flex items-center gap-1 transition-colors"
          >
            <span>Customer Storefront</span>
            <ExternalLink class="w-3 h-3 opacity-70" />
          </a>
        </nav>

        <!-- Current Admin Info & Actions -->
        <div class="flex items-center gap-3">
          <div v-if="admin" class="hidden sm:flex flex-col text-right">
            <span class="text-xs font-semibold text-white leading-tight truncate max-w-[180px]">
              {{ admin.name }}
            </span>
            <div class="flex items-center justify-end gap-1.5 mt-0.5">
              <span 
                v-for="role in admin.roles" 
                :key="role"
                class="text-[10px] bg-[#a47a3c]/20 text-[#e4b97a] px-1.5 py-0.2 rounded font-mono font-medium"
              >
                {{ role }}
              </span>
            </div>
          </div>

          <!-- Logout Button -->
          <button 
            type="button"
            @click="handleLogout"
            class="p-2 sm:px-3 sm:py-1.5 rounded-full bg-[#2a2a2a] hover:bg-red-950/40 hover:text-red-300 text-stone-300 text-xs font-medium flex items-center gap-1.5 transition-colors border border-white/10 cursor-pointer"
            title="Log Out of Admin Guard"
          >
            <LogOut class="w-3.5 h-3.5" />
            <span class="hidden sm:inline">Logout</span>
          </button>
        </div>
      </div>
    </header>

    <!-- Subheader Notification Toast if session success -->
    <div 
      v-if="$page.props.flash?.success" 
      class="bg-emerald-800 text-emerald-100 text-xs px-4 py-2.5 text-center font-medium shadow-inner flex items-center justify-center gap-1.5"
    >
      <ShieldCheck class="w-4 h-4 text-emerald-300" />
      <span>{{ $page.props.flash.success }}</span>
    </div>

    <!-- Top Navigating Indicator -->
    <div 
      v-if="isPageLoading" 
      class="fixed top-0 left-0 right-0 h-0.5 bg-[#a47a3c] z-50 animate-pulse shadow-xs"
    ></div>

    <!-- Main Content Area -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8 relative">
      <Transition name="fade-skeleton" mode="out-in">
        <PageSkeleton v-if="isPageLoading" type="admin" key="skeleton" />
        <div v-else key="content">
          <slot />
        </div>
      </Transition>
    </main>

    <!-- Footer -->
    <footer class="border-t border-[#e0d9cc]/60 py-6 text-center text-xs text-[#6e6e73] bg-[#f3efe7]/50 mt-auto">
      <div class="max-w-7xl mx-auto px-4 flex flex-col sm:flex-row items-center justify-between gap-2">
        <div class="flex items-center gap-2">
          <span class="font-serif font-medium text-[#1d1d1f]">Masala Mart Admin</span>
          <span>·</span>
          <span>Multi-Auth Guard Isolation Active</span>
        </div>
        <div class="text-[11px] font-mono text-[#86868b]">
          Guard: <span class="text-[#7a5620] font-semibold">admin</span> · Provider: <span class="text-[#7a5620]">admins</span> · Spatie Permission v8
        </div>
      </div>
    </footer>
  </div>
</template>
