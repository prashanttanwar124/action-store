<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Skeleton } from '@/Components/ui/skeleton';

const props = defineProps({
  type: {
    type: String,
    default: 'auto', // 'auto' | 'home' | 'product' | 'cart' | 'admin'
  },
});

const page = usePage();

const detectedType = computed(() => {
  if (props.type !== 'auto') return props.type;
  const url = page.url || '';
  if (url.startsWith('/admin')) return 'admin';
  if (url.startsWith('/products')) return 'product';
  if (url.startsWith('/cart') || url.startsWith('/checkout')) return 'cart';
  if (url.startsWith('/account') || url.startsWith('/reorder')) return 'account';
  return 'home';
});
</script>

<template>
  <div class="w-full space-y-8 animate-in fade-in duration-200">
    
    <!-- HOME STOREFRONT SKELETON -->
    <div v-if="detectedType === 'home'" class="space-y-8">
      <!-- Hero Banner Skeleton -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
        <div class="lg:col-span-8 border border-border/60 rounded-3xl p-6 sm:p-8 min-h-[190px] sm:min-h-[220px] flex justify-between gap-4 bg-card/50">
          <div class="w-7/12 space-y-3">
            <Skeleton class="h-3 w-36" />
            <Skeleton class="h-8 w-4/5" />
            <Skeleton class="h-8 w-3/5" />
            <div class="pt-2">
              <Skeleton class="h-7 w-32 rounded-full" />
            </div>
          </div>
          <div class="w-5/12 h-full">
            <Skeleton class="w-full h-full min-h-[140px] rounded-2xl" />
          </div>
        </div>

        <div class="hidden lg:block lg:col-span-4 bg-card border border-border/60 rounded-3xl p-6 space-y-4">
          <Skeleton class="h-3 w-28" />
          <Skeleton class="h-6 w-4/5" />
          <Skeleton class="h-12 w-full" />
          <div class="pt-4 flex justify-between items-center">
            <Skeleton class="h-8 w-24" />
            <Skeleton class="h-8 w-24 rounded-full" />
          </div>
        </div>
      </div>

      <!-- Dots Skeleton -->
      <div class="flex items-center gap-1.5 pl-1 -mt-4">
        <Skeleton class="h-1.5 w-6 rounded-full" />
        <Skeleton class="h-1.5 w-2 rounded-full" />
        <Skeleton class="h-1.5 w-2 rounded-full" />
      </div>

      <!-- Buy It Again Section Skeleton -->
      <div class="space-y-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <Skeleton class="h-6 w-36" />
            <Skeleton class="h-5 w-12 rounded-full" />
          </div>
          <Skeleton class="h-4 w-12" />
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3.5 sm:gap-5">
          <div v-for="n in 5" :key="n" class="space-y-2.5">
            <Skeleton class="w-full aspect-square rounded-[22px] sm:rounded-3xl border border-border/60" />
            <div class="space-y-1.5 pt-1">
              <Skeleton class="h-4 w-16" />
              <Skeleton class="h-3.5 w-28" />
              <Skeleton class="h-3 w-20" />
            </div>
          </div>
        </div>
      </div>

      <!-- Masala Dabba Section Skeleton -->
      <div class="space-y-4 pt-2">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <Skeleton class="h-6 w-48" />
            <Skeleton class="h-5 w-16 rounded-full" />
          </div>
          <Skeleton class="h-4 w-24" />
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3 sm:gap-4">
          <div v-for="n in 7" :key="n" class="space-y-2">
            <Skeleton class="w-full aspect-square rounded-[22px] sm:rounded-3xl border border-border/60" />
            <Skeleton class="h-4 w-14" />
            <Skeleton class="h-3.5 w-20" />
          </div>
        </div>
      </div>
    </div>

    <!-- PRODUCT DETAIL SKELETON -->
    <div v-else-if="detectedType === 'product'" class="space-y-8">
      <div class="flex items-center gap-2">
        <Skeleton class="h-4 w-20" />
        <Skeleton class="h-4 w-4" />
        <Skeleton class="h-4 w-32" />
      </div>

      <div class="grid grid-cols-1 md:grid-cols-12 gap-8">
        <!-- Big Photo Skeleton -->
        <div class="md:col-span-6">
          <Skeleton class="w-full aspect-square rounded-3xl border border-border" />
        </div>

        <!-- Product Details Skeleton -->
        <div class="md:col-span-6 space-y-5">
          <div class="space-y-2">
            <Skeleton class="h-4 w-28" />
            <Skeleton class="h-8 w-4/5" />
            <Skeleton class="h-7 w-24" />
          </div>

          <Skeleton class="h-16 w-full rounded-2xl border border-border" />
          
          <div class="space-y-3 pt-2">
            <Skeleton class="h-12 w-full rounded-full" />
            <Skeleton class="h-10 w-full rounded-full" />
          </div>
        </div>
      </div>
    </div>

    <!-- CART / CHECKOUT SKELETON -->
    <div v-else-if="detectedType === 'cart'" class="space-y-6 max-w-5xl mx-auto">
      <Skeleton class="h-20 w-full rounded-3xl border border-border" />
      
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <div class="lg:col-span-8 space-y-3">
          <div v-for="n in 3" :key="n" class="p-4 bg-white rounded-2xl border border-border flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
              <Skeleton class="w-16 h-16 rounded-xl" />
              <div class="space-y-1.5">
                <Skeleton class="h-4 w-32" />
                <Skeleton class="h-3 w-20" />
              </div>
            </div>
            <Skeleton class="h-8 w-24 rounded-full" />
          </div>
        </div>

        <div class="lg:col-span-4">
          <Skeleton class="h-56 w-full rounded-3xl border border-border" />
        </div>
      </div>
    </div>

    <!-- ADMIN DASHBOARD SKELETON -->
    <div v-else-if="detectedType === 'admin'" class="space-y-6">
      <Skeleton class="h-28 w-full rounded-3xl border border-border" />
      
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <Skeleton v-for="n in 4" :key="n" class="h-24 w-full rounded-2xl border border-border" />
      </div>

      <Skeleton class="h-64 w-full rounded-3xl border border-border" />
    </div>

    <!-- GENERIC SKELETON -->
    <div v-else class="space-y-4">
      <Skeleton class="h-8 w-48" />
      <Skeleton class="h-32 w-full rounded-2xl" />
      <div class="grid grid-cols-3 gap-4">
        <Skeleton v-for="n in 3" :key="n" class="h-40 rounded-2xl" />
      </div>
    </div>

  </div>
</template>
