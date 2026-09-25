<script setup>
import { computed } from 'vue';

const props = defineProps({
  variant: {
    type: String,
    default: 'default',
  },
  class: {
    type: String,
    default: '',
  },
});

const variantClasses = computed(() => {
  switch (props.variant) {
    case 'red':
      return 'bg-[#E52E04] text-white font-bold border border-[#B82200]';
    case 'secondary':
      return 'bg-zinc-200 text-zinc-900 border border-zinc-300';
    case 'outline':
      return 'border border-zinc-800 text-zinc-900 bg-white';
    case 'accent':
      return 'bg-orange-100 text-[#E52E04] font-black border border-orange-200';
    default:
      return 'bg-zinc-950 text-white';
  }
});
</script>

<template>
  <span
    :class="[
      'inline-flex items-center rounded-none px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider',
      variantClasses,
      props.class
    ]"
  >
    <slot />
  </span>
</template>
