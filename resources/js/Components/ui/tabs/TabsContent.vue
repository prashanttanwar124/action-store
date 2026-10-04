<script setup>
import { TabsContent, useForwardProps } from 'reka-ui';
import { cn } from '@/lib/utils';

const props = defineProps({
  value: { type: [String, Number], required: true },
  forceMount: { type: Boolean, default: undefined },
  asChild: { type: Boolean, default: false },
  as: { type: null, default: 'div' },
  class: { type: null, default: '' },
});

const forwardedProps = useForwardProps(props);
</script>

<template>
  <TabsContent
    v-bind="forwardedProps"
    :class="
      cn(
        'mt-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#1a1a1a]/20',
        props.class,
      )
    "
  >
    <slot />
  </TabsContent>
</template>
