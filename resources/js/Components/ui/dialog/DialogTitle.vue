<script setup>
import { DialogTitle, useForwardProps } from 'reka-ui';
import { cn } from '@/lib/utils';

const props = defineProps({
  asChild: { type: Boolean, default: false },
  as: { type: null, default: 'h2' },
  class: { type: null, default: '' },
});

const forwardedProps = useForwardProps(props);
</script>

<template>
  <DialogTitle
    v-bind="forwardedProps"
    :class="cn('text-lg font-serif font-medium leading-none tracking-tight text-[#1d1d1f]', props.class)"
  >
    <slot />
  </DialogTitle>
</template>
