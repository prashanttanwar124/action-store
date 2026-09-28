<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { 
  ShoppingBag, 
  ArrowLeft, 
  Home, 
  SearchX, 
  ShieldAlert, 
  ServerOff, 
  Clock, 
  RefreshCw, 
  Sparkles, 
  ChevronRight,
  Store
} from 'lucide-vue-next';

const props = defineProps({
  status: {
    type: [Number, String],
    default: 404,
  },
  message: {
    type: String,
    default: '',
  },
});

const errorDetails = computed(() => {
  const code = Number(props.status);

  switch (code) {
    case 404:
      return {
        code: 404,
        badge: 'AISLE NOT FOUND',
        title: 'We couldn’t find that grocery item or page.',
        description: props.message || 'The product, recipe kit, or page you were looking for has either moved to a different aisle or is temporarily unavailable.',
        icon: SearchX,
        actionLabel: 'Return to Storefront',
        actionHref: '/',
      };
    case 403:
      return {
        code: 403,
        badge: 'RESTRICTED AREA',
        title: 'Access to this shelf is restricted.',
        description: props.message || 'You do not have permission to view this section of the store. If you are a staff member, please log in through the admin portal.',
        icon: ShieldAlert,
        actionLabel: 'Back to Home',
        actionHref: '/',
      };
    case 500:
      return {
        code: 500,
        badge: 'KITCHEN ERROR',
        title: 'Our store servers hit a temporary bump.',
        description: props.message || 'Something unexpected happened on our end while preparing your request. Our team has been notified and is fixing it now.',
        icon: ServerOff,
        actionLabel: 'Refresh Storefront',
        actionHref: '/',
      };
    case 503:
      return {
        code: 503,
        badge: 'RESTOCKING IN PROGRESS',
        title: 'We are restocking the shelves right now.',
        description: props.message || 'Masala Mart is undergoing a brief maintenance update to bring you fresh stock and better performance. We will be right back online!',
        icon: Clock,
        actionLabel: 'Check Again',
        actionHref: '/',
      };
    case 419:
      return {
        code: 419,
        badge: 'SESSION EXPIRED',
        title: 'Your shopping session has timed out.',
        description: props.message || 'For your security, checkout and browsing sessions expire after inactivity. Please refresh the page to continue.',
        icon: RefreshCw,
        actionLabel: 'Refresh Session',
        actionHref: '/',
      };
    default:
      return {
        code: code || 'Error',
        badge: 'UNEXPECTED ISSUE',
        title: 'Something went wrong.',
        description: props.message || 'An unexpected issue occurred while loading this page. Please return home or contact store support.',
        icon: ShoppingBag,
        actionLabel: 'Return Home',
        actionHref: '/',
      };
  }
});

const popularAisles = [
  { name: 'Chakki Atta & Whole Wheat', href: '/products/atta', tag: 'Staple' },
  { name: 'Desi Ghee & Fresh Dairy', href: '/products/desi-ghee', tag: 'Pure Ghee' },
  { name: 'Chef Recipe Kits & Bundles', href: '/recipe-kits/paneer-curry', tag: 'Dinner in 20m' },
  { name: 'Diwali Mithai & Gift Boxes', href: '/products/sweets-box', tag: 'Festival' },
];
</script>

