<script setup>
import { Separator } from 'reka-ui';
import { cn } from '@/lib/utils';

const props = defineProps({
  orientation: { type: String, default: 'horizontal' },
  decorative: { type: Boolean, default: true },
  asChild: { type: Boolean, default: false },
  as: { type: null, default: 'div' },
  class: { type: null, default: '' },
});
</script>

<template>
  <Separator
    :orientation="orientation"
    :decorative="decorative"
    :as-child="asChild"
    :as="as"
    :class="
      cn(
        'shrink-0 bg-border',
        props.orientation === 'horizontal' ? 'h-px w-full' : 'w-px h-full',
        props.class,
      )
    "
  />
</template>
