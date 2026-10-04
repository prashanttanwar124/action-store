<script setup>
import { TabsTrigger, useForwardProps } from 'reka-ui';
import { cn } from '@/lib/utils';

const props = defineProps({
  value: { type: [String, Number], required: true },
  disabled: { type: Boolean, default: false },
  asChild: { type: Boolean, default: false },
  as: { type: null, default: 'button' },
  class: { type: null, default: '' },
});

const forwardedProps = useForwardProps(props);
</script>

<template>
  <TabsTrigger
    v-bind="forwardedProps"
    :class="
      cn(
        'inline-flex items-center justify-center whitespace-nowrap rounded-full px-3.5 py-1.5 text-xs font-semibold transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#1a1a1a]/20 disabled:pointer-events-none disabled:opacity-50 data-[state=active]:bg-[#1a1a1a] data-[state=active]:text-white data-[state=active]:shadow-xs cursor-pointer',
        props.class,
      )
    "
  >
    <slot />
  </TabsTrigger>
</template>