<template>
  <Head :title="`${errorDetails.code} — ${errorDetails.title} | Masala Mart`" />

  <div class="min-h-screen bg-[#fbf9f5] text-[#1d1d1f] flex flex-col font-sans selection:bg-[#1a1a1a] selection:text-white antialiased">
    <!-- Top Minimalist Store Header -->
    <header class="bg-white/95 border-b border-[#e0d9cc] sticky top-0 z-40 backdrop-blur-md">
      <div class="max-w-5xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
        <Link href="/" class="flex items-center gap-2 group">
          <span class="w-8 h-8 rounded-full bg-[#1a1a1a] text-white flex items-center justify-center font-serif font-bold text-sm tracking-tighter group-hover:scale-105 transition-transform">
            M
          </span>
          <span class="font-serif font-semibold text-lg text-[#1d1d1f] tracking-tight">
            Masala Mart
          </span>
        </Link>

        <div class="flex items-center gap-3">
          <Link 
            href="/cart"
            class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-semibold bg-[#f5eee2] text-[#7a5620] border border-[#e0d9cc] hover:bg-[#ede2d1] transition-colors"
          >
            <ShoppingBag class="w-3.5 h-3.5" />
            <span>Store Cart</span>
          </Link>
          <Link 
            href="/"
            class="hidden sm:inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-semibold bg-[#1a1a1a] text-white hover:bg-black transition-colors shadow-xs"
          >
            <Home class="w-3.5 h-3.5" />
            <span>Storefront</span>
          </Link>
        </div>
      </div>
    </header>

    <!-- Main Error Content Container -->
    <main class="flex-1 flex items-center justify-center px-4 sm:px-6 py-12 sm:py-16">
      <div class="max-w-xl w-full text-center space-y-6">
        
        <!-- Animated Badge & Icon -->
        <div class="inline-flex flex-col items-center">
          <div class="w-20 h-20 sm:w-24 sm:h-24 rounded-3xl bg-[#f5eee2] border-2 border-[#e0d9cc] text-[#7a5620] flex items-center justify-center shadow-xs mx-auto mb-4 transition-transform hover:scale-105">
            <component :is="errorDetails.icon" class="w-10 h-10 sm:w-12 sm:h-12 stroke-[1.8]" />
          </div>

          <span class="text-[10px] sm:text-xs font-bold uppercase tracking-widest text-[#7a5620] bg-[#f5eee2] px-3.5 py-1 rounded-full border border-[#e0d9cc]">
            {{ errorDetails.badge }}
          </span>
        </div>

        <!-- Big Luxury Status Code -->
        <div class="space-y-2">
          <div class="text-6xl sm:text-7xl lg:text-8xl font-serif font-bold text-[#1a1a1a] tracking-tight leading-none">
            {{ errorDetails.code }}
          </div>
          <h1 class="text-xl sm:text-2xl lg:text-3xl font-serif font-medium text-[#1d1d1f] tracking-tight max-w-lg mx-auto leading-snug">
            {{ errorDetails.title }}
          </h1>
          <p class="text-xs sm:text-sm text-[#6e6e73] font-normal max-w-md mx-auto leading-relaxed pt-1">
            {{ errorDetails.description }}
          </p>
        </div>

        <!-- Primary & Secondary Action CTAs -->
        <div class="flex flex-col sm:flex-row items-center justify-center gap-3 pt-3">
          <Link 
            :href="errorDetails.actionHref"
            class="w-full sm:w-auto px-7 py-3.5 bg-[#1a1a1a] hover:bg-black text-white text-xs sm:text-sm font-semibold rounded-full shadow-md transition-all active:scale-[0.99] flex items-center justify-center gap-2"
          >
            <ArrowLeft class="w-4 h-4 stroke-[2.2]" />
            <span>{{ errorDetails.actionLabel }}</span>
          </Link>

          <Link 
            href="/cart"
            class="w-full sm:w-auto px-6 py-3.5 bg-white hover:bg-[#f3efe7] text-[#1d1d1f] text-xs sm:text-sm font-semibold rounded-full border border-[#e0d9cc] transition-colors flex items-center justify-center gap-2 shadow-xs"
          >
            <Store class="w-4 h-4 stroke-[2] text-[#a47a3c]" />
            <span>Check Store Pickup</span>
          </Link>
        </div>

        <!-- Popular Aisles Quick Help Card -->
        <div class="bg-white rounded-3xl border border-[#e0d9cc] p-5 sm:p-6 text-left shadow-xs space-y-3.5 mt-8">
          <div class="flex items-center justify-between border-b border-[#e0d9cc]/60 pb-2.5">
            <span class="text-xs font-semibold uppercase tracking-wider text-[#7a5620] flex items-center gap-1.5">
              <Sparkles class="w-3.5 h-3.5 text-[#a47a3c]" />
              <span>Looking for these popular aisles?</span>
            </span>
            <span class="text-[10px] text-[#86868b] font-normal">Click & Collect</span>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
            <Link 
              v-for="aisle in popularAisles"
              :key="aisle.name"
              :href="aisle.href"
              class="group flex items-center justify-between p-3 rounded-2xl bg-[#fbf9f5] hover:bg-[#f5eee2] border border-[#e0d9cc] transition-colors text-xs"
            >
              <div>
                <div class="font-semibold text-[#1d1d1f] group-hover:text-[#7a5620] transition-colors">
                  {{ aisle.name }}
                </div>
                <div class="text-[10px] text-[#86868b]">{{ aisle.tag }}</div>
              </div>
              <ChevronRight class="w-4 h-4 text-[#86868b] group-hover:translate-x-0.5 group-hover:text-[#7a5620] transition-all" />
            </Link>
          </div>
        </div>

        <!-- Store Pickup Location & Hours Note -->
        <div class="text-center pt-2 text-[11px] text-[#86868b] font-normal">
          <span>Masala Mart · 214 Main St. · Open daily 9:00 AM – 9:00 PM for pickup.</span>
        </div>

      </div>
    </main>

    <!-- Minimal Clean Footer -->
    <footer class="border-t border-[#e0d9cc] py-4 text-center text-xs text-[#86868b]">
      © 2026 Masala Mart Inc. All rights reserved. Express Click & Collect.
    </footer>
  </div>
</template>
