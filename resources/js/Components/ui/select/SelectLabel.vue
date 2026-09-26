<script setup>
import { SelectLabel } from 'reka-ui';
import { cn } from '@/lib/utils';

const props = defineProps({
  asChild: { type: Boolean, default: false },
  as: { type: null, default: 'div' },
  class: { type: null, default: '' },
});
</script>

<template>
  <SelectLabel
    :class="cn('py-1.5 pl-8 pr-2 text-[10px] font-mono uppercase tracking-wider text-stone-500 font-semibold', props.class)"
  >
    <slot />
  </SelectLabel>
</template>
