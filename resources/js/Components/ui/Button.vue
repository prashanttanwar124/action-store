<script setup>
import { computed } from 'vue';

const props = defineProps({
  variant: {
    type: String,
    default: 'default',
  },
  size: {
    type: String,
    default: 'default',
  },
  disabled: Boolean,
  type: {
    type: String,
    default: 'button',
  },
  class: {
    type: String,
    default: '',
  },
});

const variantClasses = computed(() => {
  switch (props.variant) {
    case 'destructive':
      return 'bg-red-600 text-white hover:bg-red-700 active:scale-[0.98] border border-red-700';
    case 'outline':
      return 'border border-zinc-900 bg-white text-zinc-900 hover:bg-zinc-100 active:bg-zinc-200';
    case 'secondary':
      return 'bg-zinc-200 text-zinc-900 hover:bg-zinc-300 border border-zinc-300';
    case 'accent-red':
      return 'bg-[#E52E04] text-white hover:bg-[#CC2500] active:scale-[0.98] border border-[#B82200] font-bold';
    case 'ghost':
      return 'hover:bg-zinc-100 text-zinc-900';
    default:
      return 'bg-zinc-950 text-white hover:bg-zinc-800 active:scale-[0.98] border border-zinc-950';
  }
});

const sizeClasses = computed(() => {
  switch (props.size) {
    case 'sm':
      return 'h-8 px-3 text-xs rounded-none';
    case 'lg':
      return 'h-12 px-6 text-base rounded-none';
    case 'icon':
      return 'h-9 w-9 p-0 flex items-center justify-center rounded-none';
    case 'xs':
      return 'h-7 px-2 text-[11px] rounded-none';
    default:
      return 'h-10 px-4 text-sm rounded-none';
  }
});
</script>

<template>
  <button
    :type="type"
    :disabled="disabled"
    :class="[
      'inline-flex items-center justify-center font-bold tracking-tight transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-600 disabled:pointer-events-none disabled:opacity-50 cursor-pointer select-none rounded-none',
      variantClasses,
      sizeClasses,
      props.class
    ]"
  >
    <slot />
  </button>
</template>
