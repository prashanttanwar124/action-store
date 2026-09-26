<script setup>
import { SelectSeparator } from 'reka-ui';
import { cn } from '@/lib/utils';

const props = defineProps({
  asChild: { type: Boolean, default: false },
  as: { type: null, default: 'div' },
  class: { type: null, default: '' },
});
</script>

<template>
  <SelectSeparator :class="cn('-mx-1 my-1 h-px bg-[#e0d9cc]', props.class)" />
</template>
