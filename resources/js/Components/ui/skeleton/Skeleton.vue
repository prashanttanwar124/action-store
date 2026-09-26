<script setup>
import { cn } from '@/lib/utils';

const props = defineProps({
  class: {
    type: String,
    default: '',
  },
});
</script>

<template>
  <div
    :class="cn('skeleton-shimmer rounded-md bg-[#ece7de] select-none pointer-events-none', props.class)"
  />
</template>
