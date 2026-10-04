<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
  navTabs: {
    type: Array,
    required: true,
  },
});
</script>

<template>
  <nav class="fixed bottom-0 left-0 right-0 z-40 bg-white/95 backdrop-blur-md border-t border-[#e0d9cc] sm:hidden">
    <div class="grid grid-cols-4 h-[60px] text-center max-w-md mx-auto">
      <Link
        v-for="tab in navTabs"
        :key="tab.id"
        :href="tab.href"
        :class="[
          'flex flex-col items-center justify-center w-full h-full py-1 transition-colors select-none',
          tab.isActive ? 'text-[#1a1a1a] font-semibold' : 'text-[#6e6e73] font-normal'
        ]"
      >
        <component 
          :is="tab.icon" 
          :class="[
            'w-5 h-5 transition-transform',
            tab.isActive ? 'stroke-[2.5] scale-105 text-[#1a1a1a]' : 'stroke-[1.8]'
          ]" 
        />
        <span class="text-[10px] mt-0.5 tracking-tight font-semibold">
          {{ tab.label }}
        </span>
      </Link>
    </div>
  </nav>
</template>
