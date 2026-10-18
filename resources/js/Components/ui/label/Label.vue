<script setup>
import { Label as RekaLabel } from 'reka-ui';
import { cn } from '@/lib/utils';

const props = defineProps({
  for: { type: String, default: undefined },
  asChild: { type: Boolean, default: false },
  as: { type: null, default: 'label' },
  class: { type: null, default: '' },
});
</script>

<template>
  <RekaLabel
    :for="props.for"
    :as-child="asChild"
    :as="as"
    :class="
      cn(
        'text-xs font-medium text-foreground/90 peer-disabled:cursor-not-allowed peer-disabled:opacity-70 select-none',
        props.class,
      )
    "
  >
    <slot />
  </RekaLabel>
</template>
