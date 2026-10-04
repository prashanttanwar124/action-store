<script setup>
import { DialogDescription, useForwardProps } from 'reka-ui';
import { cn } from '@/lib/utils';

const props = defineProps({
  asChild: { type: Boolean, default: false },
  as: { type: null, default: 'p' },
  class: { type: null, default: '' },
});

const forwardedProps = useForwardProps(props);
</script>

<template>
  <DialogDescription
    v-bind="forwardedProps"
    :class="cn('text-xs text-[#6e6e73]', props.class)"
  >
    <slot />
  </DialogDescription>
</template>
