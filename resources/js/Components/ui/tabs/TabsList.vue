<script setup>
import { TabsList, useForwardProps } from 'reka-ui';
import { cn } from '@/lib/utils';

const props = defineProps({
  loop: { type: Boolean, default: true },
  asChild: { type: Boolean, default: false },
  as: { type: null, default: 'div' },
  class: { type: null, default: '' },
});

const forwardedProps = useForwardProps(props);
</script>

<template>
  <TabsList
    v-bind="forwardedProps"
    :class="
      cn(
        'inline-flex items-center justify-center rounded-full bg-[#f3efe7] p-1 text-[#6e6e73] border border-[#e0d9cc]',
        props.class,
      )
    "
  >
    <slot />
  </TabsList>
</template>
